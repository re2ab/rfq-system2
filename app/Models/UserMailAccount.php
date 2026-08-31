<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class UserMailAccount extends Model
{
    protected $fillable = [
        'user_id', 'email', 'display_name',
        'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password',
        'imap_host', 'imap_port', 'imap_encryption', 'imap_username', 'imap_password',
        'pop3_host', 'pop3_port', 'pop3_encryption', 'pop3_username', 'pop3_password',
        'is_active', 'last_synced_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function setSmtpPasswordAttribute($value): void
    {
        if ($value === null || $value === '') {
            return;
        }
        $this->attributes['smtp_password'] = Crypt::encryptString($value);
    }

    public function setImapPasswordAttribute($value): void
    {
        if ($value === null || $value === '') {
            return;
        }
        $this->attributes['imap_password'] = Crypt::encryptString($value);
    }

    public function setPop3PasswordAttribute($value): void
    {
        if ($value === null || $value === '') {
            return;
        }
        $this->attributes['pop3_password'] = Crypt::encryptString($value);
    }

    public function smtpPasswordPlain(): ?string
    {
        if (empty($this->attributes['smtp_password'] ?? null)) {
            return null;
        }
        try {
            return Crypt::decryptString($this->attributes['smtp_password']);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function imapPasswordPlain(): ?string
    {
        if (empty($this->attributes['imap_password'] ?? null)) {
            return null;
        }
        try {
            return Crypt::decryptString($this->attributes['imap_password']);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function isConfiguredForSend(): bool
    {
        return $this->is_active && $this->smtp_host && $this->smtp_username && $this->smtpPasswordPlain();
    }

    public function isConfiguredForReceive(): bool
    {
        return $this->is_active && $this->imap_host && $this->imap_username && $this->imapPasswordPlain();
    }
}
