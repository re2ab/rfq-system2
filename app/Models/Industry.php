<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Industry extends Model
{
    protected $fillable = ['name', 'code', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function organizations(): HasMany
    {
        return $this->hasMany(Organization::class);
    }

    public static function activeOptions()
    {
        return static::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
