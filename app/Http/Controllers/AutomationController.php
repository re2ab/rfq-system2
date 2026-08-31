<?php
namespace App\Http\Controllers;

use App\Models\AutomationRule;
use App\Models\CaseModel;
use Illuminate\Http\Request;

class AutomationController extends Controller
{
    public function index()
    {
        $rules = AutomationRule::orderByDesc('id')->get();
        $statuses = CaseModel::statusLabels();
        return view('settings.automation', compact('rules', 'statuses'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'trigger' => 'required|in:status_changed,inactive_days',
            'to_status' => 'nullable|string',
            'inactive_days' => 'nullable|integer|min:1|max:365',
            'action' => 'required|in:create_task,notify_assignees,notify_role',
            'task_title' => 'nullable|string|max:200',
            'due_days' => 'nullable|integer|min:1|max:90',
            'notify_message' => 'nullable|string|max:500',
            'role' => 'nullable|string|max:50',
            'is_active' => 'sometimes|boolean',
        ]);

        $conditions = [];
        if ($data['trigger'] === 'status_changed' && !empty($data['to_status'])) {
            $conditions['to_status'] = $data['to_status'];
        }
        if ($data['trigger'] === 'inactive_days') {
            $conditions['days'] = (int) ($data['inactive_days'] ?? 7);
        }

        $payload = [];
        if ($data['action'] === 'create_task') {
            $payload = [
                'title' => $data['task_title'] ?: ($data['trigger'] === 'inactive_days' ? 'پیگیری عدم فعالیت' : 'پیگیری خودکار'),
                'due_days' => $data['due_days'] ?? 3,
            ];
        } elseif ($data['action'] === 'notify_assignees') {
            $payload = ['message' => $data['notify_message'] ?? 'اعلان اتوماسیون'];
        } else {
            $payload = [
                'role' => $data['role'] ?? 'admin',
                'message' => $data['notify_message'] ?? 'اعلان اتوماسیون',
            ];
        }

        AutomationRule::create([
            'name' => $data['name'],
            'is_active' => $request->boolean('is_active', true),
            'trigger' => $data['trigger'],
            'conditions' => $conditions,
            'action' => $data['action'],
            'action_payload' => $payload,
        ]);

        return back()->with('success', 'قانون اتوماسیون ذخیره شد.');
    }

    public function toggle(AutomationRule $rule)
    {
        $rule->update(['is_active' => !$rule->is_active]);
        return back()->with('success', 'وضعیت قانون به‌روز شد.');
    }

    public function destroy(AutomationRule $rule)
    {
        $rule->delete();
        return back()->with('success', 'قانون حذف شد.');
    }

    public function runNow()
    {
        $n = app(\App\Services\AutomationService::class)->runInactiveCases();
        return back()->with('success', "اجرای دستی: {$n} اقدام انجام شد.");
    }
}
