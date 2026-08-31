@extends('layouts.app')
@section('title', 'سازمان‌ها')
@section('actions')
  <a href="{{ route('organizations.create') }}" class="btn btn-primary">سازمان جدید</a>
  <a href="{{ route('contacts.index') }}" class="btn btn-ghost">مخاطبان</a>
@endsection
@section('content')
<form method="GET" class="rfq-filters rfq-filters-stack" id="orgsFilters">
  @csrf
  <div class="rfq-filters-search">
    <input type="text" name="q" value="{{ request('q') }}" placeholder="جستجوی نام یا ایمیل…" class="rfq-f-input">
    <button type="submit" class="btn btn-primary btn-sm rfq-f-btn">جستجو</button>
    <x-bulk-toolbar form-id="orgsFilters" :action="route('organizations.bulk-action')" />
  </div>
</form>
<div class="card" style="overflow:hidden">
  <div class="data-table-desktop">
    <table class="tbl">
      <thead><tr><th style="width:36px"><input type="checkbox" class="bulk-select-all" data-form-target="orgsFilters"></th><th style="text-align:right">نام</th><th style="text-align:center">نوع</th><th style="text-align:center">تلفن</th><th style="text-align:center">ایمیل</th></tr></thead>
      <tbody>
      @forelse($organizations as $org)
      <tr style="cursor:pointer" onclick="location='{{ route('organizations.show', $org) }}'">
        <td onclick="event.stopPropagation()"><input type="checkbox" class="bulk-row-check" form="orgsFilters" name="ids[]" value="{{ $org->id }}"></td>
        <td class="font-medium" style="text-align:right">{{ $org->name }}</td>
        <td style="text-align:center"><span class="badge">{{ $org->type_label }}</span></td>
        <td style="text-align:center">{{ $org->phone ?? '—' }}</td>
        <td style="text-align:center">{{ $org->email ?? '—' }}</td>
      </tr>
      @empty
      <tr><td colspan="5" style="text-align:center;padding:24px;color:var(--muted)">سازمانی نیست</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  <div class="data-table-mobile">
    @forelse($organizations as $org)
    <a href="{{ route('organizations.show', $org) }}" class="mobile-list-card">
      <div style="display:flex;justify-content:space-between;gap:8px">
        <strong style="color:var(--brand)">{{ $org->name }}</strong>
        <span class="badge">{{ $org->type_label }}</span>
      </div>
      <div class="rel-meta" style="margin-top:6px">{{ $org->phone ?? '—' }}@if($org->email) · {{ $org->email }}@endif</div>
    </a>
    @empty
    <div style="padding:16px;text-align:center;color:var(--muted)">سازمانی نیست</div>
    @endforelse
  </div>
</div>
@if(method_exists($organizations, 'links'))
<div class="rfq-pagination">{{ $organizations->withQueryString()->links() }}</div>
@endif
@endsection
