<?php

namespace App\Models\Mail;

use App\Models\CaseModel;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailMessage extends Model
{
    protected $table = 'mail_messages';

    protected $fillable = [
        'mail_account_id', 'mail_folder_id', 'uid', 'message_id', 'in_reply_to',
        'references_header', 'thread_key', 'from_address', 'from_name',
        'to_json', 'cc_json', 'bcc_json', 'reply_to', 'subject',
        'body_text', 'body_html', 'date_sent', 'is_seen', 'is_flagged',
        'is_answered', 'is_draft', 'has_attachments', 'size',
        'case_id', 'contact_id', 'organization_id', 'raw_headers', 'synced_at',
    ];

    protected $casts = [
        'to_json' => 'array',
        'cc_json' => 'array',
        'bcc_json' => 'array',
        'date_sent' => 'datetime',
        'synced_at' => 'datetime',
        'is_seen' => 'boolean',
        'is_flagged' => 'boolean',
        'is_answered' => 'boolean',
        'is_draft' => 'boolean',
        'has_attachments' => 'boolean',
        'uid' => 'integer',
        'size' => 'integer',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class, 'mail_account_id');
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(MailFolder::class, 'mail_folder_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MailMessageAttachment::class, 'mail_message_id');
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public static function buildThreadKey(?string $messageId, ?string $inReplyTo, ?string $references): string
    {
        $refs = trim((string) $references);
        if ($refs !== '') {
            $parts = preg_split('/\s+/', $refs) ?: [];
            $first = trim($parts[0] ?? '', '<>');
            if ($first !== '') {
                return strtolower($first);
            }
        }
        if ($inReplyTo) {
            return strtolower(trim($inReplyTo, '<>'));
        }
        if ($messageId) {
            return strtolower(trim($messageId, '<>'));
        }

        return 'solo-'.uniqid('', true);
    }
}
