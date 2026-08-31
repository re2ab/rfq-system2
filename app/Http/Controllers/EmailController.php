<?php
namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\CaseModel;
use App\Models\Document;
use App\Models\DocumentRevision;
use App\Models\EmailAttachment;
use App\Models\EmailMessage;
use App\Services\EmailMatchingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class EmailController extends Controller
{
    public function index(Request $request)
    {
        $query = EmailMessage::with('case')->latest();
        if ($q = $request->get('q')) {
            $query->where(function ($w) use ($q) {
                $w->where('subject', 'like', "%{$q}%")
                    ->orWhere('from_address', 'like', "%{$q}%")
                    ->orWhere('to_address', 'like', "%{$q}%");
            });
        }
        $emails = $query->paginate(20)->withQueryString();
        return view('emails.index', compact('emails'));
    }

    public function compose(Request $request)
    {
        $cases = CaseModel::orderByDesc('id')->limit(100)->get(['id', 'case_number', 'title']);
        $caseId = $request->get('case_id');
        $caseAttachments = collect();
        $caseDocuments = collect();
        if ($caseId) {
            $case = CaseModel::with('attachments')->find($caseId);
            $caseAttachments = $case?->attachments ?? collect();
            // ارسال ایمیل از این‌جا به بعد تنها مسیر ارسال اسناد است (بخش «ارسال
            // این سند برای مشتری» از صفحه‌ی سند حذف شد) — پس فهرست اسناد پرونده
            // هم باید این‌جا قابل انتخاب/پیوست‌کردن باشد.
            $caseDocuments = $case
                ? Document::with('currentRevision')->where('case_id', $case->id)
                    ->whereHas('currentRevision', fn ($q) => $q->whereNotNull('file_path'))
                    ->latest()->get()
                : collect();
        }
        return view('emails.compose', compact('cases', 'caseId', 'caseAttachments', 'caseDocuments'));
    }

    public function caseAttachments(CaseModel $case)
    {
        $case->load('attachments');
        return response()->json(
            $case->attachments->map(fn ($a) => [
                'id' => $a->id,
                'file_name' => $a->file_name,
                'file_size' => $a->file_size,
            ])
        );
    }

    /** فهرست اسناد پرونده که فایل واقعی برای پیوست‌کردن به ایمیل دارند (AJAX، هم‌الگو با caseAttachments()). */
    public function caseDocuments(CaseModel $case)
    {
        $documents = Document::with('currentRevision')->where('case_id', $case->id)
            ->whereHas('currentRevision', fn ($q) => $q->whereNotNull('file_path'))
            ->latest()->get();

        return response()->json(
            $documents->map(fn ($d) => [
                'revision_id' => $d->currentRevision->id,
                'document_number' => $d->document_number,
                'type' => $d->documentType->name_fa ?? $d->type,
            ])
        );
    }

    public function store(Request $request, EmailMatchingService $matcher)
    {
        $data = $request->validate([
            'to_address' => 'required|email',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'case_id' => 'nullable|exists:cases,id',
            'attachment_ids' => 'nullable|array',
            'attachment_ids.*' => 'exists:attachments,id',
            'document_revision_ids' => 'nullable|array',
            'document_revision_ids.*' => 'exists:document_revisions,id',
            'files' => 'nullable|array',
            'files.*' => 'file|max:51200|mimes:pdf,doc,docx,jpg,jpeg,png,zip,xls,xlsx',
        ]);

        $case = isset($data['case_id'])
            ? CaseModel::find($data['case_id'])
            : $matcher->matchCase($data['subject'], $data['body']);

        $storedPaths = []; // [path, name, mime, size, source_id]

        // from case attachments
        foreach ($request->input('attachment_ids', []) as $aid) {
            $att = Attachment::find($aid);
            if (!$att) {
                continue;
            }
            if ($case && (int) $att->attachable_id === (int) $case->id) {
                // ok
            } elseif ($case) {
                continue; // only allow same case
            }
            $storedPaths[] = [
                $att->file_path,
                $att->file_name,
                $att->mime_type,
                $att->file_size,
                $att->id,
                false, // not newly uploaded
            ];
        }

        // از اسناد پرونده (فایل Word/Excel واقعی همان نسخه) — فایل‌های سند روی
        // دیسک local (خصوصی) هستند، پس مثل DocumentController::sendEmail() یک
        // کپی موقت روی دیسک public ساخته می‌شود تا Mail::attach() بتواند
        // مسیر فایل‌سیستمی واقعی‌اش را بخواند.
        foreach ($request->input('document_revision_ids', []) as $rid) {
            $revision = DocumentRevision::find($rid);
            if (!$revision || !$revision->file_path || !Storage::disk('local')->exists($revision->file_path)) {
                continue;
            }
            $document = $revision->document;
            if ($case && $document && (int) $document->case_id !== (int) $case->id) {
                continue; // فقط اسناد همین پرونده
            }
            $baseName = $revision->formatted_number ?: ($document->document_number ?? 'document');
            $ext = strtolower(pathinfo($revision->file_path, PATHINFO_EXTENSION)) ?: 'docx';
            $attachName = preg_replace('/[^A-Za-z0-9_.\-]/', '_', $baseName).'.'.$ext;
            $publicRel = 'email-attachments/'.date('Y/m').'/'.uniqid().'_'.$attachName;
            Storage::disk('public')->put($publicRel, Storage::disk('local')->get($revision->file_path));
            $mime = match ($ext) {
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                default => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            };
            $storedPaths[] = [$publicRel, $attachName, $mime, Storage::disk('public')->size($publicRel), null, true];
        }

        // new uploads
        foreach ($request->file('files', []) as $file) {
            $path = $file->store('email-attachments/'.date('Y/m'), 'public');
            $storedPaths[] = [
                $path,
                $file->getClientOriginalName(),
                $file->getMimeType(),
                $file->getSize(),
                null,
                true,
            ];
        }

        try {
            Mail::send([], [], function ($message) use ($data, $storedPaths) {
                $message->to($data['to_address'])
                    ->subject($data['subject'])
                    ->html(nl2br(e($data['body'])));
                foreach ($storedPaths as $row) {
                    [$path, $name] = $row;
                    $full = Storage::disk('public')->path($path);
                    if (is_file($full)) {
                        $message->attach($full, ['as' => $name]);
                    }
                }
            });
            $sent = true;
        } catch (\Throwable $e) {
            // fallback raw without fail
            try {
                Mail::raw($data['body'], function ($message) use ($data, $storedPaths) {
                    $message->to($data['to_address'])->subject($data['subject']);
                    foreach ($storedPaths as $row) {
                        [$path, $name] = $row;
                        $full = Storage::disk('public')->path($path);
                        if (is_file($full)) {
                            $message->attach($full, ['as' => $name]);
                        }
                    }
                });
                $sent = true;
            } catch (\Throwable $e2) {
                $sent = false;
            }
        }

        $email = EmailMessage::create([
            'case_id' => $case?->id,
            'direction' => 'outbound',
            'from_address' => config('mail.from.address', 'noreply@example.com'),
            'to_address' => $data['to_address'],
            'subject' => $data['subject'],
            'body' => $data['body'],
            'is_linked' => (bool) $case,
        ]);

        foreach ($storedPaths as $row) {
            [$path, $name, $mime, $size, $sourceId] = $row;
            EmailAttachment::create([
                'email_message_id' => $email->id,
                'file_name' => $name,
                'file_path' => $path,
                'mime_type' => $mime,
                'file_size' => $size ?? 0,
                'source_attachment_id' => $sourceId,
            ]);
        }

        if ($case) {
            try {
                $names = collect($storedPaths)->pluck(1)->join('، ');
                $case->activities()->create([
                    'user_id' => auth()->id(),
                    'type' => 'note',
                    'body' => 'ایمیل ارسال شد: '.$data['subject']
                        .($names ? ' — پیوست‌ها: '.$names : ''),
                ]);
            } catch (\Throwable $e) {
            }
        }

        $msg = $sent
            ? 'ایمیل ارسال و ثبت شد.'.(count($storedPaths) ? ' ('.count($storedPaths).' پیوست)' : '')
            : 'ایمیل در سیستم ثبت شد اما ارسال SMTP ناموفق بود.';

        try {
            if ($case && $case->assigned_expert_id) {
                app(\App\Services\NotificationService::class)->notify(
                    $case->assigned_expert_id,
                    'ایمیل ارسال/ثبت شد',
                    $data['subject'],
                    '/cases/'.$case->id
                );
            }
        } catch (\Throwable $e) {
        }

        return redirect()->route('emails.index')->with('success', $msg);
    }

    public function import(Request $request, EmailMatchingService $matcher)
    {
        $data = $request->validate([
            'from_address' => 'required|email',
            'to_address' => 'nullable|email',
            'subject' => 'nullable|string',
            'body' => 'nullable|string',
        ]);
        $email = $matcher->storeInbound($data);
        $email->load('case');
        try {
            if ($email->case_id && $email->case?->assigned_expert_id) {
                app(\App\Services\NotificationService::class)->notify(
                    $email->case->assigned_expert_id,
                    'ایمیل ورودی',
                    $email->subject,
                    '/cases/'.$email->case_id
                );
            }
        } catch (\Throwable $e) {
        }

        return back()->with('success', $email->is_linked
            ? 'ایمیل وارد و به پرونده '.$email->case?->case_number.' لینک شد.'
            : 'ایمیل وارد شد (بدون لینک خودکار به پرونده).');
    }

    public function link(Request $request, EmailMessage $email)
    {
        $data = $request->validate(['case_id' => 'required|exists:cases,id']);
        $email->update(['case_id' => $data['case_id'], 'is_linked' => true]);
        return back()->with('success', 'ایمیل به پرونده لینک شد.');
    }
}
