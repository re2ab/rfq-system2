<?php

namespace App\Services\Documents;

use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\TemplateField;
use App\Services\Documents\Contracts\TemplateEngine;
use App\Services\Documents\Engines\DocxEngine;
use App\Services\Documents\Engines\XlsxEngine;
use App\Services\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * مدیریت قالب — Import، اعتبارسنجی، نسخه‌بندی، پیش‌فرض (فاز ۷ سند معماری، M2).
 *
 * قانون ثابت این سرویس: قالب هرگز به‌جز از طریق «نسخه‌ی جدید» تغییر نمی‌کند —
 * حتی وقتی کاربر «آپلود مجدد» می‌زند، فایل قبلی دست‌نخورده می‌ماند و یک ردیف
 * TemplateVersion تازه ساخته می‌شود (بند ۲۵: سندهای قبلی که به نسخه‌ی قبلی
 * وصل‌اند نباید تکان بخورند).
 */
class TemplateService
{
    /** حجم مجاز آپلود قالب (بایت) — بند ۳۳. */
    public const MAX_UPLOAD_BYTES = 20 * 1024 * 1024; // 20MB

    public const DISK = 'local';

    public function engineFor(string $fileType): TemplateEngine
    {
        return match (strtolower($fileType)) {
            'docx' => new DocxEngine(),
            'xlsx' => new XlsxEngine(),
            default => throw new \InvalidArgumentException("فرمت پشتیبانی‌نشده: {$fileType}"),
        };
    }

