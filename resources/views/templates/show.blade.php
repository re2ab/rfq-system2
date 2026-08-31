@extends('layouts.app')
@section('title', $template->name)
@section('actions')
  <x-btn variant="ghost" href="{{ route('templates.index') }}">بازگشت</x-btn>
@endsection

@section('content')
<div class="space-y-4">

@if(session('success'))<x-alert type="success">{{ session('success') }}</x-alert>@endif
@if($errors->any())
  <x-alert type="error">
    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
  </x-alert>
@endif

<div class="card">
  <div class="card-h" style="display:flex;justify-content:space-between;align-items:center">
    <span>{{ $template->name }}</span>
    <span style="display:flex;gap:6px">
      <x-badge tone="muted">{{ strtoupper($template->file_type ?? '—') }}</x-badge>
      @if($template->status === 'active')<x-badge tone="ok">فعال</x-badge>@else<x-badge tone="muted">غیرفعال</x-badge>@endif
      @if($template->is_default)<x-badge tone="brand">پیش‌فرض</x-badge>@endif
    </span>
  </div>
  <div class="card-b space-y-2 text-sm">
    <div style="display:flex;justify-content:space-between"><span style="color:var(--muted)">نوع سند</span><span>{{ $template->documentType->name_fa ?? '—' }}</span></div>
    <div style="display:flex;justify-content:space-between"><span style="color:var(--muted)">کد قالب</span><span>{{ $template->code ?? '—' }}</span></div>
    <div style="display:flex;justify-content:space-between"><span style="color:var(--muted)">نسخه فعلی</span><span>{{ $template->currentVersion?->version_number ? 'v'.$template->currentVersion->version_number : '—' }}</span></div>

    <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
      @can('template.set_default')
        @unless($template->is_default)
        <form method="POST" action="{{ route('templates.set-default', $template) }}">@csrf
          <button type="submit" class="btn btn-soft btn-sm">پیش‌فرض این نوع سند شود</button>
        </form>
        @endunless
      @endcan
      @can('template.edit')
        <form method="POST" action="{{ route('templates.activate', $template) }}">@csrf
          <input type="hidden" name="active" value="{{ $template->status === 'active' ? '0' : '1' }}">
          <button type="submit" class="btn btn-ghost btn-sm">{{ $template->status === 'active' ? 'غیرفعال کردن' : 'فعال کردن' }}</button>
        </form>
      @endcan
      @can('template.delete')
        <form method="POST" action="{{ route('templates.destroy', $template) }}" onsubmit="return confirm('این قالب حذف شود؟');">
          @csrf @method('DELETE')
          <button type="submit" class="btn btn-danger-soft btn-sm">حذف قالب</button>
        </form>
        @if(auth()->user()?->hasRole('admin'))
          {{-- فقط مدیر: اگر قالب به سند(های) واقعی وصل باشد، دکمه‌ی بالا با خطای
               محافظتی رد می‌شود — این گزینه‌ی جدا همان قالب را force حذف می‌کند
               (فقط ارجاعِ سندهای موجود به این قالب قطع می‌شود، خودِ فایل/شماره‌ی
               رسمی سندها دست‌نخورده می‌ماند). --}}
          <form method="POST" action="{{ route('templates.destroy', $template) }}" onsubmit="return confirm('این قالب حتی اگر قبلاً برای ساخت سند استفاده شده باشد حذف می‌شود (فقط ارتباط سندهای موجود با این قالب قطع می‌شود، خودشان حذف نمی‌شوند). این کار قابل بازگشت نیست — فقط برای پاک‌سازی قالب‌های اشتباه/تستی. ادامه؟');">
            @csrf @method('DELETE')
            <input type="hidden" name="force" value="1">
            <button type="submit" class="btn btn-ghost btn-sm" style="color:#b91c1c">حذف اجباری (فقط مدیر)</button>
          </form>
        @endif
      @endcan
    </div>
  </div>
</div>

@can('template.edit')
<form method="POST" action="{{ route('templates.versions.store', $template) }}" enctype="multipart/form-data" class="card">
  @csrf
  <div class="card-h">آپلود نسخه جدید</div>
  <div class="card-b space-y-2 text-sm">
    <p style="color:var(--muted)">فایل قبلی و اسنادی که به نسخه‌های قبلی وصل‌اند دست‌نخورده می‌مانند؛ فقط یک نسخه‌ی تازه اضافه می‌شود.</p>
    <input type="file" name="file" required accept=".docx,.xlsx" class="w-full border rounded px-3 py-2">
    <x-btn type="submit" size="sm">آپلود نسخه جدید</x-btn>
  </div>
</form>
@endcan

<div class="card">
  <div class="card-h">نسخه‌ها</div>
  <div class="card-b pad0">
    <table class="tbl">
      <thead><tr><th>نسخه</th><th>حجم</th><th>ایجادکننده</th><th>تاریخ</th></tr></thead>
      <tbody>
        @forelse($template->versions->sortByDesc('version_number') as $v)
        <tr>
          <td style="font-weight:700">v{{ $v->version_number }}@if($template->current_version_id === $v->id) <x-badge tone="brand" :dot="false">فعلی</x-badge>@endif</td>
          <td>{{ $v->file_size ? number_format($v->file_size / 1024, 1).' KB' : '—' }}</td>
          <td>{{ $v->creator->name ?? '—' }}</td>
          <td style="font-size:12px;color:var(--muted)">{{ $v->created_at?->diffForHumans() }}</td>
        </tr>
        @empty
        <tr><td colspan="4"><x-empty title="نسخه‌ای ثبت نشده" /></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

