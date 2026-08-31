<?php
namespace App\Console\Commands;

use App\Services\SmartReminderService;
use Illuminate\Console\Command;

class RunSmartReminders extends Command
{
    protected $signature = 'rfq:smart-reminders';
    protected $description = 'Send smart overdue/stuck reminders';

    public function handle(SmartReminderService $svc): int
    {
        $r = $svc->run();
        $this->info(json_encode($r, JSON_UNESCAPED_UNICODE));
        return self::SUCCESS;
    }
}
