<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Document Engine — مدل داده (M1)
 *
 * پیاده‌سازی فاز ۴ سند معماری (سند/موتور اسناد RFQ-Core، v1.1) روی جدول‌های
 * موجود، نه با ساختن یک ساختار موازی. تصمیم‌های کلیدی:
 *
 *  - جدول موجود `templates` (که DocumentController از قبل با آن کار می‌کند)
 *    گسترش پیدا می‌کند، نه اینکه یک جدول دوم `document_templates` بسازیم —
 *    قالب‌های HTML قدیمی (header/body/footer) و قالب‌های واقعی DOCX/XLSX جدید
 *    هر دو همین یک جدول را به اشتراک می‌گذارند (file_type تشخیص می‌دهد کدام‌اند).
 *  - `template_versions` سند به *نسخه‌ی* دقیق فایل قالب متصل می‌کند
 *    (document_revisions.template_version_id) — نه به خود قالب — دقیقاً طبق
 *    بند ۲۵ کاربر: آپلود نسخه‌ی جدید قالب نباید اسناد قبلی را تکان دهد.
 *  - `document_types` جایگزین enum سخت‌کدشده‌ی ستون `type` می‌شود (CONFLICT-3
 *    سند معماری) — افزودن نوع سند آینده (Purchase Order، نامه‌ی اداری، …) فقط
 *    یک سطر جدید است، نه تغییر کد یا مایگریشن.
 *  - شماره‌گذاری: جدول موجود `number_sequences` از قبل prefix/pad_length/
 *    start_number/last_number را به‌تفکیک `type` دارد — دقیقاً همان مکانیزم
 *    «پیشوند + شماره‌ی شروع قابل‌تنظیم برای مهاجرت» که کاربر خواسته بود.
 *    این مایگریشن ستون جدیدی به آن اضافه نمی‌کند؛ فقط از همان استفاده می‌کند.
 *  - `documents.document_number` (ستون قدیمی، NOT NULL + UNIQUE) دست‌نخورده
 *    می‌ماند تا هیچ‌جای دیگر کد نشکند. برای اسناد جدید که شماره‌شان تا لحظه‌ی
 *    Publish معلوم نیست، یک مقدار موقت یکتا (`DRAFT-{id}`) در آن نوشته می‌شود
 *    و در Publish با شماره‌ی واقعی جایگزین می‌شود. این از هرگونه ALTER…MODIFY
 *    شکننده (که بدون doctrine/dbal و در SQLite/MySQL/Postgres رفتار متفاوت
 *    دارد) بی‌نیاز می‌کند. شماره‌ی «واقعی و پایدار» در ستون تازه‌ی
 *    `documents.number_base` نگه داشته می‌شود.
 *
 * همه‌چیز additive است — هیچ `->change()` ای در این فایل نیست، چون composer.json
 * پروژه doctrine/dbal ندارد (و در این sandbox هم اصلاً composer قابل اجرا نیست).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->createDocumentTypes();
        $this->alterTemplatesPassOne();
        $this->createTemplateVersions();
        $this->createTemplateFields();
        $this->alterTemplatesPassTwo();
        $this->backfillTemplates();
        $this->alterDocuments();
        $this->backfillDocuments();
        $this->alterDocumentRevisions();
        $this->backfillDocumentRevisions();
    }

    protected function createDocumentTypes(): void
    {
        if (!Schema::hasTable('document_types')) {
            Schema::create('document_types', function (Blueprint $t) {
                $t->id();
                $t->string('key', 60)->unique(); // technical_proposal | financial_proposal | invoice | purchase_order | ...
                $t->string('name_fa');
                $t->string('name_en')->nullable();
                $t->boolean('is_active')->default(true);
                $t->unsignedSmallInteger('sort_order')->default(0);
                // جایگزین آرایه‌ی سخت‌کدشده‌ی DocumentController::syncLines
                $t->boolean('supports_lines')->default(false);
                $t->timestamps();
            });
        } else {
            // اصلاح: قبلاً اگر این جدول از یک اجرای ناقص قبلی از قبل وجود داشت،
            // این متد کاملاً رد می‌شد و ستون‌های احتمالاً ناقص هرگز تکمیل
            // نمی‌شدند — دقیقاً همان الگوی خطای «no column named file_path» که
            // در template_versions افتاد. حالا مثل بقیه‌ی این مایگریشن،
            // additive و idempotent است.
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

        $now = now();
        $seed = [
            ['key' => 'technical_proposal', 'name_fa' => 'پیشنهاد فنی', 'name_en' => 'Technical Proposal', 'sort_order' => 1, 'supports_lines' => false, 'is_active' => true],
            ['key' => 'financial_proposal', 'name_fa' => 'پیشنهاد مالی', 'name_en' => 'Financial Proposal', 'sort_order' => 2, 'supports_lines' => true, 'is_active' => true],
            ['key' => 'invoice', 'name_fa' => 'فاکتور فروش', 'name_en' => 'Sales Invoice', 'sort_order' => 3, 'supports_lines' => true, 'is_active' => true],
        ];
        // updateOrInsert به‌جای insert خام — اجرای دوباره‌ی این مایگریشن (مثلاً
        // روی جدولی که از یک تلاش قبلی نیمه‌کاره مانده) دیگر رکورد تکراری نمی‌سازد.
        foreach ($seed as $row) {
            DB::table('document_types')->updateOrInsert(
                ['key' => $row['key']],
                array_merge($row, ['updated_at' => $now, 'created_at' => $now])
            );
        }
    }

    protected function alterTemplatesPassOne(): void
    {
        if (!Schema::hasTable('templates')) {
            return; // نصب بسیار قدیمی/غیرمنتظره — بی‌خطر رد می‌شویم
        }

        Schema::table('templates', function (Blueprint $t) {
            if (!Schema::hasColumn('templates', 'document_type_id')) {
                $t->foreignId('document_type_id')->nullable()->after('type')
                    ->constrained('document_types')->nullOnDelete();
            }
            if (!Schema::hasColumn('templates', 'file_type')) {
                // NULL = قالب HTML قدیمی (header/body/footer). 'docx'|'xlsx' = قالب واقعی آپلودشده.
                $t->string('file_type', 10)->nullable()->after('code');
            }
            if (!Schema::hasColumn('templates', 'status')) {
                $t->string('status', 20)->default('active')->after('is_default'); // active | inactive
            }
        });
    }

    protected function createTemplateVersions(): void
    {
        if (!Schema::hasTable('template_versions')) {
            Schema::create('template_versions', function (Blueprint $t) {
                $t->id();
                $t->foreignId('template_id')->constrained('templates')->cascadeOnDelete();
                $t->unsignedInteger('version_number');
                $t->string('file_path')->nullable();
                $t->string('file_hash', 64)->nullable();
                $t->unsignedBigInteger('file_size')->nullable();
                $t->string('preview_path')->nullable();
                $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamps();

                $t->unique(['template_id', 'version_number']);
            });
            return;
        }

        // اصلاح خطای واقعی گزارش‌شده: «no column named file_path». جدول از یک
        // اجرای قبلی (ناقص یا با تعریف قدیمی‌تر) از قبل وجود داشت و این متد
        // قبلاً کاملاً رد می‌شد — یعنی ستون‌های لازم هرگز اضافه نمی‌شدند. حالا
        // مثل بقیه‌ی این مایگریشن، additive و idempotent است.
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

    protected function createTemplateFields(): void
    {
        if (!Schema::hasTable('template_fields')) {
            Schema::create('template_fields', function (Blueprint $t) {
                $t->id();
                $t->foreignId('template_version_id')->constrained('template_versions')->cascadeOnDelete();
                $t->string('key', 120);
                $t->string('label')->nullable();
                $t->string('binding')->nullable(); // dot-notation: case.organization.name
                $t->string('source', 20)->default('auto'); // auto | manual | line
                $t->string('data_type', 20)->default('text'); // text|number|date|currency|boolean
                $t->boolean('is_required')->default(false);
                $t->text('default_value')->nullable();
                $t->unsignedSmallInteger('sort_order')->default(0);
                $t->timestamps();

                $t->unique(['template_version_id', 'key']);
            });
            return;
        }

        // همان اصلاح idempotent بالا — جلوگیری از تکرار همان خطای «no column named …».
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

    protected function alterTemplatesPassTwo(): void
    {
        if (!Schema::hasTable('templates')) {
            return;
        }

        Schema::table('templates', function (Blueprint $t) {
            if (!Schema::hasColumn('templates', 'current_version_id')) {
                $t->foreignId('current_version_id')->nullable()->after('status')
                    ->constrained('template_versions')->nullOnDelete();
            }
            if (!Schema::hasColumn('templates', 'default_flag')) {
                // ترفند Rule 9: NULL هنگام غیرپیش‌فرض، برابر document_type_id هنگام پیش‌فرض.
                // چون هر موتور دیتابیس NULL را در ایندکس یکتا نادیده می‌گیرد، همین ستون به‌تنهایی
                // «فقط یک قالب پیش‌فرض در هر نوع سند» را تضمین می‌کند — بدون partial index/trigger.
                $t->unsignedBigInteger('default_flag')->nullable()->after('current_version_id');
            }
        });
    }

    protected function backfillTemplates(): void
    {
        if (!Schema::hasTable('templates') || !Schema::hasTable('document_types')) {
            return;
        }

        $typeMap = DB::table('document_types')->pluck('id', 'key');

        foreach ($typeMap as $key => $id) {
            DB::table('templates')->where('type', $key)->whereNull('document_type_id')->update(['document_type_id' => $id]);
        }

        // برای هر نوع سند، حداکثر یک قالب پیش‌فرض مجاز است. اگر داده‌ی موجود چند
        // قالب is_default=true برای یک نوع دارد (چون تا امروز هیچ قیدی این را
        // اجرا نمی‌کرد)، جدیدترین را برنده اعلام و بقیه را is_default=false می‌کنیم
        // — قبل از افزودن UNIQUE(default_flag)، وگرنه خودِ این مایگریشن شکست می‌خورد.
        $groups = DB::table('templates')->whereNotNull('document_type_id')->where('is_default', true)->get(['id', 'document_type_id']);
        $byType = [];
        foreach ($groups as $row) {
            $byType[$row->document_type_id][] = $row->id;
        }
        foreach ($byType as $typeId => $ids) {
            rsort($ids);
            $winner = $ids[0];
            $losers = array_slice($ids, 1);
            if ($losers) {
                DB::table('templates')->whereIn('id', $losers)->update(['is_default' => false]);
            }
            DB::table('templates')->where('id', $winner)->update(['default_flag' => $typeId]);
        }

        if (!Schema::hasColumn('templates', 'default_flag')) {
            return;
        }

        try {
            Schema::table('templates', function (Blueprint $t) {
                $t->unique('default_flag');
            });
        } catch (\Throwable $e) {
            // اگر به هر دلیلی (مثلاً درایور غیرمنتظره) شکست بخورد، قید فقط در سرویس‌لایه
            // اعمال می‌شود؛ غیرمرگبار — دقیقاً همان الگوی محافظه‌کارانه‌ی بقیه‌ی این پروژه.
        }
    }

    protected function alterDocuments(): void
    {
        Schema::table('documents', function (Blueprint $t) {
            if (!Schema::hasColumn('documents', 'document_type_id')) {
                $t->foreignId('document_type_id')->nullable()->after('type')
                    ->constrained('document_types')->nullOnDelete();
            }
            if (!Schema::hasColumn('documents', 'status')) {
                $t->string('status', 20)->default('draft')->after('document_number'); // draft|published|archived
            }
            if (!Schema::hasColumn('documents', 'number_base')) {
                $t->string('number_base')->nullable()->unique()->after('status');
            }
            if (!Schema::hasColumn('documents', 'current_revision_id')) {
                $t->foreignId('current_revision_id')->nullable()->after('number_base')
                    ->constrained('document_revisions')->nullOnDelete();
            }
            if (!Schema::hasColumn('documents', 'published_revision_id')) {
                $t->foreignId('published_revision_id')->nullable()->after('current_revision_id')
                    ->constrained('document_revisions')->nullOnDelete();
            }
        });
    }

    protected function backfillDocuments(): void
    {
        $typeMap = DB::table('document_types')->pluck('id', 'key');
        foreach ($typeMap as $key => $id) {
            DB::table('documents')->where('type', $key)->whereNull('document_type_id')->update(['document_type_id' => $id]);
        }

        // اسناد قدیمی از قبل یک document_number واقعی داشتند — همان مقدار number_base
        // هم می‌شود تا از همان لحظه با معماری جدید سازگار باشند.
        DB::table('documents')->whereNull('number_base')->whereNotNull('document_number')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                if ($row->document_number === '' || str_starts_with((string) $row->document_number, 'DRAFT-')) {
                    continue;
                }
                DB::table('documents')->where('id', $row->id)->update(['number_base' => $row->document_number]);
            }
        });

        $lockedDocIds = DB::table('document_revisions')->where('is_locked', true)->pluck('document_id')->unique();
        if ($lockedDocIds->isNotEmpty()) {
            DB::table('documents')->whereIn('id', $lockedDocIds)->where('status', 'draft')->update(['status' => 'published']);
        }
    }

    protected function alterDocumentRevisions(): void
    {
        Schema::table('document_revisions', function (Blueprint $t) {
            if (!Schema::hasColumn('document_revisions', 'template_version_id')) {
                $t->foreignId('template_version_id')->nullable()->after('document_id')
                    ->constrained('template_versions')->nullOnDelete();
            }
            if (!Schema::hasColumn('document_revisions', 'status')) {
                $t->string('status', 20)->default('draft')->after('revision_number'); // draft|in_review|published|superseded
            }
            if (!Schema::hasColumn('document_revisions', 'formatted_number')) {
                $t->string('formatted_number')->nullable()->unique()->after('status');
            }
            if (!Schema::hasColumn('document_revisions', 'data')) {
                $t->json('data')->nullable();
            }
            if (!Schema::hasColumn('document_revisions', 'change_note')) {
                // اصلاح M1: DocumentController::update() از سال‌ها پیش این کلید را به
                // DocumentRevision::create() می‌فرستد، اما نه ستونی برایش بود نه در
                // $fillable — Eloquent بی‌سروصدا نادیده‌اش می‌گرفت. حالا واقعاً ذخیره می‌شود.
                $t->text('change_note')->nullable();
            }
            if (!Schema::hasColumn('document_revisions', 'pdf_path')) {
                $t->string('pdf_path')->nullable();
            }
            if (!Schema::hasColumn('document_revisions', 'published_by')) {
                $t->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('document_revisions', 'published_at')) {
                $t->timestamp('published_at')->nullable();
            }
            if (!Schema::hasColumn('document_revisions', 'editor_key')) {
                // رزروشده برای Option A (ONLYOFFICE) در صورت ارتقای هاست — امروز استفاده نمی‌شود.
                $t->string('editor_key')->nullable();
            }
        });

        // Rule 10: دو Draft هم‌شماره برای یک سند مجاز نیست. این قید تا امروز اصلاً
        // در دیتابیس وجود نداشت. غیرمرگبار، چون داده‌ی قدیمی نظری‌اش نباید تخطی کند
        // ولی احتیاط بهتر از خرابی مایگریشن روی یک نصب واقعی است.
        try {
            Schema::table('document_revisions', function (Blueprint $t) {
                $t->unique(['document_id', 'revision_number']);
            });
        } catch (\Throwable $e) {
        }
    }

    protected function backfillDocumentRevisions(): void
    {
        DB::table('document_revisions')->where('is_locked', true)->where('status', 'draft')->update(['status' => 'published']);

        // current_revision_id = آخرین Revision هر سند؛ published_revision_id = آخرین
        // Revision قفل‌شده (اگر باشد). با DB::raw ساده، بدون نیاز به window function
        // (سازگار با SQLite قدیمی‌تر هم هست).
        $documents = DB::table('documents')->select('id')->get();
        foreach ($documents as $doc) {
            $latest = DB::table('document_revisions')->where('document_id', $doc->id)->orderByDesc('revision_number')->first();
            $lastLocked = DB::table('document_revisions')->where('document_id', $doc->id)->where('is_locked', true)->orderByDesc('revision_number')->first();

            $update = [];
            if ($latest) {
                $update['current_revision_id'] = $latest->id;
            }
            if ($lastLocked) {
                $update['published_revision_id'] = $lastLocked->id;
            }
            if ($update) {
                DB::table('documents')->where('id', $doc->id)->update($update);
            }
        }
    }

    public function down(): void
    {
        Schema::table('document_revisions', function (Blueprint $t) {
            foreach (['published_by', 'template_version_id'] as $fk) {
                if (Schema::hasColumn('document_revisions', $fk)) {
                    try {
                        $t->dropConstrainedForeignId($fk);
                    } catch (\Throwable $e) {
                    }
                }
            }
        });
        Schema::table('document_revisions', function (Blueprint $t) {
            foreach (['status', 'formatted_number', 'data', 'change_note', 'pdf_path', 'published_at', 'editor_key'] as $col) {
                if (Schema::hasColumn('document_revisions', $col)) {
                    $t->dropColumn($col);
                }
            }
        });

        Schema::table('documents', function (Blueprint $t) {
            foreach (['published_revision_id', 'current_revision_id', 'document_type_id'] as $fk) {
                if (Schema::hasColumn('documents', $fk)) {
                    try {
                        $t->dropConstrainedForeignId($fk);
                    } catch (\Throwable $e) {
                    }
                }
            }
        });
        Schema::table('documents', function (Blueprint $t) {
            foreach (['status', 'number_base'] as $col) {
                if (Schema::hasColumn('documents', $col)) {
                    $t->dropColumn($col);
                }
            }
        });

        Schema::table('templates', function (Blueprint $t) {
            foreach (['document_type_id', 'current_version_id'] as $fk) {
                if (Schema::hasColumn('templates', $fk)) {
                    try {
                        $t->dropConstrainedForeignId($fk);
                    } catch (\Throwable $e) {
                    }
                }
            }
        });
        Schema::table('templates', function (Blueprint $t) {
            foreach (['file_type', 'status', 'default_flag'] as $col) {
                if (Schema::hasColumn('templates', $col)) {
                    $t->dropColumn($col);
                }
            }
        });

        Schema::dropIfExists('template_fields');
        Schema::dropIfExists('template_versions');
        Schema::dropIfExists('document_types');
    }
};
