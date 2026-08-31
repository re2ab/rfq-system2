<?php

namespace App\Services\OnlyOffice;

use App\Models\DocumentRevision;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * ساخت config واقعی که به DocsAPI.DocEditor داده می‌شود — طبق قرارداد رسمی
 * ONLYOFFICE (document/editorConfig/documentType + token امضاشده با JWT).
 */
class OnlyOfficeConfigService
{
    public const DOCUMENT_TYPE_MAP = [
        'docx' => 'word',
        'xlsx' => 'cell',
    ];

    public function isConfigured(): bool
    {
        return config('onlyoffice.ds_url') !== '' && config('onlyoffice.jwt_secret') !== '';
    }

    /** کلید cache-bust جدید — هر بار محتوای فایل واقعاً عوض شود باید عوض شود. */
    public function freshKey(DocumentRevision $revision): string
    {
        // فقط از حروف/عدد لاتین — دقیقاً هم‌راستا با محدودیت charset رسمی ONLYOFFICE.
        return substr(hash('sha256', $revision->id.'|'.microtime(true).'|'.Str::random(8)), 0, 32);
    }

    /** @return array{ok:bool,message?:string,config?:array<string,mixed>} */
    public function buildConfig(DocumentRevision $revision, ?User $user): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'message' => 'آدرس یا رمز مشترک سرویس ONLYOFFICE در تنظیمات محیطی ست نشده.'];
        }
        if (!$revision->file_path) {
            return ['ok' => false, 'message' => 'این نسخه هنوز فایلی ندارد.'];
        }

        $ext = strtolower(pathinfo($revision->file_path, PATHINFO_EXTENSION));
        $docType = self::DOCUMENT_TYPE_MAP[$ext] ?? null;
        if (!$docType) {
            return ['ok' => false, 'message' => 'ویرایش آنلاین فقط برای فایل‌های docx/xlsx پشتیبانی می‌شود.'];
        }

        if (!$revision->editor_key) {
            $revision->update(['editor_key' => $this->freshKey($revision)]);
        }

        $downloadUrl = URL::temporarySignedRoute('onlyoffice.download', now()->addHours(6), ['revision' => $revision->id]);
        $callbackUrl = URL::temporarySignedRoute('onlyoffice.callback', now()->addHours(6), ['revision' => $revision->id]);

        $title = ($revision->document?->documentType?->name_fa ?? 'سند').'-'.($revision->formatted_number ?: 'draft').'.'.$ext;

        $document = [
            'fileType' => $ext,
            'key' => $revision->editor_key,
            'title' => $title,
            'url' => $downloadUrl,
            'permissions' => [
                'edit' => true,
                'download' => true,
                'comment' => true,
                'review' => false,
                'print' => true,
                'fillForms' => true,
            ],
        ];

        $editorConfig = [
            'callbackUrl' => $callbackUrl,
            'user' => [
                'id' => (string) ($user->id ?? 0),
                'name' => $user->name ?? 'کاربر',
            ],
            'mode' => 'edit',
            'lang' => 'fa',
            'customization' => [
                'forcesave' => true,
                'autosave' => true,
                'chat' => false,
                'comments' => true,
            ],
        ];

        $tokenPayload = ['document' => $document, 'editorConfig' => $editorConfig];
        $token = JwtService::encode($tokenPayload, config('onlyoffice.jwt_secret'));

        return [
            'ok' => true,
            'config' => [
                'documentType' => $docType,
                'document' => $document,
                'editorConfig' => $editorConfig,
                'token' => $token,
                'width' => '100%',
                'height' => '100%',
            ],
        ];
    }
}