    /**
     * چک‌لیست بند ۳۳ — پیش از هر پردازش دیگری روی فایل آپلودی اجرا می‌شود.
     *
     * @return array{ok:bool,message:string,file_type?:string}
     */
    public function validateUpload(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['docx', 'xlsx'], true)) {
            return ['ok' => false, 'message' => 'فقط فایل .docx یا .xlsx مجاز است.'];
        }

        if ($file->getSize() > self::MAX_UPLOAD_BYTES) {
            $mb = (int) (self::MAX_UPLOAD_BYTES / 1024 / 1024);
            return ['ok' => false, 'message' => "حجم فایل از {$mb} مگابایت بیشتر است."];
        }

        // امضای فایل (magic bytes) — DOCX/XLSX هر دو یک ZIP هستند (PK\x03\x04).
        // این مانع از رد کردن یک فایل غیرآفیس با پسوند جعلی می‌شود.
        $handle = fopen($file->getRealPath(), 'rb');
        $magic = $handle ? fread($handle, 4) : '';
        if ($handle) {
            fclose($handle);
        }
        if ($magic !== "PK\x03\x04") {
            return ['ok' => false, 'message' => 'محتوای فایل با پسوند آن مطابقت ندارد (فایل ZIP/Office معتبر نیست).'];
        }

        // اعتبارسنجی ساختار داخلی (word/document.xml یا xl/workbook.xml)
        $engine = $this->engineFor($ext);
        $structural = $engine->validate($file->getRealPath());
        if (!$structural['ok']) {
            return $structural;
        }

        return ['ok' => true, 'message' => 'فایل معتبر است.', 'file_type' => $ext];
    }

    /**
     * ساخت قالب جدید + نسخه‌ی اول از یک فایل واقعی Word/Excel.
     */
    public function createFromUpload(array $data, UploadedFile $file, ?int $userId): Template
    {
        $check = $this->validateUpload($file);
        if (!$check['ok']) {
            throw new \RuntimeException($check['message']);
        }
        $fileType = $check['file_type'];

        return DB::transaction(function () use ($data, $file, $fileType, $userId) {
            // ستون قدیمی `type` هنوز NOT NULL است (استفاده‌شده در DocumentController
            // برای قالب‌های HTML قدیمی) — برای قالب‌های جدید همیشه از کلید
            // document_types پر می‌شود تا هرگز NULL نشود.
            $documentType = \App\Models\DocumentType::findOrFail($data['document_type_id']);

            $template = Template::create([
                'type' => $documentType->key,
                'name' => $data['name'],
                'code' => $data['code'] ?? $this->generateCode($data['name']),
                'document_type_id' => $documentType->id,
                'file_type' => $fileType,
                'status' => 'active',
                'is_default' => false,
            ]);

            $version = $this->storeVersion($template, $file, $fileType, $userId);

            $template->update(['current_version_id' => $version->id]);

            AuditLogger::log('template_created', 'template', $template->id, [
                'name' => $template->name, 'file_type' => $fileType,
            ]);

            return $template->fresh(['currentVersion.fields']);
        });
    }

    /**
     * آپلود نسخه‌ی جدید برای قالب موجود — فایل قبلی و همه‌ی اسنادی که به آن
     * وصل‌اند دست‌نخورده می‌مانند (بند ۲۵).
     */
    public function addVersion(Template $template, UploadedFile $file, ?int $userId): TemplateVersion
    {
        $check = $this->validateUpload($file);
        if (!$check['ok']) {
            throw new \RuntimeException($check['message']);
        }
        if ($check['file_type'] !== $template->file_type) {
            throw new \RuntimeException('فرمت نسخه‌ی جدید باید با فرمت خود قالب یکی باشد ('.$template->file_type.').');
        }

        return DB::transaction(function () use ($template, $file, $userId) {
            $version = $this->storeVersion($template, $file, $template->file_type, $userId);
            $template->update(['current_version_id' => $version->id]);

            AuditLogger::log('template_version_uploaded', 'template', $template->id, [
                'version_number' => $version->version_number,
            ]);

            return $version;
        });
    }

    protected function storeVersion(Template $template, UploadedFile $file, string $fileType, ?int $userId): TemplateVersion
    {
        $nextVersion = (int) (TemplateVersion::where('template_id', $template->id)->max('version_number')) + 1;

        // ساختار مسیر طبق بخش ۸ سند معماری: بر پایه‌ی شناسه، نه نام فایل کاربر.
        $relPath = "templates/{$template->id}/v{$nextVersion}/source.{$fileType}";
        Storage::disk(self::DISK)->put($relPath, file_get_contents($file->getRealPath()));
        $absPath = Storage::disk(self::DISK)->path($relPath);

        $version = TemplateVersion::create([
            'template_id' => $template->id,
            'version_number' => $nextVersion,
            'file_path' => $relPath,
            'file_hash' => hash_file('sha256', $absPath),
            'file_size' => filesize($absPath),
            'created_by' => $userId,
        ]);

        $this->discoverFields($version, $absPath, $fileType);

        return $version;
    }

    /**
     * placeholderهای داخل فایل را کشف و در template_fields ثبت می‌کند —
     * source پیش‌فرض 'auto' است، چون این‌ها هنوز به هیچ binding ای وصل نشده‌اند؛
     * وصل‌کردن به مسیر داده (case.organization.name و مانند آن) کار مدیر سیستم
     * در صفحه‌ی تنظیمات قالب است (بند ۶).
     */
    protected function discoverFields(TemplateVersion $version, string $absPath, string $fileType): void
    {
        $engine = $this->engineFor($fileType);
        $keys = $engine->placeholders($absPath);

        foreach (array_unique($keys) as $i => $key) {
            TemplateField::create([
                'template_version_id' => $version->id,
                'key' => $key,
                'label' => $key,
                'binding' => null,
                'source' => 'auto',
                'data_type' => 'text',
                'is_required' => false,
                'sort_order' => $i,
            ]);
        }
    }

    /**
     * Rule 9: فقط یک قالب پیش‌فرض در هر نوع سند. از ترفند default_flag
     * (بخش ۴ سند معماری) استفاده می‌کند — پاک‌کردن پیش‌فرض قبلی و ست‌کردن
     * پیش‌فرض جدید در یک تراکنش.
     */
    public function setDefault(Template $template): void
    {
        DB::transaction(function () use ($template) {
            Template::where('document_type_id', $template->document_type_id)
                ->where('id', '!=', $template->id)
                ->where('default_flag', $template->document_type_id)
                ->update(['default_flag' => null, 'is_default' => false]);

            $template->update([
                'default_flag' => $template->document_type_id,
                'is_default' => true,
            ]);
        });

        AuditLogger::log('template_set_default', 'template', $template->id);
    }

    public function activate(Template $template, bool $active): void
    {
        $template->update(['status' => $active ? 'active' : 'inactive']);
        AuditLogger::log($active ? 'template_activated' : 'template_deactivated', 'template', $template->id);
    }

    /**
     * حذف نرم — فقط اگر هیچ Revisionای به هیچ نسخه‌ای از این قالب وصل نباشد.
     */
    /**
     * @param bool $force فقط مدیر سیستم (TemplateController) اجازه‌ی این حالت را
     *   می‌دهد. توجه مهم: ستون document_revisions.template_version_id با
     *   cascadeOnDelete به template_versions وصل است — یعنی اگر همین‌جا فقط
     *   $template->delete() صدا زده شود درحالی‌که سندی به آن وصل است، خودِ آن
     *   نسخه‌های سند هم پاک می‌شوند (از دست رفتن رکورد رسمی). پس force ابتدا
     *   این ستون را روی همان ردیف‌ها null می‌کند (فایل واقعی سند و شماره‌ی
     *   رسمی‌اش دست‌نخورده می‌ماند، فقط ارجاع به قالبِ حذف‌شده قطع می‌شود).
     */
    public function delete(Template $template, bool $force = false): void
    {
        $versionIds = $template->versions()->pluck('id');
        $inUse = $versionIds->isNotEmpty()
            && DB::table('document_revisions')->whereIn('template_version_id', $versionIds)->exists();

        if ($inUse && !$force) {
            throw new \RuntimeException('این قالب توسط حداقل یک سند استفاده شده و قابل حذف نیست — به‌جای حذف، غیرفعالش کنید.');
        }

        DB::transaction(function () use ($template, $versionIds, $inUse) {
            if ($inUse) {
                DB::table('document_revisions')
                    ->whereIn('template_version_id', $versionIds)
                    ->update(['template_version_id' => null]);
            }
            $template->delete();
        });

        AuditLogger::log('template_deleted', 'template', $template->id, [
            'forced_detach_in_use' => $inUse,
        ]);
    }

    protected function generateCode(string $name): string
    {
        return strtoupper(Str::slug($name, '-')).'-'.strtoupper(Str::random(4));
    }
}
