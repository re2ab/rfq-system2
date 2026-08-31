<?php

namespace App\Services;

use App\Models\CaseModel;
use App\Models\CaseStatusHistory;
use App\Models\Receivable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;

class CaseStatusService
{
    protected array $transitions = [
        'received' => ['waiting_info', 'stopped', 'lost'],
        'waiting_info' => ['waiting_offer', 'stopped', 'lost'],
        'waiting_offer' => ['waiting_technical', 'stopped', 'lost'],
        'waiting_technical' => ['technical_sent', 'stopped', 'lost'],
        'technical_sent' => ['waiting_financial', 'stopped', 'lost'],
        'waiting_financial' => ['financial_sent', 'stopped', 'lost'],
        'financial_sent' => ['won', 'lost', 'stopped'],
        'won' => ['purchasing', 'stopped'],
        'purchasing' => ['receivables', 'stopped'],
        'receivables' => ['closed'],
        'stopped' => [],
        'lost' => [],
        'closed' => [],
    ];

    public function canTransition(string $from, string $to, bool $isOverride = false): bool
    {
        if ($isOverride) {
            return true;
        }
        try {
            $row = \App\Models\PipelineTransition::where('from_key', $from)
                ->where('to_key', $to)
                ->where('is_allowed', true)
                ->first();
            if ($row) {
                return true;
            }
            // اگر جدولی پر است و این یال نیست → غیرمجاز
            if (\App\Models\PipelineTransition::where('from_key', $from)->exists()) {
                return false;
            }
        } catch (\Throwable $e) {
        }
        return in_array($to, $this->transitions[$from] ?? [], true);
    }

