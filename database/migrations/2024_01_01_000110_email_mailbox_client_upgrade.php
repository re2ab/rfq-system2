<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ارتقای صندوق ایمیل شخصی هر کاربر (/mailbox) به یک کلاینت واقعی (شبیه جیمیل):
// خواندن کامل نامه + پاسخ/فوروارد + پیوست (آپلود یا سند سیستم) + استفاده از قالب سیستم.
// این مایگریشن فقط یک ستون additive اضافه می‌کند تا بشود ایمیل‌های ارسالی از همین صندوق
// شخصی را (مستقل از سیستم ایمیل پرونده‌محور قدیمی‌تر که case_id دارد) در جدول مشترک
// «emails» ثبت و بعداً به‌عنوان «پوشه‌ی ارسالی» هر کاربر فیلتر کرد.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('emails') && !Schema::hasColumn('emails', 'user_id')) {
            Schema::table('emails', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('emails') && Schema::hasColumn('emails', 'user_id')) {
            Schema::table('emails', function (Blueprint $table) {
                $table->dropConstrainedForeignId('user_id');
            });
        }
    }
};
