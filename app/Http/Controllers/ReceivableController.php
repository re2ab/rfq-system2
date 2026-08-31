<?php
namespace App\Http\Controllers;

use App\Models\CaseModel;
use App\Models\Receivable;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReceivableController extends Controller
{
    public function store(Request $request, CaseModel $case)
    {
        $data = $request->validate([
            'document_id' => 'nullable|exists:documents,id',
            'currency' => 'nullable|in:EUR,IRR',
            'amount' => 'required|numeric|min:0',
            'due_date' => 'nullable|date',
        ]);
        $case->receivables()->create([
            'document_id' => $data['document_id'] ?? null,
            'currency' => $data['currency'] ?? $case->currency ?? 'EUR',
            'amount' => $data['amount'],
            'paid_amount' => 0,
            'status' => 'PENDING',
            'due_date' => $data['due_date'] ?? null,
        ]);
        return back()->with('success', 'مطالبه ثبت شد.');
    }

    public function addPayment(Request $request, Receivable $receivable)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'method' => 'nullable|string|max:50',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($receivable, $data) {
            Payment::create([
                'receivable_id' => $receivable->id,
                'amount' => $data['amount'],
                'payment_date' => $data['payment_date'],
                'method' => $data['method'] ?? null,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => Auth::id(),
            ]);
            $paid = (float)$receivable->paid_amount + (float)$data['amount'];
            $status = $paid >= (float)$receivable->amount ? 'PAID' : 'PARTIALLY_PAID';
            if ($receivable->due_date && $receivable->due_date->isPast() && $status !== 'PAID') {
                $status = 'OVERDUE';
            }
            $receivable->update(['paid_amount' => $paid, 'status' => $status]);
        });

        try {
            $ns = app(\App\Services\NotificationService::class);
            $case = $receivable->case;
            if ($case && $case->assigned_expert_id) {
                $ns->notify($case->assigned_expert_id, 'پرداخت ثبت شد', $case->case_number.' — '.$data['amount'], '/cases/'.$case->id);
            }
        } catch (\Throwable $e) {}
        return back()->with('success', 'پرداخت ثبت شد.');
    }
}
