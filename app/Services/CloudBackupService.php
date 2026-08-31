<?php
namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Off-site backup push.
 * Supported modes (settings):
 * - none
 * - local_mirror: copy to BACKUP_CLOUD_PATH or setting path (NAS/mounted drive)
 * - webhook: POST multipart to a secure receiver you host
 * - google_drive / box: requires OAuth tokens in settings (stub until credentials provided)
 */
class CloudBackupService
{
    public function driver(): string
    {
        return AppSetting::get('backup_cloud_driver', 'none');
    }

    public function pushIfEnabled(string $absolutePath, string $filename): array
    {
        $driver = $this->driver();
        if ($driver === 'none' || $driver === '') {
            return ['ok' => true, 'skipped' => true];
        }
        if (!is_file($absolutePath)) {
            return ['ok' => false, 'message' => 'file missing'];
        }

        return match ($driver) {
            'local_mirror' => $this->pushLocalMirror($absolutePath, $filename),
            'webhook' => $this->pushWebhook($absolutePath, $filename),
            'google_drive' => $this->pushGoogleDrive($absolutePath, $filename),
            'box' => $this->pushBox($absolutePath, $filename),
            default => ['ok' => false, 'message' => 'unknown driver'],
        };
    }

    protected function pushLocalMirror(string $path, string $filename): array
    {
        $destDir = AppSetting::get('backup_cloud_path', env('BACKUP_CLOUD_PATH', ''));
        if (!$destDir) {
            return ['ok' => false, 'message' => 'مسیر آینه ابری تنظیم نشده'];
        }
        if (!is_dir($destDir) && !@mkdir($destDir, 0750, true)) {
            return ['ok' => false, 'message' => 'ایجاد پوشه مقصد ممکن نیست'];
        }
        $dest = rtrim($destDir, '/').'/'.$filename;
        if (!@copy($path, $dest)) {
            return ['ok' => false, 'message' => 'کپی به مسیر آینه ناموفق'];
        }
        @chmod($dest, 0640);
        return ['ok' => true, 'path' => $dest];
    }

    protected function pushWebhook(string $path, string $filename): array
    {
        $url = AppSetting::get('backup_cloud_webhook_url', '');
        $token = AppSetting::get('backup_cloud_webhook_token', '');
        if (!$url) {
            return ['ok' => false, 'message' => 'webhook url empty'];
        }
        try {
            $req = Http::timeout(120)->attach('file', file_get_contents($path), $filename);
            if ($token) {
                $req = $req->withToken($token);
            }
            $res = $req->post($url, ['filename' => $filename]);
            return ['ok' => $res->successful(), 'status' => $res->status()];
        } catch (\Throwable $e) {
            Log::warning('cloud backup webhook failed', ['e' => $e->getMessage()]);
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * اصلاح M9: از protected به public تغییر کرد تا DocumentDriveImportService
     * (وارد کردن سند از Google Drive) بتواند همان توکنِ متصل‌شده‌ی موجود را
     * دوباره استفاده کند — یک اتصال Drive برای کل سیستم، نه یکی برای Backup و
     * یکی جدا برای Import. تغییر فقط سطح دسترسی است، رفتار داخلی دست‌نخورده.
     */
    public function ensureGoogleAccessToken(): string
    {
        $token = AppSetting::get('backup_gdrive_access_token', '');
        $expires = (int) AppSetting::get('backup_gdrive_token_expires_at', '0');
        $refresh = AppSetting::get('backup_gdrive_refresh_token', '');
        if ($token && $expires > time() + 60) {
            return $token;
        }
        if (!$refresh) {
            return $token;
        }
        $clientId = AppSetting::get('backup_gdrive_client_id', env('GOOGLE_DRIVE_CLIENT_ID', ''));
        $clientSecret = AppSetting::get('backup_gdrive_client_secret', env('GOOGLE_DRIVE_CLIENT_SECRET', ''));
        if (!$clientId || !$clientSecret) {
            return $token;
        }
        try {
            $res = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $refresh,
                'grant_type' => 'refresh_token',
            ]);
            if ($res->successful()) {
                $data = $res->json();
                if (!empty($data['access_token'])) {
                    AppSetting::set('backup_gdrive_access_token', $data['access_token']);
                    if (!empty($data['expires_in'])) {
                        AppSetting::set('backup_gdrive_token_expires_at', (string) (time() + (int) $data['expires_in']));
                    }
                    return $data['access_token'];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('gdrive refresh failed', ['e' => $e->getMessage()]);
        }
        return $token;
    }

    protected function pushGoogleDrive(string $path, string $filename): array
    {
        $token = $this->ensureGoogleAccessToken();
        $folder = AppSetting::get('backup_gdrive_folder_id', '');
        if (!$token) {
            return ['ok' => false, 'message' => 'توکن Google Drive تنظیم نشده — دکمه اتصال به گوگل را بزنید'];
        }
        try {
            $meta = ['name' => $filename];
            if ($folder) {
                $meta['parents'] = [$folder];
            }
            $res = Http::withToken($token)
                ->attach('metadata', json_encode($meta), 'metadata.json', ['Content-Type' => 'application/json'])
                ->attach('file', file_get_contents($path), $filename)
                ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart');
            return ['ok' => $res->successful(), 'body' => $res->json()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    protected function pushBox(string $path, string $filename): array
    {
        $token = AppSetting::get('backup_box_access_token', '');
        $folder = AppSetting::get('backup_box_folder_id', '0');
        if (!$token) {
            return ['ok' => false, 'message' => 'توکن Box تنظیم نشده'];
        }
        try {
            $res = Http::withToken($token)
                ->attach('attributes', json_encode(['name' => $filename, 'parent' => ['id' => $folder]]))
                ->attach('file', file_get_contents($path), $filename)
                ->post('https://upload.box.com/api/2.0/files/content');
            return ['ok' => $res->successful(), 'body' => $res->json()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
