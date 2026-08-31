<?php

namespace App\Services\Documents;

use App\Models\DocumentRevision;
use Illuminate\Support\Facades\Storage;

/**
 * M36 (درخواست کاربر): «همین عنوان، مثلاً TC-200101-0103-R01» — یعنی شماره‌ی
 * رسمیِ خودِ Revision (DocumentRevision::formatted_number) به‌عنوانِ
 * placeholder در قالب. چون این شماره طبق معماری فقط در Publish صادر می‌شود
 * (DocumentPublishService)، در لحظه‌ی ساختِ سند (DocumentGenerationService::
 * generate()) اصلاً وجود ندارد — پس {{document.number}} در آن لحظه عمداً
 * دست‌نخورده باقی می‌ماند (به‌جای پاک‌شدن یا خالی‌شدن؛ نگاه کنید:
 * DocumentGenerationService::LETTER_NUMBER_KEY).
 *
 * این سرویس دقیقاً همان لحظه‌ای که DocumentPublishService شماره را صادر
 * می‌کند صدا زده می‌شود: فایلِ *همان* Revision را دوباره باز می‌کند (با همان
 * پردازشگرِ zero-dependency ای که خودِ generate() هم استفاده می‌کند — نه
 * رندرِ دوباره از قالب، فقط یک ویرایشِ متنیِ خیلی کوچک روی فایلِ از‌قبل‌ساخته‌
 * شده)، مقدار را جای placeholder می‌گذارد، و فایل را سرِ جایش (همان مسیر)
 * ذخیره می‌کند. اگر قالب اصلاً از این placeholder استفاده نکرده باشد، هیچ
 * کاری انجام نمی‌شود (بدونِ بازنویسیِ بی‌دلیلِ فایل).
 */
class DocumentNumberStampService
{
    public function stamp(DocumentRevision $revision, string $formattedNumber): void
    {
        if (!$revision->file_path || !Storage::disk(TemplateService::DISK)->exists($revision->file_path)) {
            return;
        }

        $ext = strtolower(pathinfo($revision->file_path, PATHINFO_EXTENSION));
        if (!in_array($ext, ['docx', 'xlsx'], true)) {
            return;
        }

        $absPath = Storage::disk(TemplateService::DISK)->path($revision->file_path);
        $key = DocumentGenerationService::LETTER_NUMBER_KEY;

        $processor = $ext === 'xlsx' ? new XlsxTemplateProcessor($absPath) : new DocxTemplateProcessor($absPath);

        if (!in_array($key, $processor->placeholders(), true)) {
            // این قالب اصلاً {{document.number}} ندارد — دست‌نزدن، بدونِ بازنویسیِ بی‌دلیلِ فایل.
            return;
        }

        $processor->setValue($key, $formattedNumber)->clearUnused()->saveAs($absPath);
    }

    /**
     * M40-b (رفعِ باگ): وقتی DocumentGenerationService::carryForward() فایلِ
     * یک Revisionِ *قبلاً منتشرشده* را برای Draft بعدی کپی می‌کند، متنِ
     * شماره‌ی رسمیِ آن (که stamp() قبلاً جای {{document.number}} نوشته بود)
     * در همان کپی هم باقی می‌ماند — یعنی وقتی خودِ این Draft هم منتشر شود،
     * دیگر هیچ {{document.number}}ای برای stamp() باقی نمانده و شماره‌ی
     * تازه هرگز نوشته نمی‌شود (این‌جا دقیقاً همان چیزی که کاربر گزارش داد:
     * «پس از انتشار، در جای placeholder ثبت نمی‌شود» — برای هر Revision به‌جز
     * اولینِ سند). این متد بلافاصله بعدِ carryForward صدا زده می‌شود تا متنِ
     * قدیمی را به {{document.number}} برگرداند و مسیرِ Publish/Stamp برای
     * این Draft هم باز بماند.
     */
    public function restoreAfterCarryForward(DocumentRevision $revision, string $previousFormattedNumber): void
    {
        if (!$revision->file_path || !Storage::disk(TemplateService::DISK)->exists($revision->file_path)) {
            return;
        }

        $ext = strtolower(pathinfo($revision->file_path, PATHINFO_EXTENSION));
        if (!in_array($ext, ['docx', 'xlsx'], true)) {
            return;
        }

        $absPath = Storage::disk(TemplateService::DISK)->path($revision->file_path);
        $key = DocumentGenerationService::LETTER_NUMBER_KEY;

        $processor = $ext === 'xlsx' ? new XlsxTemplateProcessor($absPath) : new DocxTemplateProcessor($absPath);
        $processor->restoreLiteralAsPlaceholder($previousFormattedNumber, $key)->saveAs($absPath);
    }
}
