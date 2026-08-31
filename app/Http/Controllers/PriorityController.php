<?php
namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\Request;

class PriorityController extends Controller
{
    public function index()
    {
        return view('settings.priorities', [
            'taskPriorities' => task_priorities_meta(),
            'casePriorities' => case_priorities_meta(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'scope' => 'required|in:task,case',
            'key' => 'required|string|max:40|regex:/^[a-z0-9_]+$/',
            'label' => 'required|string|max:80',
            'color' => 'nullable|string|max:20',
        ]);
        $settingKey = $data['scope'] === 'task' ? 'task_priorities' : 'case_priorities';
        $map = $data['scope'] === 'task' ? task_priorities_meta() : case_priorities_meta();
        if (isset($map[$data['key']])) {
            return back()->withErrors(['key' => 'این کلید قبلاً وجود دارد.']);
        }
        $map[$data['key']] = [
            'label' => $data['label'],
            'color' => $data['color'] ?: '#64748b',
        ];
        AppSetting::set($settingKey, json_encode($map, JSON_UNESCAPED_UNICODE));
        return back()->with('success', 'اولویت اضافه شد.');
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'scope' => 'required|in:task,case',
            'key' => 'required|string|max:40',
            'label' => 'required|string|max:80',
            'color' => 'nullable|string|max:20',
        ]);
        $settingKey = $data['scope'] === 'task' ? 'task_priorities' : 'case_priorities';
        $map = $data['scope'] === 'task' ? task_priorities_meta() : case_priorities_meta();
        if (!isset($map[$data['key']])) {
            return back()->withErrors(['key' => 'اولویت یافت نشد.']);
        }
        $map[$data['key']] = [
            'label' => $data['label'],
            'color' => $data['color'] ?: ($map[$data['key']]['color'] ?? '#64748b'),
        ];
        AppSetting::set($settingKey, json_encode($map, JSON_UNESCAPED_UNICODE));
        return back()->with('success', 'اولویت به‌روز شد.');
    }

    public function destroy(Request $request)
    {
        $data = $request->validate([
            'scope' => 'required|in:task,case',
            'key' => 'required|string|max:40',
        ]);
        $settingKey = $data['scope'] === 'task' ? 'task_priorities' : 'case_priorities';
        $map = $data['scope'] === 'task' ? task_priorities_meta() : case_priorities_meta();
        if (count($map) <= 1) {
            return back()->withErrors(['key' => 'حداقل یک اولویت باید باقی بماند.']);
        }
        unset($map[$data['key']]);
        AppSetting::set($settingKey, json_encode($map, JSON_UNESCAPED_UNICODE));
        return back()->with('success', 'اولویت حذف شد.');
    }
}
