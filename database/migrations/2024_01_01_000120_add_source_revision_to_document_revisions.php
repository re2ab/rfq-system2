<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// M34 (درخواست کاربر): ستون «بر اساس» در جدولِ تاریخچه‌ی نسخه‌ها — هر Draftی
// که از رویِ محتوای یک Revisionِ دیگر ساخته شده (دکمه‌ی «ساخت نسخه‌ی جدید
// برای ویرایش» / documents.revisions.copy از M23، یا مسیرِ قدیمی‌ترِ
// documents.new-draft از M6)، حالا id همان Revisionِ مبدأ را در
// source_revision_id ذخیره می‌کند تا صفحه‌ی سند بتواند نشان دهد این
// پیش‌نویس از کدام نسخه کپی شده. Revisionی که مستقیم از قالب ساخته شده
// (اولین Draft هر سند، DocumentRevisionService::createInitial) یا از یک
// فایلِ وارد‌شده/آپلودی ساخته شده (DocumentFileImportService، تشخیصِ
// خودکارِ M25) مبدأِ مشخصی ندارد — این ستون برایشان null می‌ماند و در
// جدول به‌صورت خط‌تیره نمایش داده می‌شود.
//
// خودارجاع به همان جدول، دقیقاً هم‌الگو با current_revision_id/
// published_revision_id روی ستون documents (مایگریشن ۰۷۰) — nullOnDelete
// تا اگر روزی خودِ Revisionِ مبدأ حذف شد (حذفِ تکِ نسخه، M24)، این ستون
// فقط null می‌شود، نه خطای قید ارجاعی.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_revisions', function (Blueprint $t) {
            if (!Schema::hasColumn('document_revisions', 'source_revision_id')) {
                $t->foreignId('source_revision_id')->nullable()->after('revision_number')
                    ->constrained('document_revisions')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('document_revisions', function (Blueprint $t) {
            if (Schema::hasColumn('document_revisions', 'source_revision_id')) {
                $t->dropConstrainedForeignId('source_revision_id');
            }
        });
    }
};
