<?php
namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GoogleDriveOAuthController extends Controller
{
    protected function clientId(): string
    {
        return AppSetting::get('backup_gdrive_client_id', env('GOOGLE_DRIVE_CLIENT_ID', ''));
    }

    protected function clientSecret(): string
    {
        return AppSetting::get('backup_gdrive_client_secret', env('GOOGLE_DRIVE_CLIENT_SECRET', ''));
    }

    protected function redirectUri(): string
    {
        return route('settings.backup.gdrive.callback');
    }

    public function connect()
    {
        $id = $this->clientId();
        if (!$id) {
            return redirect()->route('settings.backup')
                ->withErrors(['gdrive' => 'ابتدا Client ID گوگل را در تنظیمات ابری ذخیره کنید.']);
        }
        $params = http_build_query([
            'client_id' => $id,
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/drive.file',
            'access_type' => 'offline',
            'prompt' => 'consent',
        ]);
        return redirect('https://accounts.google.com/o/oauth2/v2/auth?'.$params);
    }

    public function callback(Request $request)
    {
        if ($request->get('error')) {
            return redirect()->route('settings.backup')
                ->withErrors(['gdrive' => 'مجوز گوگل رد شد: '.$request->get('error')]);
        }
        $code = $request->get('code');
        if (!$code) {
            return redirect()->route('settings.backup')->withErrors(['gdrive' => 'کد OAuth دریافت نشد']);
        }
        $res = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'redirect_uri' => $this->redirectUri(),
            'grant_type' => 'authorization_code',
        ]);
        if (!$res->successful()) {
            return redirect()->route('settings.backup')
                ->withErrors(['gdrive' => 'خطا در تبادل توکن: '.$res->body()]);
        }
        $data = $res->json();
        if (!empty($data['access_token'])) {
            AppSetting::set('backup_gdrive_access_token', $data['access_token']);
        }
        if (!empty($data['refresh_token'])) {
            AppSetting::set('backup_gdrive_refresh_token', $data['refresh_token']);
        }
        if (!empty($data['expires_in'])) {
            AppSetting::set('backup_gdrive_token_expires_at', (string) (time() + (int) $data['expires_in']));
        }
        return redirect()->route('settings.backup')->with('success', 'اتصال Google Drive برقرار شد.');
    }

    public function disconnect()
    {
        AppSetting::set('backup_gdrive_access_token', '');
        AppSetting::set('backup_gdrive_refresh_token', '');
        AppSetting::set('backup_gdrive_token_expires_at', '');
        return redirect()->route('settings.backup')->with('success', 'اتصال Google Drive قطع شد.');
    }
}
