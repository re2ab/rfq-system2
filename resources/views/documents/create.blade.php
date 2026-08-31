@extends('layouts.app')
@section('title', 'ایجاد سند')
@section('actions')
  <x-btn variant="ghost" href="{{ route('documents.index') }}">بازگشت</x-btn>
@endsection

@section('content')
@php
  $templates = \Illuminate\Support\Facades\DB::table('templates')
    ->whereIn('type', ['technical_proposal','financial_proposal','invoice'])
    ->orderBy('type')->orderByDesc('is_default')->get();
@endphp
<form method="POST" action="{{ route('documents.store') }}" class="card" id="docForm">@csrf
  <div class="card-h">اطلاعات سند</div>
  <div class="card-b space-y-3 text-sm">
    <div class="rfq-grid-2">
      <div>
        <label class="block mb-1 font-semibold">پرونده *</label>
        <select name="case_id" required class="w-full border rounded px-3 py-2">
          @foreach($cases as $c)
            <option value="{{ $c->id }}" @selected(($caseId ?? null)==$c->id)>{{ $c->case_number }} — {{ $c->title }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="block mb-1 font-semibold">نوع سند *</label>
        <select name="type" id="docType" required class="w-full border rounded px-3 py-2">
          <option value="technical_proposal">پیشنهاد فنی</option>
          <option value="financial_proposal">پیشنهاد مالی</option>
          <option value="invoice">فاکتور فروش</option>
        </select>
      </div>
    </div>
    <div>
      <label class="block mb-1 font-semibold">قالب بصری</label>
      <select name="template_id" id="templateId" class="w-full border rounded px-3 py-2">
        <option value="">— پیش‌فرض نوع سند —</option>
        @foreach($templates as $tpl)
          <option value="{{ $tpl->id }}" data-type="{{ $tpl->type }}">{{ $tpl->name }} ({{ $tpl->type }})@if($tpl->is_default) ★@endif</option>
        @endforeach
      </select>
      <p class="text-xs text-muted" style="color:var(--muted);margin-top:4px">قالب‌ها را از تنظیمات → قالب‌ها با ادیتور حرفه‌ای بسازید.</p>
    </div>
    <div><label class="block mb-1 font-semibold">عنوان</label><input name="title" class="w-full border rounded px-3 py-2"></div>
    <div class="rfq-grid-2">
      <div>
        <label class="block mb-1 font-semibold">ارز</label>
        <select name="currency" class="w-full border rounded px-3 py-2"><option value="EUR">یورو</option><option value="IRR">ریال</option></select>
      </div>
      <div>
        <label class="block mb-1 font-semibold">ترم تحویل</label>
        <select name="incoterm" class="w-full border rounded px-3 py-2">
          <option value="">—</option>
          @foreach(['CPT','CFR','DDP','FOB','EXW','CIF'] as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
        </select>
      </div>
    </div>
    <div><label class="block mb-1 font-semibold">مبلغ خالص</label><input type="number" step="0.01" name="net_amount" class="w-full border rounded px-3 py-2"></div>
    <label class="flex gap-2 items-center"><input type="checkbox" name="use_default_template" value="1" id="useTpl" checked> پر کردن محتوا از قالب انتخاب‌شده / پیش‌فرض</label>
    <div>
      <label class="block mb-1 font-semibold">محتوای سند (ادیتور حرفه‌ای)</label>
      <textarea name="content" id="docContent" rows="12" class="w-full"></textarea>
    </div>
    <x-btn type="submit">ایجاد سند</x-btn>
  </div>

<div id="docLinesBox" class="card mt-4" style="display:none">
  <div class="card-h">ردیف‌های کالا / خدمات (محاسبه خودکار)</div>
  <div class="card-b text-sm">
    <p class="muted mb-2">برای پیشنهاد مالی و فاکتور: شرح، تعداد و قیمت واحد را وارد کنید. جمع ردیف و جمع کل خودکار محاسبه می‌شود.</p>
    <div class="mb-2">
      <label class="text-xs font-semibold">درصد ارزش افزوده (VAT)</label>
      <input type="number" step="0.01" min="0" max="100" name="vat_percent" id="vatPercent" value="0" class="border rounded px-2 py-1 w-32">
    </div>
    <table class="tbl" id="docLines">
      <thead><tr><th>شرح</th><th>واحد</th><th>تعداد</th><th>قیمت واحد</th><th>جمع ردیف</th><th></th></tr></thead>
      <tbody></tbody>
      <tfoot>
        <tr><td colspan="4" style="text-align:left;font-weight:800">جمع خالص</td><td id="sumNet" style="font-weight:800">0</td><td></td></tr>
        <tr><td colspan="4" style="text-align:left">مالیات</td><td id="sumVat">0</td><td></td></tr>
        <tr><td colspan="4" style="text-align:left;font-weight:800;color:var(--brand)">جمع کل</td><td id="sumGross" style="font-weight:800;color:var(--brand)">0</td><td></td></tr>
      </tfoot>
    </table>
    <button type="button" class="btn btn-ghost btn-sm mt-2" id="addLineBtn">+ ردیف</button>
  </div>
</div>
<script>
(function(){
  const box = document.getElementById('docLinesBox');
  const typeSel = document.querySelector('[name="type"]');
  const tbody = document.querySelector('#docLines tbody');
  let i = 0;
  function showBox(){
    const t = typeSel?.value;
    box.style.display = (t === 'financial_proposal' || t === 'invoice') ? 'block' : 'none';
  }
  function rowHtml(idx){
    return `<tr>
      <td><input name="lines[${idx}][description]" class="w-full border rounded px-2 py-1" placeholder="شرح کالا"></td>
      <td><input name="lines[${idx}][unit]" value="عدد" class="w-full border rounded px-2 py-1" style="width:70px"></td>
      <td><input type="number" step="0.001" name="lines[${idx}][quantity]" value="1" class="qty border rounded px-2 py-1" style="width:90px"></td>
      <td><input type="number" step="0.01" name="lines[${idx}][unit_price]" value="0" class="price border rounded px-2 py-1" style="width:110px"></td>
      <td class="lineTotal font-bold">0</td>
      <td><button type="button" class="text-red-600 text-xs rm">حذف</button></td>
    </tr>`;
  }
  function recalc(){
    let net = 0;
    tbody.querySelectorAll('tr').forEach(tr => {
      const q = parseFloat(tr.querySelector('.qty')?.value || 0);
      const p = parseFloat(tr.querySelector('.price')?.value || 0);
      const tot = Math.round(q * p * 100) / 100;
      const cell = tr.querySelector('.lineTotal');
      if (cell) cell.textContent = tot.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
      net += tot;
    });
    const vatP = parseFloat(document.getElementById('vatPercent')?.value || 0);
    const vat = Math.round(net * vatP) / 100;
    const gross = Math.round((net + vat) * 100) / 100;
    document.getElementById('sumNet').textContent = net.toFixed(2);
    document.getElementById('sumVat').textContent = vat.toFixed(2);
    document.getElementById('sumGross').textContent = gross.toFixed(2);
  }
  function bindRow(tr){
    tr.querySelectorAll('.qty,.price').forEach(el => el.addEventListener('input', recalc));
    tr.querySelector('.rm')?.addEventListener('click', () => { tr.remove(); recalc(); });
  }
  document.getElementById('addLineBtn')?.addEventListener('click', () => {
    tbody.insertAdjacentHTML('beforeend', rowHtml(i++));
    bindRow(tbody.lastElementChild);
    recalc();
  });
  document.getElementById('vatPercent')?.addEventListener('input', recalc);
  typeSel?.addEventListener('change', showBox);
  // seed one row
  tbody.insertAdjacentHTML('beforeend', rowHtml(i++));
  bindRow(tbody.lastElementChild);
  showBox(); recalc();
})();
</script>

</form>
@include('partials.tinymce')
@push('scripts')
<script>
initRfqEditor('#docContent', { height: 420 });
document.getElementById('docType').addEventListener('change', function(){
  const t = this.value;
  document.querySelectorAll('#templateId option').forEach(o=>{
    if (!o.value) return;
    o.hidden = o.dataset.type && o.dataset.type !== t;
  });
});
document.getElementById('docType').dispatchEvent(new Event('change'));
</script>
@endpush
@endsection
