@extends('layouts.app')
@section('title','ارسال ایمیل')
@section('content')
<div class="max-w-2xl mx-auto bg-white rounded-lg shadow p-6">
<form method="POST" action="{{ route('emails.store') }}" enctype="multipart/form-data" class="space-y-3 text-sm">@csrf
<div>
  <label class="block mb-1">گیرنده *</label>
  <input type="email" name="to_address" required class="w-full border rounded px-3 py-2">
</div>
<div>
  <label class="block mb-1">موضوع *</label>
  <input name="subject" required class="w-full border rounded px-3 py-2" placeholder="مثلاً پیشنهاد فنی CASE-000001">
</div>
<div>
  <label class="block mb-1">پرونده (اختیاری)</label>
  <select name="case_id" id="emailCaseId" class="w-full border rounded px-3 py-2">
    <option value="">—</option>
    @foreach($cases as $c)
      <option value="{{ $c->id }}" @selected(($caseId ?? null)==$c->id)>{{ $c->case_number }} — {{ \Illuminate\Support\Str::limit($c->title, 40) }}</option>
    @endforeach
  </select>
</div>
<div>
  <label class="block mb-1">متن *</label>
  <textarea name="body" required rows="6" class="w-full border rounded px-3 py-2"></textarea>
</div>
<div class="border rounded-lg p-3 bg-slate-50">
  <div class="font-semibold mb-2">پیوست‌ها</div>
  <div id="caseDocBox" class="mb-3 text-xs">
    <div class="muted mb-1">از اسناد پرونده (فایل واقعی Word/Excel همان نسخه):</div>
    <div id="caseDocList" class="space-y-1">
      @forelse($caseDocuments ?? [] as $doc)
        <label class="flex items-center gap-2">
          <input type="checkbox" name="document_revision_ids[]" value="{{ $doc->currentRevision->id }}">
          <span>{{ $doc->document_number }} — {{ $doc->documentType->name_fa ?? $doc->type }}</span>
        </label>
      @empty
        <span class="muted">پرونده را انتخاب کنید تا اسناد لود شوند.</span>
      @endforelse
    </div>
  </div>
  <div id="caseAttBox" class="mb-3 text-xs">
    <div class="muted mb-1">از پیوست‌های پرونده (مثلاً نقشه فنی):</div>
    <div id="caseAttList" class="space-y-1">
      @forelse($caseAttachments ?? [] as $att)
        <label class="flex items-center gap-2">
          <input type="checkbox" name="attachment_ids[]" value="{{ $att->id }}">
          <span>{{ $att->file_name }}</span>
        </label>
      @empty
        <span class="muted">پرونده را انتخاب کنید تا پیوست‌ها لود شوند.</span>
      @endforelse
    </div>
  </div>
  <div>
    <label class="block mb-1">آپلود فایل جدید</label>
    <input type="file" name="files[]" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.zip,.xls,.xlsx" class="w-full text-xs">
  </div>
</div>
<button class="bg-blue-600 text-white px-4 py-2 rounded">ارسال و ثبت</button>
</form>
</div>
<script>
(function(){
  const sel = document.getElementById('emailCaseId');
  const list = document.getElementById('caseAttList');
  const docList = document.getElementById('caseDocList');
  sel?.addEventListener('change', async () => {
    const id = sel.value;
    if (!id) {
      list.innerHTML = '<span class="muted">پرونده را انتخاب کنید.</span>';
      docList.innerHTML = '<span class="muted">پرونده را انتخاب کنید.</span>';
      return;
    }
    list.innerHTML = 'در حال بارگذاری…';
    docList.innerHTML = 'در حال بارگذاری…';
    try {
      const res = await fetch('/emails/case-attachments/' + id, { headers: { 'Accept': 'application/json' } });
      const data = await res.json();
      if (!data.length) { list.innerHTML = '<span class="muted">این پرونده پیوستی ندارد.</span>'; }
      else { list.innerHTML = data.map(a => `<label class="flex items-center gap-2"><input type="checkbox" name="attachment_ids[]" value="${a.id}"><span>${a.file_name}</span></label>`).join(''); }
    } catch(e) {
      list.innerHTML = '<span class="text-red-600">خطا در بارگذاری پیوست‌ها</span>';
    }
    try {
      const res2 = await fetch('/emails/case-documents/' + id, { headers: { 'Accept': 'application/json' } });
      const docs = await res2.json();
      if (!docs.length) { docList.innerHTML = '<span class="muted">این پرونده سند دانلودپذیری ندارد.</span>'; }
      else { docList.innerHTML = docs.map(d => `<label class="flex items-center gap-2"><input type="checkbox" name="document_revision_ids[]" value="${d.revision_id}"><span>${d.document_number} — ${d.type}</span></label>`).join(''); }
    } catch(e) {
      docList.innerHTML = '<span class="text-red-600">خطا در بارگذاری اسناد</span>';
    }
  });
})();
</script>
@endsection
