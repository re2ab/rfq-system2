<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PipelineTransition extends Model
{
    protected $fillable = [
        'from_key', 'to_key', 'is_allowed', 'condition_code',
    ];
    protected $casts = ['is_allowed' => 'boolean'];

    public const CONDITIONS = [
        '' => 'بدون شرط اضافه',
        'receivables_paid' => 'مطالبات باید کامل وصول شده باشد',
        'proposal_amount' => 'مبلغ پیشنهاد و VAT٪ الزامی است',
        'lost_reason' => 'دلیل باخت الزامی است',
    ];
}
