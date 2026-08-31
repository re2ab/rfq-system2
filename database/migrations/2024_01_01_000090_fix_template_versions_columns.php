<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ترمیم: روی حداقل یک نصب واقعی (Railway/SQLite)، مایگریشن
 * 2024_01_01_000070_document_template_engine.php قبلاً یک‌بار اجرا شده بود
 * و چون در آن نسخه، createTemplateVersions()/createTemplateFields()/
 * createDocumentTypes() با یک «اگر جدول بود کلاً رد شو» محافظت می‌شدند (نه
 * افزودن idempotent ستون‌به‌ستون)، اگر آن اجرای اول به هر دلیلی جدول را
 * ناقص ساخته بود (یا نسخه‌ی قدیمی‌تری از تعریف را داشت)، دیگر هیچ اجرای
 * بعدی artisan migrate آن را کامل نمی‌کرد — چون Laravel مایگریشن‌ها را بر
 * اساس نام فایل ردیابی می‌کند، نه محتوا. نتیجه‌اش خطای واقعی گزارش‌شده بود:
 * «table template_versions has no column named file_path».
 *
 * همان فایل (000070) هم در همین پچ idempotent شد (برای نصب‌های تازه)، ولی
 * روی این نصبِ از قبل مهاجرت‌شده تنها راه واقعاً اجراشدن، یک نام فایل تازه
 * است — همین فایل. کاملاً additive و بی‌خطر برای اجرای مکرر.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('template_versions')) {
            Schema::table('template_versions', function (Blueprint $t) {
                if (!Schema::hasColumn('template_versions', 'file_path')) {
                    $t->string('file_path')->nullable();
                }
                if (!Schema::hasColumn('template_versions', 'file_hash')) {
                    $t->string('file_hash', 64)->nullable();
                }
                if (!Schema::hasColumn('template_versions', 'file_size')) {
                    $t->unsignedBigInteger('file_size')->nullable();
                }
                if (!Schema::hasColumn('template_versions', 'preview_path')) {
                    $t->string('preview_path')->nullable();
                }
                if (!Schema::hasColumn('template_versions', 'created_by')) {
                    $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('template_fields')) {
            Schema::table('template_fields', function (Blueprint $t) {
                if (!Schema::hasColumn('template_fields', 'binding')) {
                    $t->string('binding')->nullable();
                }
                if (!Schema::hasColumn('template_fields', 'source')) {
                    $t->string('source', 20)->default('auto');
                }
                if (!Schema::hasColumn('template_fields', 'data_type')) {
                    $t->string('data_type', 20)->default('text');
                }
                if (!Schema::hasColumn('template_fields', 'is_required')) {
                    $t->boolean('is_required')->default(false);
                }
                if (!Schema::hasColumn('template_fields', 'default_value')) {
                    $t->text('default_value')->nullable();
                }
                if (!Schema::hasColumn('template_fields', 'sort_order')) {
                    $t->unsignedSmallInteger('sort_order')->default(0);
                }
            });
        }

        if (Schema::hasTable('document_types')) {
            Schema::table('document_types', function (Blueprint $t) {
                if (!Schema::hasColumn('document_types', 'is_active')) {
                    $t->boolean('is_active')->default(true);
                }
                if (!Schema::hasColumn('document_types', 'sort_order')) {
                    $t->unsignedSmallInteger('sort_order')->default(0);
                }
                if (!Schema::hasColumn('document_types', 'supports_lines')) {
                    $t->boolean('supports_lines')->default(false);
                }
                if (!Schema::hasColumn('document_types', 'name_en')) {
                    $t->string('name_en')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // این مایگریشن فقط ستون‌های ناقص‌مانده را کامل می‌کند — down عمداً
        // خالی است تا rollback آن به‌اشتباه ستون‌هایی را که 000070 هم مسئولشان
        // است، دوبار drop نکند.
    }
};
