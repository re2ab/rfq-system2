<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppSetting extends Model
{
    protected $fillable = ['key','value'];

    public static function get(string $key, $default = null)
    {
        return Cache::remember("app_setting_$key", 60, function () use ($key, $default) {
            $row = static::query()->where('key', $key)->first();
            return $row?->value ?? $default;
        });
    }

    public static function set(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("app_setting_$key");
    }
}
