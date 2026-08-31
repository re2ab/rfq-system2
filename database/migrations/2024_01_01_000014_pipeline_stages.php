<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pipeline_stages')) {
            Schema::create('pipeline_stages', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->string('label');
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->boolean('show_on_kanban')->default(true);
                $table->string('color', 20)->nullable();
                $table->timestamps();
            });
        }

        $defaults = [
            ['received', 'دریافت درخواست', 10, true],
            ['waiting_info', 'در انتظار اطلاعات', 20, true],
            ['waiting_offer', 'در انتظار پیشنهاد', 30, true],
            ['waiting_technical', 'در انتظار پیشنهاد فنی', 40, true],
            ['technical_sent', 'پیشنهاد فنی ارسال‌شده', 50, true],
            ['waiting_financial', 'در انتظار پیشنهاد مالی', 60, true],
            ['financial_sent', 'پیشنهاد مالی ارسال‌شده', 70, true],
            ['won', 'برنده', 80, true],
            ['purchasing', 'خرید', 90, true],
            ['receivables', 'دریافت مطالبات', 100, true],
            ['stopped', 'متوقف', 110, true],
            ['lost', 'باخت', 120, false],
            ['closed', 'بسته', 130, false],
        ];
        foreach ($defaults as [$key, $label, $sort, $kanban]) {
            if (!DB::table('pipeline_stages')->where('key', $key)->exists()) {
                DB::table('pipeline_stages')->insert([
                    'key' => $key,
                    'label' => $label,
                    'sort_order' => $sort,
                    'is_active' => true,
                    'show_on_kanban' => $kanban,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_stages');
    }
};
