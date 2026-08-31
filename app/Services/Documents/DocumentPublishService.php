<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\DocumentRevision;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * انتشار یک Revision — تنها نقطه‌ای که شماره‌ی رسمی سند صادر می‌شود (بند صریح
 * معماری: نه در ساخت Draft، فقط در Publish).
 *
 * تدافع هم‌زمانی سه‌لایه (بند «دو Publish هم‌زمان» سند معماری):
 *  ۱. لاک ردیف Document در کل طول تراکنش — دو Publish هم‌زمان روی همان سند
 *     (چه دو Revision متفاوت، چه دوبار همان Revision) سریالی می‌شوند.
 *  ۲. لاک ردیف number_sequences داخل NumberGeneratorService::next() — دو سند
 *     هم‌نوع که هم‌زمان Publish می‌شوند هرگز یک سریال را دوبار نمی‌گیرند.
 *  ۳. قید UNIQUE روی document_revisions.formatted_number و documents.number_base
 *     به‌عنوان خط دفاع نهایی دیتابیس، حتی اگر لایه‌ی سرویس جایی نقض شود.
 */
class DocumentPublishService
{
    public function __construct(
        protected DocumentNumberingService $numbering,
        protected DocumentNumberStampService $stamper,
    ) {
    }

    public function publish(DocumentRevision $revision, ?int $userId): DocumentRevision
    {
        return DB::transaction(function () use ($revision, $userId) {
            $document = Document::where('id', $revision->document_id)->lockForUpdate()->firstOrFail();
            $freshRevision = DocumentRevision::where('id', $revision->id)->lockForUpdate()->firstOrFail();

            if ($freshRevision->document_id !== $document->id) {
                throw new \RuntimeException('نسخه به این سند تعلق ندارد.');
            }
            if ($freshRevision->is_locked || $freshRevision->status === DocumentRevision::STATUS_PUBLISHED) {
                throw new \RuntimeException('این نسخه قبلاً منتشر شده است.');
            }
            if ($freshRevision->status === DocumentRevision::STATUS_SUPERSEDED) {
                throw new \RuntimeException('نسخه‌ی جایگزین‌شده قابل انتشار نیست.');
            }

            $numberBase = $this->numbering->ensureBaseNumber($document);

            // اصلاح M27 (درخواست کاربر، با مثالِ دقیق): شماره‌ی رسمیِ نمایشی
            // («-R01»، «-R02»، ...) باید بر اساسِ *ترتیبِ واقعیِ انتشار* باشد،
            // نه ترتیبِ ساختِ داخلیِ Draft (ستونِ revision_number). مثال کاربر:
            // یک سند R00 منتشرشده دارد و ۴ پیش‌نویس (به ترتیب ساخته‌شده:
            // ۱و۲و۳و۴). اگر کاربر اول پیش‌نویسِ ۴ را منتشر کند، باید R01 بگیرد
            // (نه R04!) چون این دومین انتشارِ این سند است؛ اگر بعد از آن
            // پیش‌نویسِ ۲ را منتشر کند، باید R02 بگیرد — با شمارشِ
            // Revisionهایی از همین سند که قبلاً شماره‌ی رسمی گرفته‌اند
            // (چه هنوز published چه بعداً superseded — Rule 4: شماره‌ی
            // صادرشده هرگز پاک نمی‌شود)، نه با ستونِ revision_number خودِ
            // Revisionِ در حالِ انتشار. زیرِ همان لاکِ ردیفِ Document که بالای
            // این تراکنش گرفته شده محاسبه می‌شود، پس دو Publish هم‌زمان روی
            // یک سند هرگز یک شماره را دوبار نمی‌گیرند.
            $publishSequence = DocumentRevision::where('document_id', $document->id)
                ->whereNotNull('formatted_number')
                ->count();
            $formatted = $this->numbering->formatRevisionNumber($numberBase, $publishSequence);

            $freshRevision->update([
                'status' => DocumentRevision::STATUS_PUBLISHED,
                'is_locked' => true,
                'formatted_number' => $formatted,
                'published_by' => $userId,
                'published_at' => now(),
            ]);

            // M36 (درخواست کاربر): اگر قالبِ این Revision از placeholderِ
            // {{document.number}} استفاده کرده، همین‌جا — درست بعد از صدورِ
            // شماره، هنوز زیرِ همان لاکِ ردیفِ سند — داخلِ فایلِ واقعیِ
            // Word/Excel نوشته می‌شود. عمداً *قبل* از قفل‌شدنِ منطقیِ Revision
            // در بالا نیست چون این خودش تنها نوشتنِ مجاز روی فایلِ یک
            // Revisionِ در حالِ منتشرشدن است (نه نقضِ Rule 11 — همان لحظه‌ای
            // است که Revision از Draft به Published می‌رود).
            $this->stamper->stamp($freshRevision, $formatted);

            // Rule 11: Revisionِ منتشرشده‌ی قبلی (اگر بود) از این پس Superseded است —
            // خودش هرگز تغییر نمی‌کند، فقط دیگر «نسخه‌ی فعلیِ منتشرشده» نیست.
            if ($document->published_revision_id && (int) $document->published_revision_id !== $freshRevision->id) {
                DocumentRevision::where('id', $document->published_revision_id)
                    ->where('status', DocumentRevision::STATUS_PUBLISHED)
                    ->update(['status' => DocumentRevision::STATUS_SUPERSEDED]);
            }

            $document->update([
                // ستون قدیمی document_number همیشه با آخرین Revisionِ منتشرشده هم‌گام است —
                // مسیرهای قدیمی کد که هنوز از این ستون می‌خوانند (فهرست اسناد، چاپ) بدون تغییر کار می‌کنند.
                'document_number' => $formatted,
                'number_base' => $numberBase,
                'status' => Document::STATUS_PUBLISHED,
                'published_revision_id' => $freshRevision->id,
            ]);

            AuditLogger::log('document_published', 'document', $document->id, [
                'revision_number' => $freshRevision->revision_number,
                'publish_sequence' => $publishSequence,
                'formatted_number' => $formatted,
            ]);

            return $freshRevision->fresh();
        });
    }
}
