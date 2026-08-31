<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'receivable_id','amount','payment_date','method','reference','notes','recorded_by',
    ];
    protected $casts = ['amount'=>'decimal:2','payment_date'=>'date'];
    public function receivable(): BelongsTo { return $this->belongsTo(Receivable::class); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
}
