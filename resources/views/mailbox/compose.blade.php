@extends('layouts.app')
@section('title', $mode === 'reply' ? 'پاسخ' : ($mode === 'forward' ? 'فوروارد' : 'نامه‌ی جدید'))
@section('actions')
  <a href="{{ route('mailbox.inbox') }}" class="btn btn-ghost btn-sm">صندوق ورودی</a>
@endsection
@section('content')
<div class="max-w-2xl mx-auto bg-white rounded-lg shadow p-6">
<form method="POST" action="{{ route('mailbox.compose.send') }}" enctype="multipart/form-data" class="space-y-3 text-sm">@csrf
  <input type="hidden" name="in_reply_to" value="{{ old('in_reply_to', $prefill['in_reply_to']) }}">
  <input type="hidden" name="references" value="{{ old('references', $prefill['references']) }}">
  <input type="hidden" name="mark_answered_uid" value="{{ old('mark_answered_uid', $prefill['mark_answered_uid']) }}">

  @if($sourceMessage)
    <div class="text-xs text-gray-500 border rounded px-2 py-1">
      {{ $mode === 'reply' ? 'پاسخ به:' : 'فوروارد نامه‌ی:' }} «{{ $sourceMessage['subject'] }}» — <span dir="ltr">{{ $sourceMessage['from'] }}</span>
    </div>
  @endif

  <div>
    <label class="block mb-1">گیرنده *</label>
    <input type="email" name="to" required value="{{ old('to', $prefill['to']) }}" class="w-full border rounded px-3 py-2" dir="ltr">
  </div>
  <div>
    <label class="block mb-1">رونوشت (CC)</label>
    <input name="cc" value="{{ old('cc', $prefill['cc']) }}" class="w-full border rounded px-3 py-2" dir="ltr" placeholder="یک یا چند ایمیل با ویرگول جدا شود">
  </div>
  <div>
    <label class="block mb-1">موضوع *</label>
    <input name="subject" required value="{{ old('subject', $prefill['subject']) }}" class="w-full border rounded px-3 py-2">
  </div>

  <div class="border rounded-lg p-3 bg-slate-50">
    <div class="font-semibold mb-2">استفاده از قالب سیستم</div>
    <div class="flex gap-2 items-center">
      <select id="mbTemplateId" class="flex-1 border rounded px-2 py-1.5">
        <option value="">— بدون قالب —</option>
        @foreach($templates as $t)
          <option value="{{ $t->id }}">{{ $t->name }}</option>
        @endforeach
      </select>
      <select id="mbTemplateCase" class="flex-1 border rounded px-2 py-1.5">
        <option value="">پرونده برای جای‌نگه‌دارها (اختیاری)</option>
        @foreach($cases as $c)
          <option value="{{ $c->id }}">{{ $c->case_number }} — {{ \Illuminate\Support\Str::limit($c->title, 30) }}</option>
        @endforeach
      </select>
      <button type="button" id="mbTemplateApply" class="btn btn-sm">درج در متن</button>
    </div>
    <p class="text-xs text-gray-500 mt-1">قالب انتخابی به ابتدای متن نامه اضافه می‌شود؛ جای‌نگه‌دارها فقط وقتی پرونده انتخاب شود پر می‌شوند.</p>
  </div>

  <div>
    <label class="block mb-1">متن *</label>
    <textarea name="body" id="mbBody" required rows="10" class="w-full border rounded px-3 py-2">{{ old('body', $prefill['body']) }}</textarea>
  </div>

  <div class="border rounded-lg p-3 bg-slate-50">
    <div class="font-semibold mb-2">پیوست‌ها</div>
    <div class="mb-3 text-xs">
      <div class="muted mb-1">پرونده برای انتخاب پیوست/سند:</div>
      <select id="mbCaseId" class="w-full border rounded px-2 py-1">
        <option value="">—</option>
        @foreach($cases as $c)
          <option value="{{ $c->id }}">{{ $c->case_number }} — {{ \Illuminate\Support\Str::limit($c->title, 40) }}</option>
        @endforeach
      </select>
    </div>
    <div id="mbDocBox" class="mb-3 text-xs">
      <div class="muted mb-1">از اسناد پرونده (فایل واقعی Word/Excel همان نسخه):</div>
      <div id="mbDocList" class="space-y-1"><span class="muted">پرونده را انتخاب کنید تا اسناد لود شوند.</span></div>
    </div>
    <div id="mbAttBox" class="mb-3 text-xs">
      <div class="muted mb-1">از پیوست‌های پرونده:</div>
      <div id="mbAttList" class="space-y-1"><span class="muted">پرونده را انتخاب کنید تا پیوست‌ها لود شوند.</span></div>
    </div>
    <div>
      <label class="block mb-1">آپلود فایل از خارج از سیستم</label>
      <input type="file" name="files[]" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.zip,.xls,.xlsx" class="w-full text-xs">
    </div>
  </div>

  <button class="bg-blue-600 text-white px-4 py-2 rounded">ارسال</button>
