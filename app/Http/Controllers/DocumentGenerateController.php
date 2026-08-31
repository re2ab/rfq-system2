<?php

namespace App\Http\Controllers;

use App\Models\CaseModel;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Template;
use App\Services\Documents\DocumentGenerationService;
use App\Services\Documents\DocumentLineService;
use App\Services\Documents\DocumentRevisionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * مسیر جدید «ساخت سند از قالب واقعی Word/Excel» (M4 — اولین نقطه‌ی ارزش
 * قابل‌نمایش طبق نقشه‌راه سند معماری). عمداً از DocumentController جدا است:
 * مسیر قدیمی (محتوای HTML، شماره‌گذاری زودهنگام) دست‌نخورده می‌ماند، این یکی
 * از قالب واقعی + template_fields + شماره‌گذاری دیرهنگام (فقط در Publish)
 * استفاده می‌کند — هر دو روی همان جدول‌های documents/document_revisions
 * (بند صریح معماری: بدون مسیر موازی در لایه‌ی داده).
 */
class DocumentGenerateController extends Controller
{
    public function __construct(
        protected DocumentRevisionService $revisions,
        protected DocumentGenerationService $generator,
        protected DocumentLineService $lines,
    ) {
    }

    public function create(Request $request)
    {
        $cases = CaseModel::orderByDesc('id')->limit(100)->get(['id', 'case_number', 'title']);
        $documentTypes = DocumentType::active();

        $caseId = $request->get('case_id');
        $documentTypeId = $request->get('document_type_id');
        $templateId = $request->get('template_id');
        // M12: از منوی «سند جدید» — وقتی کاربر زیرشاخه‌ی Word یا Excel را انتخاب
        // کرده، فقط قالب‌های همان فرمت نشان داده شود (یک نوع سند می‌تواند هم
        // قالب Word هم Excel فعال داشته باشد).
        $fileType = in_array($request->get('file_type'), ['docx', 'xlsx'], true) ? $request->get('file_type') : null;

        $templates = collect();
        if ($documentTypeId) {
            $templates = Template::where('document_type_id', $documentTypeId)
                ->where('status', 'active')
                ->whereNotNull('file_type')
                ->when($fileType, fn ($q) => $q->where('file_type', $fileType))
                ->orderByDesc('is_default')->orderBy('name')->get();
        }

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

        $documentType = $documentTypeId ? DocumentType::find($documentTypeId) : null;
        $supportsLines = (bool) ($documentType?->supports_lines) && $hasLineFields;

        return view('documents.generate', compact(
            'cases', 'documentTypes', 'templates', 'templateVersion',
            'manualFields', 'supportsLines',
            'caseId', 'documentTypeId', 'templateId', 'fileType'
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'case_id' => 'required|exists:cases,id',
            'document_type_id' => 'required|exists:document_types,id',
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
        if ((int) $template->document_type_id !== (int) $data['document_type_id']) {
            return back()->withErrors(['template_id' => 'این قالب متعلق به نوع سند انتخاب‌شده نیست.'])->withInput();
        }

        $documentType = DocumentType::findOrFail($data['document_type_id']);

        try {
            $document = DB::transaction(function () use ($data, $documentType, $templateVersion, $request) {
                $doc = Document::create([
                    'case_id' => $data['case_id'],
                    'type' => $documentType->key,
                    'document_type_id' => $documentType->id,
                    // شماره‌ی رسمی فقط در Publish صادر می‌شود — تا آن لحظه یک مقدار
                    // موقت یکتا در ستون قدیمی NOT NULL+UNIQUE می‌نشیند (بند طراحی M1).
                    'document_number' => 'DRAFT-'.uniqid(),
                    'status' => Document::STATUS_DRAFT,
                ]);

                $this->lines->sync($doc, $request->input('lines', []), $request->input('vat_percent'));

                $revision = $this->revisions->createInitial($doc, $templateVersion, $request->input('manual', []), auth()->id());

                $this->generator->generate($doc->fresh(['case', 'lines']), $revision, $templateVersion, $request->input('manual', []));

                return $doc;
            });
        } catch (\Throwable $e) {
            return back()->withErrors(['template_id' => 'ساخت سند ممکن نشد: '.$e->getMessage()])->withInput();
        }

        return redirect()->route('documents.show', $document)->with('success', 'سند از روی قالب ساخته شد — پیش‌نویس آماده‌ی دانلود/انتشار است.');
    }
}
