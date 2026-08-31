<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\DocumentRevision;
use App\Models\TemplateVersion;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Storage;

/**
 * تزریق داده در قالب واقعی Word/Excel و تولید فایل خروجی — بخش مرکزی M4
 * («ساخت سند از قالب»، اولین نقطه‌ی ارزش قابل‌نمایش طبق نقشه‌راه سند معماری).
 *
 * سه دسته‌ی TemplateField (بند ۶ سند معماری):
 *  - auto:   مقدار از binding (dot-path مثل case.organization.name) روی
 *            Document/Case/Organization خوانده می‌شود. اگر binding خالی بود،
 *            خودِ کلید placeholder به‌عنوان مسیر امتحان می‌شود — یعنی نویسنده‌ی
 *            قالب می‌تواند مستقیم {{case.case_number}} تایپ کند بدون نیاز به
 *            صفحه‌ی تنظیم Binding.
 *  - manual: کاربر هنگام ساخت سند مقدار را تایپ می‌کند.
 *  - line:   داخل ردیف تکرارشونده‌ی جدول اقلام قرار می‌گیرد؛ فقط برای انواع
 *            سندی که typeSupportsLines()==true معنا دارد.
 */
class DocumentGenerationService
{
    public const DISK = TemplateService::DISK;

    /**
     * M36 (درخواست کاربر): «شماره‌ی رسمیِ خودِ سند» (مثل TC-200101-0103-R01)
     * تا لحظه‌ی Publish اصلاً وجود ندارد (DocumentPublishService/
     * DocumentNumberingService) — یعنی هیچ binding ای این‌جا، در لحظه‌ی
     * ساختِ سند، نمی‌تواند آن را resolve کند. این کلیدِ رزروشده به‌جای
     * resolve/پاک‌شدنِ معمولیِ فیلدهای auto، کاملاً از پردازشِ این متد کنار
     * گذاشته می‌شود — {{document.number}} به‌صورتِ متنِ خام در فایلِ
     * تولیدشده باقی می‌ماند تا DocumentNumberStampService، درست بعد از صدورِ
     * شماره در Publish، همان یک فایل را دوباره باز کند و مقدارِ واقعی را
     * جایش بنویسد. کاربرِ قالب فقط کافی است دقیقاً همین متن را در Word/Excel
     * تایپ کند — نیازی به ثبتِ Binding از صفحه‌ی تنظیماتِ قالب نیست.
     */
    public const LETTER_NUMBER_KEY = 'document.number';

    /**
     * @param array<string,string> $manualValues کلید = TemplateField.key، مقدار تایپ‌شده‌ی کاربر
     * @return string مسیر نسبی (روی دیسک local) فایل تولیدشده
     */
    public function generate(Document $document, DocumentRevision $revision, TemplateVersion $templateVersion, array $manualValues = []): string
    {
        $template = $templateVersion->relationLoaded('template') ? $templateVersion->template : $templateVersion->template()->first();
        $engine = app(TemplateService::class)->engineFor($template->file_type);

        $document->loadMissing('case.organization', 'case.contact');
        $resolver = BindingResolver::forDocument($document, $revision);

        $values = [];
        $lineFields = [];
        foreach ($templateVersion->fields as $field) {
            // M36: کلیدِ رزروشده هرگز از راهِ معمولِ auto/manual/line پردازش
            // نمی‌شود — حتی اگر مدیر سیستم روی صفحه‌ی تنظیماتِ قالب برایش
            // source/binding دیگری هم ثبت کرده باشد (TemplateService::
            // discoverFields() این کلید را هم مثل هر placeholder دیگری با
            // source='auto' کشف و ثبت می‌کند؛ این‌جا صریحاً override می‌شود).
            if ($field->key === self::LETTER_NUMBER_KEY) {
                continue;
            }
            switch ($field->source) {
                case 'manual':
                    $values[$field->key] = $manualValues[$field->key] ?? ($field->default_value ?? '');
                    break;
                case 'line':
                    $lineFields[] = $field;
                    break;
                case 'auto':
                default:
                    $path = $field->binding ?: $field->key;
                    $resolved = $resolver->resolve($path);
                    $values[$field->key] = $resolved !== '' ? $resolved : ($field->default_value ?? '');
                    break;
            }
        }

        [$lineRows, $lineMarker] = $this->buildLineRows($document, $lineFields);

        $absTemplatePath = Storage::disk(self::DISK)->path($templateVersion->file_path);
        $destRel = "documents/{$document->id}/revisions/{$revision->id}/generated.".$template->file_type;
        $destAbs = Storage::disk(self::DISK)->path($destRel);

        $engine->render($absTemplatePath, $values, $lineRows, $lineMarker, $destAbs, [self::LETTER_NUMBER_KEY]);

        $revision->update(['file_path' => $destRel]);

        AuditLogger::log('document_generated', 'document', $document->id, [
            'revision_number' => $revision->revision_number,
            'template_version_id' => $templateVersion->id,
        ]);

        return $destRel;
    }

