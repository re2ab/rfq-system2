<?php
namespace App\Services;

use App\Models\AppSetting;
use App\Support\ModuleGate;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

class RealMailService
{
    public function applyConfigFromSettings(): void
    {
        if (!ModuleGate::enabled('real_email')) {
            return;
        }
        $host = AppSetting::get('mail_smtp_host', env('MAIL_HOST', ''));
        if (!$host) {
            return;
        }
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp', [
            'transport' => 'smtp',
            'host' => $host,
            'port' => (int) AppSetting::get('mail_smtp_port', '587'),
            'encryption' => AppSetting::get('mail_smtp_encryption', 'tls') ?: null,
            'username' => AppSetting::get('mail_smtp_username', ''),
            'password' => AppSetting::get('mail_smtp_password', ''),
            'timeout' => 30,
        ]);
        Config::set('mail.from', [
            'address' => AppSetting::get('mail_from_address', 'noreply@example.com'),
            'name' => AppSetting::get('mail_from_name', 'RFQ'),
        ]);
    }

    public function sendRaw(string $to, string $subject, string $body): array
    {
        if (!ModuleGate::enabled('real_email')) {
            return ['ok' => false, 'message' => 'ماژول ایمیل واقعی غیرفعال است'];
        }
        $this->applyConfigFromSettings();
        try {
            Mail::raw($body, function ($m) use ($to, $subject) {
                $m->to($to)->subject($subject);
            });
            return ['ok' => true];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function imapStatus(): array
    {
        if (!ModuleGate::enabled('real_email')) {
            return ['ok' => false, 'message' => 'ماژول غیرفعال'];
        }
        $host = AppSetting::get('mail_imap_host', '');
        if (!$host || !function_exists('imap_open')) {
            return ['ok' => false, 'message' => 'IMAP host خالی است یا extension imap روی PHP نصب نیست'];
        }
        $mailbox = sprintf('{%s:%s/imap/%s}INBOX',
            $host,
            AppSetting::get('mail_imap_port', '993'),
            AppSetting::get('mail_imap_encryption', 'ssl')
        );
        $user = AppSetting::get('mail_imap_username', '');
        $pass = AppSetting::get('mail_imap_password', '');
        try {
            $inbox = @imap_open($mailbox, $user, $pass, OP_HALFOPEN, 1);
            if (!$inbox) {
                return ['ok' => false, 'message' => imap_last_error() ?: 'اتصال ناموفق'];
            }
            imap_close($inbox);
            return ['ok' => true, 'message' => 'اتصال IMAP موفق'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
