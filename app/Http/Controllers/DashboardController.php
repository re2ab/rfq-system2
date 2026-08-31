<?php

namespace App\Http\Controllers;

use App\Models\CaseActivity;
use App\Models\CaseModel;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $openStatuses = [
            'received', 'waiting_info', 'waiting_offer', 'waiting_technical',
            'technical_sent', 'waiting_financial', 'financial_sent',
            'won', 'purchasing', 'receivables', 'stopped',
        ];

        $openCases = CaseModel::whereNotIn('current_status', ['closed', 'lost'])->count();
        // فقط وضعیت won خالص برای کارت «برنده شده»
        $wonPure = CaseModel::where('current_status', 'won')->count();

        $won = CaseModel::whereIn('current_status', ['won', 'purchasing', 'receivables', 'closed'])->count();
        $lost = CaseModel::where('current_status', 'lost')->count();
        $decided = $won + $lost;
        $winRate = $decided > 0 ? round(($won / $decided) * 100, 1) : 0;

        $byStatus = CaseModel::select('current_status', DB::raw('count(*) as total'))
            ->groupBy('current_status')
            ->pluck('total', 'current_status');

        // پرونده‌های باز به تفکیک اولویت (برای نمودار قابل انتخاب داشبورد)
        $byPriorityRaw = CaseModel::whereNotIn('current_status', ['closed', 'lost'])
            ->select('priority', DB::raw('count(*) as total'))
            ->groupBy('priority')
            ->pluck('total', 'priority');

        // بار کاری کارشناسان: تعداد پرونده‌های باز به‌ازای هر کارشناس مسئول (برای تصمیم مدیریتی)
        $expertWorkload = CaseModel::whereNotIn('current_status', ['closed', 'lost'])
            ->whereNotNull('assigned_expert_id')
            ->select('assigned_expert_id', DB::raw('count(*) as total'))
            ->groupBy('assigned_expert_id')
            ->orderByDesc('total')
            ->with('expert')
            ->limit(8)
            ->get()
            ->mapWithKeys(fn ($row) => [($row->expert->name ?? ('#'.$row->assigned_expert_id)) => $row->total]);

        // پرونده‌های راکد: باز و بدون به‌روزرسانی بیش از ۱۴ روز، به تفکیک کارشناس
        $staleThreshold = now()->subDays(14);
        $staleByExpert = CaseModel::whereNotIn('current_status', ['closed', 'lost'])
            ->whereNotNull('assigned_expert_id')
            ->where('updated_at', '<', $staleThreshold)
            ->select('assigned_expert_id', DB::raw('count(*) as total'))
            ->groupBy('assigned_expert_id')
            ->orderByDesc('total')
            ->with('expert')
            ->limit(8)
            ->get()
            ->mapWithKeys(fn ($row) => [($row->expert->name ?? ('#'.$row->assigned_expert_id)) => $row->total]);

        $recentCases = CaseModel::with(['customer', 'expert'])
            ->latest()
            ->limit(5)
            ->get();

        $recentActivities = collect();
        try {
            $recentActivities = CaseActivity::with(['user', 'case'])
                ->latest()
                ->limit(6)
                ->get();
        } catch (\Throwable $e) {
        }

        $myTasks = collect();
        $tasksDue = 0;
        try {
            if (auth()->check()) {
                $myTasks = Task::where('assigned_to', auth()->id())
                    ->whereNotIn('status', ['done', 'completed', 'cancelled'])
                    ->orderBy('due_at')
                    ->limit(5)
                    ->get();
                $tasksDue = Task::where('assigned_to', auth()->id())
                    ->whereNotIn('status', ['done', 'completed', 'cancelled'])
                    ->whereNotNull('due_at')
                    ->where('due_at', '<=', now()->addDay())
                    ->count();
            }
        } catch (\Throwable $e) {
        }

        // روند ۱۴ روز اخیر برای نمودار خطی
        $days = 14;
        $labels = [];
        $casesPerDay = [];
        $activitiesPerDay = [];
        $wonPerDay = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = Carbon::today()->subDays($i);
            try {
                if (class_exists(\Morilog\Jalali\Jalalian::class)) {
                    $labels[] = \Morilog\Jalali\Jalalian::fromCarbon($day)->format('%m/%d');
                } else {
                    $labels[] = $day->format('m/d');
                }
            } catch (\Throwable $e) {
                $labels[] = $day->format('m/d');
            }
            $start = $day->copy()->startOfDay();
            $end = $day->copy()->endOfDay();
            try {
                $casesPerDay[] = CaseModel::whereBetween('created_at', [$start, $end])->count();
            } catch (\Throwable $e) {
                $casesPerDay[] = 0;
            }
            try {
                $activitiesPerDay[] = CaseActivity::whereBetween('created_at', [$start, $end])->count();
            } catch (\Throwable $e) {
                $activitiesPerDay[] = 0;
            }
            try {
                $wonPerDay[] = CaseModel::where('current_status', 'won')
                    ->whereBetween('updated_at', [$start, $end])
                    ->count();
            } catch (\Throwable $e) {
                $wonPerDay[] = 0;
            }
        }

        // وظایف باز که این مدیر (یا ادمین) تخصیص داده و هنوز کامل نشده
        $assignedOpenTasks = collect();
        $showAssignedOpenPanel = false;
        try {
            if (auth()->check()) {
                $user = auth()->user();
                $isAdmin = method_exists($user, 'hasRole') && $user->hasRole('admin');
                $isManager = $isAdmin || (method_exists($user, 'hasRole') && (
                    $user->hasRole('finance_manager') || $user->hasRole('technical_manager') || $user->hasRole('manager')
                ));
                // dashboard is typically managers/admin only; still gate panel
                if ($isManager || $isAdmin) {
                    $showAssignedOpenPanel = true;
                    $q = Task::query()
                        ->with(['assignee', 'case', 'creator'])
                        ->whereNotIn('status', ['done', 'completed', 'cancelled'])
                        ->whereNotNull('assigned_to');
                    if ($isAdmin) {
                        // همه وظایف باز تخصیص‌داده‌شده توسط هر کسی (مدیران + خودش)
                        // فیلتر: created_by پر باشد یا assigned_to با created_by متفاوت
                        $q->where(function ($qq) use ($user) {
                            $qq->whereNotNull('created_by')
                                ->orWhere('created_by', $user->id);
                        });
                    } else {
                        // فقط وظایفی که این مدیر ایجاد/تخصیص کرده
                        $q->where('created_by', $user->id);
                    }
                    $assignedOpenTasks = $q->orderByRaw('due_at is null')->orderBy('due_at')->limit(25)->get();
                }
            }
        } catch (\Throwable $e) {
            $assignedOpenTasks = collect();
        }


        // پرونده‌های باز/کل بر حسب صنعت سازمان مشتری
        $industryLabels = [];
        $industryCounts = [];
        try {
            $rows = CaseModel::query()
                ->leftJoin('organizations', 'organizations.id', '=', 'cases.customer_organization_id')
                ->leftJoin('industries', 'industries.id', '=', 'organizations.industry_id')
                ->selectRaw("COALESCE(industries.name, 'بدون صنعت / بدون سازمان') as industry_name, COUNT(cases.id) as total")
                ->groupBy('industry_name')
                ->orderByDesc('total')
                ->get();
            foreach ($rows as $r) {
                $industryLabels[] = $r->industry_name;
                $industryCounts[] = (int) $r->total;
            }
        } catch (\Throwable $e) {
            $industryLabels = [];
            $industryCounts = [];
        }


        $openReceivables = 0;
        $overdueReceivables = 0;
        try {
            if (class_exists(\App\Models\Receivable::class)) {
                $openReceivables = \App\Models\Receivable::query()
                    ->where(function ($q) {
                        $q->whereNull('status')->orWhereNotIn('status', ['paid', 'closed', 'cancelled']);
                    })
                    ->whereRaw('COALESCE(amount,0) > COALESCE(paid_amount,0)')
                    ->count();
                $overdueReceivables = \App\Models\Receivable::query()
                    ->where(function ($q) {
                        $q->whereNull('status')->orWhereNotIn('status', ['paid', 'closed', 'cancelled']);
                    })
                    ->whereRaw('COALESCE(amount,0) > COALESCE(paid_amount,0)')
                    ->whereNotNull('due_date')
                    ->whereDate('due_date', '<', now()->toDateString())
                    ->count();
            } else {
                $openReceivables = CaseModel::where('current_status', 'receivables')->count();
                $overdueReceivables = 0;
            }
        } catch (\Throwable $e) {
            try {
                $openReceivables = CaseModel::where('current_status', 'receivables')->count();
            } catch (\Throwable $e2) {
                $openReceivables = 0;
            }
            $overdueReceivables = 0;
        }

        $paidReceivables = 0;
        try {
            if (class_exists(\App\Models\Receivable::class)) {
                $paidReceivables = \App\Models\Receivable::where('status', 'paid')->count();
            }
        } catch (\Throwable $e) {
            $paidReceivables = 0;
        }

        $tasksDone = 0;
        $tasksNotDone = 0;
        try {
            $tasksDone = Task::whereIn('status', ['done', 'completed'])->count();
            $tasksNotDone = Task::whereNotIn('status', ['done', 'completed', 'cancelled'])->count();
        } catch (\Throwable $e) {
        }

        $stats = [
            'open_cases' => $openCases,
            'open_receivables' => $openReceivables,
            'overdue_receivables' => $overdueReceivables,
            'receivables_paid' => $paidReceivables,
            'tasks_done' => $tasksDone,
            'tasks_not_done' => $tasksNotDone,
            'won_pure' => $wonPure ?? CaseModel::where('current_status', 'won')->count(),
            'win_rate' => $winRate,
            'won_count' => $won,
            'lost_count' => $lost,
            'total_cases' => CaseModel::count(),
            'stopped_cases' => CaseModel::where('current_status', 'stopped')->count(),
            'by_status' => $byStatus,
            'by_priority' => $byPriorityRaw,
            'expert_workload' => $expertWorkload,
            'stale_by_expert' => $staleByExpert,            'recent_cases' => $recentCases,
            'recent_activities' => $recentActivities,
            'my_tasks' => $myTasks,
            'tasks_due' => $tasksDue,
            'line_labels' => $labels,
            'line_cases' => $casesPerDay,
            'line_activities' => $activitiesPerDay,
            'line_won' => $wonPerDay,
            'industry_labels' => $industryLabels,
            'industry_counts' => $industryCounts,
            'assigned_open_tasks' => $assignedOpenTasks,
            'show_assigned_open_panel' => $showAssignedOpenPanel,
            'assigned_open_count' => $assignedOpenTasks->count(),
        ];

        return view('dashboard', ['stats' => $stats, 'dashLayout' => dashboard_layout()]);
    }
}
