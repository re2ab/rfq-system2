<?php

namespace App\Models\Mail;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailFolder extends Model
{
    protected $table = 'mail_folders';

    protected $fillable = [
        'mail_account_id', 'name', 'remote_path', 'role', 'delimiter',
        'uidvalidity', 'message_count', 'unseen_count', 'last_synced_at',
    ];

    protected $casts = [
        'uidvalidity' => 'integer',
        'message_count' => 'integer',
        'unseen_count' => 'integer',
        'last_synced_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class, 'mail_account_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MailMessage::class, 'mail_folder_id');
    }

    public static function guessRole(string $remotePath): string
    {
        $p = strtolower(str_replace(['/', '.'], ' ', $remotePath));
        if ($p === 'inbox' || str_ends_with($p, ' inbox') || $p === 'inbox') {
            return 'inbox';
        }
        if (str_contains($p, 'sent')) {
            return 'sent';
        }
        if (str_contains($p, 'draft')) {
            return 'drafts';
        }
        if (str_contains($p, 'trash') || str_contains($p, 'deleted')) {
            return 'trash';
        }
        if (str_contains($p, 'spam') || str_contains($p, 'junk')) {
            return 'spam';
        }
        if (str_contains($p, 'archive')) {
            return 'archive';
        }

        return 'custom';
    }
}
