<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\CaseModel;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\NotificationService;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        if ($user->hasAnyRole(['admin', 'technical_manager', 'financial_manager'])) {
            $query = Task::with(['assignee', 'case', 'creator'])->latest();
        } else {
            $query = Task::visibleTo($user->id)->with(['assignee', 'case', 'creator'])->latest();
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($priority = $request->get('priority')) {
            $query->where('priority', $priority);
        }
        if ($q = $request->get('q')) {
            $query->where(function ($qb) use ($q) {
                $qb->where('title', 'like', "%{$q}%")
                   ->orWhere('description', 'like', "%{$q}%")
                   ->orWhereHas('assignee', fn ($u) => $u->where('name', 'like', "%{$q}%"))
                   ->orWhereHas('case', fn ($c) => $c->where('case_number', 'like', "%{$q}%")->orWhere('title', 'like', "%{$q}%"))
                   ->orWhereHas('contact', fn ($c) => $c->where('first_name', 'like', "%{$q}%")->orWhere('last_name', 'like', "%{$q}%"));
            });
        }

        $scope = $request->get('scope', 'all'); // all|general|case
        if ($scope === 'general') {
            $query->whereNull('case_id');
        } elseif ($scope === 'case') {
            $query->whereNotNull('case_id');
        }

        $tasks = $query->paginate(20)->withQueryString();

        return view('tasks.index', compact('tasks', 'scope'));
    }

    public function create()
    {
        $this->authorizeCreate();

        $cases = CaseModel::orderByDesc('id')->limit(150)->get(['id', 'case_number', 'title']);
        $contacts = Contact::orderBy('first_name')->limit(150)->get();
        $users = $this->assignableUsers();

        return view('tasks.create', compact('cases', 'contacts', 'users'));
    }

    public function store(Request $request)
    {
        $this->authorizeCreate();

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'task_type' => 'nullable|string|max:50',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'due_at' => 'nullable|string|max:40',
            'due_at_j' => 'nullable|string|max:40',
            'assigned_to' => 'nullable|exists:users,id',
            'assignee_ids' => 'nullable|array',
            'assignee_ids.*' => 'exists:users,id',
            'case_id' => 'nullable|exists:cases,id',
            'contact_id' => 'nullable|exists:contacts,id',
            'is_team' => 'boolean',
            'requires_approval' => 'boolean',
        ]);
        $rawDue = $request->input('due_at_j') ?: ($data['due_at'] ?? null);
        $data['due_at'] = function_exists('parse_due_date') ? parse_due_date($rawDue) : $rawDue;

        // سررسید فقط تاریخ — بدون ساعت و بدون جابه‌جایی روز به‌خاطر timezone



        $data['created_by'] = Auth::id();
        $data['status'] = 'open';
        $data['priority'] = $data['priority'] ?? 'medium';
        $data['is_team'] = $request->boolean('is_team');
        $data['requires_approval'] = $request->boolean('requires_approval');

        $task = Task::create($data);

        try {
            $ns = app(NotificationService::class);
            $extra = $request->input('assignee_ids', []);
            if (!is_array($extra)) $extra = [];
            $sync = collect($extra);
            if (!empty($data['assigned_to'])) {
                $sync->push($data['assigned_to']);
            }
            $task->assignees()->sync($sync->unique()->filter()->values()->all());
            if (!empty($data['assigned_to'])) {
                $ns->notify((int)$data['assigned_to'], 'وظیفه جدید', $task->title, '/tasks/'.$task->id);
            }
        } catch (\Throwable $e) {}

        if ($request->boolean('return_to_case') && !empty($data['case_id'])) {
            return redirect()->route('cases.show', $data['case_id'])
                ->with('success', 'وظیفه پرونده ایجاد و ارجاع شد.');
        }

        return redirect()->route('tasks.show', $task)->with('success', 'وظیفه ایجاد شد.');
    }

    public function show(Task $task)
    {
        $this->authorizeView($task);
        $task->load(['assignee', 'creator', 'case', 'contact', 'checklistItems']);

        return view('tasks.show', compact('task'));
    }

    public function edit(Task $task)
    {
        $this->authorizeCreate();
        $cases = CaseModel::orderByDesc('id')->limit(150)->get(['id', 'case_number', 'title']);
        $contacts = Contact::orderBy('first_name')->limit(150)->get();
        $users = $this->assignableUsers();

        return view('tasks.edit', compact('task', 'cases', 'contacts', 'users'));
    }

    public function update(Request $request, Task $task)
    {
        $this->authorizeCreate();

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'task_type' => 'nullable|string|max:50',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'status' => 'nullable|in:open,in_progress,done,cancelled,overdue',
            'due_at' => 'nullable|string|max:40',
            'due_at_j' => 'nullable|string|max:40',
            'assigned_to' => 'nullable|exists:users,id',
            'assignee_ids' => 'nullable|array',
            'assignee_ids.*' => 'exists:users,id',
            'case_id' => 'nullable|exists:cases,id',
            'contact_id' => 'nullable|exists:contacts,id',
            'is_team' => 'boolean',
        ]);
        $rawDue = $request->input('due_at_j') ?: ($data['due_at'] ?? null);
        $data['due_at'] = function_exists('parse_due_date') ? parse_due_date($rawDue) : $rawDue;

        // سررسید فقط تاریخ — بدون ساعت و بدون جابه‌جایی روز به‌خاطر timezone



        $data['is_team'] = $request->boolean('is_team');
        $task->update($data);

        return redirect()->route('tasks.show', $task)->with('success', 'وظیفه به‌روزرسانی شد.');
    }

    public function complete(Request $request, Task $task)
    {
        $this->authorizeView($task);

        $data = $request->validate([
            'completion_note' => 'nullable|string|max:2000',
        ]);

        $task->update([
            'status' => 'done',
            'completed_at' => now(),
            'completion_note' => $data['completion_note'] ?? null,
        ]);

        return back()->with('success', 'وظیفه انجام شد.');
    }

    public function destroy(Task $task)
    {
        $this->authorizeCreate();
        $task->delete();
        return redirect()->route('tasks.index')->with('success', 'وظیفه حذف شد.');
    }

    public function bulkAction(Request $request)
    {
        $this->authorizeCreate();
        $data = $request->validate([
            'bulk_action' => 'required|in:delete',
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:tasks,id',
        ]);
        if ($data['bulk_action'] === 'delete') {
            Task::whereIn('id', $data['ids'])->delete();
        }
        return back()->with('success', 'وظایف انتخاب‌شده حذف شدند.');
    }

    protected function authorizeCreate(): void
    {
        if (!Auth::user()->hasAnyRole(['admin', 'technical_manager', 'financial_manager'])) {
            abort(403, 'فقط مدیران می‌توانند وظیفه ایجاد/ویرایش کنند.');
        }
    }

    protected function authorizeView(Task $task): void
    {
        $user = Auth::user();
        if ($user->hasAnyRole(['admin', 'technical_manager', 'financial_manager'])) {
            return;
        }
        if ($task->assigned_to == $user->id || $task->is_team) {
            return;
        }
        abort(403, 'به این وظیفه دسترسی ندارید.');
    }

    protected function assignableUsers()
    {
        try {
            return User::role([
                'technical_expert', 'financial_expert', 'expert',
                'technical_manager', 'financial_manager', 'admin',
            ])->get();
        } catch (\Exception $e) {
            return User::all();
        }
    }

    public function addChecklistItem(\Illuminate\Http\Request $request, Task $task)
    {
        $this->authorizeView($task);
        $data = $request->validate(['title' => 'required|string|max:255']);
        $task->checklistItems()->create([
            'title' => $data['title'],
            'sort_order' => $task->checklistItems()->count(),
        ]);
        return back()->with('success', 'آیتم چک‌لیست اضافه شد.');
    }

    public function toggleChecklistItem(\App\Models\TaskChecklistItem $item)
    {
        $task = $item->task;
        $this->authorizeView($task);
        $item->update(['is_done' => !$item->is_done]);
        return back();
    }

    public function destroyChecklistItem(\App\Models\TaskChecklistItem $item)
    {
        $task = $item->task;
        $this->authorizeView($task);
        $item->delete();
        return back()->with('success', 'آیتم چک‌لیست حذف شد.');
    }
}
