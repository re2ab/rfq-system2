<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Receivable extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'case_id','document_id','currency','amount','paid_amount','status','due_date',
    ];
    protected $casts = [
        'amount'=>'decimal:2','paid_amount'=>'decimal:2','due_date'=>'date',
    ];
    public function case(): BelongsTo { return $this->belongsTo(CaseModel::class, 'case_id'); }
    public function document(): BelongsTo { return $this->belongsTo(Document::class); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
    public function getRemainingAttribute(): float
    {
        return (float)$this->amount - (float)$this->paid_amount;
    }
}
