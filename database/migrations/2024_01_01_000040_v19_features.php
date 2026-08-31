<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Optional modules registry
        $mods = [
            ['key' => 'smart_reminders', 'name' => 'اعلان و یادآوری هوشمند', 'is_core' => false, 'is_enabled' => true],
            ['key' => 'case_pdf', 'name' => 'PDF یک‌کلیک از پرونده', 'is_core' => false, 'is_enabled' => true],
            ['key' => 'field_acl', 'name' => 'دسترسی فیلدهای حساس', 'is_core' => false, 'is_enabled' => true],
            ['key' => 'real_email', 'name' => 'ایمیل واقعی SMTP/IMAP', 'is_core' => false, 'is_enabled' => false],
            ['key' => 'case_chat', 'name' => 'پیام‌رسان داخلی پرونده', 'is_core' => false, 'is_enabled' => true],
            ['key' => 'accounting_export', 'name' => 'خروجی حسابداری CSV', 'is_core' => false, 'is_enabled' => true],
            ['key' => 'audit_log', 'name' => 'لاگ حسابرسی', 'is_core' => false, 'is_enabled' => true],
            ['key' => 'two_factor', 'name' => 'احراز دو مرحله‌ای (2FA)', 'is_core' => false, 'is_enabled' => false],
            ['key' => 'data_retention', 'name' => 'سیاست نگهداری داده', 'is_core' => false, 'is_enabled' => false],
        ];
        if (Schema::hasTable('modules')) {
            foreach ($mods as $m) {
                DB::table('modules')->updateOrInsert(
                    ['key' => $m['key']],
                    ['name' => $m['name'], 'is_core' => $m['is_core'], 'is_enabled' => $m['is_enabled'], 'updated_at' => now(), 'created_at' => now()]
                );
            }
        }

        if (!Schema::hasTable('case_chat_messages')) {
            Schema::create('case_chat_messages', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('case_id')->index();
                $t->unsignedBigInteger('user_id')->index();
                $t->text('body');
                $t->timestamps();
            });
        }

        // اصلاح M0: قبلاً اینجا اگر audit_logs نبود، با ستون‌های entity_type/entity_id/meta/ip
        // دوباره ساخته می‌شد. این کد در عمل هرگز اجرا نمی‌شد چون مایگریشن هسته
        // (2024_01_01_000001_create_core_tables.php، همیشه زودتر از این فایل اجرا می‌شود)
        // از قبل audit_logs را با ستون‌های auditable_type/auditable_id/old_values/new_values/
        // ip_address/user_agent ساخته است — همان جدولی که AuditLogger واقعاً باید با آن کار کند.
        // این بلوک تکراری و ناسازگار حذف شد تا کسی در آینده با آن گیج نشود؛ اگر روزی این
        // مایگریشن به‌تنهایی و بدون مایگریشن هسته اجرا شود، باز هم باید همان تعریف اصلی
        // استفاده شود، نه یک شِمای جایگزین.

        if (!Schema::hasTable('field_permissions')) {
            Schema::create('field_permissions', function (Blueprint $t) {
                $t->id();
                $t->string('field_key', 64)->unique();
                $t->string('label');
                $t->json('allowed_roles'); // ["admin","finance_manager"]
                $t->boolean('is_sensitive')->default(true);
                $t->timestamps();
            });
            $defaults = [
                ['field_key' => 'purchase_cost', 'label' => 'قیمت خرید از سازنده', 'allowed_roles' => json_encode(['admin', 'finance_manager']), 'is_sensitive' => true],
                ['field_key' => 'margin', 'label' => 'حاشیه سود', 'allowed_roles' => json_encode(['admin', 'finance_manager']), 'is_sensitive' => true],
                ['field_key' => 'proposal_amount', 'label' => 'مبلغ پیشنهاد مالی', 'allowed_roles' => json_encode(['admin', 'finance_manager', 'finance_expert']), 'is_sensitive' => true],
                ['field_key' => 'contact_confidential', 'label' => 'یادداشت محرمانه مخاطب', 'allowed_roles' => json_encode(['admin', 'finance_manager', 'technical_manager']), 'is_sensitive' => true],
            ];
            foreach ($defaults as $d) {
                $d['created_at'] = now();
                $d['updated_at'] = now();
                DB::table('field_permissions')->insert($d);
            }
        }

        // mail settings keys via app_settings if table exists — seeded in service defaults
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'two_factor_secret')) {
            Schema::table('users', function (Blueprint $t) {
                $t->string('two_factor_secret')->nullable();
                $t->boolean('two_factor_enabled')->default(false);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('case_chat_messages');
        // audit_logs توسط این مایگریشن ساخته نمی‌شود (بالا را ببینید)، پس اینجا drop نمی‌شود —
        // مالکیت آن با مایگریشن هسته است.
        Schema::dropIfExists('field_permissions');
    }
};
