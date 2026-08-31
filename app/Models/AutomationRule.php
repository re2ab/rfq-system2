<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationRule extends Model
{
    protected $fillable = [
        'name', 'is_active', 'trigger', 'conditions', 'action', 'action_payload',
    ];
    protected $casts = [
        'is_active' => 'boolean',
        'conditions' => 'array',
        'action_payload' => 'array',
    ];
}
