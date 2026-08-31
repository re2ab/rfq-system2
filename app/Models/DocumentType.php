<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * جایگزین enum سخت‌کدشده‌ی ستون documents.type (CONFLICT-3 سند معماری).
 * افزودن نوع سند جدید (Purchase Order، نامه‌ی اداری، Delivery Note، ...) یعنی
 * افزودن یک سطر اینجا — هیچ مایگریشن یا تغییر کدی لازم نیست. پیشوند شماره و
 * عدد شروع (Seed) این نوع سند در جدول number_sequences (ستون‌های prefix و
 * start_number، به‌تفکیک همین key) نگه داشته می‌شود، نه اینجا.
 */
class DocumentType extends Model
{
    protected $fillable = [
        'key', 'name_fa', 'name_en', 'is_active', 'sort_order', 'supports_lines',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'supports_lines' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function templates(): HasMany
    {
        return $this->hasMany(Template::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public static function active()
    {
        return static::where('is_active', true)->orderBy('sort_order')->get();
    }
}
