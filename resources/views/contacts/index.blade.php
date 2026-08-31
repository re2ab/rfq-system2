@extends('layouts.app')
@section('object', 'مخاطبان')
@section('title', 'مخاطبان')
@section('actions')
  <a href="{{ route('contacts.create') }}" class="btn btn-primary">مخاطب جدید</a>
  <a href="{{ route('organizations.index') }}" class="btn btn-ghost">سازمان‌ها</a>
  <a href="{{ route('contacts.export') }}" class="btn btn-ghost">خروجی CSV</a>
@endsection

@section('content')
<form method="GET" class="rfq-filters rfq-filters-stack" id="contactsFilters">
  @csrf
  <div class="rfq-filters-search">
    <input type="text" name="q" value="{{ request('q') }}" placeholder="جستجو در نام، ایمیل، تلفن…" class="rfq-f-input">
    <button type="submit" class="btn btn-primary btn-sm rfq-f-btn">جستجو</button>
    <x-bulk-toolbar form-id="contactsFilters" :action="route('contacts.bulk-action')" />
  </div>
</form>



<div class="card hide-on-mobile">
  <div class="card-b pad0">
    <table class="tbl">
      <thead>
        <tr>
          <th style="width:36px"><input type="checkbox" class="bulk-select-all" data-form-target="contactsFilters"></th>
          <th style="text-align:right">نام</th>
          <th style="text-align:center">سمت</th>
          <th style="text-align:center">سازمان</th>
          <th style="text-align:center">تلفن / موبایل</th>
          <th style="text-align:center">ایمیل</th>
        </tr>
      </thead>
      <tbody>
        @forelse($contacts as $contact)
        <tr style="cursor:pointer" onclick="window.location='{{ route('contacts.card', $contact) }}'">
          <td onclick="event.stopPropagation()"><input type="checkbox" class="bulk-row-check" form="contactsFilters" name="ids[]" value="{{ $contact->id }}"></td>
          <td class="font-medium" style="text-align:right">{{ $contact->full_name }}</td>
          <td style="text-align:center">{{ $contact->position ?? '—' }}</td>
          <td style="text-align:center">{{ $contact->organization?->name ?? '—' }}</td>
          <td style="text-align:center">{{ $contact->mobile ?? $contact->phone ?? '—' }}</td>
          <td style="text-align:center">{{ $contact->email ?? '—' }}</td>
        </tr>
        @empty
        <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:24px">مخاطبی یافت نشد.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
<div class="list-cards card hide-on-desktop" style="overflow:hidden">
  @forelse($contacts as $contact)
  <a href="{{ route('contacts.card', $contact) }}" class="list-card">
    <div class="list-card-top">
      <span class="list-card-title" style="margin:0">{{ $contact->full_name }}</span>
      <span class="badge">{{ $contact->position ?? '—' }}</span>
    </div>
    <div class="list-card-meta">{{ $contact->organization?->name ?? '—' }}</div>
    <div class="list-card-meta" style="margin-top:2px">{{ $contact->mobile ?? $contact->phone ?? '—' }} · {{ $contact->email ?? '—' }}</div>
  </a>
  @empty
  <div style="padding:24px;text-align:center;color:var(--muted)">مخاطبی یافت نشد.</div>
  @endforelse
</div>

<div class="rfq-pagination">{{ $contacts->withQueryString()->links() }}</div>
@endsection
