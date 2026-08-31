<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\DocumentRevision;
use App\Models\DocumentType;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * M9الف: ثبت یک فایل *موجود* (نه رندرشده از قالب) به‌عنوان سند یک پرونده —
 * چه از آپلود دستی کاربر، چه از Google Drive (M9ب). خروجی دقیقاً همان شکل
 * Document+DocumentRevision مسیر M4 است (همان قرارداد مسیر فایل)، پس تمام
 * قابلیت‌های موجود — دانلود، Publish، ارسال ایمیل، تبدیل PDF، Option C — روی
 * این اسناد هم بدون هیچ کد اضافه‌ای کار می‌کنند.
 */
class DocumentFileImportService
{
    public const MAX_BYTES = 20 * 1024 * 1024; // 20MB — هم‌راستا با TemplateService
    public const ALLOWED_EXT = ['docx', 'xlsx', 'pdf'];

    public function __construct(protected DocumentRevisionService $revisions)
    {
    }

    /** @return array{ok:bool,message:string,file_type?:string} */
    public function validate(string $absPath, string $originalName, int $size): array
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXT, true)) {
            return ['ok' => false, 'message' => 'فقط فایل .docx، .xlsx یا .pdf مجاز است.'];
        }
        if ($size > self::MAX_BYTES) {
            $mb = (int) (self::MAX_BYTES / 1024 / 1024);
            return ['ok' => false, 'message' => "حجم فایل از {$mb} مگابایت بیشتر است."];
        }
        if (!is_file($absPath)) {
            return ['ok' => false, 'message' => 'فایل پیدا نشد.'];
        }

        $handle = fopen($absPath, 'rb');
        $magic = $handle ? fread($handle, 4) : '';
        if ($handle) {
            fclose($handle);
        }

        if ($ext === 'pdf') {
            if (substr((string) $magic, 0, 4) !== '%PDF') {
                return ['ok' => false, 'message' => 'محتوای فایل با پسوند PDF مطابقت ندارد.'];
            }
        } elseif ($magic !== "PK\x03\x04") {
            return ['ok' => false, 'message' => 'محتوای فایل یک آرشیو Office معتبر (ZIP) نیست — پسوند جعلی است.'];
        }

        return ['ok' => true, 'message' => 'فایل معتبر است.', 'file_type' => $ext];
    }

    /**
     * @param string $absSourcePath مسیر مطلق فایل روی دیسک محلی (آپلود موقت PHP یا فایل دانلودشده از Drive)
     * @param array<string,mixed> $meta فقط برای AuditLogger — مثلاً ['source' => 'google_drive', 'drive_file_id' => '...']
     */
    public function importAsDocument(
        int $caseId,
        int $documentTypeId,
        string $absSourcePath,
        string $originalName,
        ?string $title,
        ?int $userId,
        array $meta = []
    ): Document {
        $check = $this->validate($absSourcePath, $originalName, (int) (filesize($absSourcePath) ?: 0));
        if (!$check['ok']) {
            throw new \RuntimeException($check['message']);
        }

        $documentType = DocumentType::findOrFail($documentTypeId);

        // M25 (درخواست کاربر): پیش از ساختِ یک سند کاملاً جدا، تلاش می‌کنیم
        // ببینیم آیا نام فایل در واقع به یک سند *موجود* اشاره می‌کند — یعنی
        // این فایل رویژنِ تازه‌ی همان سند است، نه یک سند بی‌ربط. اگر تطبیق
        // پیدا شد، به‌جای Document::create، یک Draft تازه روی همان سند
        // ساخته می‌شود (دقیقاً هم‌خانواده با اصلاح «ساخت کپی» در M23).
        $matchedDocument = $this->matchExistingDocumentFromFilename($originalName, $caseId, $documentTypeId);
        if ($matchedDocument) {
            return $this->attachAsNewRevision($matchedDocument, $absSourcePath, $check['file_type'], $originalName, $userId, $meta);
        }

        return DB::transaction(function () use ($caseId, $documentType, $absSourcePath, $check, $title, $userId, $originalName, $meta) {
            $doc = Document::create([
                'case_id' => $caseId,
                'type' => $documentType->key,
                'document_type_id' => $documentType->id,
                // شماره‌ی رسمی فقط در Publish صادر می‌شود (بند طراحی M1/M4)؛ تا آن
                // لحظه یک مقدار موقت یکتا در ستون قدیمی NOT NULL+UNIQUE می‌نشیند.
                'document_number' => 'DRAFT-'.uniqid(),
                'status' => Document::STATUS_DRAFT,
                'title' => $title,
            ]);

            $revision = DocumentRevision::create([
                'document_id' => $doc->id,
                // هم‌راستا با DocumentRevisionService::createInitial() — اولین
                // ریویژن هر سند از صفر شروع می‌شود (R00)، نه از ۱.
                'revision_number' => 0,
                'status' => DocumentRevision::STATUS_DRAFT,
                'created_by' => $userId,
                'is_locked' => false,
            ]);
            $doc->update(['current_revision_id' => $revision->id]);

            $destRel = "documents/{$doc->id}/revisions/{$revision->id}/generated.".$check['file_type'];
            Storage::disk(TemplateService::DISK)->makeDirectory(dirname($destRel));
            Storage::disk(TemplateService::DISK)->put($destRel, file_get_contents($absSourcePath));
            $revision->update(['file_path' => $destRel]);

            AuditLogger::log('document_imported', 'document', $doc->id, array_merge([
                'original_name' => $originalName,
                'source' => $meta['source'] ?? 'upload',
            ], $meta));

            return $doc->fresh(['currentRevision']);
        });
    }

    // اصلاح M23: متد copyFromRevision() که این‌جا در M21 اضافه شده بود (ساخت
    // یک Document مستقل جدا برای «ساخت کپی») بر اساس مثال دقیق کاربر اشتباه
    // بود — منظور واقعی از «ساخت کپی»، یک Revision تازه روی همان سند است، نه
    // یک سند جداگانه. آن پیاده‌سازی حذف شد؛ منطق درست حالا در
    // DocumentController::copyRevision() است (ترکیب DocumentRevisionService
    // + DocumentGenerationService::carryForward()، بدون کد تکراری تازه).

    /**
     * M25 (درخواست کاربر): تلاش برای تشخیص خودکارِ اینکه نام فایلِ آپلودی/
     * وارد‌شده از فضای ابری، در واقع اشاره به یک سند *موجود* دارد — مثلاً
     * «TC-100151-1613-02.docx» یعنی رویژنِ تازه‌ی همان سندی که number_base
     * آن «TC-100151-1613» است.
     *
     * الگو: پیشوندِ حروفی (مثل TC/FI/INV) + سریال عددی + برچسبِ‌پرونده‌ی
     * عددی — دقیقاً همان قالبی که DocumentNumberingService::ensureBaseNumber()
     * برای number_base می‌سازد — با یک پسوندِ عددیِ اختیاری در انتها (با یا
     * بدون حرف R) که همان شماره‌ی رویژن است اما *فقط برای تطبیق* استفاده
     * می‌شود، نه برای تعیینِ شماره‌ی واقعیِ رویژنِ تازه (آن همیشه توسط
     * DocumentRevisionService::createNextDraft به‌صورت «آخرین + ۱» محاسبه
     * می‌شود — دقیقاً هم‌قاعده با اصلاح «ساخت کپی» در M23، تا هرگز به عددِ
     * دلخواهِ داخلِ نام فایل اعتماد نشود).
     *
     * برای امنیت/جلوگیری از تطبیقِ اشتباه بین پرونده‌ها، تطبیق فقط وقتی
     * پذیرفته می‌شود که caseId و documentTypeId انتخاب‌شده در فرم هم دقیقاً
     * با سندِ پیداشده یکی باشند — در غیر این صورت null برمی‌گردد و مسیر
     * قدیمی (ساختِ سند مستقل جدید) دنبال می‌شود، بدون هیچ خطا/توقفی.
     */
    protected function matchExistingDocumentFromFilename(string $originalName, int $caseId, int $documentTypeId): ?Document
    {
        $name = pathinfo($originalName, PATHINFO_FILENAME);
        if (!preg_match('/(?<base>[A-Za-z]{1,6}-\d{1,12}-\d{1,6})-R?\d{1,4}(?=\D|$)/i', $name, $m)) {
            return null;
        }

        $document = Document::whereRaw('UPPER(number_base) = ?', [strtoupper($m['base'])])->first();
        if (!$document) {
            return null;
        }

        // اگر پرونده/نوعِ سند انتخاب‌شده در فرم با سندِ پیداشده نمی‌خواند،
        // احتمالاً کاربر اشتباهاً پرونده‌ی دیگری را انتخاب کرده — به‌جای
        // تطبیقِ خطرناک (چسباندن فایل به سندِ پرونده‌ای دیگر)، محافظه‌کارانه
        // رفتار می‌کنیم و اجازه می‌دهیم مسیر قدیمی یک سند جدید بسازد.
        if ((int) $document->case_id !== $caseId || (int) $document->document_type_id !== $documentTypeId) {
            return null;
        }

        return $document;
    }

    /** ساختِ یک Draft تازه روی سندِ *موجودِ* تطبیق‌داده‌شده، با محتوای فایلِ وارد‌شده. */
    protected function attachAsNewRevision(
        Document $document,
        string $absSourcePath,
        string $fileType,
        string $originalName,
        ?int $userId,
        array $meta
    ): Document {
        return DB::transaction(function () use ($document, $absSourcePath, $fileType, $originalName, $userId, $meta) {
            $changeNote = 'وارد شده از فایل «'.$originalName.'» (تشخیص خودکار شماره‌ی سند از نام فایل)';
            $revision = $this->revisions->createNextDraft($document, null, $changeNote, $userId);

            $destRel = "documents/{$document->id}/revisions/{$revision->id}/generated.{$fileType}";
            Storage::disk(TemplateService::DISK)->makeDirectory(dirname($destRel));
            Storage::disk(TemplateService::DISK)->put($destRel, file_get_contents($absSourcePath));
            $revision->update(['file_path' => $destRel]);

            AuditLogger::log('document_imported_as_revision', 'document', $document->id, array_merge([
                'original_name' => $originalName,
                'matched_number_base' => $document->number_base,
                'new_revision_number' => $revision->revision_number,
                'source' => $meta['source'] ?? 'upload',
            ], $meta));

            return $document->fresh(['currentRevision']);
        });
    }
}
