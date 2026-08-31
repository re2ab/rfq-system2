<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Services\NumberGeneratorService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * فرمول شماره‌گذاری نهایی (سند معماری v1.1، فاز ۶ — مثال تأییدشده‌ی کاربر):
 *
 *   {prefix}-{serial}-{چهار رقم آخر شماره‌ی پرونده}[-R{شماره‌ی ریویژن:۰۲}]
 *   مثال: پرونده INQ-101633 → پیشنهاد فنی TC-200242-1633-R00 (اولین ریویژن)
 *
 * شماره‌گذاری ریویژن از صفر شروع می‌شود (تغییر بعدی، درخواست صریح کاربر):
 * اولین Draft هر سند تازه R00 است، بعدی R01، R02 و به همین ترتیب — نه از ۱.
 * DocumentRevisionService این را در ساخت هر Revision تازه رعایت می‌کند.
 *
 * دو بخش جدا با دو طول عمر متفاوت:
 *  - number_base («TC-200242-1633») — فقط یک‌بار، در اولین Publish سند صادر
 *    می‌شود و تا ابد همان می‌ماند (Rule 4). تولیدش یعنی مصرف یک سریال واقعی از
 *    number_sequences، پس هرگز نباید بیش از یک‌بار برای یک سند فراخوانی شود —
 *    این تضمین را ensureBaseNumber() با چک number_base موجود می‌دهد؛ ایمنیِ
 *    هم‌زمانی‌اش وظیفه‌ی فراخواننده (DocumentPublishService، زیر لاک ردیف Document) است.
 *  - پسوند «-R01» به‌ازای هر Revision عوض می‌شود؛ صرفاً قالب‌بندی است، هیچ
 *    شماره‌ای مصرف نمی‌کند.
 */
class DocumentNumberingService
{
    public function __construct(protected NumberGeneratorService $numbers)
    {
    }

    /**
     * چهار رقم آخر شماره‌ی پرونده. اگر شماره‌ی پرونده رقم کافی نداشت (نظری،
     * عملاً نباید پیش بیاید چون شماره‌ی پرونده‌ها طبق فرمت موجود سیستم عددی‌اند)،
     * تمام ارقام موجود را برمی‌گرداند تا خروجی هرگز خالی نشود.
     */
    public function caseTag(string $caseNumber): string
    {
        $digits = preg_replace('/\D/', '', $caseNumber) ?? '';
        if ($digits === '') {
            $fallback = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $caseNumber) ?? '');
            return $fallback !== '' ? $fallback : 'X';
        }
        return substr($digits, -4);
    }

    /**
     * number_base را برای سند تولید و ذخیره می‌کند — Idempotent: اگر سند از قبل
     * number_base دارد (چاپ دوباره / Revisionهای بعدی همان سند)، همان مقدار
     * برگردانده می‌شود و هیچ سریال تازه‌ای مصرف نمی‌شود.
     *
     * هشدار: این متد باید همیشه از داخل تراکنشی فراخوانی شود که قبلش ردیف
     * Document را lockForUpdate() کرده — در غیر این صورت دو Publish هم‌زمان
     * روی یک سند می‌توانند هر دو number_base را null ببینند و دو سریال جدا
     * مصرف کنند. DocumentPublishService این پیش‌شرط را تضمین می‌کند.
     */
    public function ensureBaseNumber(Document $document): string
    {
        if (!empty($document->number_base)) {
            return $document->number_base;
        }

        $typeKey = $document->documentType?->key ?: $document->type;
        $caseNumber = $document->case?->case_number ?? '';
        $caseTag = $this->caseTag($caseNumber);

        // حلقه‌ی خوداصلاح‌گر: اگر سریالِ تازه با یک ردیفِ قدیمیِ (مثلاً حذف‌شده)
        // برخورد کرد، آن را کنار می‌گذاریم و یک سریالِ تازه‌ی دیگر می‌گیریم — حداکثر
        // ۵ بار. هر تلاش داخلِ DB::transaction() جدا (روی درایورهایی مثل SQLite
        // معادلِ SAVEPOINT) اجرا می‌شود تا شکستِ یک تلاش، تراکنشِ بیرونیِ
        // DocumentPublishService را «مسموم» نکند.
        $maxAttempts = 5;
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $prefixedSerial = $this->numbers->next($typeKey); // مثلاً TC-200242
            $base = $prefixedSerial.'-'.$caseTag; // TC-200242-1633

            try {
                DB::transaction(function () use ($document, $base) {
                    $document->number_base = $base;
                    $document->save();
                });

                return $base;
            } catch (QueryException $e) {
                $document->number_base = null; // state را برای تلاشِ بعدی پاک می‌کنیم
                $lastException = $e;

                if (!$this->looksLikeUniqueViolation($e)) {
                    throw $e;
                }
                // در غیرِ این صورت: دوباره حلقه می‌زنیم و از numbers->next() یک
                // سریالِ کاملاً تازه می‌گیریم (شمارنده قبلاً واقعاً جلو رفته).
            }
        }

        throw $lastException ?? new \RuntimeException(
            'صدورِ شماره‌ی سند بعد از چند تلاش ناموفق بود — با مدیرِ سیستم تماس بگیرید.'
        );
    }

    /**
     * تشخیصِ برخوردِ قیدِ یکتایی روی number_base (SQLSTATE 23000) — تا فقط
     * همین نوعِ خطا را دوباره‌تلاش کنیم و خطاهای دیگر را بدونِ تغییر پرتاب کنیم.
     */
    protected function looksLikeUniqueViolation(QueryException $e): bool
    {
        return (string) $e->getCode() === '23000'
            && str_contains($e->getMessage(), 'number_base');
    }

    /**
     * number_base + پسوند ریویژن. مصرف‌کننده‌ی سریال نیست، فقط قالب‌بندی.
     * شماره‌ی ریویژن از ۰ مجاز است (اولین ریویژن هر سند) — فقط منفی را به صفر می‌بندد.
     */
    public function formatRevisionNumber(string $numberBase, int $revisionNumber): string
    {
        return $numberBase.'-R'.str_pad((string) max(0, $revisionNumber), 2, '0', STR_PAD_LEFT);
    }
}
