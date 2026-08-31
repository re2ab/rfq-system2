<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * M3 — هم‌راستاسازی پیشوندهای پیش‌فرض number_sequences با مثال تأییدشده‌ی
 * کاربر (سند معماری v1.1، فاز ۶): پیشنهاد فنی TC، پیشنهاد مالی PI، فاکتور CI.
 *
 * مایگریشن 2024_01_01_000010 پیشوندهای FI و INV را به‌عنوان مقدار اولیه
 * ساخته بود (پیش از آنکه کاربر مثال نهایی را بدهد). این مایگریشن فقط داده
 * است، نه ساختار — و فقط وقتی پیشوند را عوض می‌کند که هنوز همان مقدار قدیمی
 * دست‌نخورده باشد؛ اگر مدیر سیستم قبلاً پیشوند را از تنظیمات عوض کرده، این
 * مایگریشن هیچ‌کاری نمی‌کند (پیشوند طبق خواسته‌ی کاربر کاملاً قابل‌تنظیم است،
 * این فقط مقدار پیش‌فرض کارخانه‌ای را به مثال واقعی کاربر نزدیک می‌کند).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('number_sequences')) {
            return;
        }

        $renames = [
            'financial_proposal' => ['from' => 'FI', 'to' => 'PI'],
            'invoice' => ['from' => 'INV', 'to' => 'CI'],
        ];

        foreach ($renames as $type => $cfg) {
            DB::table('number_sequences')
                ->where('type', $type)
                ->where('prefix', $cfg['from'])
                ->update(['prefix' => $cfg['to'], 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('number_sequences')) {
            return;
        }

        $renames = [
            'financial_proposal' => ['from' => 'PI', 'to' => 'FI'],
            'invoice' => ['from' => 'CI', 'to' => 'INV'],
        ];

        foreach ($renames as $type => $cfg) {
            DB::table('number_sequences')
                ->where('type', $type)
                ->where('prefix', $cfg['from'])
                ->update(['prefix' => $cfg['to'], 'updated_at' => now()]);
        }
    }
};
