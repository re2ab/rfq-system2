<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// RFQ scheduled jobs
Schedule::command('rfq:backup-run')->daily();
Schedule::command('rfq:automations-inactive')->dailyAt('07:30');
Schedule::command('rfq:smart-reminders')->dailyAt('08:00');
// Unified mail client — همگام‌سازی دوره‌ای IMAP (فاز A)
Schedule::command('mail:sync')->everyTenMinutes()->withoutOverlapping();
