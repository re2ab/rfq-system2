<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Seeder امن برای پچ M0–M9 (موتور اسناد) — فقط برای اجرا روی دیتابیس واقعی
 * که از قبل seed شده. برخلاف DatabaseSeeder اصلی (که کاربر ادمین را
 * firstOrCreate و number_sequences.last_number را با updateOrInsert روی صفر
 * می‌نشاند — روی یک دیتابیس تولیدی که سند واقعی صادر کرده خطرناک است)، این
 * فایل فقط چیزهایی را که موتور اسناد به آن‌ها نیاز دارد و ممکن است نبوده
 * باشند اضافه می‌کند؛ هیچ‌چیز موجود را reset/overwrite نمی‌کند.
 *
 * اجرا: php artisan db:seed --class="Database\Seeders\PatchDocumentEngineSeeder"
 */
class PatchDocumentEngineSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'template.view', 'template.create', 'template.edit', 'template.delete', 'template.set_default',
            // M11+: مدیر باید بتواند اسناد ایجادشده را هم حذف کند (نه فقط قالب‌ها).
            'document.delete',
        ];
        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // فقط اعطا (additive) — هیچ نقشی sync/reset نمی‌شود، پس دسترسی‌های
        // دستی‌ای که مدیر سیستم قبلاً چیده هیچ‌جا از بین نمی‌رود.
        foreach (['admin', 'technical_manager', 'financial_manager'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo($permissions);
            }
        }

        // ماژول‌های جدید — فقط اگر از قبل نبودند (تا وضعیت فعال/غیرفعالِ
        // دستیِ مدیر سیستم برای ماژول‌های موجود دست‌نخورده بماند).
        $modules = [
            ['key' => 'documents', 'name' => 'اسناد', 'is_core' => false, 'is_enabled' => true],
            ['key' => 'templates', 'name' => 'قالب‌ها', 'is_core' => false, 'is_enabled' => true],
        ];
        foreach ($modules as $mod) {
            if (!DB::table('modules')->where('key', $mod['key'])->exists()) {
                DB::table('modules')->insert(array_merge($mod, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }

        // number_sequences عمداً اینجا هیچ‌جا دست زده نمی‌شود — انواع سند
        // موجود از خود مایگریشن 2024_01_01_000070 (اگر جدول document_types
        // تازه ساخته شود) یا از تنظیمات → شماره‌گذاری قابل مدیریت‌اند.

        $this->command?->info('پچ موتور اسناد seed شد (بدون دست‌زدن به number_sequences یا کاربر ادمین).');
    }
}
