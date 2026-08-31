<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\CaseModel;
use App\Models\DocumentType;
use App\Services\CloudBackupService;
use App\Services\Documents\DocumentFileImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * M9ب: انتخاب یک فایل *موجود* در Google Drive متصل‌شده (همان اتصال بخش
 * تنظیمات → پشتیبان‌گیری ابری) و ثبت آن به‌عنوان سند یک پرونده — دقیقاً با
 * همان قرارداد DocumentFileImportService که M9الف (آپلود دستی) استفاده می‌کند.
 *
 * نکته‌ی مهم درباره‌ی scope: اتصال موجود Drive با
 * scope=https://www.googleapis.com/auth/drive.file ساخته شده که فقط به
 * فایل‌هایی دسترسی می‌دهد که همین اپ ساخته یا کاربر آن‌ها را از طریق ویجت
 * Picker گوگل به‌صورت صریح انتخاب کرده باشد — پس «چسباندن لینک/ID یک فایل
 * دلخواه» کار نمی‌کند و باید حتماً از Picker (در ویوی create) استفاده شود.
 */
class DocumentDriveController extends Controller
{
    public function __construct(
        protected CloudBackupService $cloud,
        protected DocumentFileImportService $importer
    ) {
    }

    public function create()
    {
        $connected = (bool) AppSetting::get('backup_gdrive_refresh_token', '')
            || (bool) AppSetting::get('backup_gdrive_access_token', '');

        if (!$connected) {
            return redirect()->route('settings.backup')
                ->withErrors(['gdrive' => 'برای Import سند از Google Drive، ابتدا از تنظیمات → پشتیبان‌گیری ابری، اتصال Google Drive را برقرار کنید.']);
        }

        // توکن تازه برای ویجت Picker سمت کلاینت — اگر منقضی شده باشد همین‌جا
        // با refresh token تمدید می‌شود (همان منطق CloudBackupService).
        $accessToken = $this->cloud->ensureGoogleAccessToken();
        if (!$accessToken) {
            return redirect()->route('settings.backup')
                ->withErrors(['gdrive' => 'دریافت توکن معتبر از Google Drive ممکن نشد — اتصال را دوباره برقرار کنید.']);
        }

        $cases = CaseModel::orderByDesc('id')->limit(100)->get(['id', 'case_number', 'title']);
        $documentTypes = DocumentType::active();
        $apiKey = AppSetting::get('backup_gdrive_api_key', '');

        return view('documents.drive', compact('cases', 'documentTypes', 'accessToken', 'apiKey'));
    }

    public function import(Request $request)
    {
        $data = $request->validate([
            'case_id' => 'required|exists:cases,id',
            'document_type_id' => 'required|exists:document_types,id',
            'title' => 'nullable|string|max:255',
            'drive_file_id' => 'required|string|max:200',
            'drive_file_name' => 'required|string|max:255',
        ]);

        $token = $this->cloud->ensureGoogleAccessToken();
        if (!$token) {
            return back()->withErrors(['drive_file_id' => 'اتصال Google Drive معتبر نیست — دوباره متصل شوید.'])->withInput();
        }

        $fileId = $data['drive_file_id'];

        try {
            $content = Http::withToken($token)
                ->get("https://www.googleapis.com/drive/v3/files/{$fileId}", ['alt' => 'media']);
        } catch (\Throwable $e) {
            return back()->withErrors(['drive_file_id' => 'خطا در دریافت فایل از Drive: '.$e->getMessage()])->withInput();
        }

        if (!$content->successful()) {
            return back()->withErrors(['drive_file_id' => 'دریافت فایل از Drive ناموفق بود (کد '.$content->status().').'])->withInput();
        }

        $tmp = tempnam(sys_get_temp_dir(), 'gdrive_');
        file_put_contents($tmp, $content->body());

        try {
            $document = $this->importer->importAsDocument(
                (int) $data['case_id'],
                (int) $data['document_type_id'],
                $tmp,
                $data['drive_file_name'],
                $data['title'] ?? null,
                auth()->id(),
                ['source' => 'google_drive', 'drive_file_id' => $fileId]
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['drive_file_id' => $e->getMessage()])->withInput();
        } finally {
            @unlink($tmp);
        }

        // M25: همان منطقِ تشخیص «آیا به سند موجود چسبانده شد؟» — DocumentUploadController را ببینید.
        $wasAttachedToExisting = $document->revisions()->count() > 1;
        $message = $wasAttachedToExisting
            ? 'شماره‌ی داخلِ نام فایل با این سند تطبیق پیدا کرد — به‌عنوان رویژن Draft تازه‌ی همین سند ثبت شد (به‌جای سند جدید).'
            : 'سند از Google Drive ثبت شد — پیش‌نویس آماده‌ی دانلود/انتشار است.';

        return redirect()->route('documents.show', $document)->with('success', $message);
    }
}
