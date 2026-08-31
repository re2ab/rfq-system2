<?php

namespace App\Http\Controllers\Mail;

use App\Http\Controllers\Controller;
use App\Models\CaseModel;
use App\Models\Contact;
use App\Models\Document;
use App\Models\Mail\MailAccount;
use App\Models\Mail\MailDraft;
use App\Models\Mail\MailMessage;
use App\Models\Mail\MailUserSignature;
use App\Services\Mail\MailAccountService;
use App\Services\Mail\MailSendService;
use App\Support\ModuleGate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MailComposeController extends Controller
{
    public function create(Request $request, MailAccountService $accounts)
    {
        $this->gate();
        $user = auth()->user();
        $list = $accounts->accountsForUser($user);
        if ($list->isEmpty()) {
            return redirect()->route('mail.inbox')->with('error', 'اکانتی برای ارسال ندارید.');
        }

        $accountId = (int) $request->get('account', $list->first()->id);
        $account = $list->firstWhere('id', $accountId) ?: $list->first();
        if (!$accounts->userCanAccess($user, $account, 'send')) {
            abort(403);
        }

        $mode = $request->get('mode', 'new'); // new|reply|forward
        $prefill = [
            'to' => '',
            'cc' => '',
            'bcc' => '',
            'reply_to' => '',
            'subject' => '',
            'body_html' => '',
            'in_reply_to' => '',
            'references' => '',
            'case_id' => $request->get('case_id'),
            'contact_id' => $request->get('contact_id'),
        ];

        $source = null;
        if ($request->filled('reply_to_msg')) {
            $source = MailMessage::where('id', (int) $request->get('reply_to_msg'))
                ->where('mail_account_id', $account->id)
                ->first();
            if ($source) {
                $mode = $request->get('mode', 'reply');
                if ($mode === 'reply') {
                    $prefill['to'] = $source->from_address ?: '';
                    $prefill['subject'] = $this->replySubject($source->subject);
                    $prefill['in_reply_to'] = $source->message_id;
                    $prefill['references'] = trim(($source->references_header ?: '').' <'.($source->message_id ?: '').'>');
                    $prefill['case_id'] = $source->case_id;
                    $prefill['contact_id'] = $source->contact_id;
                } elseif ($mode === 'forward') {
                    $prefill['subject'] = $this->forwardSubject($source->subject);
                    $prefill['body_html'] = $this->quotedBlock($source);
                }
                $source = $source;
            }
        }

        // از صفحه مخاطب
        if ($request->filled('contact_id') && !$prefill['to']) {
            $contact = Contact::find((int) $request->get('contact_id'));
            if ($contact) {
                $prefill['contact_id'] = $contact->id;
                $prefill['to'] = $contact->email ?? '';
            }
        }

        // از صفحه پرونده — زمینه اختیاری
        $case = null;
        if ($request->filled('case_id')) {
            $case = CaseModel::with('contact')->find((int) $request->get('case_id'));
            if ($case) {
                $prefill['case_id'] = $case->id;
                if (!$prefill['to'] && $case->contact) {
                    $prefill['to'] = $case->contact->email ?? '';
                    $prefill['contact_id'] = $case->contact_id;
                }
                $useCaseMeta = $request->boolean('use_case_meta', true);
                if ($useCaseMeta && $mode === 'new' && !$prefill['subject']) {
                    $num = $case->case_number ?? $case->id;
                    $prefill['subject'] = 'پرونده '.$num.($case->title ? ' — '.$case->title : '');
                }
            }
        }

        $signature = MailUserSignature::defaultFor($user, 'fa');
        if ($signature && $signature->body_html && $mode === 'new') {
            $prefill['body_html'] = ($prefill['body_html'] ?: '<p><br></p>').'<br>'.$signature->body_html;
        } elseif ($signature && $signature->body_html && $mode === 'reply') {
            $prefill['body_html'] = '<p><br></p>'.$signature->body_html.'<br>'.$this->quotedBlock($source);
        }

        $draftId = $request->get('draft');
        $draft = null;
        if ($draftId) {
            $draft = MailDraft::where('user_id', $user->id)->where('id', (int) $draftId)->first();
            if ($draft) {
                $prefill = array_merge($prefill, [
                    'to' => $draft->to_address,
                    'cc' => $draft->cc,
                    'bcc' => $draft->bcc,
                    'reply_to' => $draft->reply_to,
                    'subject' => $draft->subject,
                    'body_html' => $draft->body_html,
                    'in_reply_to' => $draft->in_reply_to,
                    'references' => $draft->references_header,
                    'case_id' => $draft->case_id,
                    'contact_id' => $draft->contact_id,
                ]);
                $mode = $draft->mode ?: $mode;
                if ($draft->mail_account_id) {
                    $account = $list->firstWhere('id', $draft->mail_account_id) ?: $account;
                }
            }
        }

        $caseDocuments = collect();
        if (!empty($prefill['case_id'])) {
            $caseDocuments = Document::with('currentRevision')
                ->where('case_id', $prefill['case_id'])
                ->whereHas('currentRevision', fn ($q) => $q->whereNotNull('file_path'))
                ->latest()
                ->limit(50)
                ->get();
        }

        $drafts = MailDraft::where('user_id', $user->id)->orderByDesc('updated_at')->limit(20)->get();

        return view('mail.compose.form', [
            'accounts' => $list,
            'account' => $account,
            'prefill' => $prefill,
            'mode' => $mode,
            'source' => $source,
            'case' => $case,
            'caseDocuments' => $caseDocuments,
            'signature' => $signature,
            'draft' => $draft,
            'drafts' => $drafts,
        ]);
    }

    public function send(Request $request, MailAccountService $accounts, MailSendService $sender)
    {
        $this->gate();
        $user = auth()->user();
        $data = $request->validate([
            'mail_account_id' => 'required|integer|exists:mail_accounts,id',
            'to' => 'required|email',
            'cc' => 'nullable|string|max:2000',
            'bcc' => 'nullable|string|max:2000',
            'reply_to' => 'nullable|email',
            'subject' => 'required|string|max:500',
            'body_html' => 'nullable|string',
            'in_reply_to' => 'nullable|string|max:500',
            'references' => 'nullable|string|max:2000',
            'case_id' => 'nullable|integer|exists:cases,id',
            'contact_id' => 'nullable|integer|exists:contacts,id',
            'draft_id' => 'nullable|integer',
            'attachments.*' => 'nullable|file|max:20480',
            'document_ids' => 'nullable|array',
            'document_ids.*' => 'integer',
        ]);

        $account = MailAccount::findOrFail($data['mail_account_id']);
        if (!$accounts->userCanAccess($user, $account, 'send')) {
            abort(403);
        }

        $attachments = $this->collectAttachments($request, $data);

        $result = $sender->send($user, $account, [
            'to' => $data['to'],
            'cc' => $data['cc'] ?? '',
            'bcc' => $data['bcc'] ?? '',
            'reply_to' => $data['reply_to'] ?? null,
            'subject' => $data['subject'],
            'body_html' => $data['body_html'] ?? '',
            'in_reply_to' => $data['in_reply_to'] ?? null,
            'references' => $data['references'] ?? null,
            'attachments' => $attachments,
            'case_id' => $data['case_id'] ?? null,
            'contact_id' => $data['contact_id'] ?? null,
        ]);

        if (!($result['ok'] ?? false)) {
            return back()->withInput()->with('error', $result['message'] ?? 'ارسال ناموفق');
        }

        if (!empty($data['draft_id'])) {
            MailDraft::where('user_id', $user->id)->where('id', $data['draft_id'])->delete();
        }

        // اگر از پرونده ارسال شده، پیام محلی case_id دارد → تایم‌لاین در فاز D؛ فعلاً redirect با پیام
        $params = ['account' => $account->id];
        if (!empty($result['mail_message_id'])) {
            $params['msg'] = $result['mail_message_id'];
        }

        return redirect()->route('mail.inbox', $params)->with('success', 'ایمیل ارسال شد.');
    }

    public function saveDraft(Request $request)
    {
        $this->gate();
        $user = auth()->user();
        $data = $request->validate([
            'draft_id' => 'nullable|integer',
            'mail_account_id' => 'nullable|integer|exists:mail_accounts,id',
            'to' => 'nullable|string|max:190',
            'cc' => 'nullable|string|max:2000',
            'bcc' => 'nullable|string|max:2000',
            'reply_to' => 'nullable|string|max:190',
            'subject' => 'nullable|string|max:500',
            'body_html' => 'nullable|string',
            'in_reply_to' => 'nullable|string|max:500',
            'references' => 'nullable|string|max:2000',
            'case_id' => 'nullable|integer',
            'contact_id' => 'nullable|integer',
            'mode' => 'nullable|string|max:16',
        ]);

        $draft = null;
        if (!empty($data['draft_id'])) {
            $draft = MailDraft::where('user_id', $user->id)->where('id', $data['draft_id'])->first();
        }
        if (!$draft) {
            $draft = new MailDraft(['user_id' => $user->id]);
        }
        $draft->fill([
            'mail_account_id' => $data['mail_account_id'] ?? null,
            'to_address' => $data['to'] ?? null,
            'cc' => $data['cc'] ?? null,
            'bcc' => $data['bcc'] ?? null,
            'reply_to' => $data['reply_to'] ?? null,
            'subject' => $data['subject'] ?? null,
            'body_html' => $data['body_html'] ?? null,
            'in_reply_to' => $data['in_reply_to'] ?? null,
            'references_header' => $data['references'] ?? null,
            'case_id' => $data['case_id'] ?? null,
            'contact_id' => $data['contact_id'] ?? null,
            'mode' => $data['mode'] ?? 'new',
        ]);
        $draft->user_id = $user->id;
        $draft->save();

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'draft_id' => $draft->id]);
        }

        return redirect()->route('mail.compose', ['draft' => $draft->id])
            ->with('success', 'پیش‌نویس ذخیره شد.');
    }

    public function signatureForm()
    {
        $this->gate();
        $user = auth()->user();
        $fa = MailUserSignature::where('user_id', $user->id)->where('locale', 'fa')->first();
        $en = MailUserSignature::where('user_id', $user->id)->where('locale', 'en')->first();

        return view('mail.compose.signature', compact('fa', 'en'));
    }

    public function signatureSave(Request $request)
    {
        $this->gate();
        $user = auth()->user();
        $data = $request->validate([
            'body_html_fa' => 'nullable|string',
            'body_html_en' => 'nullable|string',
        ]);
        foreach (['fa' => 'body_html_fa', 'en' => 'body_html_en'] as $loc => $field) {
            $sig = MailUserSignature::firstOrNew([
                'user_id' => $user->id,
                'locale' => $loc,
                'name' => 'پیش‌فرض',
            ]);
            $sig->body_html = $data[$field] ?? '';
            $sig->is_default = true;
            $sig->save();
        }

        return back()->with('success', 'امضا ذخیره شد.');
    }

    protected function collectAttachments(Request $request, array $data): array
    {
        $out = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                if (!$file || !$file->isValid()) {
                    continue;
                }
                $path = $file->store('mail-attachments/'.date('Y/m'), 'local');
                $out[] = [
                    'full_path' => Storage::disk('local')->path($path),
                    'name' => $file->getClientOriginalName(),
                    'mime' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ];
            }
        }
        if (!empty($data['document_ids'])) {
            foreach ($data['document_ids'] as $docId) {
                $doc = Document::with('currentRevision')->find($docId);
                $rev = $doc?->currentRevision;
                if (!$rev || !$rev->file_path) {
                    continue;
                }
                $full = storage_path('app/'.$rev->file_path);
                if (!is_file($full)) {
                    $full = public_path('storage/'.$rev->file_path);
                }
                if (!is_file($full)) {
                    continue;
                }
                $out[] = [
                    'full_path' => $full,
                    'name' => $rev->original_name ?? basename($rev->file_path),
                    'mime' => $rev->mime ?? null,
                ];
            }
        }

        return $out;
    }

    protected function replySubject(?string $subject): string
    {
        $s = trim((string) $subject);
        if ($s === '') {
            return 'Re: ';
        }
        if (preg_match('/^re:\s*/i', $s)) {
            return $s;
        }

        return 'Re: '.$s;
    }

    protected function forwardSubject(?string $subject): string
    {
        $s = trim((string) $subject);
        if ($s === '') {
            return 'Fwd: ';
        }
        if (preg_match('/^(fwd|fw):\s*/i', $s)) {
            return $s;
        }

        return 'Fwd: '.$s;
    }

    protected function quotedBlock(?MailMessage $source): string
    {
        if (!$source) {
            return '';
        }
        $from = e($source->from_name ?: $source->from_address ?: '');
        $date = $source->date_sent ? $source->date_sent->format('Y-m-d H:i') : '';
        $body = $source->body_html ?: nl2br(e($source->body_text ?: ''));

        return '<br><blockquote style="border-right:3px solid #ccc;padding-right:12px;margin:12px 0;color:#444">'
            .'<div style="font-size:12px;color:#666">در '.$date.'، '.$from.' نوشت:</div>'
            .$body
            .'</blockquote>';
    }

    protected function gate(): void
    {
        if (!ModuleGate::enabled('unified_mail', true)) {
            abort(403);
        }
        if (!auth()->check()) {
            abort(403);
        }
    }
}
