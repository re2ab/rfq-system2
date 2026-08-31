<?php
namespace App\Http\Controllers;

use App\Models\CaseModel;
use App\Models\Delivery;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function store(Request $request, CaseModel $case)
    {
        $data = $request->validate([
            'delivery_number' => 'nullable|string|max:100',
            'delivery_date' => 'nullable|date',
            'description' => 'nullable|string',
            'status' => 'nullable|string|max:50',
        ]);
        $data['status'] = $data['status'] ?? 'pending';
        $case->deliveries()->create($data);
        try {
            $ns = app(\App\Services\NotificationService::class);
            if ($case->assigned_expert_id) {
                $ns->notify($case->assigned_expert_id, 'تحویل جدید', $case->case_number, '/cases/'.$case->id);
            }
        } catch (\Throwable $e) {}
        return back()->with('success', 'تحویل ثبت شد.');
    }
}
