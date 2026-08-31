<?php

namespace App\Http\Controllers;

use App\Models\CaseModel;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WorkQueueController extends Controller
{
    public const PERIODS = [
        'today_overdue' => 'امروز و منقضی‌شده',
        'today' => 'فقط امروز',
        'overdue' => 'فقط منقضی‌شده',
        'tomorrow' => 'فردا',
        'this_week' => 'هفته جاری',
        'next_week' => 'هفته آینده',
        'this_month' => 'این ماه',
        'next_month' => 'ماه آینده',
    ];

    public function index(Request $request)
    {
        $user = Auth::user();
        $period = $request->get('period', 'today_overdue');
        if (!array_key_exists($period, self::PERIODS)) {
            $period = 'today_overdue';
        }
        $tab = $request->get('tab', 'tasks');
        if (!in_array($tab, ['tasks', 'case_tasks', 'cases', 'assigned_open'], true)) {
            $tab = 'tasks';
        }

        [$start, $end, $mode] = $this->bounds($period);

        // وظایف عمومی من (بدون پرونده)
        $generalQ = Task::query()
            ->with(['case', 'assignee'])
            ->where('assigned_to', $user->id)
            ->whereNull('case_id')
            ->whereNotIn('status', ['done', 'completed', 'cancelled']);
        $this->applyTaskPeriod($generalQ, $mode, $start, $end);
        $taskCount = (clone $generalQ)->count();

        // وظایف مربوط به پرونده‌های من (تخصیص به من + case_id)
        $caseTaskQ = Task::query()
            ->with(['case', 'assignee'])
            ->where('assigned_to', $user->id)
            ->whereNotNull('case_id')
            ->whereNotIn('status', ['done', 'completed', 'cancelled']);
        $this->applyTaskPeriod($caseTaskQ, $mode, $start, $end);
        $caseTaskCount = (clone $caseTaskQ)->count();

        // پرونده‌های من
        $caseQ = CaseModel::query()
            ->with(['customer', 'expert'])
            ->where('assigned_expert_id', $user->id)
            ->whereNotIn('current_status', ['closed', 'lost']);
        $this->applyCasePeriod($caseQ, $mode, $start, $end);
        $caseCount = (clone $caseQ)->count();

        // وظایف باز که این کاربر به دیگران تخصیص داده (مدیران/ادمین)
        $isAdmin = method_exists($user, 'hasRole') && $user->hasRole('admin');
        $isManager = $isAdmin || (method_exists($user, 'hasRole') && (
            $user->hasRole('finance_manager') || $user->hasRole('technical_manager') || $user->hasRole('manager')
        ));
        $showAssignedOpen = $isManager;
        $assignedOpenQ = Task::query()
            ->with(['case', 'assignee', 'creator'])
            ->whereNotIn('status', ['done', 'completed', 'cancelled'])
            ->whereNotNull('assigned_to');
        if ($isAdmin) {
            $assignedOpenQ->whereNotNull('created_by');
        } else {
            $assignedOpenQ->where('created_by', $user->id);
        }
        // اختیاری: فیلتر بازه روی due_at
        $this->applyTaskPeriod($assignedOpenQ, $mode, $start, $end);
        $assignedOpenCount = $showAssignedOpen ? (clone $assignedOpenQ)->count() : 0;

        $tasks = collect();
        $caseTasks = collect();
        $cases = collect();
        $assignedOpenTasks = collect();
        if ($tab === 'tasks') {
            $tasks = (clone $generalQ)->orderByRaw('due_at is null')->orderBy('due_at')->limit(200)->get();
        } elseif ($tab === 'case_tasks') {
            $caseTasks = (clone $caseTaskQ)->orderByRaw('due_at is null')->orderBy('due_at')->limit(200)->get();
        } elseif ($tab === 'assigned_open') {
            if (!$showAssignedOpen) {
                $tab = 'tasks';
                $tasks = (clone $generalQ)->orderByRaw('due_at is null')->orderBy('due_at')->limit(200)->get();
            } else {
                $assignedOpenTasks = (clone $assignedOpenQ)->orderByRaw('due_at is null')->orderBy('due_at')->limit(200)->get();
            }
        } else {
            $cases = (clone $caseQ)->orderBy('updated_at')->limit(200)->get();
        }

        $periods = self::PERIODS;

        $unreadMailCount = 0;
        try {
            $unreadMailCount = app(\App\Services\UserMailboxService::class)->unreadCount($user);
        } catch (\Throwable $e) {
            $unreadMailCount = 0;
        }

        return view('workqueue.index', compact('unreadMailCount',
            'period', 'tab', 'periods',
            'tasks', 'caseTasks', 'cases', 'assignedOpenTasks',
            'taskCount', 'caseTaskCount', 'caseCount', 'assignedOpenCount', 'showAssignedOpen',
            'start', 'end'
        ));
    }

    protected function bounds(string $period): array
    {
        $today = Carbon::today();
        $weekStart = $today->copy()->startOfWeek(Carbon::SATURDAY);
        $weekEnd = $weekStart->copy()->addDays(6)->endOfDay();

        return match ($period) {
            'today' => [$today->copy()->startOfDay(), $today->copy()->endOfDay(), 'range'],
            'overdue' => [null, $today->copy()->startOfDay()->subSecond(), 'overdue'],
            'tomorrow' => [$today->copy()->addDay()->startOfDay(), $today->copy()->addDay()->endOfDay(), 'range'],
            'this_week' => [$weekStart, $weekEnd, 'range'],
            'next_week' => [$weekStart->copy()->addWeek(), $weekEnd->copy()->addWeek(), 'range'],
            'this_month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth(), 'range'],
            'next_month' => [
                $today->copy()->addMonthNoOverflow()->startOfMonth(),
                $today->copy()->addMonthNoOverflow()->endOfMonth(),
                'range',
            ],
            default => [$today->copy()->startOfDay(), $today->copy()->endOfDay(), 'today_overdue'],
        };
    }

    protected function applyTaskPeriod($query, string $mode, $start, $end): void
    {
        if ($mode === 'overdue') {
            $query->whereNotNull('due_at')->where('due_at', '<', now()->startOfDay());
            return;
        }
        if ($mode === 'today_overdue') {
            $query->where(function ($q) use ($start, $end) {
                $q->where(function ($q2) use ($start, $end) {
                    $q2->whereNotNull('due_at')->whereBetween('due_at', [$start, $end]);
                })->orWhere(function ($q2) {
                    $q2->whereNotNull('due_at')->where('due_at', '<', now()->startOfDay());
                });
            });
            return;
        }
        $query->whereNotNull('due_at')->whereBetween('due_at', [$start, $end]);
    }

    protected function applyCasePeriod($query, string $mode, $start, $end): void
    {
        if ($mode === 'overdue') {
            $query->where('updated_at', '<', now()->startOfDay());
            return;
        }
        if ($mode === 'today_overdue') {
            $query->where(function ($q) use ($start, $end) {
                $q->whereBetween('updated_at', [$start, $end])
                    ->orWhere('updated_at', '<', now()->startOfDay());
            });
            return;
        }
        $query->whereBetween('updated_at', [$start, $end]);
    }
}
