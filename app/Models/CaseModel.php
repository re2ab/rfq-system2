<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class CaseModel extends Model
{
    use SoftDeletes;
    protected $table = 'cases';
    protected $fillable = [
        'case_number', 'customer_organization_id', 'contact_id', 'customer_request_number', 'assigned_expert_id',
        'title', 'description', 'current_status', 'previous_status',
        'priority', 'currency', 'exchange_rate', 'incoterm',
        'proposal_amount', 'vat_percent', 'proposal_gross',
        'won_reason', 'lost_reason', 'stopped_reason', 'closed_at',
    ];
    protected $casts = [
        'closed_at' => 'datetime',
        'exchange_rate' => 'decimal:6',
        'proposal_amount' => 'decimal:2',
        'vat_percent' => 'decimal:2',
        'proposal_gross' => 'decimal:2',
    ];

    public const STATUSES = [
        'received' => 'درخواست دریافتی',
        'waiting_info' => 'منتظر اطلاعات تکمیلی',
        'waiting_offer' => 'منتظر دریافت پیشنهاد',
        'waiting_technical' => 'منتظر تهیه پیشنهاد فنی',
        'technical_sent' => 'پیشنهاد فنی ارسال‌شده',
        'waiting_financial' => 'منتظر تهیه پیشنهاد مالی',
        'financial_sent' => 'پیشنهاد مالی ارسال‌شده',
        'won' => 'برنده شده',
        'purchasing' => 'در حال خرید و حمل',
        'receivables' => 'دریافت مطالبات',
        'stopped' => 'متوقف',
        'lost' => 'بازنده شده',
        'closed' => 'بسته شده',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'customer_organization_id');
    }

    /** Alias for customer organization */
    public function customerOrganization(): BelongsTo
    {
        return $this->customer();
    }

    public function organization(): BelongsTo
    {
        return $this->customer();
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function expert(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_expert_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(CaseStatusHistory::class, 'case_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CaseActivity::class, 'case_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'case_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'case_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'case_id');
    }

    public function receivables(): HasMany
    {
        return $this->hasMany(Receivable::class, 'case_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'case_user', 'case_id', 'user_id')->withTimestamps();
    }

    /** کارشناس اصلی + همکاران */
    public function allAssignees()
    {
        $this->loadMissing(['expert', 'assignees']);
        $list = collect();
        if ($this->expert) {
            $list->push($this->expert);
        }
        foreach ($this->assignees as $u) {
            if (!$list->contains('id', $u->id)) {
                $list->push($u);
            }
        }
        return $list;
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable')->withTimestamps();
    }

    public static function statusLabels(): array
    {
        try {
            $map = \App\Models\PipelineStage::labelsMap();
            if (!empty($map)) {
                return $map;
            }
        } catch (\Throwable $e) {
        }
        return self::STATUSES;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusLabels()[$this->current_status] ?? self::STATUSES[$this->current_status] ?? $this->current_status;
    }

    public function getPriorityLabelAttribute(): string
    {
        $meta = function_exists('case_priorities_meta') ? case_priorities_meta() : [];
        return $meta[$this->priority]['label'] ?? $this->priority ?? '—';
    }

    public function getPriorityColorAttribute(): string
    {
        $meta = function_exists('case_priorities_meta') ? case_priorities_meta() : [];
        return $meta[$this->priority]['color'] ?? '#64748b';
    }

    public function computeGross(?float $net = null, ?float $vatPercent = null): float
    {
        $net = $net ?? (float) ($this->proposal_amount ?? 0);
        $vat = $vatPercent ?? (float) ($this->vat_percent ?? 0);
        return round($net * (1 + $vat / 100), 2);
    }

    public function totalCollected(): float
    {
        $sum = 0.0;
        $this->loadMissing('receivables.payments');
        foreach ($this->receivables as $r) {
            if ($r->payments && $r->payments->count() > 0) {
                foreach ($r->payments as $p) {
                    $sum += (float) $p->amount;
                }
            } else {
                $sum += (float) ($r->paid_amount ?? 0);
            }
        }
        return round($sum, 2);
    }

    public function isReceivablesFullyPaid(): bool
    {
        $due = $this->totalDue();
        if ($due <= 0) {
            return false;
        }
        $epsilon = 0.02;
        return $this->totalCollected() + $epsilon >= $due;
    }

    public function totalDue(): float
    {
        if ($this->proposal_gross !== null) {
            return (float) $this->proposal_gross;
        }
        $this->loadMissing('receivables');
        $fromRec = (float) $this->receivables->sum(fn ($r) => (float) $r->amount);
        if ($fromRec > 0) {
            return $fromRec;
        }
        return $this->computeGross();
    }

    public function isFullyCollected(float $epsilon = 0.01): bool
    {
        $due = $this->totalDue();
        if ($due <= 0) {
            return false;
        }
        return $this->totalCollected() + $epsilon >= $due;
    }
}
