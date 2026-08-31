<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class MentionController extends Controller
{
    public function users(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $query = User::query()->orderBy('name')->limit(15);
        if ($q !== '') {
            $query->where('name', 'like', '%'.$q.'%');
        }
        return response()->json($query->get(['id', 'name', 'email']));
    }
}
