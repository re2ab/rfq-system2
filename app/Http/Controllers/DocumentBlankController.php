<?php

namespace App\Http\Controllers;

use App\Models\CaseModel;
use App\Models\DocumentType;
use App\Services\Documents\DocumentFileImportService;
use Illuminate\Http\Request;

/**
 * M12 — «ایجاد سند خالی» (منوی سند جدید، مثل Zoho/Google Docs): یک فایل
 * Word یا Excel کاملاً خالی (بدون قالب/جای‌نگه‌دار) به‌عنوان سند ثبت می‌شود
 * تا بلافاصله با «ویرایش آنلاین» (M11 — ONLYOFFICE) باز و از صفر نوشته شود.
 *
 * عمداً از همان DocumentFileImportService مسیر M9الف (آوردن فایل موجود)
 * استفاده می‌کند — تنها تفاوت این‌جا این است که فایل مبدأ به‌جای آپلود
 * کاربر، یک اسکلت خالیِ از پیش‌ساخته‌شده در storage/app/blank-templates
 * است؛ خروجی همان قرارداد Document+DocumentRevision است، پس دانلود/
 * Publish/ایمیل/PDF/ویرایش آنلاین بدون هیچ کد اضافه‌ای روی این اسناد هم کار می‌کند.
 */
class DocumentBlankController extends Controller
{
    public function __construct(protected DocumentFileImportService $importer)
    {
    }

    public function create(Request $request)
    {
        $format = $request->get('format') === 'xlsx' ? 'xlsx' : 'docx';
        $cases = CaseModel::orderByDesc('id')->limit(100)->get(['id', 'case_number', 'title']);
        $documentTypes = DocumentType::active();

        return view('documents.blank', compact('cases', 'documentTypes', 'format'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'case_id' => 'required|exists:cases,id',
            'document_type_id' => 'required|exists:document_types,id',
            'title' => 'nullable|string|max:255',
            'format' => 'required|in:docx,xlsx',
        ]);

        $skeleton = storage_path('app/blank-templates/blank.'.$data['format']);
        if (!is_file($skeleton)) {
            return back()->withErrors(['format' => 'فایل اسکلت خالی («'.$data['format'].'») روی سرور پیدا نشد.'])->withInput();
        }

        try {
            $document = $this->importer->importAsDocument(
                (int) $data['case_id'],
                (int) $data['document_type_id'],
                $skeleton,
                'سند خالی.'.$data['format'],
                $data['title'] ?? null,
                auth()->id(),
                ['source' => 'blank_document']
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['format' => $e->getMessage()])->withInput();
        }

        return redirect()->route('documents.show', $document)
            ->with('success', 'سند خالی ایجاد شد — از دکمه‌ی «ویرایش آنلاین» برای نوشتن آن استفاده کنید.');
    }
}
