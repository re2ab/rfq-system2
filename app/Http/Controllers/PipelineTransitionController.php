<?php
namespace App\Http\Controllers;

use App\Models\CaseModel;
use App\Models\PipelineStage;
use App\Models\PipelineTransition;
use Illuminate\Http\Request;

class PipelineTransitionController extends Controller
{
    public function index()
    {
        try {
            $stages = PipelineStage::orderBy('sort_order')->get();
            if ($stages->isEmpty()) {
                throw new \RuntimeException('empty');
            }
        } catch (\Throwable $e) {
            $stages = collect(CaseModel::STATUSES)->map(fn ($label, $key) => (object) [
                'key' => $key, 'label' => $label,
            ]);
        }

        $transitions = PipelineTransition::all()->keyBy(fn ($t) => $t->from_key.'|'.$t->to_key);
        $conditions = PipelineTransition::CONDITIONS;

        return view('settings.pipeline_transitions', compact('stages', 'transitions', 'conditions'));
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'edges' => 'nullable|array',
            'edges.*.from' => 'required|string',
            'edges.*.to' => 'required|string',
            'edges.*.allowed' => 'sometimes|boolean',
            'edges.*.condition' => 'nullable|string|max:50',
        ]);

        $edges = $data['edges'] ?? [];
        // فقط یال‌های ارسال‌شده را به‌روز می‌کنیم؛ برای سادگی: همه را از فرم می‌گیریم
        foreach ($edges as $edge) {
            $from = $edge['from'];
            $to = $edge['to'];
            if ($from === $to) {
                continue;
            }
            $allowed = !empty($edge['allowed']);
            $cond = $edge['condition'] ?? null;
            if ($cond === '') {
                $cond = null;
            }

            PipelineTransition::updateOrCreate(
                ['from_key' => $from, 'to_key' => $to],
                ['is_allowed' => $allowed, 'condition_code' => $allowed ? $cond : null]
            );
        }

        return back()->with('success', 'قوانین انتقال ذخیره شد.');
    }
}
