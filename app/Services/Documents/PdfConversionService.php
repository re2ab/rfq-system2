<?php

namespace App\Services\Documents;

use App\Models\DocumentRevision;
use App\Services\Documents\Contracts\PdfConverter;
use App\Services\Documents\Converters\LibreOfficePdfConverter;
use App\Services\Documents\Converters\NullPdfConverter;
use Illuminate\Support\Facades\Storage;

/**
 * انتخاب‌گر درایور PDF (M7، فاز ۱۳ سند معماری). چون هدف نصب فعلی cPanel
 * اشتراکی است، هیچ درایوری تضمینی نیست — سیستم در «auto» به‌ترتیب
 * LibreOffice را امتحان می‌کند و اگر نبود بی‌صدا به «فقط دانلود Word/Excel»
 * برمی‌گردد (NullPdfConverter)، هرگز کاربر نهایی را با خطای فنی متوقف نمی‌کند.
 *
 * سرویس ابری (اولویت اول طبق سند معماری برای cPanel واقعی) عمداً اینجا
 * پیاده نشده — انتخاب vendor (CloudConvert/Gotenberg/…) و اعتبارنامه‌ی آن
 * یک تصمیم بیرونی است که باید از کاربر گرفته شود، نه چیزی که این جلسه حدس
 * بزند؛ config('documents.pdf_driver') از قبل برای افزودن یک کلید 'cloud'
 * در آینده آماده است — فقط کافی‌ست کلاسی که همین PdfConverter را پیاده
 * می‌کند به drivers() اضافه شود.
 */
class PdfConversionService
{
    public const DISK = TemplateService::DISK;

    /** @return array<int,PdfConverter> */
    public function drivers(): array
    {
        return [
            new LibreOfficePdfConverter(),
        ];
    }

    /** درایور واقعاً قابل‌استفاده‌ی همین لحظه — هیچ‌وقت null نیست، در بدترین حالت NullPdfConverter. */
    public function active(): PdfConverter
    {
        $configured = (string) config('documents.pdf_driver', 'auto');

        foreach ($this->drivers() as $driver) {
            if ($configured === $driver->key()) {
                return $driver->isAvailable() ? $driver : new NullPdfConverter();
            }
        }

        if ($configured === 'auto') {
            foreach ($this->drivers() as $driver) {
                if ($driver->isAvailable()) {
                    return $driver;
                }
            }
        }

        return new NullPdfConverter();
    }

    /**
     * فایل واقعی یک Revision را به PDF تبدیل می‌کند و مسیر را روی
     * document_revisions.pdf_path می‌نویسد. هر بار از نو تبدیل می‌کند (نه
     * cache) چون upload-edit می‌تواند فایل مبدأ را بین دو دانلود عوض کند.
     *
     * @return string|null مسیر نسبی PDF، یا null اگر درایوری در دسترس نبود
     */
    public function convertRevisionFile(DocumentRevision $revision): ?string
    {
        if (!$revision->file_path || !Storage::disk(self::DISK)->exists($revision->file_path)) {
            return null;
        }

        $driver = $this->active();
        if (!$driver->isAvailable()) {
            return null;
        }

        $absSource = Storage::disk(self::DISK)->path($revision->file_path);
        $destRel = preg_replace('/\.(docx|xlsx)$/i', '.pdf', $revision->file_path, 1, $replaced);
        if (!$replaced || $destRel === $revision->file_path) {
            // نگهبان: اگر پسوند فایل مبدأ docx/xlsx نبود، هرگز روی خودِ فایل مبدأ نمی‌نویسیم.
            $destRel = $revision->file_path.'.pdf';
        }
        $absDest = Storage::disk(self::DISK)->path($destRel);

        $result = $driver->convert($absSource, $absDest);
        if (!$result) {
            return null;
        }

        $revision->update(['pdf_path' => $destRel]);

        return $destRel;
    }
}
