<?php
namespace App\Services;

use App\Models\AppSetting;
use App\Models\CaseModel;
use App\Models\Task;
use App\Support\ModuleGate;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class SmartReminderService
{
    public function __construct(protected NotificationService $notifications) {}

    public function run(): array
    {
        if (!ModuleGate::enabled('smart_reminders')) {
            return ['skipped' => true];
        }
        $stats = ['tasks' => 0, 'stuck' => 0, 'receivables' => 0];

        // Overdue tasks
        if (Schema::hasTable('tasks')) {
            $tasks = Task::query()
                ->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->where(function ($q) {
                    $q->whereNull('status')->orWhereNotIn('status', ['done', 'completed', 'cancelled']);
                })
                ->limit(200)
                ->get();
            foreach ($tasks as $task) {
                $uid = $task->assignee_id ?? $task->user_id ?? null;
                if ($uid) {
                    $this->notifications->notify(
                        (int) $uid,
                        'وظیفه سررسید گذشته',
                        $task->title ?? ('#'.$task->id),
                        isset($task->id) ? url('/tasks/'.$task->id) : null
                    );
                    $stats['tasks']++;
                }
            }
        }

        // Cases stuck in status
        $days = (int) AppSetting::get('reminder_stuck_days', '7');
        if (Schema::hasTable('cases') && class_exists(CaseModel::class)) {
            $cutoff = now()->subDays(max(1, $days));
            $cases = CaseModel::query()
                ->where('updated_at', '<', $cutoff)
                ->whereNotIn('status', ['closed', 'lost', 'won', 'cancelled'])
                ->limit(100)
                ->get();
            foreach ($cases as $case) {
                $this->notifications->notifyRole(
                    'admin',
                    'پرونده مانده در وضعیت',
                    ($case->case_number ?? $case->id).' — بیش از '.$days.' روز بدون تغییر',
                    url('/cases/'.$case->id)
                );
                $stats['stuck']++;
            }
        }

        // Open receivables past due (if due_date exists)
        if (Schema::hasTable('payments') === false && Schema::hasTable('receivables')) {
            // skip complex
        }

        // Morning digest flag stored
        AppSetting::set('reminder_last_run', now()->toIso8601String());
        return $stats;
    }
}
