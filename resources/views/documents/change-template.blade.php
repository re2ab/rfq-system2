@extends('layouts.app')
@section('title', 'تغییرِ قالب سند')
@section('actions')
  <x-btn variant="ghost" href="{{ route('documents.show', $document) }}">بازگشت به سند</x-btn>
@endsection

@section('content')
<div class="space-y-4">

@if($errors->any())
  <x-alert type="error">
    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
  </x-alert>
@endif

<x-alert type="warning">
  @if($mode === 'in_place')
    این فرم قالبِ همین Draft (نسخه‌ی {{ $revision->revision_number }}) را تغییر می‌دهد. چون ساختارِ قالب‌ها متفاوت است، فایل <b>کاملاً از نو</b> ساخته می‌شود — فیلدهای خودکار دوباره از پرونده/سند پر می‌شوند، ولی هر ویرایشِ دستی‌ای که خودتان قبلاً روی همین فایلِ Word/Excel انجام داده باشید (مثلاً با «آپلودِ نسخه‌ی ویرایش‌شده» یا ویرایشِ آنلاین) از بین می‌رود.
  @else
    این فرم یک <b>Draftِ تازه</b> از رویِ همین نسخه می‌سازد، ولی به‌جای کپیِ فایل، آن را با قالبِ انتخابی‌تان از نو می‌سازد — نسخه‌ی فعلی دست‌نخورده می‌ماند.
  @endif
</x-alert>

<form method="GET" action="{{ route('documents.revisions.template-form', $revision) }}" class="card">
  <input type="hidden" name="mode" value="{{ $mode }}">
  <div class="card-h">۱. انتخابِ قالبِ تازه</div>
  <div class="card-b space-y-3 text-sm">
    <div>
      <label class="block mb-1 font-semibold">قالب *</label>
      @if($templates->isEmpty())
        <x-empty title="قالبِ فعالِ دیگری برای نوعِ این سند نیست">
          @can('template.create')
            یک فایل Word یا Excel واقعیِ دیگر را از <a href="{{ route('templates.create') }}">صفحه‌ی قالب‌ها</a> برای همین نوعِ سند وارد کنید.
          @else
            با مدیر سیستم تماس بگیرید تا قالبِ دیگری برای این نوع سند وارد کند.
          @endcan
        </x-empty>
      @else
        <select name="template_id" required class="w-full border rounded px-3 py-2" onchange="this.form.submit()">
          <option value="">— انتخاب کنید —</option>
          @foreach($templates as $tpl)
            <option value="{{ $tpl->id }}" @selected($templateId == $tpl->id)>{{ $tpl->name }}@if($tpl->is_default) (پیش‌فرض){{ '' }}@endif — {{ strtoupper($tpl->file_type) }}</option>
          @endforeach
        </select>
      @endif
    </div>
    <noscript><button type="submit" class="btn btn-ghost btn-sm">به‌روزرسانی</button></noscript>
  </div>
</form>

