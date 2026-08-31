<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    use SoftDeletes;
    protected $fillable = ['case_id','delivery_number','delivery_date','description','status'];
    protected $casts = ['delivery_date'=>'date'];
    public function case(): BelongsTo { return $this->belongsTo(CaseModel::class, 'case_id'); }
}
