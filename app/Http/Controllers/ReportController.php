<?php

namespace App\Http\Controllers;

use App\Models\CaseActivity;
use App\Models\CaseModel;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index()
    {
        return view('reports.index');
    }

    public function pipeline(Request $request)
    {
        $byStatus = CaseModel::select('current_status', DB::raw('count(*) as total'))
            ->groupBy('current_status')
            ->pluck('total', 'current_status');

        $labels = CaseModel::STATUSES;
        $rows = [];
        foreach ($labels as $key => $label) {
            $rows[] = [
                'status' => $key,
                'label' => $label,
                'count' => $byStatus[$key] ?? 0,
            ];
        }

        return view('reports.pipeline', compact('rows', 'byStatus'));
    }

    public function performance(Request $request)
    {
        $experts = User::withCount([
            'assignedCases as open_cases_count' => function ($q) {
                $q->whereNotIn('current_status', ['closed', 'lost']);
            },
            'assignedCases as won_cases_count' => function ($q) {
                $q->whereIn('current_status', ['won', 'purchasing', 'receivables', 'closed']);
            },
            'assignedCases as lost_cases_count' => function ($q) {
                $q->where('current_status', 'lost');
            },
        ])->get();

        return view('reports.performance', compact('experts'));
    }

    public function tasks(Request $request)
    {
        $byStatus = Task::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $overdue = Task::whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->whereNotIn('status', ['done', 'cancelled'])
            ->count();

        $open = Task::whereIn('status', ['open', 'in_progress', 'overdue'])->count();

        return view('reports.tasks', compact('byStatus', 'overdue', 'open'));
    }

    public function aging()
    {
        $items = \App\Models\Receivable::with('case')
            ->whereNotIn('status', ['PAID'])
            ->orderBy('due_date')
            ->get()
            ->map(function ($r) {
                $days = $r->due_date ? now()->diffInDays($r->due_date, false) : null;

                return [
                    'receivable' => $r,
                    'days_to_due' => $days,
                    'bucket' => $days === null ? '—' : ($days < 0 ? 'معوق' : ($days <= 7 ? 'این هفته' : 'آینده')),
                ];
            });

        return view('reports.aging', compact('items'));
    }

    /** مشتریان/سازمان‌هایی با بیشترین درخواست در بازه زمانی */
    public function topCustomers(Request $request)
    {
        [$from, $to] = $this->range($request);

        $rows = CaseModel::query()
            ->select('customer_organization_id', DB::raw('count(*) as total'))
            ->whereNotNull('customer_organization_id')
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->groupBy('customer_organization_id')
            ->orderByDesc('total')
            ->limit(50)
            ->get()
            ->map(function ($r) {
                $org = Organization::find($r->customer_organization_id);

                return [
                    'organization' => $org,
                    'total' => (int) $r->total,
                ];
            });

        return view('reports.top_customers', compact('rows', 'from', 'to'));
    }

    /** سازمان‌ها و مخاطبان با بیشترین تماس/پیگیری */
    public function topFollowups(Request $request)
    {
        [$from, $to] = $this->range($request);

        $byOrg = CaseActivity::query()
            ->join('cases', 'cases.id', '=', 'case_activities.case_id')
            ->whereNotNull('cases.customer_organization_id')
            ->when($from, fn ($q) => $q->where('case_activities.created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('case_activities.created_at', '<=', $to))
            ->select('cases.customer_organization_id', DB::raw('count(*) as total'),
                DB::raw("sum(case when case_activities.type in ('phone_call_report','phone_call') then 1 else 0 end) as calls"))
            ->groupBy('cases.customer_organization_id')
            ->orderByDesc('total')
            ->limit(40)
            ->get()
            ->map(function ($r) {
                return [
                    'organization' => Organization::find($r->customer_organization_id),
                    'total' => (int) $r->total,
                    'calls' => (int) $r->calls,
                ];
            });

        $byContact = CaseActivity::query()
            ->whereNotNull('contact_id')
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->select('contact_id', DB::raw('count(*) as total'),
                DB::raw("sum(case when type in ('phone_call_report','phone_call') then 1 else 0 end) as calls"))
            ->groupBy('contact_id')
            ->orderByDesc('total')
            ->limit(40)
            ->get()
            ->map(function ($r) {
                return [
                    'contact' => Contact::find($r->contact_id),
                    'total' => (int) $r->total,
                    'calls' => (int) $r->calls,
                ];
            });

        return view('reports.top_followups', compact('byOrg', 'byContact', 'from', 'to'));
    }

    /** مخاطبانی که بیشترین درخواست از آن‌ها آمده (از طریق سازمان یا contact روی پرونده/فعالیت) */
    public function topContacts(Request $request)
    {
        [$from, $to] = $this->range($request);

        // تماس‌هایی که روی پرونده‌های بازه ثبت شده‌اند + سازمان مشتری
        $rows = CaseModel::query()
            ->join('contacts', 'contacts.organization_id', '=', 'cases.customer_organization_id')
            ->when($from, fn ($q) => $q->where('cases.created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('cases.created_at', '<=', $to))
            ->whereNotNull('cases.customer_organization_id')
            ->select(
                'contacts.id as contact_id',
                DB::raw('count(distinct cases.id) as total')
            )
            ->groupBy('contacts.id')
            ->orderByDesc('total')
            ->limit(50)
            ->get()
            ->map(function ($r) {
                return [
                    'contact' => Contact::with('organization')->find($r->contact_id),
                    'total' => (int) $r->total,
                ];
            });

        return view('reports.top_contacts', compact('rows', 'from', 'to'));
    }

    /** پرونده‌های ماندگار در وضعیت فعلی بیش از N روز */
    public function stuckCases(Request $request)
    {
        $days = max(1, (int) $request->get('days', 14));
        $cutoff = now()->subDays($days);

        $cases = CaseModel::with(['customer', 'expert'])
            ->whereNotIn('current_status', ['closed', 'lost'])
            ->where('updated_at', '<=', $cutoff)
            ->orderBy('updated_at')
            ->limit(200)
            ->get()
            ->map(function ($c) {
                $c->days_in_status = $c->updated_at ? $c->updated_at->diffInDays(now()) : null;

                return $c;
            });

        return view('reports.stuck_cases', compact('cases', 'days'));
    }

    /** تعداد درخواست دریافتی در بازه */
    public function receivedCount(Request $request)
    {
        [$from, $to] = $this->range($request, defaultDays: 30);

        $total = CaseModel::query()
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->count();

        $byDay = CaseModel::query()
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->select(DB::raw('date(created_at) as d'), DB::raw('count(*) as total'))
            ->groupBy('d')
            ->orderBy('d')
            ->get();

        $byStatus = CaseModel::query()
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->select('current_status', DB::raw('count(*) as total'))
            ->groupBy('current_status')
            ->pluck('total', 'current_status');

        return view('reports.received_count', compact('total', 'byDay', 'byStatus', 'from', 'to'));
    }

    /** تعداد و فهرست بازنده‌ها در بازه */
    public function lostCount(Request $request)
    {
        [$from, $to] = $this->range($request, defaultDays: 90);

        $query = CaseModel::with(['customer', 'expert'])
            ->where('current_status', 'lost')
            ->when($from, fn ($q) => $q->where('updated_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('updated_at', '<=', $to));

        $total = (clone $query)->count();
        $cases = (clone $query)->orderByDesc('updated_at')->limit(200)->get();

        return view('reports.lost_count', compact('total', 'cases', 'from', 'to'));
    }



    public function remainingReceivables(Request $request)
    {
        $cases = \App\Models\CaseModel::with(['customer', 'expert', 'receivables.payments'])
            ->where('current_status', 'receivables')
            ->orderBy('case_number')
            ->get();

        $rows = [];
        $totalDue = 0.0;
        $totalPaid = 0.0;
        $totalRemain = 0.0;
        $byCurrency = [];

        foreach ($cases as $case) {
            $due = $case->totalDue();
            $paid = $case->totalCollected();
            $remain = max(0, round($due - $paid, 2));
            $cur = $case->currency ?? 'EUR';
            if (!isset($byCurrency[$cur])) {
                $byCurrency[$cur] = ['due' => 0.0, 'paid' => 0.0, 'remain' => 0.0, 'count' => 0];
            }
            $byCurrency[$cur]['due'] += $due;
            $byCurrency[$cur]['paid'] += $paid;
            $byCurrency[$cur]['remain'] += $remain;
            $byCurrency[$cur]['count']++;

            $totalDue += $due;
            $totalPaid += $paid;
            $totalRemain += $remain;

            $rows[] = [
                'case' => $case,
                'due' => $due,
                'paid' => $paid,
                'remain' => $remain,
                'currency' => $cur,
            ];
        }

        // sort by remaining desc
        usort($rows, fn ($a, $b) => $b['remain'] <=> $a['remain']);

        return view('reports.remaining_receivables', compact(
            'rows', 'totalDue', 'totalPaid', 'totalRemain', 'byCurrency'
        ));
    }

    public function conversionFunnel(Request $request)
    {
        [$from, $to] = $this->range($request, 180);
        $base = CaseModel::query()
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to));
        $total = (clone $base)->count();
        $rows = [];
        foreach (CaseModel::STATUSES as $key => $label) {
            $c = (clone $base)->where('current_status', $key)->count();
            $rows[] = [
                'status' => $key,
                'label' => $label,
                'count' => $c,
                'pct' => $total ? round($c / $total * 100, 1) : 0,
            ];
        }
        $won = (clone $base)->whereIn('current_status', ['won', 'purchasing', 'receivables', 'closed'])->count();
        $lost = (clone $base)->where('current_status', 'lost')->count();
        return view('reports.conversion_funnel', compact('rows', 'total', 'won', 'lost', 'from', 'to'));
    }

    public function winLossMonthly(Request $request)
    {
        $months = max(3, min(24, (int) $request->get('months', 12)));
        $start = now()->subMonths($months - 1)->startOfMonth();
        $labels = [];
        $won = [];
        $lost = [];
        for ($i = 0; $i < $months; $i++) {
            $m = $start->copy()->addMonths($i);
            $labels[] = $m->format('Y-m');
            $from = $m->copy()->startOfMonth();
            $to = $m->copy()->endOfMonth();
            $won[] = CaseModel::whereIn('current_status', ['won', 'purchasing', 'receivables', 'closed'])
                ->whereBetween('updated_at', [$from, $to])->count();
            $lost[] = CaseModel::where('current_status', 'lost')
                ->whereBetween('updated_at', [$from, $to])->count();
        }
        return view('reports.win_loss_monthly', compact('labels', 'won', 'lost', 'months'));
    }

    public function pipelineValue(Request $request)
    {
        $open = CaseModel::with('customer')
            ->whereNotIn('current_status', ['closed', 'lost'])
            ->orderByDesc('id')
            ->limit(300)
            ->get();
        // value from linked documents gross if any
        $docSums = DB::table('documents')
            ->select('case_id', DB::raw('sum(gross_amount) as total'))
            ->groupBy('case_id')
            ->pluck('total', 'case_id');
        $byCurrency = ['EUR' => 0, 'IRR' => 0];
        foreach ($open as $c) {
            $amt = (float) ($docSums[$c->id] ?? 0);
            $cur = $c->currency ?? 'EUR';
            if (!isset($byCurrency[$cur])) $byCurrency[$cur] = 0;
            $byCurrency[$cur] += $amt;
            $c->pipeline_amount = $amt;
        }
        return view('reports.pipeline_value', compact('open', 'byCurrency'));
    }

    public function expertWorkload(Request $request)
    {
        $users = User::withCount([
            'assignedCases as open_cases' => fn ($q) => $q->whereNotIn('current_status', ['closed', 'lost']),
            'assignedCases as won_cases' => fn ($q) => $q->whereIn('current_status', ['won', 'purchasing', 'receivables', 'closed']),
        ])->get();
        foreach ($users as $u) {
            try {
                $u->open_tasks = Task::where('assigned_to', $u->id)
                    ->whereNotIn('status', ['done', 'completed', 'cancelled'])->count();
                $u->overdue_tasks = Task::where('assigned_to', $u->id)
                    ->whereNotNull('due_at')->where('due_at', '<', now())
                    ->whereNotIn('status', ['done', 'completed', 'cancelled'])->count();
            } catch (\Throwable $e) {
                $u->open_tasks = 0;
                $u->overdue_tasks = 0;
            }
        }
        return view('reports.expert_workload', compact('users'));
    }

    public function cycleTime(Request $request)
    {
        [$from, $to] = $this->range($request, 180);
        $cases = CaseModel::query()
            ->whereIn('current_status', ['won', 'purchasing', 'receivables', 'closed', 'lost'])
            ->when($from, fn ($q) => $q->where('updated_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('updated_at', '<=', $to))
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get()
            ->map(function ($c) {
                $c->cycle_days = $c->created_at && $c->updated_at
                    ? $c->created_at->diffInDays($c->updated_at) : null;
                return $c;
            });
        $avg = $cases->whereNotNull('cycle_days')->avg('cycle_days');
        return view('reports.cycle_time', compact('cases', 'avg', 'from', 'to'));
    }

    public function documentsByType(Request $request)
    {
        [$from, $to] = $this->range($request, 90);
        $rows = DB::table('documents')
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->select('type', 'currency', DB::raw('count(*) as total'), DB::raw('sum(gross_amount) as amount'))
            ->groupBy('type', 'currency')
            ->orderBy('type')
            ->get();
        return view('reports.documents_by_type', compact('rows', 'from', 'to'));
    }

    public function overdueTasks(Request $request)
    {
        $tasks = Task::with(['assignee', 'case'])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->whereNotIn('status', ['done', 'completed', 'cancelled'])
            ->orderBy('due_at')
            ->limit(200)
            ->get();
        $byUser = $tasks->groupBy(fn ($t) => $t->assigned_to ?? 0)->map->count();
        return view('reports.overdue_tasks', compact('tasks', 'byUser'));
    }

    public function vatIncoterm(Request $request)
    {
        [$from, $to] = $this->range($request, 180);
        $rows = DB::table('documents')
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->select('incoterm', 'currency',
                DB::raw('count(*) as total'),
                DB::raw('sum(net_amount) as net'),
                DB::raw('sum(vat_amount) as vat'),
                DB::raw('sum(gross_amount) as gross'))
            ->groupBy('incoterm', 'currency')
            ->orderBy('incoterm')
            ->get();
        return view('reports.vat_incoterm', compact('rows', 'from', 'to'));
    }

    public function receivablesSummary(Request $request)
    {
        $all = \App\Models\Receivable::with('case')->get();
        $overdue = $all->filter(fn ($r) => $r->due_date && $r->due_date->isPast() && !in_array($r->status, ['PAID', 'paid'], true));
        $thisWeek = $all->filter(fn ($r) => $r->due_date && $r->due_date->isBetween(now()->startOfDay(), now()->addDays(7)) && !in_array($r->status, ['PAID', 'paid'], true));
        $paidMonth = $all->filter(fn ($r) => in_array($r->status, ['PAID', 'paid'], true) && $r->updated_at && $r->updated_at->gte(now()->startOfMonth()));
        return view('reports.receivables_summary', compact('overdue', 'thisWeek', 'paidMonth', 'all'));
    }

    public function invoiceGaps(Request $request)
    {
        $invoices = DB::table('documents')->where('type', 'invoice')->pluck('id');
        $withRec = DB::table('receivables')->whereIn('document_id', $invoices)->pluck('document_id')->unique();
        $invoiceNoRec = DB::table('documents')->where('type', 'invoice')->whereNotIn('id', $withRec)->orderByDesc('id')->limit(100)->get();
        $recNoPay = \App\Models\Receivable::with('case')
            ->where(function ($q) {
                $q->whereNull('paid_amount')->orWhere('paid_amount', 0);
            })
            ->whereNotIn('status', ['PAID', 'paid'])
            ->orderBy('due_date')
            ->limit(100)
            ->get();
        return view('reports.invoice_gaps', compact('invoiceNoRec', 'recNoPay'));
    }

    public function paymentsPeriod(Request $request)
    {
        [$from, $to] = $this->range($request, 90);
        $payments = collect();
        try {
            $payments = \App\Models\Payment::query()
                ->when($from, fn ($q) => $q->where('paid_at', '>=', $from)->orWhere(function ($q2) use ($from) {
                    $q2->whereNull('paid_at')->where('created_at', '>=', $from);
                }))
                ->when($to, fn ($q) => $q->where(function ($q2) use ($to) {
                    $q2->where('paid_at', '<=', $to)->orWhere(function ($q3) use ($to) {
                        $q3->whereNull('paid_at')->where('created_at', '<=', $to);
                    });
                }))
                ->orderByDesc('id')
                ->limit(200)
                ->get();
        } catch (\Throwable $e) {
            try {
                $payments = DB::table('payments')
                    ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
                    ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
                    ->orderByDesc('id')->limit(200)->get();
            } catch (\Throwable $e2) {
            }
        }
        $sum = $payments->sum(fn ($p) => (float) ($p->amount ?? 0));
        return view('reports.payments_period', compact('payments', 'sum', 'from', 'to'));
    }

    public function inactiveCases(Request $request)
    {
        $days = max(1, (int) $request->get('days', 14));
        $cutoff = now()->subDays($days);
        $activeIds = CaseActivity::where('created_at', '>=', $cutoff)->pluck('case_id')->unique();
        $cases = CaseModel::with(['customer', 'expert'])
            ->whereNotIn('current_status', ['closed', 'lost'])
            ->whereNotIn('id', $activeIds)
            ->orderBy('updated_at')
            ->limit(200)
            ->get();
        return view('reports.inactive_cases', compact('cases', 'days'));
    }

    public function callRatio(Request $request)
    {
        [$from, $to] = $this->range($request, 90);
        $cases = CaseModel::query()
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->count();
        $acts = CaseActivity::query()
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->count();
        $calls = CaseActivity::query()
            ->whereIn('type', ['phone_call_report', 'phone_call'])
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->count();
        $ratio = $cases ? round($calls / $cases, 2) : 0;
        $actRatio = $cases ? round($acts / $cases, 2) : 0;
        return view('reports.call_ratio', compact('cases', 'acts', 'calls', 'ratio', 'actRatio', 'from', 'to'));
    }

    public function unmatchedEmails(Request $request)
    {
        $emails = \App\Models\EmailMessage::query()
            ->where(function ($q) {
                $q->where('is_linked', false)->orWhereNull('case_id');
            })
            ->orderByDesc('id')
            ->limit(200)
            ->get();
        return view('reports.unmatched_emails', compact('emails'));
    }

    public function statusAudit(Request $request)
    {
        [$from, $to] = $this->range($request, 30);
        $rows = DB::table('case_status_histories')
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->select('from_status', 'to_status', DB::raw('count(*) as total'))
            ->groupBy('from_status', 'to_status')
            ->orderByDesc('total')
            ->limit(100)
            ->get();
        $recent = DB::table('case_status_histories')
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->orderByDesc('id')
            ->limit(50)
            ->get();
        return view('reports.status_audit', compact('rows', 'recent', 'from', 'to'));
    }

    public function oneTimeOrgs(Request $request)
    {
        $rows = CaseModel::query()
            ->whereNotNull('customer_organization_id')
            ->select('customer_organization_id', DB::raw('count(*) as total'))
            ->groupBy('customer_organization_id')
            ->having('total', '=', 1)
            ->orderBy('customer_organization_id')
            ->limit(100)
            ->get()
            ->map(fn ($r) => [
                'organization' => Organization::find($r->customer_organization_id),
                'total' => (int) $r->total,
            ]);
        return view('reports.one_time_orgs', compact('rows'));
    }

    public function wonCustomers(Request $request)
    {
        $months = max(1, min(24, (int) $request->get('months', 12)));
        $from = now()->subMonths($months)->startOfDay();
        $rows = CaseModel::with('customer')
            ->whereIn('current_status', ['won', 'purchasing', 'receivables', 'closed'])
            ->where('updated_at', '>=', $from)
            ->whereNotNull('customer_organization_id')
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get()
            ->groupBy('customer_organization_id')
            ->map(function ($group) {
                $first = $group->first();
                return [
                    'organization' => $first->customer,
                    'count' => $group->count(),
                    'cases' => $group,
                ];
            })->values();
        return view('reports.won_customers', compact('rows', 'months'));
    }

    public function topSuppliers(Request $request)
    {
        $rows = collect();
        try {
            $rows = DB::table('case_suppliers')
                ->select('organization_id', DB::raw('count(*) as total'))
                ->groupBy('organization_id')
                ->orderByDesc('total')
                ->limit(50)
                ->get()
                ->map(fn ($r) => [
                    'organization' => Organization::find($r->organization_id),
                    'total' => (int) $r->total,
                ]);
        } catch (\Throwable $e) {
        }
        return view('reports.top_suppliers', compact('rows'));
    }


    /**
     * M13-fix: از request('from')/request('to')‌ خام مستقیماً Carbon::parse
     * نمی‌سازیم — چون این پارامترها می‌توانند رشته‌ی شمسی باشند (مثلاً اگر
     * سینک سمت کلاینت انجام نشده باشد) و Carbon::parse('1405-03-07') آن را
     * به‌اشتباه به‌عنوان یک تاریخ میلادی واقعی (سال ۱۴۰۵ میلادی!) می‌خواند —
     * دقیقاً همان باگی که در نوار تاریخ گزارش‌ها دیده می‌شد. parse_due_date()
     * با بازه‌ی سال (شمسی ۱۲۰۰-۱۵۰۰ / میلادی ۱۹۰۰-۲۱۰۰) تشخیص می‌دهد و همیشه
     * یک رشته‌ی میلادی معتبر برمی‌گرداند تا Carbon::parse ایمن باشد.
     * @return array{0:?\Carbon\Carbon,1:?\Carbon\Carbon}
     */
    protected function range(Request $request, int $defaultDays = 90): array
    {
        $fromRaw = parse_due_date($request->get('from'));
        $toRaw = parse_due_date($request->get('to'));

        $from = $fromRaw ? Carbon::parse($fromRaw)->startOfDay() : now()->subDays($defaultDays)->startOfDay();
        $to = $toRaw ? Carbon::parse($toRaw)->endOfDay() : now()->endOfDay();

        return [$from, $to];
    }
}
