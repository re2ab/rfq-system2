<?php
namespace App\Services;

use App\Support\ModuleGate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountingExportService
{
    public function paymentsCsv(): StreamedResponse
    {
        if (!ModuleGate::enabled('accounting_export')) {
            abort(403, 'ماژول خروجی حسابداری غیرفعال است');
        }
        $filename = 'accounting-payments-'.date('Ymd-His').'.csv';
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            // BOM for Excel
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, ['date', 'case_number', 'amount', 'currency', 'exchange_rate', 'amount_irr', 'description']);
            if (Schema::hasTable('payments')) {
                $rows = DB::table('payments')
                    ->leftJoin('cases', 'cases.id', '=', 'payments.case_id')
                    ->select('payments.*', 'cases.case_number')
                    ->orderByDesc('payments.id')
                    ->limit(5000)
                    ->get();
                foreach ($rows as $r) {
                    $rate = (float) ($r->exchange_rate ?? 1);
                    $amount = (float) ($r->amount ?? $r->amount_base ?? 0);
                    $curr = $r->payment_currency ?? $r->currency ?? 'IRR';
                    $irr = strtoupper($curr) === 'IRR' ? $amount : $amount * $rate;
                    fputcsv($out, [
                        $r->payment_date ?? $r->created_at ?? '',
                        $r->case_number ?? $r->case_id,
                        $amount,
                        $curr,
                        $rate,
                        round($irr, 0),
                        $r->note ?? $r->description ?? '',
                    ]);
                }
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
