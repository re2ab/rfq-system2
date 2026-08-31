<?php

namespace App\Models\Mail;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailUserSignature extends Model
{
    protected $table = 'mail_user_signatures';

    protected $fillable = [
        'user_id', 'locale', 'name', 'body_html', 'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function defaultFor(User $user, string $locale = 'fa'): ?self
    {
        return static::where('user_id', $user->id)
            ->where('locale', $locale)
            ->where('is_default', true)
            ->first()
            ?? static::where('user_id', $user->id)->where('locale', $locale)->first()
            ?? static::where('user_id', $user->id)->first();
    }
}
