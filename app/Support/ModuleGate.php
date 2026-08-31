<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ModuleGate
{
    public static function enabled(string $key, bool $default = true): bool
    {
        if (!Schema::hasTable('modules')) {
            return $default;
        }
        $map = Cache::remember('rfq_modules_map', 60, function () {
            return DB::table('modules')->pluck('is_enabled', 'key')->map(fn ($v) => (bool) $v)->all();
        });
        if (!array_key_exists($key, $map)) {
            return $default;
        }
        return (bool) $map[$key];
    }

    public static function forget(): void
    {
        Cache::forget('rfq_modules_map');
    }
}