@if($templateVersion)
<form method="POST" action="{{ route('documents.revisions.template-store', $revision) }}" class="card">
  @csrf
  <input type="hidden" name="mode" value="{{ $mode }}">
  <input type="hidden" name="template_id" value="{{ $templateId }}">
  <div class="card-h">۲. تکمیلِ اطلاعات و ساخت</div>
  <div class="card-b space-y-3 text-sm">

    @if($manualFields->isNotEmpty())
      <p style="color:var(--muted)">این مقادیر مستقیم در قالبِ تازه جایگزین می‌شوند و به داده‌ی سیستمی متصل نیستند:</p>
      @foreach($manualFields as $f)
        <div>
          <label class="block mb-1 font-semibold">{{ $f->label ?: $f->key }}@if($f->is_required) *@endif</label>
          <input name="manual[{{ $f->key }}]" value="{{ old('manual.'.$f->key, $revision->data[$f->key] ?? $f->default_value) }}" @if($f->is_required) required @endif class="w-full border rounded px-3 py-2">
        </div>
      @endforeach
    @else
      <p style="color:var(--muted)">این قالب فیلدِ دستی ندارد — همه‌ی جای‌نگه‌دارها خودکار از پرونده/سند پر می‌شوند.</p>
    @endif

    @if($supportsLines)
      <div>
        <label class="block mb-1 font-semibold">درصد ارزش افزوده (VAT)</label>
        <input type="number" step="0.01" min="0" max="100" name="vat_percent" value="{{ old('vat_percent', $document->vat_percent ?? 0) }}" class="border rounded px-2 py-1 w-32">
      </div>
      <p style="color:var(--muted)">این قالب جدولِ اقلام دارد — ردیف‌های زیر جایگزینِ ردیف‌های فعلیِ همین سند می‌شوند:</p>
      <table class="tbl" id="tplLines">
        <thead><tr><th>شرح</th><th>واحد</th><th>تعداد</th><th>قیمت واحد</th><th></th></tr></thead>
        <tbody></tbody>
      </table>
      <button type="button" class="btn btn-ghost btn-sm" id="tplAddLine">+ ردیف</button>
    @endif

    <x-btn type="submit">{{ $mode === 'in_place' ? 'ساختِ فایل با این قالب' : 'ساختِ Draftِ تازه با این قالب' }}</x-btn>
  </div>
</form>
@php
  // M38 (رفعِ باگ): @json(...) در Blade، آرگومانش را با یک explode(',', ...)
  // ساده در بالاترین سطح جدا می‌کند — یعنی هر کاما، حتی کاماهای داخلِ یک
  // آرایه‌ی PHP مثلِ ['description','unit',...]، اشتباهاً به‌عنوانِ جداکننده‌ی
  // آرگومان‌های خودِ @json (مقدار/options/depth) خوانده می‌شد. نتیجه: PHP
  // کامپایل‌شده ناقص/نامعتبر بود و خروجیِ نهایی در مرورگر با خطای
  // «Unclosed '[' does not match ')'» می‌شکست. اصلاح: مقدار اول در یک متغیرِ
  // ساده (بدون هیچ کاما) محاسبه می‌شود، بعد @json($existingLines) — دقیقاً
  // فقط یک شناسه، بدون کاما — صدا زده می‌شود.
  $existingLines = $supportsLines
    ? $document->lines()->orderBy('sort_order')->get(['description', 'unit', 'quantity', 'unit_price'])
    : collect();
@endphp
@push('scripts')
<script>
(function(){
  const tbody = document.querySelector('#tplLines tbody');
  if (!tbody) return;
  const existing = @json($existingLines);
  let i = 0;
  function rowHtml(idx, row){
    row = row || {};
    const esc = (s) => String(s ?? '').replace(/"/g, '&quot;');
    return `<tr>
      <td><input name="lines[${idx}][description]" class="w-full border rounded px-2 py-1" placeholder="شرح کالا" value="${esc(row.description)}"></td>
      <td><input name="lines[${idx}][unit]" value="${esc(row.unit || 'عدد')}" class="border rounded px-2 py-1" style="width:70px"></td>
      <td><input type="number" step="0.001" name="lines[${idx}][quantity]" value="${esc(row.quantity ?? 1)}" class="border rounded px-2 py-1" style="width:90px"></td>
      <td><input type="number" step="0.01" name="lines[${idx}][unit_price]" value="${esc(row.unit_price ?? 0)}" class="border rounded px-2 py-1" style="width:110px"></td>
      <td><button type="button" class="text-red-600 text-xs rm">حذف</button></td>
    </tr>`;
  }
  function addRow(row){
    tbody.insertAdjacentHTML('beforeend', rowHtml(i++, row));
    tbody.lastElementChild.querySelector('.rm')?.addEventListener('click', function(){ this.closest('tr').remove(); });
  }
  document.getElementById('tplAddLine')?.addEventListener('click', () => addRow());
  if (existing.length) {
    existing.forEach(addRow);
  } else {
    addRow();
  }
})();
</script>
@endpush
@endif

</div>
@endsection
