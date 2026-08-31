<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * M13: تقویم تعطیلات رسمی سفارشی.
 * تاریخ به‌صورت شمسی (jalali_date، فرمت Y-m-d با ارقام لاتین، مثلاً
 * "1405-03-07") ذخیره می‌شود — نه میلادی — چون کل نمای تقویم (ماهانه/هفتگی/
 * روزانه در CalendarController) از همین کلید شمسی برای مچ کردن روزها با
 * وظایف استفاده می‌کند (`Jalalian::fromCarbon($t->due_at)->format('Y-m-d')`)؛
 * ذخیره‌ی شمسی یعنی نگاه‌کردن تعطیلی هر روز بدون هیچ تبدیل تقویمی اضافه،
 * فقط یک lookup ساده در آرایه است. سالانه ثابت نیست (نوروز/تعطیلات رسمی هر
 * سال با میلادی جابه‌جا می‌شود) پس هر سال باید جداگانه وارد شود — دقیقاً
 * همان چیزی که کاربر به‌عنوان «امکان ورود تقویم اختصاصی» خواسته است.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('holidays')) {
            Schema::create('holidays', function (Blueprint $table) {
                $table->id();
                $table->string('jalali_date', 10)->unique(); // 'YYYY-MM-DD' شمسی
                $table->string('title')->nullable();
                $table->boolean('recurring_yearly')->default(false); // مثلاً 13 فروردین هر سال
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
