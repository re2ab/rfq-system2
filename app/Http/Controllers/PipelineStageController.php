<?php
namespace App\Http\Controllers;

use App\Models\PipelineStage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PipelineStageController extends Controller
{
    public function index()
    {
        $stages = PipelineStage::orderBy('sort_order')->get();
        return view('settings.pipeline', compact('stages'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'label' => 'required|string|max:100',
            'key' => 'nullable|string|max:50|alpha_dash',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'show_on_kanban' => 'sometimes|boolean',
            'color' => 'nullable|string|max:20',
        ]);
        $key = $data['key'] ?: Str::slug($data['label'], '_');
        if ($key === '' || PipelineStage::where('key', $key)->exists()) {
            $key = ($key ?: 'stage').'_'.substr(uniqid(), -4);
        }
        $max = (int) PipelineStage::max('sort_order');
        PipelineStage::create([
            'key' => $key,
            'label' => $data['label'],
            'sort_order' => $data['sort_order'] ?? ($max + 10),
            'is_active' => true,
            'show_on_kanban' => $request->boolean('show_on_kanban', true),
            'color' => $data['color'] ?? null,
        ]);
        return back()->with('success', 'مرحله اضافه شد.');
    }

    public function update(Request $request, PipelineStage $stage)
    {
        $data = $request->validate([
            'label' => 'required|string|max:100',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'is_active' => 'sometimes|boolean',
            'show_on_kanban' => 'sometimes|boolean',
            'color' => 'nullable|string|max:20',
        ]);
        $stage->update([
            'label' => $data['label'],
            'sort_order' => $data['sort_order'] ?? $stage->sort_order,
            'is_active' => $request->boolean('is_active', $stage->is_active),
            'show_on_kanban' => $request->boolean('show_on_kanban', $stage->show_on_kanban),
            'color' => $data['color'] ?? $stage->color,
        ]);
        return back()->with('success', 'مرحله به‌روز شد.');
    }

    public function destroy(PipelineStage $stage)
    {
        $inUse = \App\Models\CaseModel::where('current_status', $stage->key)->exists();
        if ($inUse) {
            return back()->with('error', 'این مرحله روی پرونده‌های فعال استفاده شده؛ ابتدا وضعیت آن‌ها را عوض کنید یا فقط از کانبان مخفی کنید.');
        }
        $stage->delete();
        return back()->with('success', 'مرحله حذف شد.');
    }
}
