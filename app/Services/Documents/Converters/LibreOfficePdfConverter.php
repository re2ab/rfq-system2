<?php

namespace App\Services\Documents\Converters;

use App\Services\Documents\Contracts\PdfConverter;

/**
 * درایور تبدیل PDF با LibreOffice سرور (`soffice --headless`). فقط وقتی
 * واقعاً کار می‌کند که هم exec/shell_exec در PHP این هاست غیرفعال نشده باشد
 * و هم باینری soffice/libreoffice روی سرور نصب باشد — دو شرطی که فقط
 * می‌شود در لحظه‌ی اجرا چک کرد، نه از قبل فرض کرد (بند OQ-5 سند معماری).
 * روی cPanel اشتراکی معمولی این درایور isAvailable()==false برمی‌گرداند و
 * PdfConversionService بی‌صدا سراغ NullPdfConverter می‌رود.
 */
class LibreOfficePdfConverter implements PdfConverter
{
    public function key(): string
    {
        return 'libreoffice';
    }

    public function label(): string
    {
        return 'LibreOffice (سرور)';
    }

    public function isAvailable(): bool
    {
        if (!function_exists('shell_exec')) {
            return false;
        }
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        if (in_array('shell_exec', $disabled, true)) {
            return false;
        }

        $which = @shell_exec('command -v soffice 2>/dev/null || command -v libreoffice 2>/dev/null');

        return is_string($which) && trim($which) !== '';
    }

    public function diagnosis(): string
    {
        if (!function_exists('shell_exec')) {
            return 'تابع shell_exec در PHP این هاست وجود ندارد.';
        }
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        if (in_array('shell_exec', $disabled, true)) {
            return 'shell_exec در php.ini این هاست غیرفعال (disable_functions) شده است — معمول در cPanel اشتراکی.';
        }

        return $this->isAvailable()
            ? 'باینری LibreOffice پیدا شد — تبدیل PDF روی این سرور فعال است.'
            : 'باینری soffice/libreoffice روی این سرور نصب نیست.';
    }

    public function convert(string $sourcePath, string $destinationPath): ?string
    {
        if (!$this->isAvailable()) {
            return null;
        }
        if (!is_file($sourcePath)) {
            throw new \RuntimeException('فایل مبدأ برای تبدیل به PDF پیدا نشد.');
        }

        $outDir = dirname($destinationPath);
        if (!is_dir($outDir)) {
            mkdir($outDir, 0755, true);
        }

        // M32 (درخواست کاربر): فونت‌های اختصاصیِ فارسی (B Nazanin/B Titr) که در
        // قالب‌های Word استفاده می‌شوند، روی سرورِ Railway نصب نیستند (نه در
        // apt، نه در هیچ مخزنِ عمومی — فونتِ اختصاصی‌اند)، پس LibreOffice
        // موقعِ تبدیل به PDF خودکار جایگزین می‌کرد. راه‌حل: فونت‌ها مستقیم در
        // ریپو باندل شدند (resources/fonts/*.ttf) و اینجا با ست‌کردنِ
        // FONTCONFIG_FILE فقط برای همین یک دستور (نه سراسریِ سرور)، fontconfig
        // علاوه‌بر مسیرهای پیش‌فرضِ سیستم، این پوشه را هم برای فونت جست‌وجو
        // می‌کند. عمداً از نوشتنِ فایل در مسیرهای سیستمی (/usr/share/fonts)
        // پرهیز شد چون Railpack بیلد/دیپلوی را در مراحلِ جدا انجام می‌دهد و
        // نوشته‌های سیستمیِ زمانِ بیلد لزوماً به ایمیجِ نهایی منتقل نمی‌شوند؛
        // ولی resources/ چون بخشی از سورسِ اپ است، همیشه در ایمیجِ نهایی هست.
        $fontconfigFile = $this->ensureFontconfig();

        $cmd = ($fontconfigFile ? 'FONTCONFIG_FILE='.escapeshellarg($fontconfigFile).' ' : '')
            .'soffice --headless --norestore --convert-to pdf --outdir '
            .escapeshellarg($outDir).' '.escapeshellarg($sourcePath).' 2>&1';
        $output = @shell_exec($cmd);

        $expected = $outDir.'/'.pathinfo($sourcePath, PATHINFO_FILENAME).'.pdf';
        if (!is_file($expected)) {
            throw new \RuntimeException('تبدیل PDF با LibreOffice ناموفق بود: '.trim((string) $output));
        }

        if ($expected !== $destinationPath) {
            rename($expected, $destinationPath);
        }

        return $destinationPath;
    }

    /**
     * فایلِ کوچکِ fontconfig را (اگر فونتِ باندل‌شده‌ای در resources/fonts
     * موجود باشد) در storage/app می‌سازد — شاملِ include از تنظیماتِ
     * پیش‌فرضِ سیستم (تا هیچ فونتِ دیگری از دست نرود) به‌اضافه‌ی پوشه‌ی
     * فونت‌های باندل‌شده‌ی خودِ اپ. مسیرها با base_path/storage_path محاسبه
     * می‌شوند (نه هارد‌کد /app) تا مستقل از محیطِ استقرار درست کار کند.
     * اگر پوشه‌ی resources/fonts خالی/غایب باشد، null برمی‌گرداند — یعنی
     * دستورِ soffice بدونِ FONTCONFIG_FILE و با تنظیماتِ عادیِ سرور اجرا
     * می‌شود (بدونِ تغییر نسبت به قبل).
     */
    private function ensureFontconfig(): ?string
    {
        $fontsDir = base_path('resources/fonts');
        if (!is_dir($fontsDir) || count(glob($fontsDir.'/*.{ttf,otf,TTF,OTF}', GLOB_BRACE)) === 0) {
            return null;
        }

        $confPath = storage_path('app/rfq-fontconfig.conf');
        $cacheDir = storage_path('app/rfq-fontconfig-cache');
        if (!is_dir(dirname($confPath))) {
            mkdir(dirname($confPath), 0755, true);
        }
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $xml = '<?xml version="1.0"?>'."\n"
            .'<!DOCTYPE fontconfig SYSTEM "fonts.dtd">'."\n"
            .'<fontconfig>'."\n"
            .'  <include ignore_missing="yes">/etc/fonts/fonts.conf</include>'."\n"
            .'  <dir>'.htmlspecialchars($fontsDir, ENT_XML1).'</dir>'."\n"
            .'  <cachedir>'.htmlspecialchars($cacheDir, ENT_XML1).'</cachedir>'."\n"
            .'</fontconfig>'."\n";

        // فقط اگر محتوا فرق دارد دوباره نوشته می‌شود — بی‌دلیل هر بار I/O
        // انجام نمی‌شود (این متد در هر تبدیلِ PDF صدا زده می‌شود).
        if (!is_file($confPath) || file_get_contents($confPath) !== $xml) {
            file_put_contents($confPath, $xml);
        }

        return $confPath;
    }
}
