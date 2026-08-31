<?php

namespace App\Http\Controllers\Mail;

use App\Http\Controllers\Controller;
use App\Models\CaseModel;
use App\Models\Mail\MailMessage;
use App\Services\Mail\MailAccountService;
use App\Services\Mail\MailMatchingService;
use App\Support\ModuleGate;
use Illuminate\Http\Request;

class MailLinkController extends Controller
{
    public function linkCase(Request $request, MailMessage $message, MailMatchingService $matching, MailAccountService $accounts)
    {
        $this->gate($message, $accounts);
        $data = $request->validate([
            'case_id' => 'required|integer|exists:cases,id',
        ]);
        $matching->linkToCase($message, (int) $data['case_id']);

        return back()->with('success', 'ایمیل به پرونده لینک شد.');
    }

    public function unlinkCase(MailMessage $message, MailMatchingService $matching, MailAccountService $accounts)
    {
        $this->gate($message, $accounts);
        $matching->unlinkCase($message);

        return back()->with('success', 'لینک پرونده برداشته شد.');
    }

    public function linkContact(Request $request, MailMessage $message, MailMatchingService $matching, MailAccountService $accounts)
    {
        $this->gate($message, $accounts);
        $data = $request->validate([
            'contact_id' => 'required|integer|exists:contacts,id',
        ]);
        $matching->linkContact($message, (int) $data['contact_id']);

        return back()->with('success', 'به مخاطب لینک شد.');
    }

    public function searchCases(Request $request)
    {
        $this->module();
        $q = trim((string) $request->get('q', ''));
        $query = CaseModel::query()->orderByDesc('id')->limit(20);
        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($w) use ($like, $q) {
                $w->where('case_number', 'like', $like)
                    ->orWhere('title', 'like', $like)
                    ->orWhere('customer_request_number', 'like', $like);
                if (ctype_digit($q)) {
                    $w->orWhere('id', (int) $q);
                }
            });
        }

        return response()->json(
            $query->get(['id', 'case_number', 'title', 'customer_request_number'])
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'label' => ($c->case_number ?: '#'.$c->id).($c->title ? ' — '.$c->title : ''),
                ])
        );
    }

    public function unmatched(MailMatchingService $matching)
    {
        $this->module();
        $messages = MailMessage::query()
            ->whereNull('case_id')
            ->with(['account', 'folder'])
            ->orderByDesc('date_sent')
            ->paginate(40);

        return view('mail.link.unmatched', [
            'messages' => $messages,
            'totalUnmatched' => $matching->unmatchedCount(),
        ]);
    }

    protected function gate(MailMessage $message, MailAccountService $accounts): void
    {
        $this->module();
        $user = auth()->user();
        if (!$message->account || !$accounts->userCanAccess($user, $message->account, 'read')) {
            abort(403);
        }
    }

    protected function module(): void
    {
        if (!ModuleGate::enabled('unified_mail', true)) {
            abort(403);
        }
        if (!auth()->check()) {
            abort(403);
        }
    }
}
