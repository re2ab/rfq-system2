<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateField extends Model
{
    protected $fillable = [
        'template_version_id', 'key', 'label', 'binding', 'source',
        'data_type', 'is_required', 'default_value', 'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(TemplateVersion::class);
    }
}
