<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRevision extends Model
{
    // Rule 11: Published تغییرناپذیر است. Superseded یعنی یک نسخه‌ی جدیدتر
    // جایگزینش شده (خودش هرگز عوض نمی‌شود، فقط دیگر «فعلی» نیست).
    public const STATUS_DRAFT = 'draft';
    public const STATUS_IN_REVIEW = 'in_review';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_SUPERSEDED = 'superseded';

    protected $fillable = [
        'document_id','revision_number','source_revision_id','template_version_id','status','formatted_number',
        'content','file_path','pdf_path','data','change_note',
        'created_by','is_locked','published_by','published_at','editor_key',
    ];
    protected $casts = [
        'is_locked' => 'boolean',
        'data' => 'array',
        'published_at' => 'datetime',
    ];

    public function document(): BelongsTo { return $this->belongsTo(Document::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function publisher(): BelongsTo { return $this->belongsTo(User::class, 'published_by'); }
    public function templateVersion(): BelongsTo { return $this->belongsTo(TemplateVersion::class); }
    // M34: نسخه‌ای که این Draft محتوایش از رویِ آن کپی شده (اگر کپی بوده — وگرنه null).
    public function sourceRevision(): BelongsTo { return $this->belongsTo(DocumentRevision::class, 'source_revision_id'); }

    public function isPublished(): bool { return $this->status === self::STATUS_PUBLISHED || $this->is_locked; }

    /** طبق CONFLICT-5 / Rule 11: هیچ نوشتنی — نه از ادیتور، نه از Auto Save، نه از upload-edit — روی نسخه‌ی قفل‌شده مجاز نیست. */
    public function isEditable(): bool
    {
        return !$this->is_locked && $this->status === self::STATUS_DRAFT;
    }
}