    public function transitionCondition(string $from, string $to): ?string
    {
        try {
            $row = \App\Models\PipelineTransition::where('from_key', $from)
                ->where('to_key', $to)
                ->where('is_allowed', true)
                ->first();
            return $row?->condition_code ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @param  array{proposal_amount?:float|string,vat_percent?:float|string}  $extra
     */
    public function changeStatus(
        CaseModel $case,
        string $newStatus,
        ?string $reason = null,
        bool $isOverride = false,
        array $extra = []
    ): void {
        $oldStatus = $case->current_status;

        if ($oldStatus === $newStatus) {
            return;
        }

        if (!$this->canTransition($oldStatus, $newStatus, $isOverride)) {
            throw new Exception('انتقال از «'.(CaseModel::statusLabels()[$oldStatus] ?? $oldStatus).'» به «'.(CaseModel::statusLabels()[$newStatus] ?? $newStatus).'» مجاز نیست.');
        }

        if (!$isOverride) {
            $cond = $this->transitionCondition($oldStatus, $newStatus);
            if ($cond === 'receivables_paid' || ($newStatus === 'closed' && $cond === null && $oldStatus === 'receivables')) {
                // اگر شرط صریح یا پیش‌فرض مطالبات
            }
            if ($cond === 'receivables_paid') {
                if (!$case->isReceivablesFullyPaid()) {
                    $remain = max(0, $case->totalDue() - $case->totalCollected());
                    throw new Exception('بستن/انتقال ممکن نیست؛ مانده مطالبات: '.number_format($remain, 2).' '.($case->currency ?? ''));
                }
            }
            if ($cond === 'proposal_amount') {
                $amount = $extra['proposal_amount'] ?? $case->proposal_amount;
                $vat = $extra['vat_percent'] ?? $case->vat_percent;
                if ($amount === null || $amount === '' || !is_numeric($amount)) {
                    throw new Exception('برای این انتقال، مبلغ نهایی پیشنهاد الزامی است.');
                }
                if ($vat === null || $vat === '' || !is_numeric($vat)) {
                    throw new Exception('برای این انتقال، درصد ارزش افزوده الزامی است.');
                }
            }
            if ($cond === 'lost_reason') {
                $lr = $extra['lost_reason'] ?? $case->lost_reason ?? $reason;
                if (!$lr || !trim((string)$lr)) {
                    throw new Exception('برای وضعیت باخت، ذکر دلیل الزامی است.');
                }
            }
        }

        // پیشنهاد مالی ارسال‌شده: الزام مبلغ و VAT
        if ($newStatus === 'financial_sent') {
            $amount = $extra['proposal_amount'] ?? $case->proposal_amount;
            $vat = $extra['vat_percent'] ?? $case->vat_percent;
            if ($amount === null || $amount === '' || !is_numeric($amount) || (float) $amount < 0) {
                throw new Exception('برای وضعیت «پیشنهاد مالی ارسال‌شده» وارد کردن مبلغ نهایی پیشنهاد الزامی است.');
            }
            if ($vat === null || $vat === '' || !is_numeric($vat) || (float) $vat < 0) {
                throw new Exception('برای وضعیت «پیشنهاد مالی ارسال‌شده» درصد ارزش افزوده الزامی است (در صورت عدم شمول، ۰ وارد کنید).');
            }
        }

        if ($newStatus === 'receivables' && !$isOverride) {
            $hasDelivery = $case->deliveries()->count() >= 1;
            $hasInvoice = $case->documents()->where('type', 'invoice')->count() >= 1;
            $hasAmount = $case->proposal_gross !== null || $case->proposal_amount !== null
                || isset($extra['proposal_amount']);
            if (!$hasDelivery && !$hasInvoice && !$hasAmount) {
                throw new Exception('برای ورود به دریافت مطالبات، مبلغ پیشنهاد مالی یا فاکتور/تحویل لازم است.');
            }
        }

        if ($newStatus === 'closed' && !$isOverride) {
            $case->load(['receivables.payments']);
            if (!$case->isFullyCollected()) {
                $due = number_format($case->totalDue(), 2);
                $paid = number_format($case->totalCollected(), 2);
                throw new Exception("بستن پرونده فقط وقتی مجاز است که جمع وصول‌ها برابر مبلغ قابل دریافت باشد. قابل دریافت: {$due} — وصول‌شده: {$paid}");
            }
        }

        if (in_array($newStatus, ['won', 'lost', 'stopped'], true) && empty($reason) && !$isOverride) {
            throw new Exception('برای این تغییر وضعیت، درج دلیل الزامی است.');
        }

        DB::transaction(function () use ($case, $oldStatus, $newStatus, $reason, $isOverride, $extra) {
            $case->previous_status = $oldStatus;
            $case->current_status = $newStatus;

            if ($newStatus === 'financial_sent') {
                $net = (float) ($extra['proposal_amount'] ?? $case->proposal_amount);
                $vat = (float) ($extra['vat_percent'] ?? $case->vat_percent ?? 0);
                $case->proposal_amount = $net;
                $case->vat_percent = $vat;
                $case->proposal_gross = round($net * (1 + $vat / 100), 2);
            }

            if ($newStatus === 'receivables') {
                $this->ensureReceivable($case);
            }

            if ($newStatus === 'won') {
                $case->won_reason = $reason;
            } elseif ($newStatus === 'lost') {
                $case->lost_reason = $reason;
            } elseif ($newStatus === 'stopped') {
                $case->stopped_reason = $reason;
            } elseif ($newStatus === 'closed') {
                $case->closed_at = now();
            }

            $case->save();

            try {
                $ns = app(NotificationService::class);
                if ($case->assigned_expert_id) {
                    $ns->notify(
                        $case->assigned_expert_id,
                        'تغییر وضعیت پرونده',
                        $case->case_number.' → '.(CaseModel::statusLabels()[$newStatus] ?? $newStatus),
                        '/cases/'.$case->id
                    );
                }
            } catch (\Throwable $e) {
            }

            CaseStatusHistory::create([
                'case_id' => $case->id,
                'from_status' => $oldStatus,
                'to_status' => $newStatus,
                'user_id' => Auth::id(),
                'reason' => $reason,
                'is_override' => $isOverride,
            ]);

            try {
                app(\App\Services\AutomationService::class)->onCaseStatusChanged($case, $oldStatus, $newStatus);
            } catch (\Throwable $e) {
            }
        });
    }

    protected function ensureReceivable(CaseModel $case): void
    {
        $due = (float) ($case->proposal_gross ?? $case->computeGross());
        if ($due <= 0) {
            return;
        }
        $existing = $case->receivables()->whereIn('status', ['PENDING', 'PARTIALLY_PAID', 'OVERDUE', 'pending', 'partial'])->first();
        if ($existing) {
            if ((float) $existing->amount <= 0) {
                $existing->update(['amount' => $due, 'currency' => $case->currency ?? 'EUR']);
            }
            return;
        }
        Receivable::create([
            'case_id' => $case->id,
            'currency' => $case->currency ?? 'EUR',
            'amount' => $due,
            'paid_amount' => 0,
            'status' => 'PENDING',
            'due_date' => now()->addDays(30),
        ]);
    }

    public function resume(CaseModel $case): void
    {
        if ($case->current_status !== 'stopped') {
            throw new Exception('فقط پرونده‌های متوقف قابل ازسرگیری هستند.');
        }
        $target = $case->previous_status ?: 'received';
        $this->changeStatus($case, $target, 'ازسرگیری از وضعیت متوقف', false);
    }
}
