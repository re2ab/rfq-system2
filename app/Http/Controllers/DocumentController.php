<?php
namespace App\Http\Controllers;

use App\Models\CaseModel;
use App\Models\Document;
use App\Models\DocumentRevision;
use App\Models\Template;
use App\Services\NumberGeneratorService;
use App\Services\VatCalculatorService;
use App\Services\PlaceholderLibrary;
use App\Services\TemplateRenderService;
use App\Services\AuditLogger;
use App\Services\Documents\DocumentLineService;
use App\Services\Documents\DocumentPublishService;
use App\Services\Documents\PdfConversionService;
use App\Models\EmailMessage;
use App\Models\EmailAttachment;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;

class DocumentController extends Controller
{
    public function __construct(
        protected NumberGeneratorService $numbers,
        protected VatCalculatorService $vat,
        protected DocumentPublishService $publisher
    ) {}

    public function index(Request $request)
    {
        // M5: قبلاً فیلتر نوع سند روی آرایه‌ی سخت‌کدشده‌ی سه‌تایی در ویو بود —
        // یعنی افزودن نوع سند آینده (Purchase Order، نامه‌ی اداری، …) که کاربر
        // به‌عنوان نیاز آتی مطرح کرد، بدون تغییر کد این صفحه ممکن نبود. حالا از
        // document_types می‌خواند؛ ستون documents.type همچنان همان key است، پس
        // فیلتر زیر بدون تغییر کار می‌کند.
        // فهرست اسناد باید تاریخچه‌ی نسخه‌ها (Revision) هر سند را هم نشان بدهد،
        // نه فقط سند/نسخه‌ی فعلی — پس همه‌ی نسخه‌ها هم eager-load می‌شوند.
        $query = Document::with([
            'case', 'lines', 'documentType', 'currentRevision',
            'revisions' => fn ($q) => $q->orderByDesc('revision_number'),
        ])->latest();

        // M24 (درخواست کاربر): اسناد/رویژن‌های پیش‌نویس در فهرست نمایش داده
        // نمی‌شوند — فقط رویژن‌هایی که حداقل یک‌بار منتشر شده‌اند (is_locked،
        // که هم رویژن فعلاً‌منتشرشده هم رویژن‌های Superseded‌ی قبلی را شامل
        // می‌شود، نه فقط status===published؛ Superseded چون واقعاً شماره‌ی
        // رسمی گرفته و احتمالاً برای مشتری فرستاده شده، یک رکورد واقعی است و
        // مخفی‌شدنش از فهرست، گم‌شدن یک سند رسمی به‌نظر می‌رسد). سندی که هنوز
        // هیچ‌وقت منتشر نشده، اصلاً در فهرست دیده نمی‌شود — فقط از صفحه‌ی
        // مشخصات خودش (لینک‌شده از پرونده/جست‌وجو) قابل‌دسترسی است؛ نمایش
        // ردیف‌های تک‌تکِ رویژن هم در ویو یک‌بار دیگر همین شرط را چک می‌کند
        // (برای سندی که هم رویژن منتشرشده هم Draft دارد، فقط Draftش پنهان شود).
        $query->whereHas('revisions', fn ($q) => $q->where('is_locked', true));

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($q = $request->get('q')) {
            $query->where(function ($qb) use ($q) {
                $qb->where('document_number', 'like', "%{$q}%")
                    ->orWhere('number_base', 'like', "%{$q}%")
                    ->orWhere('title', 'like', "%{$q}%")
                    ->orWhereHas('case', function ($c) use ($q) {
                        $c->where('case_number', 'like', "%{$q}%")
                          ->orWhere('title', 'like', "%{$q}%");
                    });
            });
        }
        // فیلترهای «شماره پرونده» و «ارز» از رابط کاربری حذف شدند (درخواست کاربر) —
        // چون خودِ شماره‌ی سند/پرونده از طریق جستجوی q هم قابل پیدا کردن است و این
        // دو فیلتر عملاً کم‌استفاده بودند؛ منطق سمت سرور هم حذف شد تا مرده نماند.

        $documents = $query->paginate(20)->withQueryString();
        $documentTypes = \App\Models\DocumentType::active();
        $pdfAvailable = app(PdfConversionService::class)->active()->isAvailable();

        return view('documents.index', compact('documents', 'documentTypes', 'pdfAvailable'));
    }

