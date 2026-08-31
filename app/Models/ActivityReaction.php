<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityReaction extends Model
{
    protected $fillable = ['case_activity_id','user_id','type','emoji'];
    public function activity(): BelongsTo { return $this->belongsTo(CaseActivity::class, 'case_activity_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
