<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailMessage extends Model
{
    protected $table = 'emails';
    protected $fillable = [
        'case_id','contact_id','user_id','direction','from_address','to_address',
        'subject','body','message_id','is_linked',
    ];
    protected $casts = ['is_linked' => 'boolean'];
    public function case(): BelongsTo { return $this->belongsTo(CaseModel::class, 'case_id'); }
    public function contact(): BelongsTo { return $this->belongsTo(Contact::class); }
    /** پرشده فقط وقتی این ایمیل از صندوق شخصی کاربر (/mailbox) ارسال شده — رکوردهای قدیمی‌تر سیستم ایمیل پرونده‌محور این را ندارند. */
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function attachments(): HasMany
    {
        return $this->hasMany(EmailAttachment::class, 'email_message_id');
    }
}
