<?php

namespace App\Services;

use App\Models\AutomationRule;
use App\Models\CaseModel;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutomationService
{
    public function onCaseStatusChanged(CaseModel $case, string $from, string $to): void
    {
        $rules = AutomationRule::where('is_active', true)
            ->where('trigger', 'status_changed')
            ->get();

        foreach ($rules as $rule) {
            $cond = $rule->conditions ?? [];
            if (!empty($cond['to_status']) && $cond['to_status'] !== $to) {
                continue;
            }
            if (!empty($cond['from_status']) && $cond['from_status'] !== $from) {
                continue;
            }
            $this->runAction($rule, $case);
        }
    }

    /**
     * برای اجرای روزانه: پرونده‌هایی که N روز بدون فعالیت بوده‌اند.
     */
    public function runInactiveCases(): int
    {
        $rules = AutomationRule::where('is_active', true)
            ->where('trigger', 'inactive_days')
            ->get();

        $ran = 0;
        foreach ($rules as $rule) {
            $days = (int) (($rule->conditions['days'] ?? 7));
            if ($days < 1) {
                $days = 7;
            }
            $cutoff = now()->subDays($days);

            $cases = CaseModel::query()
                ->whereNotIn('current_status', ['closed', 'lost', 'stopped'])
                ->where(function ($q) use ($cutoff) {
                    $q->where('updated_at', '<=', $cutoff)
                        ->whereDoesntHave('activities', fn ($a) => $a->where('created_at', '>', $cutoff));
                })
                ->limit(100)
                ->get();

            foreach ($cases as $case) {
                // جلوگیری از تکرار در همان روز: چک وظیفه باز با همان عنوان اتوماسیون
                $title = ($rule->action_payload['title'] ?? 'پیگیری عدم فعالیت').' — '.$case->case_number;
                $exists = Task::where('case_id', $case->id)
                    ->where('title', $title)
                    ->whereDate('created_at', today())
                    ->exists();
                if ($exists) {
                    continue;
                }
                $this->runAction($rule, $case, ['inactive_days' => $days]);
                $ran++;
            }
        }
        return $ran;
    }

    protected function runAction(AutomationRule $rule, CaseModel $case, array $extra = []): void
    {
        $payload = $rule->action_payload ?? [];
        try {
            if ($rule->action === 'create_task') {
                $title = $payload['title'] ?? ('پیگیری خودکار: '.$case->case_number);
                if (!empty($extra['inactive_days'])) {
                    $title = ($payload['title'] ?? 'پیگیری عدم فعالیت').' — '.$case->case_number;
                }
                $days = (int) ($payload['due_days'] ?? 3);
                $assignTo = $case->assigned_expert_id;
                if (!$assignTo) {
                    $assignTo = $case->assignees()->value('users.id');
                }
                if (!$assignTo) {
                    return;
                }
                $task = Task::create([
                    'title' => $title,
                    'description' => $payload['description']
                        ?? ('ایجادشده توسط اتوماسیون: '.$rule->name
                            .(!empty($extra['inactive_days']) ? " (بدون فعالیت {$extra['inactive_days']} روز)" : '')),
                    'status' => 'open',
                    'priority' => $payload['priority'] ?? 'medium',
                    'due_at' => now()->addDays($days),
                    'assigned_to' => $assignTo,
                    'created_by' => $assignTo,
                    'case_id' => $case->id,
                ]);
                if (!empty($payload['also_assign_ids']) && is_array($payload['also_assign_ids'])) {
                    $task->assignees()->syncWithoutDetaching($payload['also_assign_ids']);
                }
            } elseif ($rule->action === 'notify_assignees') {
                $ns = app(NotificationService::class);
                $msg = $payload['message'] ?? ('وضعیت پرونده '.$case->case_number.' تغییر کرد.');
                if (!empty($extra['inactive_days'])) {
                    $msg = $payload['message'] ?? ("پرونده {$case->case_number} بیش از {$extra['inactive_days']} روز بدون فعالیت است.");
                }
                foreach ($case->allAssignees() as $u) {
                    $ns->notify($u->id, $rule->name, $msg, '/cases/'.$case->id);
                }
            } elseif ($rule->action === 'notify_role') {
                $role = $payload['role'] ?? 'admin';
                $ns = app(NotificationService::class);
                $msg = $payload['message'] ?? ('اعلان خودکار برای '.$case->case_number);
                User::role($role)->get()->each(function ($u) use ($ns, $rule, $msg, $case) {
                    $ns->notify($u->id, $rule->name, $msg, '/cases/'.$case->id);
                });
            }
        } catch (\Throwable $e) {
            Log::warning('automation failed: '.$e->getMessage());
        }
    }
}
