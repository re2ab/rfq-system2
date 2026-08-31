@extends('layouts.app')
@section('title', ($fileType ?? null) === 'xlsx' ? 'ساخت سند Excel از قالب' : (($fileType ?? null) === 'docx' ? 'ساخت سند Word از قالب' : 'ساخت سند از قالب'))
@section('actions')
  <x-btn variant="ghost" href="{{ route('documents.index') }}">بازگشت</x-btn>
@endsection

@section('content')
<div class="space-y-4">

@if($errors->any())
  <x-alert type="error">
    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
  </x-alert>
@endif

<form method="GET" action="{{ route('documents.generate.create') }}" class="card">
  <input type="hidden" name="file_type" value="{{ $fileType ?? '' }}">
  <div class="card-h">۱. پرونده، نوع سند و قالب</div>
  <div class="card-b space-y-3 text-sm">
    <div class="rfq-grid-2">
      <div>
        <label class="block mb-1 font-semibold">پرونده *</label>
        <select name="case_id" required class="w-full border rounded px-3 py-2" onchange="this.form.submit()">
          <option value="">— انتخاب کنید —</option>
          @foreach($cases as $c)
            <option value="{{ $c->id }}" @selected($caseId == $c->id)>{{ $c->case_number }} — {{ $c->title }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="block mb-1 font-semibold">نوع سند *</label>
        <select name="document_type_id" required class="w-full border rounded px-3 py-2" onchange="this.form.submit()">
          <option value="">— انتخاب کنید —</option>
          @foreach($documentTypes as $dt)
            <option value="{{ $dt->id }}" @selected($documentTypeId == $dt->id)>{{ $dt->name_fa }}</option>
          @endforeach
        </select>
      </div>
    </div>

    @if($documentTypeId)
      <div>
        <label class="block mb-1 font-semibold">قالب *</label>
        @if($templates->isEmpty())
          <x-empty title="قالب فعالی برای این نوع سند نیست">
            @can('template.create')
              یک فایل Word یا Excel واقعی را از <a href="{{ route('templates.create') }}">صفحه‌ی قالب‌ها</a> وارد کنید.
            @else
              با مدیر سیستم تماس بگیرید تا یک قالب برای این نوع سند وارد کند.
            @endcan
          </x-empty>
        @else
        <select name="template_id" required class="w-full border rounded px-3 py-2" onchange="this.form.submit()">
          <option value="">— انتخاب کنید —</option>
          @foreach($templates as $tpl)
            <option value="{{ $tpl->id }}" @selected($templateId == $tpl->id)>{{ $tpl->name }}@if($tpl->is_default) (پیش‌فرض)@endif</option>
          @endforeach
        </select>
        @endif
      </div>
    @endif
    <noscript><button type="submit" class="btn btn-ghost btn-sm">به‌روزرسانی</button></noscript>
  </div>
</form>

@if($templateVersion)
<form method="POST" action="{{ route('documents.generate.store') }}" class="card">
  @csrf
  <input type="hidden" name="case_id" value="{{ $caseId }}">
  <input type="hidden" name="document_type_id" value="{{ $documentTypeId }}">
  <input type="hidden" name="template_id" value="{{ $templateId }}">
  <div class="card-h">۲. تکمیل اطلاعات و ساخت سند</div>
  <div class="card-b space-y-3 text-sm">

    @if($manualFields->isNotEmpty())
      <p style="color:var(--muted)">این مقادیر مستقیم در قالب جایگزین می‌شوند و به داده‌ی سیستمی متصل نیستند:</p>
      @foreach($manualFields as $f)
        <div>
          <label class="block mb-1 font-semibold">{{ $f->label ?: $f->key }}@if($f->is_required) *@endif</label>
          <input name="manual[{{ $f->key }}]" value="{{ old('manual.'.$f->key, $f->default_value) }}" @if($f->is_required) required @endif class="w-full border rounded px-3 py-2">
        </div>
      @endforeach
    @else
      <p style="color:var(--muted)">این قالب فیلد دستی ندارد — همه‌ی جای‌نگه‌دارها خودکار از پرونده پر می‌شوند.</p>
    @endif

    @if($supportsLines)
      <div>
        <label class="block mb-1 font-semibold">درصد ارزش افزوده (VAT)</label>
        <input type="number" step="0.01" min="0" max="100" name="vat_percent" value="0" class="border rounded px-2 py-1 w-32">
      </div>
      <table class="tbl" id="genLines">
        <thead><tr><th>شرح</th><th>واحد</th><th>تعداد</th><th>قیمت واحد</th><th></th></tr></thead>
        <tbody></tbody>
      </table>
      <button type="button" class="btn btn-ghost btn-sm" id="genAddLine">+ ردیف</button>
    @endif

    <x-btn type="submit">ساخت سند</x-btn>
  </div>
</form>
@push('scripts')
<script>
(function(){
  const tbody = document.querySelector('#genLines tbody');
  if (!tbody) return;
  let i = 0;
  function rowHtml(idx){
    return `<tr>
      <td><input name="lines[${idx}][description]" class="w-full border rounded px-2 py-1" placeholder="شرح کالا"></td>
      <td><input name="lines[${idx}][unit]" value="عدد" class="border rounded px-2 py-1" style="width:70px"></td>
      <td><input type="number" step="0.001" name="lines[${idx}][quantity]" value="1" class="border rounded px-2 py-1" style="width:90px"></td>
      <td><input type="number" step="0.01" name="lines[${idx}][unit_price]" value="0" class="border rounded px-2 py-1" style="width:110px"></td>
      <td><button type="button" class="text-red-600 text-xs rm">حذف</button></td>
    </tr>`;
  }
  document.getElementById('genAddLine')?.addEventListener('click', () => {
    tbody.insertAdjacentHTML('beforeend', rowHtml(i++));
    tbody.lastElementChild.querySelector('.rm')?.addEventListener('click', function(){ this.closest('tr').remove(); });
  });
  document.getElementById('genAddLine')?.click();
})();
</script>
@endpush
@endif

</div>
@endsection
