<?php

namespace App\Models\Mail;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailMessageAttachment extends Model
{
    protected $table = 'mail_message_attachments';

    protected $fillable = [
        'mail_message_id', 'part_number', 'filename', 'mime', 'size',
        'content_id', 'storage_path', 'is_inline',
    ];

    protected $casts = [
        'size' => 'integer',
        'is_inline' => 'boolean',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(MailMessage::class, 'mail_message_id');
    }
}
