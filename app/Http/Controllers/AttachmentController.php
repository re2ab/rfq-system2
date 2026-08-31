<?php
namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\CaseModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    public function store(Request $request, CaseModel $case)
    {
        $request->validate([
            'file' => 'required|file|max:51200|mimes:pdf,doc,docx,jpg,jpeg,png,zip,xls,xlsx',
            'note' => 'nullable|string|max:500',
        ]);
        $file = $request->file('file');
        $path = $file->store('attachments/cases/'.$case->id, 'public');
        $case->attachments()->create([
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by' => Auth::id(),
        ]);

        // optional timeline note
        try {
            if ($request->filled('note') || true) {
                $label = $file->getClientOriginalName();
                $extra = $request->input('note') ? ' — '.$request->input('note') : '';
                $case->activities()->create([
                    'user_id' => Auth::id(),
                    'type' => 'note',
                    'body' => 'پیوست فایل: '.$label.$extra,
                ]);
            }
        } catch (\Throwable $e) {
        }

        return back()->with('success', 'فایل به پرونده پیوست شد.');
    }

    public function download(Attachment $attachment)
    {
        if (!Storage::disk('public')->exists($attachment->file_path)) {
            abort(404, 'فایل یافت نشد.');
        }
        return Storage::disk('public')->download(
            $attachment->file_path,
            $attachment->file_name
        );
    }

    public function destroy(Attachment $attachment)
    {
        $user = auth()->user();
        if ($attachment->uploaded_by !== $user->id && !$user->hasAnyRole(['admin', 'technical_manager', 'financial_manager'])) {
            abort(403);
        }
        Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();
        return back()->with('success', 'پیوست حذف شد.');
    }
}
