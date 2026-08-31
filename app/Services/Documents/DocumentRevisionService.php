<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\DocumentRevision;
use App\Models\TemplateVersion;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * ساخت/مدیریت Revisionهای Draft برای مسیر جدید «سند از قالب واقعی» (M4).
 *
 * قانون ثابت (Rule 11 / CONFLICT-5 سند معماری): هیچ نوشتنی روی Revisionِ
 * قفل‌شده یا منتشرشده مجاز نیست. «ویرایش» یک سند منتشرشده همیشه یعنی ساخت
 * یک Revision تازه‌ی Draft با شماره‌ی بعدی — نه دست‌کاری Revision قبلی.
 */
class DocumentRevisionService
{
    /**
     * اولین Draft یک سند تازه‌ساخته‌شده. بلافاصله بعد از Document::create
     * فراخوانی می‌شود؛ شماره‌ی رسمی سند اینجا صادر نمی‌شود — طبق معماری فقط
     * در Publish صادر می‌شود (DocumentPublishService).
     */
    public function createInitial(Document $document, ?TemplateVersion $templateVersion, array $data, ?int $userId): DocumentRevision
    {
        return DB::transaction(function () use ($document, $templateVersion, $data, $userId) {
            $revision = DocumentRevision::create([
                'document_id' => $document->id,
                // شماره‌گذاری ریویژن از ۰ شروع می‌شود (درخواست کاربر) — اولین
                // Draft هر سند همیشه R00 است، نه R01.
                'revision_number' => 0,
                'template_version_id' => $templateVersion?->id,
                'status' => DocumentRevision::STATUS_DRAFT,
                'data' => $data,
                'created_by' => $userId,
                'is_locked' => false,
            ]);

            $document->update(['current_revision_id' => $revision->id]);

            AuditLogger::log('document_revision_created', 'document', $document->id, [
                'revision_number' => 0,
            ]);

            return $revision;
        });
    }

    /**
     * Draft بعدی — برای ویرایش سندی که Revision فعلی‌اش منتشر/قفل شده.
     * داده‌ی Revision قبلی به‌عنوان نقطه‌ی شروع کپی می‌شود مگر داده‌ی تازه
     * صریحاً پاس داده شده باشد. شماره‌ی Revision زیر لاک محاسبه می‌شود تا دو
     * Draft هم‌زمان با شماره‌ی یکسان ساخته نشوند (قید UNIQUE(document_id,
     * revision_number) هم به‌عنوان خط دفاع نهایی دیتابیس وجود دارد).
     *
     * $sourceRevisionId (M34): اگر این Draft واقعاً محتوایش کپیِ یک
     * Revisionِ مشخص است (نه فقط «آخرین»)، id همان Revision این‌جا پاس داده
     * می‌شود تا در ستونِ «بر اساس» جدولِ تاریخچه نمایش داده شود. پارامتری و
     * پیش‌فرضش null است تا فراخوانی‌های قبلی (بدون این آرگومان) بشکنند نه.
     */
    public function createNextDraft(Document $document, ?array $data, ?string $changeNote, ?int $userId, ?int $sourceRevisionId = null): DocumentRevision
    {
        return DB::transaction(function () use ($document, $data, $changeNote, $userId, $sourceRevisionId) {
            $lockedDocument = Document::where('id', $document->id)->lockForUpdate()->firstOrFail();

            $last = DocumentRevision::where('document_id', $lockedDocument->id)
                ->lockForUpdate()
                ->orderByDesc('revision_number')
                ->first();

            $nextNumber = $last ? $last->revision_number + 1 : 0;

            $revision = DocumentRevision::create([
                'document_id' => $lockedDocument->id,
                'revision_number' => $nextNumber,
                'source_revision_id' => $sourceRevisionId,
                'template_version_id' => $last?->template_version_id,
                'status' => DocumentRevision::STATUS_DRAFT,
                'data' => $data ?? $last?->data ?? [],
                'change_note' => $changeNote,
                'created_by' => $userId,
                'is_locked' => false,
            ]);

            $lockedDocument->update(['current_revision_id' => $revision->id]);

            AuditLogger::log('document_revision_created', 'document', $lockedDocument->id, [
                'revision_number' => $nextNumber,
            ]);

            return $revision;
        });
    }

    /** طبق CONFLICT-5: پیش از هر نوشتن روی محتوای یک Revision، این را صدا بزنید. */
    public function assertEditable(DocumentRevision $revision): void
    {
        if (!$revision->isEditable()) {
            throw new \RuntimeException('این نسخه قفل‌شده یا منتشرشده است و قابل ویرایش نیست — یک نسخه‌ی جدید بسازید.');
        }
    }
}
