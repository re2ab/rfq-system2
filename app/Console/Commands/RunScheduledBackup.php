<?php
namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Services\BackupService;
use Illuminate\Console\Command;

class RunScheduledBackup extends Command
{
    protected $signature = 'rfq:backup-run';
    protected $description = 'Run scheduled RFQ backup, prune retention, push cloud';

    public function handle(BackupService $backups): int
    {
        if (AppSetting::get('backup_schedule_enabled', '0') !== '1') {
            $this->info('Scheduled backup disabled.');
            return self::SUCCESS;
        }
        $freq = AppSetting::get('backup_schedule_frequency', 'daily');
        if ($freq === 'weekly' && (int) now()->dayOfWeek !== 6) {
            // Saturday in Carbon: 6 — adjust: run weekly only on Saturday
            $this->info('Weekly schedule: skip (not Saturday).');
            return self::SUCCESS;
        }
        $encrypt = AppSetting::get('backup_encrypt', '1') === '1';
        $result = $backups->exportZip($encrypt, 'scheduled', null, null, true);
        $pruned = $backups->pruneOldBackups();
        $this->info('Backup written: '.$result['filename'].' | pruned: '.$pruned);
        return self::SUCCESS;
    }
}
