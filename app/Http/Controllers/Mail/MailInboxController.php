<?php

namespace App\Http\Controllers\Mail;

use App\Http\Controllers\Controller;
use App\Models\Mail\MailAccount;
use App\Models\Mail\MailFolder;
use App\Models\Mail\MailMessage;
use App\Services\Mail\MailAccountService;
use App\Services\Mail\MailSyncService;
use App\Services\Mail\MailMatchingService;
use App\Support\ModuleGate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MailInboxController extends Controller
{
    public function index(Request $request, MailAccountService $accounts, MailMatchingService $matching)
    {
        $this->gate();
        $user = auth()->user();
        $list = $accounts->accountsForUser($user);
        if ($list->isEmpty()) {
            return view('mail.inbox.empty', ['message' => 'هنوز اکانتی به شما اختصاص داده نشده. از ادمین بخواهید در «اکانت‌های ایمیل یکپارچه» دسترسی بدهد.']);
        }

        $accountId = (int) $request->get('account', $list->first()->id);
        $account = $list->firstWhere('id', $accountId) ?: $list->first();
        if (!$accounts->userCanAccess($user, $account, 'read')) {
            abort(403);
        }

        $folders = MailFolder::where('mail_account_id', $account->id)
            ->orderByRaw("CASE role WHEN 'inbox' THEN 0 WHEN 'sent' THEN 1 WHEN 'drafts' THEN 2 WHEN 'archive' THEN 3 WHEN 'spam' THEN 4 WHEN 'trash' THEN 5 ELSE 9 END")
            ->orderBy('name')
            ->get();

        $folderId = $request->get('folder');
        $folder = null;
        if ($folderId) {
            $folder = $folders->firstWhere('id', (int) $folderId);
        }
        if (!$folder) {
            $folder = $folders->firstWhere('role', 'inbox') ?: $folders->first();
        }

        $q = trim((string) $request->get('q', ''));
        $filter = $request->get('filter'); // unseen|flagged

        $messagesQuery = MailMessage::query()
            ->where('mail_account_id', $account->id);
        if ($folder) {
            $messagesQuery->where('mail_folder_id', $folder->id);
        }
        if ($filter === 'unseen') {
            $messagesQuery->where('is_seen', false);
        }
        if ($filter === 'flagged') {
            $messagesQuery->where('is_flagged', true);
        }
        if ($q !== '') {
            $like = '%'.$q.'%';
            $messagesQuery->where(function ($w) use ($like) {
                $w->where('subject', 'like', $like)
                    ->orWhere('from_address', 'like', $like)
                    ->orWhere('from_name', 'like', $like)
                    ->orWhere('body_text', 'like', $like)
                    ->orWhere('body_html', 'like', $like);
            });
        }

        $messages = $messagesQuery
            ->orderByDesc('date_sent')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $selectedId = (int) $request->get('msg', 0);
        $selected = null;
        if ($selectedId) {
            $selected = MailMessage::where('mail_account_id', $account->id)->where('id', $selectedId)->first();
            if ($selected && !$selected->is_seen) {
                $selected->is_seen = true;
                $selected->save();
                if ($folder) {
                    $folder->unseen_count = max(0, (int) $folder->unseen_count - 1);
                    $folder->save();
                }
            }
        }

        $thread = collect();
        if ($selected && $selected->thread_key) {
            $thread = MailMessage::where('mail_account_id', $account->id)
                ->where('thread_key', $selected->thread_key)
                ->orderBy('date_sent')
                ->orderBy('id')
                ->with('attachments')
                ->limit(50)
                ->get();
        } elseif ($selected) {
            $selected->load('attachments');
            $thread = collect([$selected]);
        }

        $suggestions = null;
        if ($selected) {
            $suggestions = $matching->suggest($selected);
        }

        return view('mail.inbox.index', [
            'accounts' => $list,
            'account' => $account,
            'folders' => $folders,
            'folder' => $folder,
            'messages' => $messages,
            'selected' => $selected,
            'thread' => $thread,
            'q' => $q,
            'filter' => $filter,
            'suggestions' => $suggestions,
        ]);
    }

    public function show(MailMessage $message, MailAccountService $accounts)
    {
        $this->gate();
        $user = auth()->user();
        $account = $message->account;
        if (!$account || !$accounts->userCanAccess($user, $account, 'read')) {
            abort(403);
        }
        if (!$message->is_seen) {
            $message->is_seen = true;
            $message->save();
        }

        return redirect()->route('mail.inbox', [
            'account' => $account->id,
            'folder' => $message->mail_folder_id,
            'msg' => $message->id,
        ]);
    }

    public function toggleFlag(MailMessage $message, MailAccountService $accounts)
    {
        $this->gate();
        if (!$accounts->userCanAccess(auth()->user(), $message->account, 'read')) {
            abort(403);
        }
        $message->is_flagged = !$message->is_flagged;
        $message->save();

        return back()->with('success', $message->is_flagged ? 'ستاره‌دار شد' : 'ستاره برداشته شد');
    }

    public function markSeen(Request $request, MailMessage $message, MailAccountService $accounts)
    {
        $this->gate();
        if (!$accounts->userCanAccess(auth()->user(), $message->account, 'read')) {
            abort(403);
        }
        $message->is_seen = $request->boolean('seen', true);
        $message->save();

        return back();
    }

    public function archive(MailMessage $message, MailAccountService $accounts)
    {
        $this->gate();
        $user = auth()->user();
        $account = $message->account;
        if (!$account || !$accounts->userCanAccess($user, $account, 'read')) {
            abort(403);
        }

        $archive = MailFolder::firstOrCreate(
            ['mail_account_id' => $account->id, 'remote_path' => 'RFQ.Archive'],
            ['name' => 'آرشیو', 'role' => 'archive', 'delimiter' => '.']
        );
        $message->mail_folder_id = $archive->id;
        $message->save();

        return redirect()->route('mail.inbox', ['account' => $account->id, 'folder' => $archive->id])
            ->with('success', 'به آرشیو منتقل شد (فقط داخل RFQ؛ روی سرور میل حذف نشد).');
    }

    public function sync(Request $request, MailAccountService $accounts, MailSyncService $sync)
    {
        $this->gate();
        $user = auth()->user();
        $accountId = (int) $request->get('account');
        $account = MailAccount::findOrFail($accountId);
        if (!$accounts->userCanAccess($user, $account, 'read')) {
            abort(403);
        }
        $r = $sync->syncAccount($account, true);

        return back()->with($r['ok'] ? 'success' : 'error', $r['ok']
            ? 'همگام‌سازی انجام شد'
            : ($r['message'] ?? 'خطا در همگام‌سازی'));
    }

    protected function gate(): void
    {
        if (!ModuleGate::enabled('unified_mail', true)) {
            abort(403, 'ماژول ایمیل یکپارچه غیرفعال است');
        }
        if (!auth()->check()) {
            abort(403);
        }
    }
}
