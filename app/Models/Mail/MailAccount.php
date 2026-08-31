<?php

namespace App\Models\Mail;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class MailAccount extends Model
{
    protected $table = 'mail_accounts';

    protected $fillable = [
        'name', 'email', 'display_name', 'is_shared', 'is_active',
        'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password',
        'imap_host', 'imap_port', 'imap_encryption', 'imap_username', 'imap_password',
        'imap_sent_folder', 'last_synced_at', 'last_sync_error', 'created_by',
    ];

    protected $casts = [
        'is_shared' => 'boolean',
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
        'smtp_port' => 'integer',
        'imap_port' => 'integer',
    ];

    protected $hidden = [
        'smtp_password',
        'imap_password',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'mail_account_user')
            ->withPivot(['can_read', 'can_send', 'is_default'])
            ->withTimestamps();
    }

    public function folders(): HasMany
    {
        return $this->hasMany(MailFolder::class, 'mail_account_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MailMessage::class, 'mail_account_id');
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

    public function smtpPasswordPlain(): ?string
    {
        $raw = $this->attributes['smtp_password'] ?? null;
        if (!$raw) {
            return null;
        }
        try {
            return Crypt::decryptString($raw);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function imapPasswordPlain(): ?string
    {
        $raw = $this->attributes['imap_password'] ?? null;
        if (!$raw) {
            return null;
        }
        try {
            return Crypt::decryptString($raw);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function isReadyToSend(): bool
    {
        return $this->is_active
            && (bool) $this->smtp_host
            && (bool) ($this->smtp_username ?: $this->email)
            && (bool) $this->smtpPasswordPlain();
    }

    public function isReadyToReceive(): bool
    {
        return $this->is_active
            && (bool) $this->imap_host
            && (bool) ($this->imap_username ?: $this->email)
            && (bool) $this->imapPasswordPlain();
    }

    /** ادغام با تنظیمات سرور شرکت (AppSetting) در صورت خالی بودن host اکانت */
    public function effectiveConfig(): array
    {
        $companySmtpHost = \App\Models\AppSetting::get('company_smtp_host', \App\Models\AppSetting::get('mail_smtp_host', ''));
        $companyImapHost = \App\Models\AppSetting::get('company_imap_host', \App\Models\AppSetting::get('mail_imap_host', ''));

        return [
            'email' => $this->email,
            'display_name' => $this->display_name ?: $this->name,
            'smtp_host' => $this->smtp_host ?: $companySmtpHost,
            'smtp_port' => (int) ($this->smtp_port ?: \App\Models\AppSetting::get('company_smtp_port', \App\Models\AppSetting::get('mail_smtp_port', 587))),
            'smtp_encryption' => $this->smtp_encryption ?: \App\Models\AppSetting::get('company_smtp_encryption', \App\Models\AppSetting::get('mail_smtp_encryption', 'tls')),
            'smtp_username' => $this->smtp_username ?: $this->email,
            'smtp_password' => $this->smtpPasswordPlain(),
            'imap_host' => $this->imap_host ?: $companyImapHost,
            'imap_port' => (int) ($this->imap_port ?: \App\Models\AppSetting::get('company_imap_port', \App\Models\AppSetting::get('mail_imap_port', 993))),
            'imap_encryption' => $this->imap_encryption ?: \App\Models\AppSetting::get('company_imap_encryption', \App\Models\AppSetting::get('mail_imap_encryption', 'ssl')),
            'imap_username' => $this->imap_username ?: $this->email,
            'imap_password' => $this->imapPasswordPlain(),
            'imap_sent_folder' => $this->imap_sent_folder ?: \App\Models\AppSetting::get('company_imap_sent_folder', ''),
        ];
    }
}
