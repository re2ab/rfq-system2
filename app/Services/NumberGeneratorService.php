<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class NumberGeneratorService
{
    /** @var array<string, array{prefix:string,pad:int}> */
    public const DEFAULTS = [
        'case' => ['prefix' => 'CASE', 'pad' => 6],
        'technical_proposal' => ['prefix' => 'TC', 'pad' => 6],
        'financial_proposal' => ['prefix' => 'FI', 'pad' => 6],
        'invoice' => ['prefix' => 'INV', 'pad' => 6],
    ];

    public static function typeLabels(): array
    {
        return [
            'case' => 'پرونده',
            'technical_proposal' => 'پیشنهاد فنی',
            'financial_proposal' => 'پیشنهاد مالی',
            'invoice' => 'فاکتور',
        ];
    }

    /**
     * Generate next sequential number using configured prefix / pad / last_number.
     */
    public function next(string $type): string
    {
        return DB::transaction(function () use ($type) {
            $defaults = self::DEFAULTS[$type] ?? ['prefix' => strtoupper($type), 'pad' => 6];

            $sequence = DB::table('number_sequences')
                ->where('type', $type)
                ->lockForUpdate()
                ->first();

            if (!$sequence) {
                DB::table('number_sequences')->insert([
                    'type' => $type,
                    'prefix' => $defaults['prefix'],
                    'pad_length' => $defaults['pad'],
                    'start_number' => 1,
                    'last_number' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $next = 1;
                $prefix = $defaults['prefix'];
                $pad = $defaults['pad'];
            } else {
                $start = (int) ($sequence->start_number ?? 1);
                $last = (int) ($sequence->last_number ?? 0);
                $next = max($last + 1, $start);
                DB::table('number_sequences')
                    ->where('type', $type)
                    ->update(['last_number' => $next, 'updated_at' => now()]);
                $prefix = $sequence->prefix ?: $defaults['prefix'];
                $pad = (int) ($sequence->pad_length ?? $defaults['pad']);
                if ($pad < 1) {
                    $pad = 6;
                }
                if ($pad > 12) {
                    $pad = 12;
                }
            }

            return $prefix.'-'.str_pad((string) $next, $pad, '0', STR_PAD_LEFT);
        });
    }

    /**
     * M21 (فهرست اسناد): وقتی سندی حذف می‌شود، اگر شماره‌ی مصرف‌شده‌اش دقیقاً
     * همان آخرین شماره‌ی صادرشده برای این نوع باشد (یعنی هیچ سند دیگری با
     * شماره‌ی بالاتر صادر نشده)، last_number را یک واحد عقب می‌بریم تا همان
     * شماره دوباره به سند بعدی داده شود. اگر سند دیگری بعداً شماره گرفته
     * (last_number دیگر برابر $serial نیست)، هیچ کاری نمی‌کنیم — طبق درخواست
     * صریح کاربر، هرگز نباید در وسط دنباله شکاف ایجاد شود؛ شماره‌های میانی
     * حذف‌شده برای همیشه «سوخته» می‌مانند، فقط آخرین شماره‌ی صادرشده قابل بازپس‌گیری است.
     *
     * زیر لاک همان ردیفی که NumberGeneratorService::next() هم قفل می‌کند اجرا
     * می‌شود — یک Publish هم‌زمان با یک Delete هم‌زمان هرگز در تشخیص «آخرین
     * شماره بودن» با هم تداخل نمی‌کنند.
     */
    public function reclaimIfLast(string $type, int $serial): bool
    {
        return DB::transaction(function () use ($type, $serial) {
            $sequence = DB::table('number_sequences')->where('type', $type)->lockForUpdate()->first();
            if (!$sequence) {
                return false;
            }

            $last = (int) ($sequence->last_number ?? 0);
            if ($last !== $serial) {
                return false;
            }

            $start = (int) ($sequence->start_number ?? 1);
            $newLast = max($start - 1, $serial - 1);

            DB::table('number_sequences')
                ->where('type', $type)
                ->update(['last_number' => $newLast, 'updated_at' => now()]);

            return true;
        });
    }

    public function preview(string $prefix, int $pad, int $number): string
    {
        $pad = max(1, min(12, $pad));
        $prefix = trim($prefix) !== '' ? trim($prefix) : 'DOC';
        return $prefix.'-'.str_pad((string) max(0, $number), $pad, '0', STR_PAD_LEFT);
    }

    public function peekNext(string $type): string
    {
        $defaults = self::DEFAULTS[$type] ?? ['prefix' => strtoupper($type), 'pad' => 6];
        $sequence = DB::table('number_sequences')->where('type', $type)->first();
        if (!$sequence) {
            return $this->preview($defaults['prefix'], $defaults['pad'], 1);
        }
        $start = (int) ($sequence->start_number ?? 1);
        $last = (int) ($sequence->last_number ?? 0);
        $next = max($last + 1, $start);
        $prefix = $sequence->prefix ?: $defaults['prefix'];
        $pad = (int) ($sequence->pad_length ?? $defaults['pad']);
        return $this->preview($prefix, $pad, $next);
    }
}