    /**
     * M6 (Option C): فایل واقعیِ یک Revision منتشرشده را به‌عنوان نقطه‌ی شروعِ
     * Draft بعدی کپی می‌کند — نه رندر تازه از قالب خالی. چون بعد از اولین دور
     * ویرایش دستی در Word/Excel واقعی (upload-edit)، منبع حقیقتِ محتوا همان
     * فایل است؛ رندر دوباره از قالب همان ویرایش‌های دستی را از بین می‌برد.
     */
    public function carryForward(Document $document, DocumentRevision $from, DocumentRevision $to): string
    {
        if (!$from->file_path || !Storage::disk(self::DISK)->exists($from->file_path)) {
            throw new \RuntimeException('نسخه‌ی مبدأ فایلی برای کپی ندارد.');
        }

        $ext = pathinfo($from->file_path, PATHINFO_EXTENSION) ?: 'docx';
        $destRel = "documents/{$document->id}/revisions/{$to->id}/generated.{$ext}";

        Storage::disk(self::DISK)->makeDirectory(dirname($destRel));
        Storage::disk(self::DISK)->copy($from->file_path, $destRel);
        $to->update(['file_path' => $destRel]);

        // M40-b (رفعِ باگ): اگر نسخه‌ی مبدأ قبلاً منتشر شده بود، شماره‌ی
        // رسمی‌اش به‌جای {{document.number}} در همین فایلِ کپی‌شده هم نشسته —
        // بدونِ این رفع، وقتی همین Draft بعداً منتشر شود دیگر هیچ placeholder
        // ای برای نوشتنِ شماره‌ی تازه باقی نمی‌ماند (نگاه کنید:
        // DocumentNumberStampService::restoreAfterCarryForward()).
        if ($from->formatted_number) {
            app(DocumentNumberStampService::class)->restoreAfterCarryForward($to, $from->formatted_number);
        }

        AuditLogger::log('document_revision_carried_forward', 'document', $document->id, [
            'from_revision' => $from->revision_number,
            'to_revision' => $to->revision_number,
        ]);

        return $destRel;
    }

    /**
     * @param array<int,\App\Models\TemplateField> $lineFields
     * @return array{0: array<int,array<string,string>>, 1: string}
     */
    protected function buildLineRows(Document $document, array $lineFields): array
    {
        if ($lineFields === [] || !$document->typeSupportsLines()) {
            return [[], ''];
        }

        // اولین فیلد line-source به‌عنوان marker ردیف نمونه استفاده می‌شود —
        // دقیقاً همان قراردادی که TemplateEngine::render() انتظار دارد.
        $marker = $lineFields[0]->key;
        $lines = $document->relationLoaded('lines') ? $document->lines : $document->lines()->get();

        $rows = [];
        foreach ($lines as $line) {
            $row = [];
            foreach ($lineFields as $field) {
                $attr = $field->binding ?: $field->key;
                $row[$field->key] = $this->lineAttr($line, $attr);
            }
            $rows[] = $row;
        }

        return [$rows, $marker];
    }

    protected function lineAttr(DocumentLine $line, string $attr): string
    {
        $value = $line->{$attr} ?? null;
        if ($value === null) {
            return '';
        }
        if (is_numeric($value)) {
            return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
        }
        return (string) $value;
    }
}
