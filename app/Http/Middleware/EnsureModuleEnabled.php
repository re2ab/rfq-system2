<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next, string $module)
    {
        if (!Schema::hasTable('modules')) {
            return $next($request);
        }
        $row = DB::table('modules')->where('code', $module)->orWhere('key', $module)->first();
        if ($row && (isset($row->enabled) && !$row->enabled || isset($row->is_enabled) && !$row->is_enabled)) {
            abort(403, 'این ماژول غیرفعال است.');
        }
        return $next($request);
    }
}
