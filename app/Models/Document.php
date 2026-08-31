<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    use SoftDeletes;

    // وضعیت‌های ممکن (بخش ۵ سند معماری). Rule 2/Rule 3: فقط draft قابل ویرایش
    // مستقیم است؛ سند published فقط با Revision جدید تغییر می‌کند.
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'case_id','type','document_type_id','document_number','number_base','status',
        'current_revision_id','published_revision_id',
        'title','currency','exchange_rate','incoterm',
        'vat_percent','net_amount','vat_amount','gross_amount',
    ];
    protected $casts = [
        'exchange_rate'=>'decimal:6','vat_percent'=>'decimal:2',
        'net_amount'=>'decimal:2','vat_amount'=>'decimal:2','gross_amount'=>'decimal:2',
    ];

    public function case(): BelongsTo { return $this->belongsTo(CaseModel::class, 'case_id'); }
    public function documentType(): BelongsTo { return $this->belongsTo(DocumentType::class); }
    public function revisions(): HasMany { return $this->hasMany(DocumentRevision::class)->orderBy('revision_number'); }
    public function lines(): HasMany { return $this->hasMany(DocumentLine::class)->orderBy('sort_order'); }
    public function currentRevision(): BelongsTo { return $this->belongsTo(DocumentRevision::class, 'current_revision_id'); }
    public function publishedRevision(): BelongsTo { return $this->belongsTo(DocumentRevision::class, 'published_revision_id'); }

    public function isPublished(): bool { return $this->status === self::STATUS_PUBLISHED; }
    public function isDraft(): bool { return $this->status === self::STATUS_DRAFT; }

    /**
     * آیا این نوع سند جدول ردیف‌های اقلام دارد؟ اگر document_type_id ست شده
     * باشد از جدول document_types می‌خواند (قابل‌گسترش برای انواع آینده)؛
     * برای رکوردهای قدیمی بدون document_type_id، به همان لیست سخت‌کدشده‌ی
     * قبلی برمی‌گردد تا رفتار موجود نشکند.
     */
    public function typeSupportsLines(): bool
    {
        if ($this->relationLoaded('documentType') ? $this->documentType : $this->document_type_id) {
            $dt = $this->documentType ?: $this->documentType()->first();
            if ($dt) {
                return (bool) $dt->supports_lines;
            }
        }
        return in_array($this->type, ['financial_proposal', 'invoice'], true);
    }

    public function recalculateFromLines(): void
    {
        $net = round((float) $this->lines()->sum('line_total'), 2);
        $vatP = (float) ($this->vat_percent ?? 0);
        $vat = round($net * $vatP / 100, 2);
        $this->update([
            'net_amount' => $net,
            'vat_amount' => $vat,
            'gross_amount' => round($net + $vat, 2),
        ]);
    }

}
