<?php
namespace App\Http\Controllers;

use App\Models\CustomReport;
use App\Models\CaseModel;
use App\Models\Task;
use App\Models\Contact;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomReportController extends Controller
{
    public function create()
    {
        return view('reports.custom_create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'entity' => 'required|in:case,task,contact,organization',
            'status' => 'nullable|string',
            'priority' => 'nullable|string',
            'type' => 'nullable|string',
            'q' => 'nullable|string|max:200',
        ]);
        $criteria = collect($data)->only(['status','priority','type','q'])->filter(fn($v)=>$v!==null && $v!=='')->all();
        $report = CustomReport::create([
            'name' => $data['name'],
            'entity' => $data['entity'],
            'criteria' => $criteria,
            'created_by' => Auth::id(),
        ]);
        return redirect()->route('reports.custom.show', $report)->with('success', 'گزارش ذخیره شد');
    }

    public function show(CustomReport $customReport)
    {
        $q = $this->buildQuery($customReport);
        $rows = $q->limit(500)->get();
        return view('reports.custom_show', compact('customReport', 'rows'));
    }

    public function destroy(CustomReport $customReport)
    {
        $customReport->delete();
        return redirect()->route('reports.index')->with('success', 'گزارش حذف شد');
    }

    protected function buildQuery(CustomReport $report)
    {
        $c = $report->criteria ?? [];
        return match ($report->entity) {
            'case' => CaseModel::query()
                ->when($c['status'] ?? null, fn($q,$v)=>$q->where('current_status',$v))
                ->when($c['priority'] ?? null, fn($q,$v)=>$q->where('priority',$v))
                ->when($c['q'] ?? null, fn($q,$v)=>$q->where(function($w) use ($v) {
                    $w->where('title','like',"%$v%")->orWhere('case_number','like',"%$v%");
                })),
            'task' => Task::query()
                ->when($c['status'] ?? null, fn($q,$v)=>$q->where('status',$v))
                ->when($c['priority'] ?? null, fn($q,$v)=>$q->where('priority',$v))
                ->when($c['q'] ?? null, fn($q,$v)=>$q->where('title','like',"%$v%")),
            'contact' => Contact::query()
                ->when($c['q'] ?? null, fn($q,$v)=>$q->where(function($w) use ($v) {
                    $w->where('first_name','like',"%$v%")->orWhere('last_name','like',"%$v%")->orWhere('email','like',"%$v%");
                })),
            'organization' => Organization::query()
                ->when($c['type'] ?? null, fn($q,$v)=>$q->where('type',$v))
                ->when($c['q'] ?? null, fn($q,$v)=>$q->where('name','like',"%$v%")),
            default => CaseModel::query()->whereRaw('1=0'),
        };
    }
}
