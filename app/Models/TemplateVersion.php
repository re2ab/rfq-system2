<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * هر آپلود جدید یک ردیف تازه است، نه بازنویسی ردیف قبلی — فایل‌ها هرگز
 * جای‌گذاری نمی‌شوند (بند ۲۵). document_revisions.template_version_id همیشه
 * به یکی از همین ردیف‌ها Pin می‌شود.
 */
class TemplateVersion extends Model
{
    protected $fillable = [
        'template_id', 'version_number', 'file_path', 'file_hash', 'file_size',
        'preview_path', 'created_by',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(TemplateField::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
