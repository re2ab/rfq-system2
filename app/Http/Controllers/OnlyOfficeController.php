<?php

namespace App\Http\Controllers;

use App\Models\DocumentRevision;
use App\Services\AuditLogger;
use App\Services\Documents\TemplateService;
use App\Services\OnlyOffice\JwtService;
use App\Services\OnlyOffice\OnlyOfficeConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * M11 — درگاه ویرایش آنلاین (ONLYOFFICE Document Server، مستقل از این اپ،
 * روی Railway به‌عنوان سرویس دوم بالا می‌آید). سه نقطه:
 *  - editOnline: صفحه‌ی وب (session-authenticated) که ویجت ONLYOFFICE را نشان می‌دهد.
 *  - download: نقطه‌ای که خودِ Document Server (سرور به سرور، بدون کوکی) فایل
 *    فعلی را از آن می‌گیرد — امنیتش با Laravel Signed URL تأمین می‌شود، نه سشن.
 *  - callback: نقطه‌ای که Document Server وضعیت ذخیره را به آن POST می‌کند —
 *    هم Signed URL هم JWT رسمی خودِ ONLYOFFICE (بند ۴ قرارداد) چک می‌شود.
 */
class OnlyOfficeController extends Controller
{
    public function editOnline(Request $request, DocumentRevision $revision, OnlyOfficeConfigService $service)
    {
        if (!$revision->isEditable()) {
            return back()->with('error', 'این نسخه دیگر قابل‌ویرایش نیست (منتشرشده یا جایگزین‌شده).');
        }

        $result = $service->buildConfig($revision, $request->user());
        if (!$result['ok']) {
            return back()->with('error', 'ویرایش آنلاین در دسترس نیست: '.$result['message']);
        }

        return view('documents.edit-online', [
            'revision' => $revision,
            'config' => $result['config'],
            'dsUrl' => config('onlyoffice.ds_url'),
        ]);
    }

    /** فقط با لینک امضاشده (بدون نیاز به لاگین) — Document Server این را مستقیم صدا می‌زند. */
    public function download(Request $request, DocumentRevision $revision)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'لینک نامعتبر یا منقضی‌شده.');
        }
        if (!$revision->file_path || !Storage::disk(TemplateService::DISK)->exists($revision->file_path)) {
            abort(404, 'فایل پیدا نشد.');
        }

        return Storage::disk(TemplateService::DISK)->response($revision->file_path);
    }

    public function callback(Request $request, DocumentRevision $revision)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'لینک نامعتبر یا منقضی‌شده.');
        }

        $headerName = config('onlyoffice.jwt_header', 'Authorization');
        $raw = (string) $request->header($headerName, '');
        $jwt = str_starts_with($raw, 'Bearer ') ? substr($raw, 7) : $raw;

        $secret = config('onlyoffice.jwt_secret');
        if ($secret !== '') {
            $verified = $jwt !== '' ? JwtService::decode($jwt, $secret) : null;
            if ($verified === null) {
                Log::warning('onlyoffice_callback_bad_jwt', ['revision_id' => $revision->id]);
                return response()->json(['error' => 1]);
            }
        }

        $status = (int) $request->input('status');
        $fileUrl = $request->input('url');

        // ۲=آماده‌ی ذخیره‌ی نهایی (بعد از بسته‌شدن همه‌ی ادیتورها)، ۶=force-save حین ویرایش.
        if (in_array($status, [2, 6], true) && $fileUrl) {
            if (!$revision->isEditable()) {
                // بین باز شدن ادیتور و این callback، نسخه منتشر شده — محتوای جدید
                // نادیده گرفته می‌شود تا Rule 11 نقض نشود.
                Log::warning('onlyoffice_callback_locked_revision', ['revision_id' => $revision->id]);
                return response()->json(['error' => 0]);
            }

            try {
                $response = Http::timeout(30)->get($fileUrl);
                if ($response->successful()) {
                    Storage::disk(TemplateService::DISK)->put($revision->file_path, $response->body());
                    $revision->update(['editor_key' => null]); // بار بعد کلید تازه ساخته می‌شود
                    AuditLogger::log('document_revision_online_edited', 'document', $revision->document_id, [
                        'revision_number' => $revision->revision_number,
                        'status' => $status,
                    ]);
                } else {
                    Log::warning('onlyoffice_callback_fetch_failed', ['revision_id' => $revision->id, 'http_status' => $response->status()]);
                }
            } catch (\Throwable $e) {
                Log::error('onlyoffice_callback_exception', ['revision_id' => $revision->id, 'error' => $e->getMessage()]);
            }
        } elseif (in_array($status, [3, 7], true)) {
            Log::warning('onlyoffice_save_error', ['revision_id' => $revision->id, 'status' => $status]);
        }

        // طبق قرارداد رسمی ONLYOFFICE، پاسخ باید دقیقاً همین باشد وگرنه ادیتور خطای ذخیره نشان می‌دهد.
        return response()->json(['error' => 0]);
    }
}
