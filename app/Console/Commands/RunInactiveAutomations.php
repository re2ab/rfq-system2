<?php

namespace App\Console\Commands;

use App\Services\AutomationService;
use Illuminate\Console\Command;

class RunInactiveAutomations extends Command
{
    protected $signature = 'rfq:automations-inactive';
    protected $description = 'اجرای قوانین اتوماسیون پرونده‌های بدون فعالیت';

    public function handle(AutomationService $svc): int
    {
        $n = $svc->runInactiveCases();
        $this->info("Ran actions for {$n} case(s).");
        return self::SUCCESS;
    }
}
