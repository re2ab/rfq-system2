<?php

namespace App\Http\Controllers;

use App\Models\CaseModel;
use App\Services\CaseStatusService;
use Illuminate\Http\Request;

class KanbanController extends Controller
{
    public function __construct(protected CaseStatusService $statusService) {}

    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $tagId = $request->get('tag_id');

        $activeStatuses = [
            'received', 'waiting_info', 'waiting_offer', 'waiting_technical',
            'technical_sent', 'waiting_financial', 'financial_sent',
            'won', 'purchasing', 'receivables', 'stopped',
        ];

        $base = CaseModel::with(['customer', 'expert', 'assignees', 'tags']);

        if ($q !== '') {
            $base->where(function ($w) use ($q) {
                $w->where('case_number', 'like', "%{$q}%")
                    ->orWhere('title', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$q}%"));
            });
        }
        if ($tagId) {
            $base->whereHas('tags', fn ($t) => $t->where('tags.id', $tagId));
        }

        $columns = [];
        foreach ($activeStatuses as $status) {
            $columns[$status] = (clone $base)
                ->where('current_status', $status)
                ->latest()
                ->limit(80)
                ->get();
        }

        $tags = \App\Models\Tag::orderBy('name')->get();

        return view('kanban.index', [
            'columns' => $columns,
            'statusLabels' => CaseModel::statusLabels(),
            'q' => $q,
            'tagId' => $tagId,
            'tags' => $tags,
        ]);
    }

    public function move(Request $request, CaseModel $case)
    {
        $data = $request->validate([
            'status' => 'required|string',
            'reason' => 'nullable|string|max:2000',
            'is_override' => 'sometimes|boolean',
        ]);

        try {
            $this->statusService->changeStatus(
                $case,
                $data['status'],
                $data['reason'] ?? null,
                (bool) ($data['is_override'] ?? false)
            );

            return response()->json(['success' => true, 'message' => 'وضعیت به‌روز شد.']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
