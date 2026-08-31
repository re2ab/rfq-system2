<?php

namespace App\Http\Controllers;

use App\Models\CaseModel;
use App\Models\DocumentType;
use App\Services\Documents\DocumentFileImportService;
use Illuminate\Http\Request;

/**
 * M9الف: آوردن یک فایل *موجود* (Word/Excel/PDF) به داخل سیستم به‌عنوان سند
 * یک پرونده — بدون رد شدن از قالب. برای اسنادی که از سیستم قبلی یا خارج از
 * RFQ-Core ساخته شده‌اند ولی باید در همین‌جا ثبت/بایگانی/شماره‌گذاری شوند.
 */
class DocumentUploadController extends Controller
{
    public function __construct(protected DocumentFileImportService $importer)
    {
    }

    public function create()
    {
        $cases = CaseModel::orderByDesc('id')->limit(100)->get(['id', 'case_number', 'title']);
        $documentTypes = DocumentType::active();

        return view('documents.upload', compact('cases', 'documentTypes'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'case_id' => 'required|exists:cases,id',
            'document_type_id' => 'required|exists:document_types,id',
            'title' => 'nullable|string|max:255',
            'file' => 'required|file',
        ]);

        try {
            $document = $this->importer->importAsDocument(
                (int) $data['case_id'],
                (int) $data['document_type_id'],
                $request->file('file')->getRealPath(),
                $request->file('file')->getClientOriginalName(),
                $data['title'] ?? null,
                auth()->id(),
                ['source' => 'manual_upload']
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => $e->getMessage()])->withInput();
        }

        // M25: اگر شماره‌ی داخل نام فایل با سندی موجود تطبیق پیدا کرده باشد
        // (یعنی همین سند بیش از یک رویژن دارد — چون سند تازه همیشه دقیقاً
        // یک رویژن دارد)، پیام باید این را شفاف بگوید، نه اینکه انگار سند
        // کاملاً تازه‌ای ساخته شده.
        $wasAttachedToExisting = $document->revisions()->count() > 1;
        $message = $wasAttachedToExisting
            ? 'شماره‌ی داخلِ نام فایل با این سند تطبیق پیدا کرد — به‌عنوان رویژن Draft تازه‌ی همین سند ثبت شد (به‌جای سند جدید).'
            : 'سند از فایل موجود ثبت شد — پیش‌نویس آماده‌ی دانلود/انتشار است.';

        return redirect()->route('documents.show', $document)->with('success', $message);
    }
}
