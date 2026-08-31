<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    protected $fillable = ['name', 'slug', 'color', 'entity'];

    public const ENTITIES = [
        'case' => 'پرونده‌ها',
        'contact' => 'مخاطبان',
        'organization' => 'سازمان‌ها',
        'task' => 'وظایف',
    ];

    protected static function booted(): void
    {
        static::creating(function (Tag $tag) {
            if (empty($tag->slug)) {
                $tag->slug = Str::slug($tag->name) ?: 'tag-'.uniqid();
            }
            if (empty($tag->entity)) {
                $tag->entity = 'case';
            }
        });
    }

    public function scopeForEntity($query, string $entity)
    {
        return $query->where('entity', $entity);
    }

    public function entityLabel(): string
    {
        return self::ENTITIES[$this->entity] ?? $this->entity;
    }

    public function cases(): MorphToMany
    {
        return $this->morphedByMany(CaseModel::class, 'taggable');
    }

    public function contacts(): MorphToMany
    {
        return $this->morphedByMany(Contact::class, 'taggable');
    }

    public function organizations(): MorphToMany
    {
        return $this->morphedByMany(Organization::class, 'taggable');
    }

    public function tasks(): MorphToMany
    {
        return $this->morphedByMany(Task::class, 'taggable');
    }
}
