<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseStatusHistory extends Model
{
    protected $fillable = [
        'case_id', 'from_status', 'to_status', 'user_id', 'reason', 'is_override',
    ];

    protected $casts = [
        'is_override' => 'boolean',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
