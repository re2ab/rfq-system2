<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentLine extends Model
{
    protected $fillable = [
        'document_id', 'sort_order', 'description', 'unit',
        'quantity', 'unit_price', 'line_total',
    ];
    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public static function calcTotal($qty, $price): float
    {
        return round((float) $qty * (float) $price, 2);
    }
}
