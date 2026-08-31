<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppNotification extends Model
{
    protected $table = 'app_notifications';
    protected $fillable = ['user_id','title','body','link','is_read'];
    protected $casts = ['is_read'=>'boolean'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
