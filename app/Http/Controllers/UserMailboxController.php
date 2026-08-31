<?php
namespace App\Http\Controllers;

use App\Models\CaseModel;
use App\Models\DocumentRevision;
use App\Models\EmailMessage;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PlaceholderLibrary;
use App\Services\TemplateRenderService;
use App\Services\UserMailboxService;
use App\Support\ModuleGate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UserMailboxController extends Controller
{
    public function editOwn(UserMailboxService $svc)
    {
        if (!ModuleGate::enabled('user_mailbox')) {
            abort(403, 'ماژول صندوق ایمیل شرکتی غیرفعال است');
        }
        $account = $svc->forUser(auth()->user());
        return view('mailbox.settings', compact('account'));
    }

    public function updateOwn(Request $request, UserMailboxService $svc)
    {
        if (!ModuleGate::enabled('user_mailbox')) {
            abort(403);
        }
        $data = $this->validated($request);
        if (!empty($data['email'])) {
            $data['smtp_username'] = $data['smtp_username'] ?? $data['email'];
            $data['imap_username'] = $data['email'];
        }
        // users may not change active flag off if admin policy — allow save credentials
        $svc->save(auth()->user(), $data);
        AuditLogger::log('user_mailbox_updated', 'user', auth()->id());
        return back()->with('success', 'تنظیمات ایمیل شرکتی ذخیره شد');
    }

    public function updateForUser(Request $request, User $user, UserMailboxService $svc)
    {
        $this->authorizeAdmin();
        $data = $this->validated($request);
        $data['is_active'] = $request->boolean('is_active', true);
        $svc->save($user, $data);
        AuditLogger::log('user_mailbox_admin_updated', 'user', $user->id);
        return back()->with('success', 'صندوق ایمیل کاربر ذخیره شد');
    }

    public function testSmtp(Request $request, UserMailboxService $svc)
    {
        $request->validate(['to' => 'required|email']);
        $r = $svc->send(auth()->user(), $request->input('to'), 'تست SMTP شرکتی', 'پیام تست از سامانه RFQ با ایمیل شرکتی شما.');
        return back()->with($r['ok'] ? 'success' : 'error', $r['ok'] ? 'ارسال موفق بود' : ($r['message'] ?? 'خطا'));
    }

    public function testImap(UserMailboxService $svc)
    {
        $acc = $svc->forUser(auth()->user());
        if (!$acc) {
            return back()->with('error', 'حسابی ذخیره نشده');
        }
        $r = $svc->testImap($acc);
        return back()->with($r['ok'] ? 'success' : 'error', $r['message'] ?? '');
    }

    public function inbox(Request $request, UserMailboxService $svc)
    {
        if (!ModuleGate::enabled('user_mailbox')) {
            abort(403);
        }
        $acc = $svc->forUser(auth()->user());
        // نکته‌ی مهم: آماده‌بودن IMAP باید با منطق ادغام‌شده‌ی سرویس (تنظیمات شرکت + یوزر/رمز
        // شخصی) سنجیده شود، نه با UserMailAccount::isConfiguredForReceive() (رفع‌شده در M18).
        $imapReady = $acc ? $svc->isReadyToReceive($acc) : false;
        $perPage = 20;
        $offset = max(0, (int) $request->get('offset', 0));
        $result = $imapReady ? $svc->fetchRecent($acc, $perPage, $offset) : ['items' => [], 'total' => 0];

        return view('mailbox.inbox', [
            'acc' => $acc,
            'messages' => $result['items'],
            'total' => $result['total'],
            'offset' => $offset,
            'perPage' => $perPage,
            'imapReady' => $imapReady,
        ]);
    }

    /** خواندن کامل یک نامه‌ی دریافتی (متن/HTML + پیوست‌ها) — طبق UID (پایدار، برخلاف شماره‌ی پیام که با تغییر صندوق جابه‌جا می‌شود). */
    public function show(int $uid, UserMailboxService $svc)
    {
        if (!ModuleGate::enabled('user_mailbox')) {
            abort(403);
        }
        $acc = $svc->forUser(auth()->user());
        if (!$acc) {
            abort(404);
        }
        $message = $svc->fetchMessage($acc, $uid);
        if (!$message) {
            abort(404, 'پیام یافت نشد — شاید حذف یا جابه‌جا شده باشد.');
        }
        // چون خودِ imap_fetchbody پیام را روی سرور Seen علامت می‌زند، شمارنده‌ی نخوانده‌های
        // کش‌شده (برای نشان‌دهنده‌ی عدد کنار آیکون صندوق در سایدبار) باید invalidate شود.
        Cache::forget('mailbox_unread_'.auth()->id());
        return view('mailbox.show', compact('message'));
    }

    /** دانلود مستقیم یک پیوست از یک نامه‌ی دریافتی (بدون ذخیره‌ی موقت روی دیسک — استریم مستقیم از IMAP). */
    public function downloadAttachment(int $uid, string $part, UserMailboxService $svc)
    {
        if (!ModuleGate::enabled('user_mailbox')) {
            abort(403);
        }
        $acc = $svc->forUser(auth()->user());
        if (!$acc) {
            abort(404);
        }
        $file = $svc->fetchAttachmentPart($acc, $uid, $part);
        if (!$file) {
            abort(404);
        }
        return response($file['bytes'], 200, [
            'Content-Type' => $file['mime'] ?: 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.addslashes($file['filename']).'"',
            'Content-Length' => (string) strlen($file['bytes']),
        ]);
    }

    /** فهرست ایمیل‌های ارسالی از همین صندوق شخصی (ثبت محلی — مستقل از پوشه‌ی Sent واقعی سرور که جدا از این سیستم قابل‌مشاهده است). */
    public function sent()
    {
        if (!ModuleGate::enabled('user_mailbox')) {
            abort(403);
        }
        $emails = EmailMessage::with('attachments')
            ->where('user_id', auth()->id())
            ->where('direction', 'outbound')
            ->latest()
            ->paginate(20);
        return view('mailbox.sent', compact('emails'));
    }

    public function composeForm(Request $request, UserMailboxService $svc)
    {
        if (!ModuleGate::enabled('user_mailbox')) {
            abort(403);
        }

        $mode = null; // reply|forward
        $prefill = ['to' => '', 'cc' => '', 'subject' => '', 'body' => '', 'in_reply_to' => '', 'references' => '', 'mark_answered_uid' => null];
        $sourceMessage = null;

        $acc = $svc->forUser(auth()->user());
        $replyUid = $request->integer('reply_uid') ?: null;
        $forwardUid = $request->integer('forward_uid') ?: null;

        if ($acc && ($replyUid || $forwardUid)) {
            $uid = $replyUid ?: $forwardUid;
            $sourceMessage = $svc->fetchMessage($acc, $uid);
            if ($sourceMessage) {
                $quoted = $this->buildQuotedBody($sourceMessage);
                if ($replyUid) {
                    $mode = 'reply';
                    $prefill['to'] = $this->extractEmailAddress($sourceMessage['from']);
                    $prefill['subject'] = $this->prefixSubject($sourceMessage['subject'], 'Re:');
                    $prefill['body'] = "\n\n".$quoted;
                    $prefill['in_reply_to'] = $sourceMessage['message_id'];
                    $prefill['references'] = trim(($sourceMessage['references'] ?? '').' '.$sourceMessage['message_id']);
                    $prefill['mark_answered_uid'] = $uid;
                } else {
                    $mode = 'forward';
                    $prefill['subject'] = $this->prefixSubject($sourceMessage['subject'], 'Fwd:');
                    $prefill['body'] = "\n\n".$quoted;
                }
            }
        }

        $templates = DB::table('templates')->where('type', 'email')->orderByDesc('is_default')->orderBy('name')->get();
        $cases = CaseModel::orderByDesc('id')->limit(200)->get(['id', 'case_number', 'title']);

        return view('mailbox.compose', compact('mode', 'prefill', 'sourceMessage', 'templates', 'cases'));
    }

    /** AJAX: رندر یک قالب ایمیل سیستم (header+body+footer) با جایگزینی جای‌نگه‌دارها — هم‌الگو با PlaceholderLibrary/TemplateRenderService که برای اسناد هم استفاده می‌شود. */
    public function templatePreview(Request $request, $id)
    {
        $template = DB::table('templates')->where('id', $id)->where('type', 'email')->first();
        if (!$template) {
            return response()->json(['ok' => false, 'message' => 'قالب یافت نشد'], 404);
        }
        $case = null;
        if ($caseId = $request->get('case_id')) {
            $case = CaseModel::find($caseId);
        }
        $vars = PlaceholderLibrary::varsFromCase($case);
        $renderer = app(TemplateRenderService::class);
        $parts = array_filter([
            $renderer->render($template->header ?? '', $vars),
            $renderer->render($template->body ?? '', $vars),
            $renderer->render($template->footer ?? '', $vars),
        ], fn ($p) => trim((string) $p) !== '');

        return response()->json(['ok' => true, 'body' => implode("\n\n", $parts)]);
    }

    public function composeSend(Request $request, UserMailboxService $svc)
    {
        if (!ModuleGate::enabled('user_mailbox')) {
            abort(403);
        }
        $data = $request->validate([
            'to' => 'required|email',
            'cc' => 'nullable|string|max:500',
            'subject' => 'required|string|max:300',
            'body' => 'required|string|max:50000',
            'in_reply_to' => 'nullable|string|max:500',
            'references' => 'nullable|string|max:2000',
            'mark_answered_uid' => 'nullable|integer',
            'document_revision_ids' => 'nullable|array',
            'document_revision_ids.*' => 'exists:document_revisions,id',
            'files' => 'nullable|array',
            'files.*' => 'file|max:51200|mimes:pdf,doc,docx,jpg,jpeg,png,zip,xls,xlsx',
        ]);

        $attachments = [];

        // از اسناد ایجادشده روی سیستم — فایل واقعی Word/Excel همان نسخه. فایل روی دیسک
        // local (خصوصی) است؛ چون Mail::attach() به مسیر فایل‌سیستمی واقعی نیاز دارد، یک
        // کپی موقت روی دیسک public ساخته می‌شود (هم‌الگو با EmailController::store()).
        foreach ($request->input('document_revision_ids', []) as $rid) {
            $revision = DocumentRevision::find($rid);
            if (!$revision || !$revision->file_path || !Storage::disk('local')->exists($revision->file_path)) {
                continue;
            }
            $document = $revision->document;
            $baseName = $revision->formatted_number ?: ($document->document_number ?? 'document');
            $ext = strtolower(pathinfo($revision->file_path, PATHINFO_EXTENSION)) ?: 'docx';
            $attachName = preg_replace('/[^A-Za-z0-9_.\-]/', '_', $baseName).'.'.$ext;
            $publicRel = 'mailbox-attachments/'.date('Y/m').'/'.uniqid().'_'.$attachName;
            Storage::disk('public')->put($publicRel, Storage::disk('local')->get($revision->file_path));
            $mime = match ($ext) {
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                default => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            };
            $attachments[] = [
                'full_path' => Storage::disk('public')->path($publicRel),
                'stored_path' => $publicRel,
                'name' => $attachName,
                'mime' => $mime,
                'size' => Storage::disk('public')->size($publicRel),
            ];
        }

        // آپلود فایل از خارج از سیستم
        foreach ($request->file('files', []) as $file) {
            $path = $file->store('mailbox-attachments/'.date('Y/m'), 'public');
            $attachments[] = [
                'full_path' => Storage::disk('public')->path($path),
                'stored_path' => $path,
                'name' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ];
        }

        $r = $svc->sendCompose(auth()->user(), [
            'to' => $data['to'],
            'cc' => $data['cc'] ?? null,
            'subject' => $data['subject'],
            'body' => $data['body'],
            'in_reply_to' => $data['in_reply_to'] ?? null,
            'references' => $data['references'] ?? null,
            'mark_answered_uid' => $data['mark_answered_uid'] ?? null,
            'attachments' => $attachments,
        ]);

        return $r['ok']
            ? redirect()->route('mailbox.sent')->with('success', $r['message'] ?? 'ایمیل ارسال شد')
            : back()->withInput()->with('error', $r['message'] ?? 'خطا در ارسال');
    }

    protected function prefixSubject(string $subject, string $prefix): string
    {
        if (stripos(trim($subject), $prefix) === 0) {
            return $subject;
        }
        return $prefix.' '.$subject;
    }

    protected function extractEmailAddress(string $fromHeader): string
    {
        if (preg_match('/<([^>]+)>/', $fromHeader, $m)) {
            return trim($m[1]);
        }
        return trim($fromHeader);
    }

    protected function buildQuotedBody(array $msg): string
    {
        $text = $msg['plain'] ?? null;
        if (!$text && !empty($msg['html'])) {
            $text = trim(strip_tags(preg_replace('/<(br|p|div|tr)\b[^>]*>/i', "\n", $msg['html'])));
        }
        $text = (string) ($text ?? '');
        $lines = preg_split('/\r\n|\r|\n/', trim($text));
        $quoted = array_map(fn ($l) => '> '.$l, $lines);
        $header = sprintf('در تاریخ %s، %s نوشت:', $msg['date'] ?? '', $msg['from'] ?? '');
        return $header."\n".implode("\n", $quoted);
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'email' => 'nullable|email|max:190',
            'display_name' => 'nullable|string|max:120',
            'smtp_host' => 'nullable|string|max:190',
            'smtp_port' => 'nullable|integer|min:1|max:65535',
            'smtp_encryption' => 'nullable|in:tls,ssl,none',
            'smtp_username' => 'nullable|string|max:190',
            'smtp_password' => 'nullable|string|max:500',
            'imap_host' => 'nullable|string|max:190',
            'imap_port' => 'nullable|integer|min:1|max:65535',
            'imap_encryption' => 'nullable|in:tls,ssl,none',
            'imap_username' => 'nullable|string|max:190',
            'imap_password' => 'nullable|string|max:500',
            'pop3_host' => 'nullable|string|max:190',
            'pop3_port' => 'nullable|integer|min:1|max:65535',
            'pop3_encryption' => 'nullable|in:tls,ssl,none',
            'pop3_username' => 'nullable|string|max:190',
            'pop3_password' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);
    }

    protected function authorizeAdmin(): void
    {
        $u = auth()->user();
        if (!$u || (method_exists($u, 'hasRole') && !$u->hasRole('admin'))) {
            abort(403);
        }
    }
}
