<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CaseActivity extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'case_id','user_id','type','body','contact_id',
        'call_datetime','call_direction','duration_minutes','call_result','parent_id',
    ];
    protected $casts = ['call_datetime'=>'datetime'];

    public function case(): BelongsTo { return $this->belongsTo(CaseModel::class, 'case_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function contact(): BelongsTo { return $this->belongsTo(Contact::class); }
    public function parent(): BelongsTo { return $this->belongsTo(CaseActivity::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(CaseActivity::class, 'parent_id'); }
    public function reactions(): HasMany { return $this->hasMany(ActivityReaction::class, 'case_activity_id'); }
    public function isPhoneCall(): bool { return $this->type === 'phone_call_report'; }
}