@php $currentFields = ($template->currentVersion?->fields ?? collect())->sortBy('sort_order'); @endphp

@can('template.edit')
<div class="card">
  <div class="card-h">جای‌نگه‌دارهای شناسایی‌شده (نسخه فعلی) — تعریف اتصال داده</div>
  <div class="card-b text-sm space-y-3">
    <div style="color:var(--muted);font-size:12px;line-height:1.9">
      هر جای‌نگه‌دار داخل فایل (مثلاً <code>@{{مبلغ}}</code>) باید بدانید از کجای داده پر شود. سه نوع منبع:
      <br>— <b>خودکار (auto)</b>: در ستون «اتصال داده» یک مسیر نقطه‌ای بنویسید — ریشه‌های معتبر:
      <code dir="ltr">case.case_number</code>, <code dir="ltr">case.title</code>,
      <code dir="ltr">case.organization.name</code>, <code dir="ltr">case.contact.name</code>,
      <code dir="ltr">document.title</code>, <code dir="ltr">revision.formatted_number</code>,
      <code dir="ltr">today</code>. اگر خالی بگذارید، خودِ کلید جای‌نگه‌دار به‌عنوان همین مسیر امتحان می‌شود.
      <br>— <b>دستی (manual)</b>: نیازی به اتصال داده نیست — هنگام «ساخت سند از قالب» یک کادر متن برای پرکردنش نشان داده می‌شود.
      <br>— <b>ردیف تکرارشونده (line)</b>: فقط برای اسناد دارای جدول اقلام (پیشنهاد مالی/فاکتور) — در ستون «اتصال داده»
      یکی از این‌ها را بنویسید: <code dir="ltr">description</code>, <code dir="ltr">unit</code>,
      <code dir="ltr">quantity</code>, <code dir="ltr">unit_price</code>, <code dir="ltr">line_total</code>.
    </div>

    @if($currentFields->isEmpty())
      <x-empty title="جای‌نگه‌داری در این فایل شناسایی نشد" />
    @else
    <form method="POST" action="{{ route('templates.fields.update', $template) }}">
      @csrf
      <div style="overflow-x:auto">
      <table class="tbl">
        <thead><tr><th>کلید</th><th>برچسب</th><th>منبع</th><th>اتصال داده / پیش‌فرض</th><th>نوع</th><th>الزامی</th></tr></thead>
        <tbody>
          @foreach($currentFields as $i => $f)
          <tr>
            <td style="font-family:monospace;white-space:nowrap">
              {{ $f->key }}
              <input type="hidden" name="fields[{{ $i }}][id]" value="{{ $f->id }}">
            </td>
            <td><input name="fields[{{ $i }}][label]" value="{{ $f->label }}" class="w-full border rounded px-2 py-1" required></td>
            <td>
              <select name="fields[{{ $i }}][source]" class="border rounded px-2 py-1">
                <option value="auto" @selected($f->source==='auto')>خودکار</option>
                <option value="manual" @selected($f->source==='manual')>دستی</option>
                <option value="line" @selected($f->source==='line')>ردیف تکرارشونده</option>
              </select>
            </td>
            <td>
              <input name="fields[{{ $i }}][binding]" value="{{ $f->binding }}" dir="ltr" placeholder="مثلاً case.organization.name" class="w-full border rounded px-2 py-1" style="font-family:monospace">
              <input name="fields[{{ $i }}][default_value]" value="{{ $f->default_value }}" placeholder="مقدار پیش‌فرض (اختیاری)" class="w-full border rounded px-2 py-1 mt-1">
            </td>
            <td>
              <select name="fields[{{ $i }}][data_type]" class="border rounded px-2 py-1">
                @foreach(['text'=>'متن','number'=>'عدد','date'=>'تاریخ','currency'=>'مبلغ'] as $dtVal => $dtLabel)
                  <option value="{{ $dtVal }}" @selected($f->data_type===$dtVal)>{{ $dtLabel }}</option>
                @endforeach
              </select>
            </td>
            <td style="text-align:center">
              <input type="checkbox" name="fields[{{ $i }}][is_required]" value="1" @checked($f->is_required)>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
      </div>
      <div class="mt-3">
        <x-btn type="submit" size="sm">ذخیره اتصال داده‌ها</x-btn>
      </div>
    </form>
    @endif
  </div>
</div>
@else
<div class="card">
  <div class="card-h">جای‌نگه‌دارهای شناسایی‌شده (نسخه فعلی)</div>
  <div class="card-b pad0">
    <table class="tbl">
      <thead><tr><th>کلید</th><th>برچسب</th><th>اتصال داده</th><th>نوع</th></tr></thead>
      <tbody>
        @forelse($currentFields as $f)
        <tr>
          <td style="font-family:monospace">{{ $f->key }}</td>
          <td>{{ $f->label }}</td>
          <td>@if($f->binding)<code>{{ $f->binding }}</code>@else<span style="color:var(--muted)">متصل‌نشده</span>@endif</td>
          <td><x-badge tone="muted" :dot="false">{{ $f->data_type }}</x-badge></td>
        </tr>
        @empty
        <tr><td colspan="4"><x-empty title="جای‌نگه‌داری در این فایل شناسایی نشد" /></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endcan

</div>
@endsection
