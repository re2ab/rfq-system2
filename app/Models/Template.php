<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * یک ردیف هم قالب HTML قدیمی (header/body/footer، file_type=null) را پوشش
 * می‌دهد و هم قالب واقعی DOCX/XLSX جدید (file_type='docx'|'xlsx') را — تا
 * سیستم دو مسیر موازی «قالب» نداشته باشد. نسخه‌های واقعی فایل زیر
 * template_versions هستند؛ current_version_id فقط اشاره‌گر «آخرین نسخه» است،
 * سندها همیشه به template_versions.id مشخص وصل می‌شوند نه به این ستون.
 */
class Template extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'type', 'name', 'code', 'document_type_id', 'file_type', 'status',
        'header', 'body', 'footer', 'account_type', 'is_default', 'default_flag',
        'current_version_id', 'version',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(TemplateVersion::class)->orderByDesc('version_number');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(TemplateVersion::class, 'current_version_id');
    }

    public function isFileBased(): bool
    {
        return in_array($this->file_type, ['docx', 'xlsx'], true);
    }

    public function scopeForType($query, int $documentTypeId)
    {
        return $query->where('document_type_id', $documentTypeId)->where('status', 'active');
    }
}
