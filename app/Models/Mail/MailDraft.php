<?php

namespace App\Models\Mail;

use App\Models\CaseModel;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailDraft extends Model
{
    protected $table = 'mail_drafts';

    protected $fillable = [
        'user_id', 'mail_account_id', 'to_address', 'cc', 'bcc', 'reply_to',
        'subject', 'body_html', 'in_reply_to', 'references_header',
        'reply_to_message_id', 'case_id', 'contact_id', 'attachment_meta', 'mode',
    ];

    protected $casts = [
        'attachment_meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class, 'mail_account_id');
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }
}
