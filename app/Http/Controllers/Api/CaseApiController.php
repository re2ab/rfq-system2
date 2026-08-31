<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CaseModel;
use Illuminate\Http\Request;

class CaseApiController extends Controller
{
    public function index(Request $request)
    {
        $q = CaseModel::with(['customer:id,name','expert:id,name'])->latest();
        if ($s = $request->get('status')) $q->where('current_status', $s);
        return response()->json($q->paginate(20));
    }

    public function show(CaseModel $case)
    {
        $case->load(['customer','expert','activities.user','documents']);
        return response()->json($case);
    }
}
