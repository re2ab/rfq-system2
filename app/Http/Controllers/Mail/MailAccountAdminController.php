<?php

namespace App\Http\Controllers\Mail;

use App\Http\Controllers\Controller;
use App\Models\Mail\MailAccount;
use App\Models\User;
use App\Services\Mail\MailAccountService;
use App\Services\Mail\MailSyncService;
use App\Support\ModuleGate;
use Illuminate\Http\Request;

/**
 * مدیریت اکانت‌های ایمیل یکپارچه — فقط Admin / settings.manage
 */
class MailAccountAdminController extends Controller
{
    public function index()
    {
        $this->authorizeAdmin();
        $accounts = MailAccount::with(['users'])->orderByDesc('is_shared')->orderBy('name')->get();

        return view('mail.accounts.index', compact('accounts'));
    }

    public function create()
    {
        $this->authorizeAdmin();
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('mail.accounts.form', [
            'account' => new MailAccount(['is_active' => true, 'smtp_port' => 587, 'imap_port' => 993, 'smtp_encryption' => 'tls', 'imap_encryption' => 'ssl']),
            'users' => $users,
            'selectedUserIds' => [],
        ]);
    }

    public function store(Request $request, MailAccountService $svc)
    {
        $this->authorizeAdmin();
        $data = $this->validated($request);
        $svc->create($data, auth()->user());

        return redirect()->route('mail.accounts.index')->with('success', 'اکانت ایمیل ایجاد شد.');
    }

    public function edit(MailAccount $account)
    {
        $this->authorizeAdmin();
        $users = User::orderBy('name')->get(['id', 'name', 'email']);
        $account->load('users');

        return view('mail.accounts.form', [
            'account' => $account,
            'users' => $users,
            'selectedUserIds' => $account->users->pluck('id')->all(),
        ]);
    }

    public function update(Request $request, MailAccount $account, MailAccountService $svc)
    {
        $this->authorizeAdmin();
        $data = $this->validated($request, false);
        $svc->update($account, $data, auth()->user());

        return redirect()->route('mail.accounts.index')->with('success', 'اکانت به‌روز شد.');
    }

    public function test(MailAccount $account, MailSyncService $sync)
    {
        $this->authorizeAdmin();
        $r = $sync->testConnection($account);

        return back()->with($r['ok'] ? 'success' : 'error', $r['message'] ?? '');
    }

    public function syncNow(MailAccount $account, MailSyncService $sync)
    {
        $this->authorizeAdmin();
        $r = $sync->syncAccount($account, true);
        if ($r['ok'] ?? false) {
            $s = $r['stats'] ?? [];
            $msg = 'همگام‌سازی انجام شد — فولدر: '.($s['folders'] ?? 0).' | پیام: '.($s['messages'] ?? 0);

            return back()->with('success', $msg);
        }

        return back()->with('error', $r['message'] ?? 'همگام‌سازی ناموفق');
    }

    public function destroy(MailAccount $account)
    {
        $this->authorizeAdmin();
        $account->delete();

        return redirect()->route('mail.accounts.index')->with('success', 'اکانت حذف شد.');
    }

    protected function validated(Request $request, bool $requirePassword = true): array
    {
        $rules = [
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190',
            'display_name' => 'nullable|string|max:120',
            'is_shared' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'smtp_host' => 'nullable|string|max:190',
            'smtp_port' => 'nullable|integer|min:1|max:65535',
            'smtp_encryption' => 'nullable|in:tls,ssl,none',
            'smtp_username' => 'nullable|string|max:190',
            'smtp_password' => ($requirePassword ? 'nullable' : 'nullable').'|string|max:500',
            'imap_host' => 'nullable|string|max:190',
            'imap_port' => 'nullable|integer|min:1|max:65535',
            'imap_encryption' => 'nullable|in:tls,ssl,none',
            'imap_username' => 'nullable|string|max:190',
            'imap_password' => 'nullable|string|max:500',
            'imap_sent_folder' => 'nullable|string|max:120',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer|exists:users,id',
            'default_user_id' => 'nullable|integer|exists:users,id',
        ];
        $data = $request->validate($rules);
        $data['is_shared'] = $request->boolean('is_shared');
        $data['is_active'] = $request->boolean('is_active', true);
        $data['user_ids'] = $data['user_ids'] ?? [];

        return $data;
    }

    protected function authorizeAdmin(): void
    {
        if (!ModuleGate::enabled('unified_mail', true)) {
            abort(403, 'ماژول ایمیل یکپارچه غیرفعال است');
        }
        $u = auth()->user();
        if (!$u) {
            abort(403);
        }
        // settings.manage یا نقش admin
        if (method_exists($u, 'can') && $u->can('settings.manage')) {
            return;
        }
        if (method_exists($u, 'hasRole') && $u->hasRole('admin')) {
            return;
        }
        abort(403);
    }
}
