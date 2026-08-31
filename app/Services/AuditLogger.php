<?php
namespace App\Services;

use App\Support\ModuleGate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AuditLogger
{
    /**
     * NOTE (اصلاح M0): جدول واقعی audit_logs — که در مایگریشن هسته
     * (2024_01_01_000001_create_core_tables.php) ساخته می‌شود — ستون‌های
     * auditable_type / auditable_id / old_values / new_values / ip_address / user_agent
     * دارد. مایگریشن دوم (2024_01_01_000040_v19_features.php) هم audit_logs
     * می‌سازد اما پشت `if (!Schema::hasTable(...))` است، پس چون مایگریشن هسته
     * زودتر اجرا می‌شود، هرگز واقعاً اجرا نمی‌شود و بی‌اثر است. نسخه‌ی قبلی این
     * سرویس با entity_type/entity_id/meta/ip می‌نوشت — نامی که در هیچ‌کدام از
     * دو مایگریشن روی جدول نهایی وجود ندارد، پس هر INSERT همیشه شکست می‌خورد.
     * چون بلوک try/catch خالی بود، این شکست کاملاً بی‌صدا بود و عملاً هیچ
     * رکورد Audit ثبت نمی‌شد. این متد حالا با نام واقعی ستون‌ها می‌نویسد و
     * خطای احتمالی را (به‌جای بلعیدن) در لاگ اپلیکیشن ثبت می‌کند.
     */
    public static function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        array $meta = [],
        array $oldValues = [],
        array $newValues = []
    ): void {
        if (!ModuleGate::enabled('audit_log') || !Schema::hasTable('audit_logs')) {
            return;
        }

        // اگر meta پر شده ولی new_values صریح داده نشده، meta به‌عنوان new_values ثبت می‌شود
        // تا فراخوانی‌های قدیمی‌تر این متد (که فقط $meta می‌دادند) بدون تغییر کار کنند.
        if ($newValues === [] && $meta !== []) {
            $newValues = $meta;
        }

        try {
            DB::table('audit_logs')->insert([
                'user_id' => Auth::id(),
                'action' => $action,
                'auditable_type' => $entityType,
                'auditable_id' => $entityId,
                'old_values' => $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
                'new_values' => $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // دیگر بی‌صدا بلعیده نمی‌شود — شکست ثبت Audit خودش باید قابل ردیابی باشد.
            Log::warning('AuditLogger: insert failed', [
                'action' => $action,
                'auditable_type' => $entityType,
                'auditable_id' => $entityId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