</form>
</div>
<script>
(function(){
  const caseSel = document.getElementById('mbCaseId');
  const attList = document.getElementById('mbAttList');
  const docList = document.getElementById('mbDocList');
  caseSel?.addEventListener('change', async () => {
    const id = caseSel.value;
    if (!id) {
      attList.innerHTML = '<span class="muted">پرونده را انتخاب کنید.</span>';
      docList.innerHTML = '<span class="muted">پرونده را انتخاب کنید.</span>';
      return;
    }
    attList.innerHTML = 'در حال بارگذاری…';
    docList.innerHTML = 'در حال بارگذاری…';
    try {
      const res = await fetch('/emails/case-attachments/' + id, { headers: { 'Accept': 'application/json' } });
      const data = await res.json();
      attList.innerHTML = data.length
        ? data.map(a => `<label class="flex items-center gap-2"><input type="checkbox" name="attachment_ids[]" value="${a.id}"><span>${a.file_name}</span></label>`).join('')
        : '<span class="muted">این پرونده پیوستی ندارد.</span>';
    } catch (e) {
      attList.innerHTML = '<span class="text-red-600">خطا در بارگذاری پیوست‌ها</span>';
    }
    try {
      const res2 = await fetch('/emails/case-documents/' + id, { headers: { 'Accept': 'application/json' } });
      const docs = await res2.json();
      docList.innerHTML = docs.length
        ? docs.map(d => `<label class="flex items-center gap-2"><input type="checkbox" name="document_revision_ids[]" value="${d.revision_id}"><span>${d.document_number} — ${d.type}</span></label>`).join('')
        : '<span class="muted">این پرونده سند دانلودپذیری ندارد.</span>';
    } catch (e) {
      docList.innerHTML = '<span class="text-red-600">خطا در بارگذاری اسناد</span>';
    }
  });

  const tplId = document.getElementById('mbTemplateId');
  const tplCase = document.getElementById('mbTemplateCase');
  const tplBtn = document.getElementById('mbTemplateApply');
  const body = document.getElementById('mbBody');
  tplBtn?.addEventListener('click', async () => {
    if (!tplId.value) return;
    tplBtn.disabled = true;
    tplBtn.textContent = '...';
    try {
      const url = '/mailbox/template/' + tplId.value + (tplCase.value ? ('?case_id=' + tplCase.value) : '');
      const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
      const data = await res.json();
      if (data.ok) {
        body.value = (data.body || '') + '\n\n' + body.value;
      } else {
        alert(data.message || 'خطا در بارگذاری قالب');
      }
    } catch (e) {
      alert('خطا در بارگذاری قالب');
    } finally {
      tplBtn.disabled = false;
      tplBtn.textContent = 'درج در متن';
    }
  });
})();
</script>
@endsection
