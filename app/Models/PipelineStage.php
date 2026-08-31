<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PipelineStage extends Model
{
    protected $fillable = [
        'key', 'label', 'sort_order', 'is_active', 'show_on_kanban', 'color',
    ];
    protected $casts = [
        'is_active' => 'boolean',
        'show_on_kanban' => 'boolean',
    ];

    public static function labelsMap(): array
    {
        return Cache::remember('pipeline_stage_labels', 60, function () {
            return static::orderBy('sort_order')->pluck('label', 'key')->toArray();
        });
    }

    public static function kanbanKeys(): array
    {
        return static::where('is_active', true)
            ->where('show_on_kanban', true)
            ->orderBy('sort_order')
            ->pluck('key')
            ->all();
    }

    public static function clearCache(): void
    {
        Cache::forget('pipeline_stage_labels');
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::clearCache());
        static::deleted(fn () => static::clearCache());
    }
}
