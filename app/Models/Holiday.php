<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $fillable = ['jalali_date', 'title', 'recurring_yearly'];

    protected $casts = [
        'recurring_yearly' => 'boolean',
    ];

    /**
     * نگاشت 'md' (ماه-روز شمسی، مثلاً "01-01") → عنوان، برای تعطیلات
     * تکرارشونده‌ی سالانه (مثل نوروز) که به سال خاصی وابسته نیستند.
     */
    public static function recurringByMonthDay(): array
    {
        return static::query()
            ->where('recurring_yearly', true)
            ->get()
            ->mapWithKeys(function ($h) {
                // از 'YYYY-MM-DD' فقط 'MM-DD' را نگه می‌داریم
                $md = substr($h->jalali_date, 5);
                return [$md => $h->title];
            })->all();
    }

    /** نگاشت 'YYYY-MM-DD' شمسی → عنوان، برای تعطیلات غیرتکرارشونده (سال‌مشخص) */
    public static function fixedByDate(): array
    {
        return static::query()
            ->where('recurring_yearly', false)
            ->pluck('title', 'jalali_date')
            ->all();
    }
}
