<?php

namespace App\Http\Controllers;

use App\Services\Mail\MailMatchingService;

use App\Models\CaseModel;
use App\Models\Organization;
use App\Models\Contact;
use App\Models\User;
use App\Services\NumberGeneratorService;
use App\Services\CaseStatusService;
use App\Services\CustomFieldService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CaseController extends Controller
{
    public function __construct(
        protected NumberGeneratorService $numberGenerator,
        protected CaseStatusService $statusService
    ) {}

    public function index(Request $request)
    {
        $query = CaseModel::with(['customer', 'expert'])->latest();

        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('case_number', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('won_reason', 'like', "%{$search}%")
                  ->orWhere('lost_reason', 'like', "%{$search}%")
                  ->orWhere('stopped_reason', 'like', "%{$search}%")
                  ->orWhere('incoterm', 'like', "%{$search}%")
                  ->orWhereHas('customer', fn ($o) => $o->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('contact', fn ($c) => $c->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"))
                  ->orWhereHas('expert', fn ($u) => $u->where('name', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->get('status')) {
            $query->where('current_status', $status);
        }
        if ($tagId = $request->get('tag_id')) {
            $query->whereHas('tags', fn ($q) => $q->where('tags.id', $tagId));
        }

        if ($priority = $request->get('priority')) {
            $query->where('priority', $priority);
        }

        if ($request->get('export')) {
            $rows = $query->limit(5000)->get();
            $csv = "case_number,title,status,priority,customer,expert,updated_at\n";
            foreach ($rows as $c) {
                $csv .= sprintf(
                    "\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                    $c->case_number,
                    str_replace('"', "'", $c->title),
                    $c->current_status,
                    $c->priority ?? '',
                    str_replace('"', "'", $c->customer?->name ?? ''),
                    str_replace('"', "'", $c->expert?->name ?? ''),
                    optional($c->updated_at)->toDateTimeString()
                );
            }
            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="cases-export.csv"',
            ]);
        }

        $cases = $query->paginate(20)->withQueryString();
        $savedViews = collect();
        try {
            $savedViews = \App\Models\SavedView::where('user_id', Auth::id())
                ->where('resource', 'cases')
                ->orderBy('name')
                ->get();
        } catch (\Throwable $e) {}

        return view('cases.index', compact('cases', 'savedViews'));
    }

    public function storeView(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'q' => 'nullable|string|max:200',
            'status' => 'nullable|string|max:50',
            'priority' => 'nullable|string|max:20',
        ]);
        \App\Models\SavedView::create([
            'user_id' => Auth::id(),
            'name' => $data['name'],
            'resource' => 'cases',
            'filters' => [
                'q' => $data['q'] ?? null,
                'status' => $data['status'] ?? null,
                'priority' => $data['priority'] ?? null,
            ],
        ]);
        return back()->with('success', 'نمای ذخیره‌شده ایجاد شد.');
    }

    public function destroyView(\App\Models\SavedView $savedView)
    {
        if ($savedView->user_id !== Auth::id()) abort(403);
        $savedView->delete();
        return back()->with('success', 'نما حذف شد.');
    }

    public function create(CustomFieldService $cf)
    {
        $organizations = Organization::orderBy('name')->get();
        $contacts = Contact::orderBy('first_name')->orderBy('last_name')->limit(500)->get();
        $experts = $this->experts();
        $customFields = $cf->definitions('case');
        $customValues = [];
        $customVisible = [];
        $nextCaseNumber = '';
        try { $nextCaseNumber = $this->numberGenerator->peekNext('case'); } catch (\Throwable $e) {}

        return view('cases.create', compact('organizations', 'contacts', 'experts', 'customFields', 'customValues', 'customVisible', 'nextCaseNumber'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'customer_organization_id' => 'nullable|exists:organizations,id',
            'contact_id' => 'nullable|exists:contacts,id',
            'customer_request_number' => 'nullable|string|max:100',
            'assigned_expert_id' => 'nullable|exists:users,id',
            'assignee_ids' => 'nullable|array',
            'assignee_ids.*' => 'exists:users,id',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:tags,id',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'currency' => 'nullable|in:EUR,IRR',
            'incoterm' => 'nullable|string|max:20',
        ]);

        $assigneeIds = $data['assignee_ids'] ?? [];
        $tagIds = $data['tag_ids'] ?? [];
        unset($data['assignee_ids'], $data['tag_ids']);

        // empty strings to null for FK columns
        foreach (['customer_organization_id', 'contact_id', 'assigned_expert_id'] as $fk) {
            if (array_key_exists($fk, $data) && ($data[$fk] === '' || $data[$fk] === null)) {
                $data[$fk] = null;
            }
        }

        try {
            $data['case_number'] = $this->numberGenerator->next('case');
        } catch (\Throwable $e) {
            $data['case_number'] = 'CASE-'.str_pad((string) (CaseModel::withTrashed()->count() + 1), 6, '0', STR_PAD_LEFT);
        }
        $data['current_status'] = 'received';
        $data['priority'] = $data['priority'] ?? 'medium';
        $data['currency'] = $data['currency'] ?? 'EUR';

        try {
            $case = CaseModel::create($data);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['title' => 'خطا در ایجاد پرونده: '.$e->getMessage()]);
        }

        try {
            $sync = collect($assigneeIds);
            if (!empty($data['assigned_expert_id'])) {
                $sync->push($data['assigned_expert_id']);
            }
            if (method_exists($case, 'assignees')) {
                $case->assignees()->sync($sync->unique()->filter()->values()->all());
            }
        } catch (\Throwable $e) {}
        try {
            if ($tagIds && method_exists($case, 'tags')) {
                $valid = \App\Models\Tag::forEntity('case')->whereIn('id', $tagIds)->pluck('id');
                $case->tags()->sync($valid);
            }
        } catch (\Throwable $e) {}
        try {
            app(CustomFieldService::class)->save('case', $case->id, $request->all());
        } catch (\Throwable $e) {}

        return redirect()->route('cases.show', $case)->with('success', __('app.case_created'));
    }

    public function show(CaseModel $case)
    {
        $case->load([
            'customer', 'contact', 'expert', 'assignees', 'tags',
            'activities.user', 'activities.reactions', 'activities.children.user',
            // M29 (درخواست کاربر): تبِ «اسناد» حالا هر رویژنِ منتشرشده‌ی هر سند را
            // به‌عنوانِ یک ردیفِ جدا نشان می‌دهد (نه فقط یک ردیف در سطحِ سند) —
            // پس revisions هم باید eager-load شود، وگرنه N+1 پیش می‌آید.
            'statusHistories.user', 'documents.revisions', 'deliveries', 'receivables.payments', 'attachments.uploader',
            'tasks.assignee', 'tasks.assignees',
        ]);
        $cf = app(CustomFieldService::class);
        $customFields = $cf->definitions('case');
        $customValues = $cf->values('case', $case->id);
        $customVisible = $cf->visibility('case', $case->id);
        $canAssignTask = false;
        $assignableUsers = collect();
        try {
            $u = auth()->user();
            $canAssignTask = $u && $u->hasAnyRole(['admin', 'technical_manager', 'financial_manager']);
            if ($canAssignTask) {
                try {
                    $assignableUsers = \App\Models\User::role([
                        'technical_expert', 'financial_expert', 'expert',
                        'technical_manager', 'financial_manager', 'admin',
                    ])->orderBy('name')->get();
                } catch (\Throwable $e) {
                    $assignableUsers = \App\Models\User::orderBy('name')->get();
                }
            }
        } catch (\Throwable $e) {
        }

        $mailTimeline = [];
        try {
            if (\App\Support\ModuleGate::enabled('unified_mail', true) && \Illuminate\Support\Facades\Schema::hasTable('mail_messages')) {
                $mailTimeline = app(MailMatchingService::class)->timelineForCase($case->id);
            }
        } catch (\Throwable $e) {
            $mailTimeline = [];
        }

        return view('cases.show', compact('case', 'customFields', 'customValues', 'customVisible', 'assignableUsers', 'canAssignTask', 'mailTimeline'));
    }

    public function edit(CaseModel $case, CustomFieldService $cf)
    {
        $case->load(['assignees', 'tags']);
        $organizations = Organization::orderBy('name')->get();
        $experts = $this->experts();
        $customFields = $cf->definitions('case');
        $customValues = $cf->values('case', $case->id);
        $customVisible = $cf->visibility('case', $case->id);

        $contacts = Contact::orderBy('first_name')->orderBy('last_name')->limit(500)->get();
        return view('cases.edit', compact('case', 'organizations', 'contacts', 'experts', 'customFields', 'customValues', 'customVisible'));
    }

    public function update(Request $request, CaseModel $case)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'customer_organization_id' => 'nullable|exists:organizations,id',
            'contact_id' => 'nullable|exists:contacts,id',
            'customer_request_number' => 'nullable|string|max:100',
            'assigned_expert_id' => 'nullable|exists:users,id',
            'assignee_ids' => 'nullable|array',
            'assignee_ids.*' => 'exists:users,id',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:tags,id',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'currency' => 'nullable|in:EUR,IRR',
            'incoterm' => 'nullable|string|max:20',
            'exchange_rate' => 'nullable|numeric|min:0',
        ]);

        $assigneeIds = $data['assignee_ids'] ?? null;
        $tagIds = $data['tag_ids'] ?? null;
        unset($data['assignee_ids'], $data['tag_ids']);
        $case->update($data);
        if (is_array($assigneeIds)) {
            $sync = collect($assigneeIds);
            if (!empty($data['assigned_expert_id'])) {
                $sync->push($data['assigned_expert_id']);
            }
            $case->assignees()->sync($sync->unique()->filter()->values()->all());
        }
        if (is_array($tagIds)) {
            $valid = \App\Models\Tag::forEntity('case')->whereIn('id', $tagIds)->pluck('id');
            $case->tags()->sync($valid);
        }
        app(CustomFieldService::class)->save('case', $case->id, $request->all());

        return redirect()->route('cases.show', $case)->with('success', __('app.case_updated'));
    }

    public function destroy(CaseModel $case)
    {
        $case->delete();
        return redirect()->route('cases.index')->with('success', 'پرونده حذف شد.');
    }

    public function bulkAction(Request $request)
    {
        $data = $request->validate([
            'bulk_action' => 'required|in:delete',
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:cases,id',
        ]);
        if ($data['bulk_action'] === 'delete') {
            CaseModel::whereIn('id', $data['ids'])->delete();
        }
        return back()->with('success', 'پرونده‌های انتخاب‌شده حذف شدند.');
    }

    public function changeStatus(Request $request, CaseModel $case)
    {
        $data = $request->validate([
            'status' => 'required|string',
            'reason' => 'nullable|string',
            'is_override' => 'boolean',
            'proposal_amount' => 'nullable|numeric|min:0',
            'vat_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        try {
            $this->statusService->changeStatus(
                $case,
                $data['status'],
                $data['reason'] ?? null,
                (bool) ($data['is_override'] ?? false),
                [
                    'proposal_amount' => $data['proposal_amount'] ?? null,
                    'vat_percent' => $data['vat_percent'] ?? null,
                ]
            );
            return back()->with('success', 'وضعیت پرونده تغییر کرد.');
        } catch (\Exception $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }
    }

    public function storePayment(Request $request, CaseModel $case)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'method' => 'nullable|string|max:50',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $case->load('receivables.payments');
        $receivable = $case->receivables()->orderByDesc('id')->first();
        if (!$receivable) {
            $due = $case->totalDue();
            if ($due <= 0) {
                return back()->withErrors(['amount' => 'ابتدا مبلغ پیشنهاد مالی را مشخص کنید یا وضعیت را به دریافت مطالبات ببرید.']);
            }
            $receivable = \App\Models\Receivable::create([
                'case_id' => $case->id,
                'currency' => $case->currency ?? 'EUR',
                'amount' => $due,
                'paid_amount' => 0,
                'status' => 'PENDING',
                'due_date' => now()->addDays(30),
            ]);
        }

        \App\Models\Payment::create([
            'receivable_id' => $receivable->id,
            'amount' => $data['amount'],
            'payment_date' => $data['payment_date'],
            'method' => $data['method'] ?? null,
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'recorded_by' => auth()->id(),
        ]);

        $receivable->refresh();
        $paid = (float) $receivable->payments()->sum('amount');
        $receivable->paid_amount = $paid;
        if ($paid + 0.01 >= (float) $receivable->amount) {
            $receivable->status = 'PAID';
        } elseif ($paid > 0) {
            $receivable->status = 'PARTIALLY_PAID';
        }
        $receivable->save();

        $cur = $case->currency ?: 'EUR';
        try {
            $case->activities()->create([
                'user_id' => auth()->id(),
                'type' => 'note',
                'body' => 'ثبت وصول مطالبات: '.number_format((float)$data['amount'], 2).' '.$cur.' در تاریخ '.$data['payment_date'],
            ]);
        } catch (\Throwable $e) {
        }

        return back()->with('success', 'مبلغ وصول ثبت شد.');
    }

    public function storeActivity(Request $request, CaseModel $case)
    {
        $data = $request->validate([
            'type' => 'required|in:note,phone_call_report',
            'body' => 'required|string',
            'contact_id' => 'nullable|exists:contacts,id',
            'call_datetime' => 'nullable|date',
            'call_direction' => 'nullable|in:incoming,outgoing',
            'duration_minutes' => 'nullable|integer|min:1',
            'call_result' => 'nullable|string',
            'parent_id' => 'nullable|exists:case_activities,id',
        ]);

        $data['user_id'] = Auth::id();
        $activity = $case->activities()->create($data);
        $this->parseMentionsAndNotify($data['body'], $case, $activity);

        return back()->with('success', __('app.activity_saved'));
    }

    protected function experts()
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

    public function reactActivity(\Illuminate\Http\Request $request, \App\Models\CaseActivity $activity)
    {
        $data = $request->validate([
            'type' => 'required|in:like,emoji',
            'emoji' => 'nullable|string|max:16',
        ]);
        $userId = auth()->id();
        $existing = \App\Models\ActivityReaction::where('case_activity_id', $activity->id)
            ->where('user_id', $userId)
            ->where('type', $data['type'])
            ->first();
        if ($existing) {
            $existing->delete();
        } else {
            \App\Models\ActivityReaction::create([
                'case_activity_id' => $activity->id,
                'user_id' => $userId,
                'type' => $data['type'],
                'emoji' => $data['emoji'] ?? null,
            ]);
        }
        return back();
    }

    protected function parseMentionsAndNotify(string $body, $case, $activity = null): void
    {
        if (!preg_match_all('/@([\w\x{0600}-\x{06FF}]+)/u', $body, $m)) {
            return;
        }
        $names = array_unique($m[1]);
        try {
            $ns = app(\App\Services\NotificationService::class);
            foreach ($names as $name) {
                $user = \App\Models\User::where('name', 'like', $name.'%')->first();
                if (!$user) {
                    continue;
                }
                if ($activity) {
                    \Illuminate\Support\Facades\DB::table('activity_mentions')->updateOrInsert(
                        ['case_activity_id' => $activity->id, 'user_id' => $user->id],
                        ['created_at' => now(), 'updated_at' => now()]
                    );
                }
                $ns->notify($user, __('app.mention_title'), $case->case_number.': '.__('app.you_were_mentioned'), '/cases/'.$case->id);
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

}
