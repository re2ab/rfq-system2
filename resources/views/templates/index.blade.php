@extends('layouts.app')
@section('title', 'قالب‌های سند')
@section('actions')
  @can('template.create')
    <x-btn href="{{ route('templates.create') }}">قالب جدید</x-btn>
  @endcan
@endsection

@section('content')
<form method="GET" class="rfq-filters rfq-filters-stack">
  <div class="rfq-filters-meta">
    <select name="document_type_id" class="rfq-f-select" size="1">
      <option value="">همه انواع سند</option>
      @foreach($documentTypes as $dt)
        <option value="{{ $dt->id }}" @selected(request('document_type_id')==$dt->id)>{{ $dt->name_fa }}</option>
      @endforeach
    </select>
    <select name="file_type" class="rfq-f-select" size="1">
      <option value="">همه فرمت‌ها</option>
      <option value="docx" @selected(request('file_type')==='docx')>Word (.docx)</option>
      <option value="xlsx" @selected(request('file_type')==='xlsx')>Excel (.xlsx)</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm rfq-f-btn">فیلتر</button>
  </div>
</form>

<div class="card" style="overflow:hidden">
  <div class="data-table-desktop">
    <table class="tbl">
      <thead>
        <tr>
          <th>نام</th>
          <th>نوع سند</th>
          <th>فرمت</th>
          <th>نسخه فعلی</th>
          <th>وضعیت</th>
          <th>پیش‌فرض</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse($templates as $tpl)
        <tr style="cursor:pointer" onclick="location.href='{{ route('templates.show', $tpl) }}'">
          <td style="font-weight:800">{{ $tpl->name }}</td>
          <td>{{ $tpl->documentType->name_fa ?? '—' }}</td>
          <td><x-badge tone="muted">{{ strtoupper($tpl->file_type ?? '—') }}</x-badge></td>
          <td>{{ $tpl->currentVersion?->version_number ? 'v'.$tpl->currentVersion->version_number : '—' }}</td>
          <td>
            @if($tpl->status === 'active')
              <x-badge tone="ok">فعال</x-badge>
            @else
              <x-badge tone="muted">غیرفعال</x-badge>
            @endif
          </td>
          <td>@if($tpl->is_default)<x-badge tone="brand">پیش‌فرض</x-badge>@else — @endif</td>
          <td onclick="event.stopPropagation()"></td>
        </tr>
        @empty
        <tr><td colspan="7">
          <x-empty title="قالبی یافت نشد" :action="route('templates.create')" actionLabel="قالب جدید">
            یک فایل Word یا Excel واقعی را به‌عنوان قالب وارد کنید.
          </x-empty>
        </td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="data-table-mobile">
    @forelse($templates as $tpl)
    <a href="{{ route('templates.show', $tpl) }}" class="mobile-list-card">
      <div style="display:flex;justify-content:space-between">
        <strong>{{ $tpl->name }}</strong>
        <x-badge tone="muted">{{ strtoupper($tpl->file_type ?? '—') }}</x-badge>
      </div>
      <div class="rel-meta">{{ $tpl->documentType->name_fa ?? '—' }} · {{ $tpl->status === 'active' ? 'فعال' : 'غیرفعال' }}@if($tpl->is_default) · پیش‌فرض @endif</div>
    </a>
    @empty
      <x-empty title="قالبی نیست" :action="route('templates.create')" actionLabel="قالب جدید" />
    @endforelse
  </div>
</div>
@if(method_exists($templates, 'links'))
  <div class="rfq-pagination">{{ $templates->withQueryString()->links() }}</div>
@endif
@endsection
