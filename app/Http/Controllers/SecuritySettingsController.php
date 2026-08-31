<?php
namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Services\AuditLogger;
use App\Services\FieldAclService;
use App\Services\RealMailService;
use App\Support\ModuleGate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SecuritySettingsController extends Controller
{
    public function index(FieldAclService $acl)
    {
        $fields = $acl->all();
        $audits = Schema::hasTable('audit_logs')
            ? DB::table('audit_logs')->orderByDesc('id')->limit(50)->get()
            : collect();
        return view('settings.security', [
            'fields' => $fields,
            'audits' => $audits,
            'mail_smtp_host' => AppSetting::get('mail_smtp_host', ''),
            'mail_smtp_port' => AppSetting::get('mail_smtp_port', '587'),
            'mail_smtp_encryption' => AppSetting::get('mail_smtp_encryption', 'tls'),
            'mail_smtp_username' => AppSetting::get('mail_smtp_username', ''),
            'mail_from_address' => AppSetting::get('mail_from_address', ''),
            'mail_from_name' => AppSetting::get('mail_from_name', ''),
            'mail_imap_host' => AppSetting::get('mail_imap_host', ''),
            'mail_imap_port' => AppSetting::get('mail_imap_port', '993'),
            'mail_imap_username' => AppSetting::get('mail_imap_username', ''),
            'mail_imap_encryption' => AppSetting::get('mail_imap_encryption', 'ssl'),
            'company_smtp_host' => AppSetting::get('company_smtp_host', AppSetting::get('mail_smtp_host', '')),
            'company_smtp_port' => AppSetting::get('company_smtp_port', AppSetting::get('mail_smtp_port', '587')),
            'company_smtp_encryption' => AppSetting::get('company_smtp_encryption', AppSetting::get('mail_smtp_encryption', 'tls')),
            'company_imap_host' => AppSetting::get('company_imap_host', AppSetting::get('mail_imap_host', '')),
            'company_imap_port' => AppSetting::get('company_imap_port', AppSetting::get('mail_imap_port', '993')),
            'company_imap_encryption' => AppSetting::get('company_imap_encryption', 'ssl'),
            'company_pop3_host' => AppSetting::get('company_pop3_host', ''),
            'company_pop3_port' => AppSetting::get('company_pop3_port', '995'),
            'company_imap_sent_folder' => AppSetting::get('company_imap_sent_folder', ''),

            'reminder_stuck_days' => AppSetting::get('reminder_stuck_days', '7'),
            'retention_years' => AppSetting::get('data_retention_years', '5'),
            'real_email_on' => ModuleGate::enabled('real_email'),
            'reminders_on' => ModuleGate::enabled('smart_reminders'),
            'audit_on' => ModuleGate::enabled('audit_log'),
            'field_acl_on' => ModuleGate::enabled('field_acl'),
        ]);
    }

    public function saveMail(Request $request)
    {
        foreach (['mail_smtp_host','mail_smtp_port','mail_smtp_encryption','mail_smtp_username','mail_from_address','mail_from_name','mail_imap_host','mail_imap_port','mail_imap_username','mail_imap_encryption','company_smtp_host','company_smtp_port','company_smtp_encryption','company_imap_host','company_imap_port','company_imap_encryption','company_pop3_host','company_pop3_port','company_imap_sent_folder'] as $k) {
            if ($request->has($k)) {
                AppSetting::set($k, (string) $request->input($k, ''));
            }
        }
        if ($request->filled('mail_smtp_password')) {
            AppSetting::set('mail_smtp_password', $request->input('mail_smtp_password'));
        }
        if ($request->filled('mail_imap_password')) {
            AppSetting::set('mail_imap_password', $request->input('mail_imap_password'));
        }
        AuditLogger::log('mail_settings_updated');
        return back()->with('success', 'تنظیمات ایمیل ذخیره شد');
    }

    public function testMail(Request $request, RealMailService $mail)
    {
        $request->validate(['to' => 'required|email']);
        $r = $mail->sendRaw($request->input('to'), 'تست RFQ SMTP', 'این یک پیام تست از سامانه RFQ است.');
        return back()->with($r['ok'] ? 'success' : 'error', $r['ok'] ? 'ارسال شد' : ($r['message'] ?? 'خطا'));
    }

    public function testImap(RealMailService $mail)
    {
        $r = $mail->imapStatus();
        return back()->with($r['ok'] ? 'success' : 'error', $r['message'] ?? '');
    }

    /**
     * M28 (درخواست کاربر): بعد از M16 (اضافه‌شدن nixpacks.toml برای نصب
     * LibreOffice روی Railway)، دکمه‌ی PDF واقعی هنوز روی سرور کاربر فعال
     * نشده بود و راهِ چک‌کردنِ علتش فقط کندوکاو در لاگِ بیلدِ Railway بود —
     * چیزی که کاربر نمی‌توانست خودش انجام دهد. این دکمه همان تشخیصِ داخلیِ
     * LibreOfficePdfConverter::diagnosis() را مستقیم در برنامه نشان می‌دهد.
     */
    public function testPdf()
    {
        // عمداً مستقیم LibreOfficePdfConverter ساخته می‌شود، نه
        // PdfConversionService::active() — چون active() در حالتِ نبودِ
        // درایور واقعی، NullPdfConverter برمی‌گرداند که diagnosis() اش فقط
        // «تنظیم نشده» می‌گوید، نه علتِ دقیق (باینری نصب نیست؟ shell_exec
        // غیرفعال است؟) که همان چیزی است که کاربر برای عیب‌یابی لازم دارد.
        $driver = new \App\Services\Documents\Converters\LibreOfficePdfConverter();

        return back()->with($driver->isAvailable() ? 'success' : 'error', $driver->diagnosis());
    }

    public function saveFieldAcl(Request $request)
    {
        $request->validate([
            'field_key' => 'required|string',
            'allowed_roles' => 'required|string',
        ]);
        if (!Schema::hasTable('field_permissions')) {
            return back()->withErrors(['field_key' => 'migrate لازم است']);
        }
        $roles = array_values(array_filter(array_map('trim', explode(',', $request->input('allowed_roles')))));
        DB::table('field_permissions')->updateOrInsert(
            ['field_key' => $request->input('field_key')],
            [
                'label' => $request->input('label', $request->input('field_key')),
                'allowed_roles' => json_encode($roles),
                'is_sensitive' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
        AuditLogger::log('field_acl_updated', 'field_permission', null, ['key' => $request->input('field_key')]);
        return back()->with('success', 'دسترسی فیلد ذخیره شد');
    }

    public function saveReminders(Request $request)
    {
        AppSetting::set('reminder_stuck_days', (string) max(1, (int) $request->input('reminder_stuck_days', 7)));
        return back()->with('success', 'تنظیمات یادآوری ذخیره شد');
    }

    public function saveRetention(Request $request)
    {
        AppSetting::set('data_retention_years', (string) max(1, (int) $request->input('retention_years', 5)));
        return back()->with('success', 'سیاست نگهداری ذخیره شد (اجرای آرشیو دستی/کرون جداگانه)');
    }
}