    public function create(Request $request)
    {
        $cases = CaseModel::orderByDesc('id')->limit(100)->get(['id','case_number','title','currency','incoterm']);
        $caseId = $request->get('case_id');
        return view('documents.create', compact('cases', 'caseId'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'case_id' => 'required|exists:cases,id',
            'type' => 'required|in:technical_proposal,financial_proposal,invoice',
            'title' => 'nullable|string|max:255',
            'currency' => 'nullable|in:EUR,IRR',
            'incoterm' => 'nullable|string|max:20',
            'net_amount' => 'nullable|numeric|min:0',
            'vat_percent' => 'nullable|numeric|min:0|max:100',
            'content' => 'nullable|string',
            'lines' => 'nullable|array',
            'lines.*.description' => 'nullable|string|max:500',
            'lines.*.unit' => 'nullable|string|max:30',
            'lines.*.quantity' => 'nullable|numeric|min:0',
            'lines.*.unit_price' => 'nullable|numeric|min:0',
        ]);

        $case = CaseModel::findOrFail($data['case_id']);
        $net = (float)($data['net_amount'] ?? 0);
        $incoterm = $data['incoterm'] ?? $case->incoterm;
        $calc = $this->vat->calculate($net, $incoterm);

        $typeKey = match($data['type']) {
            'technical_proposal' => 'technical_proposal',
            'financial_proposal' => 'financial_proposal',
            'invoice' => 'invoice',
        };

        $documentTypeId = \App\Models\DocumentType::where('key', $data['type'])->value('id');
        $number = $this->numbers->next($typeKey);

        $doc = Document::create([
            'case_id' => $case->id,
            'type' => $data['type'],
            'document_type_id' => $documentTypeId,
            'document_number' => $number,
            // مسیر قدیمی (محتوای HTML) عمداً هنوز شماره را زودهنگام صادر می‌کند —
            // این رفتار قبلی سیستم است و اینجا تغییر داده نشده. مسیر جدید ساخت سند
            // از قالب واقعی Word/Excel (بخش M4) شماره را طبق معماری فقط در Publish
            // صادر می‌کند. number_base هم برای سازگاری با جدول جدید همینجا پر می‌شود.
            'number_base' => $number,
            'status' => Document::STATUS_DRAFT,
            'title' => $data['title'] ?? null,
            'currency' => $data['currency'] ?? $case->currency ?? 'EUR',
            'incoterm' => $incoterm,
            'vat_percent' => $calc['vat_percent'],
            'net_amount' => $calc['net_amount'],
            'vat_amount' => $calc['vat_amount'],
            'gross_amount' => $calc['gross_amount'],
        ]);

        $this->syncLines($doc, $request->input('lines', []), $data);

        $content = $data['content'] ?? '';
        $request = request();
        if ($content === '' || $request->boolean('use_default_template') || $request->filled('template_id')) {
            $tpl = null;
            if ($request->filled('template_id')) {
                $tpl = DB::table('templates')->where('id', $request->get('template_id'))->first();
            }
            if (!$tpl) {
                $tpl = DB::table('templates')
                    ->where('type', $data['type'])
                    ->where('is_default', true)
                    ->orderByDesc('id')
                    ->first();
            }
            if (!$tpl) {
                $tpl = DB::table('templates')->where('type', $data['type'])->orderByDesc('id')->first();
            }
            if ($tpl) {
                $renderer = app(TemplateRenderService::class);
                $vars = PlaceholderLibrary::varsFromCase($case, $doc);
                $body = $renderer->render((string)($tpl->body ?? ''), $vars);
                $header = $renderer->render((string)($tpl->header ?? ''), $vars);
                $footer = $renderer->render((string)($tpl->footer ?? ''), $vars);
                $content = trim($header."\n\n".$body."\n\n".$footer);
            }
        }

        $rev = DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => 1,
            'content' => $content,
            'created_by' => Auth::id(),
            'status' => 'draft',
        ]);
        $doc->update(['current_revision_id' => $rev->id]);

        return redirect()->route('documents.show', $doc)->with('success', 'سند ایجاد شد.');
    }

    public function show(Document $document, Request $request)
    {
        $document->load(['case', 'revisions.creator', 'lines', 'currentRevision', 'documentType']);
        $pdfAvailable = app(\App\Services\Documents\PdfConversionService::class)->active()->isAvailable();

        // M27 (درخواست کاربر): بخش بالای صفحه («فایل سند») دیگر همیشه قفلِ
        // currentRevision نیست — از جدولِ «تاریخچه‌ی نسخه‌ها» هر Revisionی
        // (نه فقط آخرین Draft ساخته‌شده) قابل «انتخاب» است و همان بالا با
        // تمام دکمه‌هایش (دانلود/ویرایش آنلاین/انتشار و صدور شماره/آپلود
        // جایگزین) روی همان Revisionِ انتخاب‌شده عمل می‌کند. انتخاب فقط یک
        // querystring («?revision=id») است، نه تغییری در دیتابیس — پس
        // current_revision_id هم‌چنان معنای قبلی‌اش («آخرین Draft
        // ساخته‌شده») را برای بقیه‌ی سیستم (مثلاً createNextDraft) حفظ
        // می‌کند. اگر id نامعتبر باشد یا به سند دیگری تعلق داشته باشد
        // (firstWhere روی مجموعه‌ی از‌قبل محدود به همین سند)، بی‌سروصدا به
        // currentRevision برمی‌گردیم.
        $selectedRevision = null;
        if ($request->filled('revision')) {
            $selectedRevision = $document->revisions->firstWhere('id', (int) $request->query('revision'));
        }
        $selectedRevision = $selectedRevision ?: $document->currentRevision;

        // M11: دکمه‌ی «ویرایش آنلاین» فقط وقتی نشان داده شود که هم سرویس ONLYOFFICE
        // پیکربندی شده هم فایل نسخه‌ی انتخاب‌شده از نوعی است که آن سرویس پشتیبانی
        // می‌کند (docx/xlsx) — وگرنه کلیک روی آن همیشه با خطا مواجه می‌شد.
        $onlyOfficeAvailable = false;
        if ($selectedRevision && $selectedRevision->file_path) {
            $ext = strtolower(pathinfo($selectedRevision->file_path, PATHINFO_EXTENSION));
            $onlyOfficeAvailable = in_array($ext, ['docx', 'xlsx'], true)
                && app(\App\Services\OnlyOffice\OnlyOfficeConfigService::class)->isConfigured();
        }

        return view('documents.show', compact('document', 'pdfAvailable', 'onlyOfficeAvailable', 'selectedRevision'));
    }

    public function print(Document $document)
    {
        $document->load(['case', 'revisions']);
        return response()->view('documents.print', compact('document'));
    }

    public function approve(\App\Models\DocumentRevision $revision)
    {
        $revision->update(['status' => 'approved', 'is_locked' => true]);
        return back()->with('success', 'نسخه تأیید و قفل شد.');
    }

    /**
     * M4: انتشار یک Revision از مسیر جدید «سند از قالب واقعی» — تنها جایی که
     * شماره‌ی رسمی سند صادر می‌شود (DocumentPublishService::publish()، شامل
     * قفل ردیف سند + Rule 11 روی Revisionِ منتشرشده‌ی قبلی).
     */
    public function publish(DocumentRevision $revision)
    {
        try {
            $this->publisher->publish($revision, auth()->id());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'سند منتشر و شماره‌گذاری شد.');
    }

    /**
     * M4: دانلود فایل واقعی Word/Excel تولیدشده برای یک Revision (مسیر قالب
     * واقعی) — نام دانلودی از formatted_number (اگر منتشر شده) یا شماره‌ی
     * موقت سند گرفته می‌شود.
     */
    public function downloadRevision(DocumentRevision $revision)
    {
        if (!$revision->file_path || !Storage::disk('local')->exists($revision->file_path)) {
            abort(404, 'فایلی برای این نسخه تولید نشده است.');
        }

        $ext = pathinfo($revision->file_path, PATHINFO_EXTENSION) ?: 'docx';
        $baseName = $revision->formatted_number ?: ($revision->document->document_number ?? 'document');
        $downloadName = preg_replace('/[^A-Za-z0-9_.\-]/', '_', $baseName).'.'.$ext;

        return Storage::disk('local')->download($revision->file_path, $downloadName);
    }

    /**
     * M6 — Option C: جایگزینی فایل یک Draft با نسخه‌ای که کاربر با Word/Excel
     * واقعی روی کامپیوتر خودش ویرایش کرده. طبق معماری، همان Draft جایگزین
     * می‌شود — نه Revisionِ جدید. فقط روی Draftِ قفل‌نشده مجاز است.
     */
    public function uploadEdit(Request $request, DocumentRevision $revision)
    {
        if (!$revision->isEditable()) {
            return back()->with('error', 'این نسخه قفل‌شده یا منتشرشده است — یک نسخه‌ی جدید بسازید.');
        }
        if (!$revision->file_path) {
            return back()->with('error', 'این نسخه فایلی برای جایگزینی ندارد.');
        }

        $data = $request->validate(['file' => 'required|file']);

        $originalExt = strtolower(pathinfo($revision->file_path, PATHINFO_EXTENSION));
        $check = app(\App\Services\Documents\TemplateService::class)->validateUpload($data['file']);
        if (!$check['ok']) {
            return back()->with('error', $check['message']);
        }
        if ($check['file_type'] !== $originalExt) {
            return back()->with('error', 'فرمت فایل آپلودی باید همان '.strtoupper($originalExt).' باشد.');
        }

        Storage::disk('local')->putFileAs(dirname($revision->file_path), $data['file'], basename($revision->file_path));

        // M11: محتوا از بیرون از ONLYOFFICE عوض شد — کلید cache قبلی دیگر معتبر
        // نیست، وگرنه دفعه‌ی بعد که «ویرایش آنلاین» باز شود، Document Server
        // ممکن است نسخه‌ی قدیمی/کش‌شده را به‌جای همین فایل تازه نشان دهد.
        if ($revision->editor_key) {
            $revision->update(['editor_key' => null]);
        }

        AuditLogger::log('document_revision_file_replaced', 'document', $revision->document_id, [
            'revision_number' => $revision->revision_number,
        ]);

        return back()->with('success', 'فایل با نسخه‌ی ویرایش‌شده جایگزین شد.');
    }

    /**
     * M6: Draft جدید برای ادامه‌ی ویرایش سندی که نسخه‌ی فعلی‌اش منتشر/قفل شده.
     * فایل منتشرشده به‌عنوان نقطه‌ی شروع کپی می‌شود (نه رندر تازه از قالب) تا
     * ویرایش‌های دستیِ قبلیِ کاربر در Word/Excel از دست نروند.
     */
    public function newDraft(Request $request, Document $document)
    {
        $document->loadMissing('currentRevision');
        $last = $document->currentRevision;
        if (!$last || $last->isEditable()) {
            return back()->with('error', 'نسخه‌ی فعلی همین الان هم Draft است.');
        }

        $data = $request->validate(['change_note' => 'nullable|string|max:255']);

        try {
            $newRevision = app(\App\Services\Documents\DocumentRevisionService::class)
                ->createNextDraft($document, null, $data['change_note'] ?? null, auth()->id(), $last->id);

            if ($last->file_path) {
                app(\App\Services\Documents\DocumentGenerationService::class)
                    ->carryForward($document, $last, $newRevision);
            }
        } catch (\Throwable $e) {
            return back()->with('error', 'ساخت نسخه‌ی جدید ممکن نشد: '.$e->getMessage());
        }

        return redirect()->route('documents.show', $document)->with('success', 'نسخه‌ی Draft جدید ساخته شد — می‌توانید دانلود/ویرایش/آپلود کنید.');
    }

    /**
     * M7: دانلود نسخه‌ی PDF یک Revisionِ مبتنی‌بر قالب واقعی. هر بار از نو
     * تبدیل می‌کند (نه cache) چون upload-edit می‌تواند فایل مبدأ را عوض کند.
     * اگر هیچ درایور PDF روی این سرور در دسترس نباشد (معمول cPanel اشتراکی
     * بدون exec)، پیام روشن می‌دهد و کاربر را به دانلود Word/Excel هدایت می‌کند.
     */
    public function downloadPdf(DocumentRevision $revision)
    {
        if (!$revision->file_path || !Storage::disk('local')->exists($revision->file_path)) {
            abort(404, 'فایلی برای این نسخه تولید نشده است.');
        }

        $service = app(\App\Services\Documents\PdfConversionService::class);
        if (!$service->active()->isAvailable()) {
            return back()->with('error', 'تبدیل PDF روی این سرور فعال نیست — به‌جایش فایل Word/Excel را دانلود کنید.');
        }

        try {
            $pdfPath = $service->convertRevisionFile($revision);
        } catch (\Throwable $e) {
            return back()->with('error', 'تبدیل به PDF ناموفق بود: '.$e->getMessage());
        }
        if (!$pdfPath) {
            return back()->with('error', 'تبدیل به PDF ممکن نشد.');
        }

        $baseName = $revision->formatted_number ?: ($revision->document->document_number ?? 'document');
        $downloadName = preg_replace('/[^A-Za-z0-9_.\-]/', '_', $baseName).'.pdf';

        return Storage::disk('local')->download($pdfPath, $downloadName);
    }

    /**
     * M8: ارسال ایمیل سند به مخاطب، با فایل واقعی (Word/Excel یا PDF) پیوست.
     * Rule 1 (سند معماری): فقط Revisionِ منتشرشده/قفل‌شده قابل ارسال است —
     * پیش‌نویس هنوز نهایی نیست و نباید دست مشتری برسد.
     */
    public function sendEmail(Request $request, DocumentRevision $revision)
    {
        if (!$revision->is_locked) {
            return back()->with('error', 'فقط نسخه‌ی منتشرشده قابل ارسال ایمیل است — ابتدا آن را منتشر کنید.');
        }

        $data = $request->validate([
            'to_address' => 'required|email',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'format' => 'required|in:word,pdf',
        ]);

        $sourcePath = $data['format'] === 'pdf' ? $revision->pdf_path : $revision->file_path;
        if ($data['format'] === 'pdf' && !$sourcePath) {
            try {
                $sourcePath = app(PdfConversionService::class)->convertRevisionFile($revision);
            } catch (\Throwable $e) {
                $sourcePath = null;
            }
        }
        if (!$sourcePath || !Storage::disk('local')->exists($sourcePath)) {
            return back()->with('error', $data['format'] === 'pdf'
                ? 'تبدیل PDF روی این سرور فعال نیست — گزینه‌ی Word/Excel را انتخاب کنید.'
                : 'فایلی برای پیوست کردن پیدا نشد.');
        }

        $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION)) ?: 'docx';
        $mime = match ($ext) {
            'pdf' => 'application/pdf',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        };
        $baseName = $revision->formatted_number ?: 'document';
        $attachName = preg_replace('/[^A-Za-z0-9_.\-]/', '_', $baseName).'.'.$ext;

        // مقصد پیوست‌های ایمیل در سراسر سیستم دیسک public است (بند EmailController::store)؛
        // فایل‌های سند خودشان روی local (خصوصی) می‌مانند — این‌جا فقط یک کپی برای همین ایمیل ساخته می‌شود.
        $publicRel = 'email-attachments/'.date('Y/m').'/'.uniqid().'_'.$attachName;
        Storage::disk('public')->put($publicRel, Storage::disk('local')->get($sourcePath));
        $fullPath = Storage::disk('public')->path($publicRel);

        $document = $revision->document ?: $revision->document()->first();

        $sent = false;
        try {
            Mail::send([], [], function ($message) use ($data, $fullPath, $attachName) {
                $message->to($data['to_address'])->subject($data['subject'])->html(nl2br(e($data['body'])));
                $message->attach($fullPath, ['as' => $attachName]);
            });
            $sent = true;
        } catch (\Throwable $e) {
            try {
                Mail::raw($data['body'], function ($message) use ($data, $fullPath, $attachName) {
                    $message->to($data['to_address'])->subject($data['subject']);
                    $message->attach($fullPath, ['as' => $attachName]);
                });
                $sent = true;
            } catch (\Throwable $e2) {
                $sent = false;
            }
        }

        $email = EmailMessage::create([
            'case_id' => $document?->case_id,
            'direction' => 'outbound',
            'from_address' => config('mail.from.address', 'noreply@example.com'),
            'to_address' => $data['to_address'],
            'subject' => $data['subject'],
            'body' => $data['body'],
            'is_linked' => (bool) $document?->case_id,
        ]);

        EmailAttachment::create([
            'email_message_id' => $email->id,
            'file_name' => $attachName,
            'file_path' => $publicRel,
            'mime_type' => $mime,
            'file_size' => Storage::disk('public')->size($publicRel),
            'source_attachment_id' => null,
        ]);

        if ($document?->case) {
            try {
                $document->case->activities()->create([
                    'user_id' => auth()->id(),
                    'type' => 'note',
                    'body' => 'سند '.($revision->formatted_number ?? $document->document_number).' برای مشتری ایمیل شد.',
                ]);
            } catch (\Throwable $e) {
            }
        }

        AuditLogger::log('document_emailed', 'document_revision', $revision->id, [
            'to' => $data['to_address'],
            'format' => $data['format'],
        ]);

        return back()->with($sent ? 'success' : 'error', $sent
            ? 'ایمیل با پیوست ارسال شد.'
            : 'ایمیل در سیستم ثبت شد اما ارسال SMTP ناموفق بود.');
    }

    public function addRevision(\Illuminate\Http\Request $request, Document $document)
    {
        if ($document->revisions()->where('is_locked', true)->exists() && !auth()->user()->can('document.approve_revision')) {
            // still allow new revision as draft
        }
        $data = $request->validate(['content' => 'required|string']);
        $next = (int) $document->revisions()->max('revision_number') + 1;
        $rev = \App\Models\DocumentRevision::create([
            'document_id' => $document->id,
            'revision_number' => $next,
            'content' => $data['content'],
            'created_by' => auth()->id(),
            'status' => 'draft',
            'is_locked' => false,
        ]);
        $document->update(['current_revision_id' => $rev->id]);
        return back()->with('success', 'نسخه جدید ثبت شد.');
    }

    public function edit(Document $document)
    {
        $document->load(['case', 'revisions' => fn ($q) => $q->orderByDesc('revision_number')]);
        $cases = CaseModel::orderByDesc('id')->limit(100)->get(['id', 'case_number', 'title', 'currency', 'incoterm']);
        $templates = DB::table('templates')->where('type', $document->type)->orderByDesc('is_default')->orderBy('name')->get();
        $latest = $document->revisions->first();
        return view('documents.edit', compact('document', 'cases', 'templates', 'latest'));
    }

    public function update(Request $request, Document $document)
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'currency' => 'nullable|in:EUR,IRR',
            'incoterm' => 'nullable|string|max:20',
            'net_amount' => 'nullable|numeric|min:0',
            'content' => 'nullable|string',
            'change_note' => 'nullable|string|max:255',
        ]);

        $net = (float)($data['net_amount'] ?? $document->net_amount ?? 0);
        $incoterm = $data['incoterm'] ?? $document->incoterm;
        $calc = $this->vat->calculate($net, $incoterm);

        $document->update([
            'title' => $data['title'] ?? $document->title,
            'currency' => $data['currency'] ?? $document->currency,
            'incoterm' => $incoterm,
            'vat_percent' => $calc['vat_percent'],
            'net_amount' => $calc['net_amount'],
            'vat_amount' => $calc['vat_amount'],
            'gross_amount' => $calc['gross_amount'],
        ]);

        $lastRev = (int) $document->revisions()->max('revision_number');
        $rev = DocumentRevision::create([
            'document_id' => $document->id,
            'revision_number' => $lastRev + 1,
            'content' => $data['content'] ?? '',
            'created_by' => Auth::id(),
            // اصلاح M1: change_note قبلاً نه ستونی داشت نه در $fillable بود؛
            // Eloquent بی‌سروصدا نادیده‌اش می‌گرفت. حالا واقعاً ذخیره می‌شود.
            'change_note' => $data['change_note'] ?? 'ویرایش محتوا',
            'status' => 'draft',
        ]);
        $document->update(['current_revision_id' => $rev->id]);

        return redirect()->route('documents.show', $document)->with('success', 'سند به‌روز شد (نسخه جدید).');
    }

    /**
     * اصلاح M0: این route قبلاً در web.php ثبت شده بود (Route::resource(...)->only([...,'destroy']))
     * ولی متد متناظرش در کنترلر وجود نداشت — هر درخواست DELETE به /documents/{document}
     * با خطای «متد یافت نشد» شکست می‌خورد. تا رسیدن به مدل کامل وضعیت/انتشار سند
     * (بخش ۵ معماری، M1-M3)، محافظت فعلی ساده است: اگر سند نسخه‌ای قفل‌شده (is_locked ⇒
     * تأییدشده/منتشرشده) دارد، حذف رد می‌شود — دقیقاً همان قانونی که خودِ approve() این
     * کنترلر روی is_locked تحمیل می‌کند.
     */
    /**
     * M11+: کاربر عادی همچنان نمی‌تواند سند دارای نسخه‌ی قفل‌شده/منتشرشده را حذف
     * کند (شماره‌ی رسمی صادرشده، طبق Rule 1 سند معماری، نباید بی‌سروصدا ناپدید
     * شود) — اما مدیر سیستم باید بتواند برای پاک‌سازی داده‌ی تستی/اشتباه این
     * محدودیت را دور بزند؛ به همین دلیل این override فقط به نقش admin (نه صرفِ
     * دسترسی document.delete که به مدیران فنی/مالی هم داده می‌شود) محدود است.
     */
    public function destroy(Request $request, Document $document)
    {
        $isAdmin = $request->user() && method_exists($request->user(), 'hasRole') && $request->user()->hasRole('admin');
        $hasLocked = $document->revisions()->where('is_locked', true)->exists();

        if ($hasLocked && !$isAdmin) {
            return back()->with('error', 'این سند نسخه‌ی تأییدشده/قفل‌شده دارد و قابل حذف نیست.');
        }

        $documentId = $document->id;
        $documentNumber = $document->document_number;
        $numberBase = $document->number_base;
        $typeKey = $document->documentType?->key ?: $document->type;
        $reclaimed = false;

        DB::transaction(function () use ($document, $numberBase, $typeKey, &$reclaimed) {
            $document->revisions()->delete();
            $document->lines()->delete();
            $document->delete();

            // M21 (فهرست اسناد): با حذف کامل سند (همه‌ی نسخه‌هایش، از همان number_base)،
            // اگر شماره‌ی این سند آخرین شماره‌ی صادرشده برای نوعش بود، برای سند بعدی
            // آزاد می‌شود؛ در غیر این صورت (سند دیگری بعداً شماره‌ی بالاتر گرفته)
            // طبق درخواست کاربر شماره برای همیشه سوخته می‌ماند — هرگز شکاف پر نمی‌شود.
            // number_base دو قالب دارد: مسیر جدید «PREFIX-SERIAL-caseTag» و مسیر
            // قدیمی «PREFIX-SERIAL»؛ در هر دو حالت بخش دوم همان سریال عددی است.
            if ($numberBase) {
                $parts = explode('-', $numberBase, 3);
                if (isset($parts[1]) && ctype_digit($parts[1])) {
                    $reclaimed = app(NumberGeneratorService::class)->reclaimIfLast($typeKey, (int) $parts[1]);
                }
            }

            // M39 (رفعِ باگ، گزارشِ کاربر): $document->delete() چون مدل SoftDeletes
            // دارد، فقط deleted_at را ست می‌کند — خودِ ردیف (با number_base و
            // document_number قدیمی‌اش) در جدول باقی می‌ماند، چون قیدِ UNIQUE
            // هیچ استثنایی برای ردیف‌های soft-deleted قائل نیست. اگر بالا
            // reclaim واقعاً انجام شده باشد (یعنی همین سریال قرار است به سندِ
            // بعدی داده شود)، باید همین ردیفِ حذف‌شده هم این دو مقدار را رها
            // کند — وگرنه اولین سندی که همین سریال را دوباره بگیرد، درست همین
            // خطای «UNIQUE constraint failed: documents.number_base» را
            // می‌گیرد (چون ردیفِ قدیمیِ soft-deleted هنوز همان مقدار را دارد).
            // document_number ستونِ NOT NULL هم هست، پس به‌جای null یک مقدارِ
            // تضمین‌شده یکتا (بر پایه‌ی id، که هرگز تکرار نمی‌شود) می‌گیرد.
            if ($reclaimed) {
                $document->forceFill([
                    'number_base' => null,
                    'document_number' => 'DELETED-'.$document->id,
                ])->save();
            }
        });

        AuditLogger::log('document_deleted', 'document', $documentId, [
            'document_number' => $documentNumber,
            'forced_locked_override' => $hasLocked,
            'number_reclaimed' => $reclaimed,
        ]);

        return redirect()->route('documents.index')->with('success', 'سند حذف شد.'.($reclaimed ? ' شماره‌ی آن برای سند بعدی آزاد شد.' : ''));
    }

    /**
     * M23 (اصلاح M21، طبق مثال دقیق کاربر): «ساخت کپی» یک Revisionِ Draft
     * *تازه روی همان سند* می‌سازد — نه یک سند مستقل جدا (اشتباه M21). شماره‌ی
     * Revisionِ تازه همیشه آخرین‌شماره‌ی سند + ۱ است (مثلاً اگر R00 و R01 از
     * قبل موجودند، فرقی نمی‌کند «ساخت کپی» روی کدام‌شان زده شود، تازه همیشه
     * R02 خواهد بود) — اما *محتوا/فایلِ* آن دقیقاً کپی همان Revisionی است که
     * کاربر رویش «ساخت کپی» را زده (نه لزوماً آخرین Revision سند)؛ یعنی اگر
     * کاربر روی R00 بزند، R02 با محتوای R00 ساخته می‌شود، نه R01.
     * از دو سرویس موجود (بدون کد تکراری) ترکیب شده: DocumentRevisionService
     * برای شماره‌گذاری صحیحِ زیر لاک (همان منطق newDraft/M6)، و
     * DocumentGenerationService::carryForward() برای کپی واقعی فایل — همان
     * متدی که newDraft() هم برای «ادامه‌ی ویرایش» استفاده می‌کند، این‌جا فقط
     * مبدأش به‌جای همیشه «آخرین نسخه» می‌شود همان Revisionِ انتخاب‌شده.
     */
    public function copyRevision(DocumentRevision $revision)
    {
        $document = $revision->document ?: $revision->document()->first();
        if (!$document) {
            return back()->with('error', 'سند این نسخه پیدا نشد.');
        }

        try {
            $changeNote = 'کپی از نسخه‌ی '.($revision->formatted_number
                ?: ('R'.str_pad((string) $revision->revision_number, 2, '0', STR_PAD_LEFT).' (پیش‌نویس)'));

            $newRevision = app(\App\Services\Documents\DocumentRevisionService::class)
                ->createNextDraft($document, $revision->data ?? [], $changeNote, auth()->id(), $revision->id);

            // createNextDraft() برای content/template_version_id هم پیش‌فرض را از
            // «آخرین Revision سند» می‌گیرد (طراحی درست برای newDraft عادی) — این‌جا
            // چون مبدأ ممکن است لزوماً آخرین نباشد، صریحاً از روی همان Revisionِ
            // انتخاب‌شده override می‌کنیم.
            $newRevision->update([
                'content' => $revision->content,
                'template_version_id' => $revision->template_version_id,
            ]);

            if ($revision->file_path) {
                app(\App\Services\Documents\DocumentGenerationService::class)
                    ->carryForward($document, $revision, $newRevision);
            }
        } catch (\Throwable $e) {
            return back()->with('error', 'ساخت کپی ممکن نشد: '.$e->getMessage());
        }

        $newLabel = $newRevision->formatted_number
            ?: ('R'.str_pad((string) $newRevision->revision_number, 2, '0', STR_PAD_LEFT));

        return redirect()->route('documents.show', $document)
            ->with('success', "نسخه‌ی Draft جدید ({$newLabel}) به‌عنوان کپی از نسخه‌ی انتخاب‌شده ساخته شد — می‌توانید آن را ویرایش آنلاین/دانلود/منتشر کنید.");
    }

    /**
     * M35 (درخواست کاربر): «آیا می‌شود برای یک پیش‌نویس، قالبی غیر از قالبِ
     * سندِ مادر انتخاب کرد؟» — این فرم (GET) پیش از ساختِ واقعیِ فایل، انتخابِ
     * قالب + تکمیلِ فیلدهای دستی/ردیف‌های آن قالب را نشان می‌دهد. دو حالت:
     *  - mode=in_place: تغییرِ قالبِ همین Draftِ *موجود* (فقط اگر هنوز
     *    قابل‌ویرایش باشد؛ Revisionِ منتشرشده/قفل هرگز از این مسیر تغییر
     *    نمی‌کند — طبق Rule 11/CONFLICT-5).
     *  - mode=new_draft: ساختِ یک Draftِ *تازه* از رویِ همین Revision، ولی با
     *    قالبِ دیگر (دقیقاً هم‌خانواده با copyRevision/M23، فقط به‌جای
     *    carryForward بایتی، رندرِ تازه از قالبِ انتخابی).
     * دامنه‌ی قالب‌ها طبق تأییدِ کاربر فقط همان document_type_id سندِ مادر است.
     */
    public function templateForm(Request $request, DocumentRevision $revision)
    {
        $document = $revision->document ?: $revision->document()->first();
        if (!$document) {
            return back()->with('error', 'سند این نسخه پیدا نشد.');
        }

        $mode = $request->query('mode') === 'new_draft' ? 'new_draft' : 'in_place';
        if ($mode === 'in_place' && !$revision->isEditable()) {
            return back()->with('error', 'این نسخه قفل‌شده/منتشرشده است — برای تغییرِ قالب باید یک Draft جدید بسازید («ساخت با قالب دیگر»).');
        }

        $templates = Template::where('document_type_id', $document->document_type_id)
            ->where('status', 'active')
            ->whereNotNull('file_type')
            ->orderByDesc('is_default')->orderBy('name')->get();

        $templateId = $request->query('template_id');
        $templateVersion = null;
        $manualFields = collect();
        $hasLineFields = false;
        if ($templateId) {
            $template = Template::with('currentVersion.fields')->find($templateId);
            $templateVersion = $template?->currentVersion;
            if ($templateVersion) {
                $manualFields = $templateVersion->fields->where('source', 'manual')->sortBy('sort_order');
                $hasLineFields = $templateVersion->fields->where('source', 'line')->isNotEmpty();
            }
        }

        $supportsLines = (bool) ($document->documentType?->supports_lines) && $hasLineFields;

        return view('documents.change-template', compact(
            'document', 'revision', 'mode', 'templates', 'templateVersion',
            'manualFields', 'supportsLines', 'templateId'
        ));
    }

    /**
     * M35: ذخیره‌ی فرمِ بالا. نکته‌ی مهم که به کاربر گفته و تأیید گرفته شده:
     * چون ساختارِ قالب‌ها متفاوت است، تعویضِ قالب یعنی رندرِ *کاملاً تازه*
     * (همان generate() که مسیرِ اولین ساختِ سند هم استفاده می‌کند) — فیلدهای
     * auto دوباره از پرونده/سند پر می‌شوند، ولی هر ویرایشِ دستی‌ای که کاربر
     * قبلاً مستقیم روی فایلِ Word/Excel همین Draft انجام داده باشد
     * (upload-edit/ONLYOFFICE) از بین می‌رود — برخلافِ carryForward که دقیقاً
     * برای حفظِ همین ویرایش‌ها طراحی شده بود (M6). به همین دلیل sync ردیف‌ها
     * فقط وقتی انجام می‌شود که قالبِ تازه واقعاً فیلدِ line دارد — وگرنه
     * ردیف‌های موجودِ سند (که مستقلِ از قالب، روی خودِ Document ذخیره‌اند)
     * دست‌نخورده می‌مانند.
     */
    public function templateStore(Request $request, DocumentRevision $revision)
    {
        $document = $revision->document ?: $revision->document()->first();
        if (!$document) {
            return back()->with('error', 'سند این نسخه پیدا نشد.');
        }

        $data = $request->validate([
            'mode' => 'required|in:in_place,new_draft',
            'template_id' => 'required|exists:templates,id',
            'manual' => 'nullable|array',
            'lines' => 'nullable|array',
            'lines.*.description' => 'nullable|string|max:500',
            'lines.*.unit' => 'nullable|string|max:30',
            'lines.*.quantity' => 'nullable|numeric|min:0',
            'lines.*.unit_price' => 'nullable|numeric|min:0',
            'vat_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        $template = Template::with('currentVersion.fields')->findOrFail($data['template_id']);
        $templateVersion = $template->currentVersion;
        if (!$templateVersion) {
            return back()->withErrors(['template_id' => 'این قالب هنوز نسخه‌ای ندارد.'])->withInput();
        }
        if ((int) $template->document_type_id !== (int) $document->document_type_id) {
            return back()->withErrors(['template_id' => 'این قالب متعلق به نوع سند این پرونده نیست.'])->withInput();
        }

        $hasLineFields = $templateVersion->fields->where('source', 'line')->isNotEmpty();
        $supportsLines = (bool) ($document->documentType?->supports_lines) && $hasLineFields;
        $manualValues = $request->input('manual', []);

        try {
            if ($data['mode'] === 'in_place') {
                if (!$revision->isEditable()) {
                    return back()->with('error', 'این نسخه دیگر Draft نیست — تغییرِ قالب فقط روی Draftِ قابل‌ویرایش ممکن است.');
                }

                DB::transaction(function () use ($document, $revision, $template, $templateVersion, $manualValues, $supportsLines, $request) {
                    if ($supportsLines) {
                        app(DocumentLineService::class)->sync($document, $request->input('lines', []), $request->input('vat_percent'));
                    }
                    app(\App\Services\Documents\DocumentGenerationService::class)
                        ->generate($document->fresh(['case', 'lines']), $revision, $templateVersion, $manualValues);
                    $revision->update([
                        'template_version_id' => $templateVersion->id,
                        'data' => $manualValues,
                        'change_note' => 'قالب به «'.$template->name.'» تغییر کرد و فایل از نو ساخته شد',
                        // M11: فایل از بیرونِ ONLYOFFICE عوض شد — کلید cache قبلی معتبر نیست.
                        'editor_key' => null,
                        // فایلِ PDFِ قبلی برای قالبِ قدیمی بود؛ دفعه‌ی بعد که دانلودِ PDF
                        // خواسته شود، PdfConversionService خودش دوباره از رویِ فایلِ
                        // تازه می‌سازد (طبق طراحی‌اش، هر بار از نو تبدیل می‌کند).
                        'pdf_path' => null,
                    ]);
                });

                return redirect()->route('documents.show', $document)
                    ->with('success', 'قالبِ این Draft عوض شد و فایل از نو ساخته شد.');
            }

            // mode === 'new_draft'
            $newRevision = DB::transaction(function () use ($document, $revision, $template, $templateVersion, $manualValues, $supportsLines, $request) {
                if ($supportsLines) {
                    app(DocumentLineService::class)->sync($document, $request->input('lines', []), $request->input('vat_percent'));
                }

                $changeNote = 'کپی از نسخه‌ی '.($revision->formatted_number
                    ?: ('R'.str_pad((string) $revision->revision_number, 2, '0', STR_PAD_LEFT).' (پیش‌نویس)'))
                    .' با قالبِ «'.$template->name.'»';

                $newRev = app(\App\Services\Documents\DocumentRevisionService::class)
                    ->createNextDraft($document, $manualValues, $changeNote, auth()->id(), $revision->id);
                $newRev->update(['template_version_id' => $templateVersion->id]);

                app(\App\Services\Documents\DocumentGenerationService::class)
                    ->generate($document->fresh(['case', 'lines']), $newRev, $templateVersion, $manualValues);

                return $newRev;
            });

            $newLabel = $newRevision->formatted_number
                ?: ('R'.str_pad((string) $newRevision->revision_number, 2, '0', STR_PAD_LEFT));

            return redirect()->route('documents.show', $document)
                ->with('success', "نسخه‌ی Draft جدید ({$newLabel}) با قالبِ دیگر ساخته شد.");
        } catch (\Throwable $e) {
            return back()->withErrors(['template_id' => 'تغییرِ قالب ممکن نشد: '.$e->getMessage()])->withInput();
        }
    }

    /**
     * M24 (درخواست کاربر): حذف یک Revisionِ *تکی*، بدون حذف کل سند — تا این
     * چک‌این چنین قابلیتی اصلاً وجود نداشت (حذف همیشه یعنی کل سند). قوانین:
     *  - اگر این تنها Revisionِ سند باشد، معنایی جز «حذف کل سند» ندارد؛ کاربر
     *    به همان مسیر (documents.destroy) هدایت می‌شود.
     *  - دقیقاً همان محافظت «فقط مدیر» که حذفِ کل سند دارای Revisionِ
     *    قفل‌شده را محدود می‌کند، اینجا هم روی تک‌تک Revision اعمال می‌شود —
     *    یک کاربر عادی فقط Draft/در‌بررسیِ قفل‌نشده را می‌تواند حذف کند.
     *  - شماره‌ی Revisionهای باقی‌مانده هرگز تغییر نمی‌کند (بدون بازشماری) —
     *    شکاف در شماره‌ی رویژن‌ها عمداً پذیرفته می‌شود، چون formatted_number
     *    رویژن‌های دیگر ممکن است قبلاً واقعاً صادر/ارسال شده باشد.
     *  - اگر current_revision_id/published_revision_id سند به همین Revision
     *    اشاره می‌کرد، به جدیدترین Revisionِ باقی‌مانده (یا جدیدترین
     *    قفل‌شده‌ی باقی‌مانده برای published_revision_id) منتقل می‌شود.
     */
    public function destroyRevision(Request $request, DocumentRevision $revision)
    {
        $document = $revision->document ?: $revision->document()->first();
        if (!$document) {
            return back()->with('error', 'سند این نسخه پیدا نشد.');
        }

        if ($document->revisions()->count() <= 1) {
            return back()->with('error', 'این تنها نسخه‌ی این سند است — برای حذف آن باید کل سند را حذف کنید.');
        }

        $isAdmin = $request->user() && method_exists($request->user(), 'hasRole') && $request->user()->hasRole('admin');
        if ($revision->is_locked && !$isAdmin) {
            return back()->with('error', 'این نسخه منتشرشده/قفل‌شده است و قابل حذف نیست — فقط مدیر سیستم می‌تواند آن را حذف کند.');
        }

        DB::transaction(function () use ($document, $revision) {
            $wasCurrent = $document->current_revision_id === $revision->id;
            $wasPublished = $document->published_revision_id === $revision->id;
            $revisionId = $revision->id;
            $revisionNumber = $revision->revision_number;

            $revision->delete();

            $updates = [];
            if ($wasCurrent) {
                $latest = $document->revisions()->orderByDesc('revision_number')->first();
                $updates['current_revision_id'] = $latest?->id;
            }
            if ($wasPublished) {
                // اگر Revisionِ منتشرشده‌ی فعلی حذف شد: اگر یک Revisionِ قفل‌شده‌ی
                // دیگر (مثلاً Supersededِ قبلی) باقی مانده، همان دوباره
                // «منتشرشده‌ی فعلی» سند می‌شود؛ وگرنه سند به «بدون نسخه‌ی
                // منتشرشده» برمی‌گردد — طبق همان قرارداد Rule 11.
                $lastLocked = $document->revisions()->where('is_locked', true)->orderByDesc('revision_number')->first();
                if ($lastLocked) {
                    $lastLocked->update(['status' => DocumentRevision::STATUS_PUBLISHED]);
                    $updates['published_revision_id'] = $lastLocked->id;
                    $updates['document_number'] = $lastLocked->formatted_number ?: $document->document_number;
                    $updates['status'] = Document::STATUS_PUBLISHED;
                } else {
                    $updates['published_revision_id'] = null;
                    $updates['status'] = Document::STATUS_DRAFT;
                }
            }
            if ($updates) {
                $document->update($updates);
            }

            AuditLogger::log('document_revision_deleted', 'document', $document->id, [
                'revision_id' => $revisionId,
                'revision_number' => $revisionNumber,
            ]);
        });

        return redirect()->route('documents.show', $document)->with('success', 'نسخه حذف شد.');
    }

    /**
     * اصلاح M4: منطق واقعی به App\Services\Documents\DocumentLineService منتقل
     * شد تا مسیر قدیمی (اینجا) و مسیر جدید «سند از قالب واقعی»
     * (DocumentGenerateController) یک منبع واحد داشته باشند، نه دو نسخه‌ی
     * موازی که ممکن است از هم جدا بیفتند. امضای این متد برای سازگاری با
     * فراخوانی موجود در store() دست‌نخورده مانده.
     */
    protected function syncLines(Document $doc, array $lines, array $data = []): void
    {
        app(DocumentLineService::class)->sync($doc, $lines, $data['vat_percent'] ?? null);
    }
}
