<?php

namespace App\Console\Commands;

use App\Models\Mail\MailAccount;
use App\Services\Mail\MailSyncService;
use Illuminate\Console\Command;

class SyncMailAccounts extends Command
{
    protected $signature = 'mail:sync
        {--account= : شناسه اکانت خاص}
        {--bodies=1 : دریافت بدنه پیام‌ها (1/0)}';

    protected $description = 'همگام‌سازی اکانت‌های ایمیل یکپارچه (IMAP → دیتابیس محلی)';

    public function handle(MailSyncService $sync): int
    {
        $accountId = $this->option('account');
        $withBodies = (string) $this->option('bodies') !== '0';

        $query = MailAccount::query()->where('is_active', true);
        if ($accountId) {
            $query->where('id', (int) $accountId);
        }
        $accounts = $query->get();

        if ($accounts->isEmpty()) {
            $this->warn('اکانت فعالی برای همگام‌سازی یافت نشد.');

            return self::SUCCESS;
        }

        $ok = 0;
        $fail = 0;
        foreach ($accounts as $account) {
            $this->line("→ #{$account->id} {$account->email} …");
            $result = $sync->syncAccount($account, $withBodies);
            if ($result['ok'] ?? false) {
                $ok++;
                $s = $result['stats'] ?? [];
                $this->info("  موفق — فولدر: ".($s['folders'] ?? 0).' | پیام جدید/به‌روز: '.($s['messages'] ?? 0));
                if (!empty($s['errors'])) {
                    foreach ($s['errors'] as $err) {
                        $this->warn('  ! '.$err);
                    }
                }
            } else {
                $fail++;
                $this->error('  خطا: '.($result['message'] ?? 'نامشخص'));
            }
        }

        $this->line("پایان — موفق: {$ok} | ناموفق: {$fail}");

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}
