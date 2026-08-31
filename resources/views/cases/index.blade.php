@extends('layouts.app')
@section('title', 'پرونده‌ها')
@section('actions')
  <x-btn variant="ghost" href="{{ route('cases.index', array_merge(request()->query(), ['export' => 1])) }}">خروجی</x-btn>
  <x-btn href="{{ route('cases.create') }}">پرونده جدید</x-btn>
@endsection

@section('content')

@php $savedViews = $savedViews ?? collect(); @endphp
@if($savedViews->count())
<div class="card hide-on-mobile" style="margin-bottom:12px;padding:10px 14px">
  <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
    <span style="font-size:12px;font-weight:800;color:var(--muted)">نماهای ذخیره‌شده:</span>
    @foreach($savedViews as $sv)
      <a href="{{ route('cases.index', $sv->filters ?? []) }}" class="btn btn-ghost btn-sm">{{ $sv->name }}</a>
    @endforeach
  </div>
</div>
@endif

<form method="GET" class="rfq-filters rfq-filters-stack" id="casesFilters">
  @csrf
  <div class="rfq-filters-search">
    <input type="text" name="q" value="{{ request('q') }}" placeholder="جستجو شماره، عنوان، مشتری…" class="rfq-f-input" autocomplete="off">
    <button type="submit" class="btn btn-primary btn-sm rfq-f-btn">جستجو</button>
    <x-bulk-toolbar form-id="casesFilters" :action="route('cases.bulk-action')" />
  </div>
  <div class="rfq-filters-meta">
    <select name="status" class="rfq-f-select" size="1" style="height:40px;max-height:40px;min-height:40px;line-height:40px;box-sizing:border-box;padding:0 28px 0 10px">
      <option value="">همه وضعیت‌ها</option>
      @foreach(\App\Models\CaseModel::statusLabels() as $key => $label)
        <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
      @endforeach
    </select>
    <select name="priority" class="rfq-f-select" size="1" style="height:40px;max-height:40px;min-height:40px;line-height:40px;box-sizing:border-box;padding:0 28px 0 10px">
      <option value="">اولویت</option>
      <option value="high" @selected(request('priority')==='high')>بالا</option>
      <option value="medium" @selected(request('priority')==='medium')>متوسط</option>
      <option value="low" @selected(request('priority')==='low')>پایین</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm rfq-f-btn">فیلتر</button>
  </div>
</form>

@php $count = method_exists($cases, 'total') ? $cases->total() : $cases->count(); @endphp

<div class="card" style="overflow:hidden;margin-top:12px">
  <div class="card-h">
    <span>فهرست پرونده‌ها <span class="rfq-page-count">{{ $count }}</span></span>
  </div>
  <div class="data-table-desktop">
    <table class="tbl">
      <thead>
        <tr>
          <th style="width:36px"><input type="checkbox" class="bulk-select-all" data-form-target="casesFilters"></th>
          <th>شماره</th>
          <th style="text-align:center">عنوان</th>
          <th style="text-align:center">مشتری</th>
          <th style="text-align:center">کارشناس</th>
          <th style="text-align:center">وضعیت</th>
          <th style="text-align:center">اولویت</th>
          <th style="text-align:center">به‌روزرسانی</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse($cases as $case)
        <tr style="cursor:pointer" onclick="location.href='{{ route('cases.show', $case) }}'">
          <td onclick="event.stopPropagation()"><input type="checkbox" class="bulk-row-check" form="casesFilters" name="ids[]" value="{{ $case->id }}"></td>
          <td style="font-weight:800;color:var(--brand)">{{ $case->case_number }}</td>
          <td style="text-align:center">{{ \Illuminate\Support\Str::limit($case->title, 40) }}</td>
          <td style="text-align:center">{{ $case->customer?->name ?? '—' }}</td>
          <td style="text-align:center">{{ $case->expert?->name ?? '—' }}</td>
          <td style="text-align:center"><x-status-badge :status="$case->current_status" /></td>
          <td style="text-align:center"><span class="badge" style="background:{{ $case->priority_color }}22;color:{{ $case->priority_color }};border:1px solid {{ $case->priority_color }}55;font-weight:700">{{ $case->priority_label }}</span></td>
          <td style="font-size:12px;color:var(--muted);text-align:center">{{ $case->updated_at?->diffForHumans() }}</td>
          <td onclick="event.stopPropagation()">
            <a href="{{ route('cases.edit', $case) }}" class="btn btn-ghost btn-sm">ویرایش</a>
          </td>
        </tr>
        @empty
        <tr><td colspan="9"><x-empty title="پرونده‌ای یافت نشد" :action="route('cases.create')" actionLabel="پرونده جدید" /></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="data-table-mobile">
    @forelse($cases as $case)
    <a href="{{ route('cases.show', $case) }}" class="mobile-list-card">
      <div style="display:flex;justify-content:space-between;gap:8px">
        <strong style="color:var(--brand)">{{ $case->case_number }}</strong>
        <x-status-badge :status="$case->current_status" />
      </div>
      <div style="font-weight:700;margin-top:4px">{{ $case->title }}</div>
      <div class="rel-meta">{{ $case->customer?->name ?? '—' }} · {{ $case->expert?->name ?? '—' }}</div>
    </a>
    @empty
      <x-empty title="پرونده‌ای یافت نشد" :action="route('cases.create')" actionLabel="پرونده جدید" />
    @endforelse
  </div>
</div>
@if(method_exists($cases, 'links'))
  <div class="rfq-pagination">{{ $cases->withQueryString()->links() }}</div>
@endif
@endsection
