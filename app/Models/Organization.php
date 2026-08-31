<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphToMany;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'name_en', 'type', 'industry_id', 'address', 'phone', 'email', 'website', 'notes',
    ];

    public const TYPES = [
        'customer' => 'مشتری',
        'supplier' => 'تأمین‌کننده',
        'both' => 'هر دو',
    ];

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type ?? '—';
    }

    public function industry()
    {
        return $this->belongsTo(Industry::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable')->withTimestamps();
    }
}
