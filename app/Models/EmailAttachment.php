<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailAttachment extends Model
{
    protected $fillable = [
        'email_message_id', 'file_name', 'file_path', 'mime_type', 'file_size', 'source_attachment_id',
    ];

    public function email(): BelongsTo
    {
        return $this->belongsTo(EmailMessage::class, 'email_message_id');
    }
}
