<?php
namespace App\Services;

use App\Support\ModuleGate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FieldAclService
{
    public function canView(string $fieldKey): bool
    {
        if (!ModuleGate::enabled('field_acl')) {
            return true;
        }
        if (!Schema::hasTable('field_permissions')) {
            return true;
        }
        $row = DB::table('field_permissions')->where('field_key', $fieldKey)->first();
        if (!$row) {
            return true;
        }
        $user = Auth::user();
        if (!$user) {
            return false;
        }
        if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
            return true;
        }
        $roles = json_decode($row->allowed_roles ?: '[]', true) ?: [];
        foreach ($roles as $role) {
            if (method_exists($user, 'hasRole') && $user->hasRole($role)) {
                return true;
            }
        }
        return false;
    }

    public function all(): array
    {
        if (!Schema::hasTable('field_permissions')) {
            return [];
        }
        return DB::table('field_permissions')->orderBy('id')->get()->all();
    }
}
