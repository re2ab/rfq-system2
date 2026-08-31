<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphToMany;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'organization_id', 'first_name', 'last_name', 'position',
        'email', 'phone', 'phone2', 'mobile', 'fax', 'avatar', 'notes',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function confidentialNotes(): HasMany
    {
        return $this->hasMany(ContactConfidentialNote::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable')->withTimestamps();
    }
}
