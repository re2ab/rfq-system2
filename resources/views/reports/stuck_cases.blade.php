@extends('layouts.app')
@section('title', 'پرونده‌های ماندگار در وضعیت')
@section('actions')
  <x-btn variant="ghost" href="{{ route('reports.index') }}">بازگشت</x-btn>
@endsection
@section('content')
<form method="GET" class="filter-bar" style="margin-bottom:12px">
  <label class="text-sm font-semibold">حداقل روز در وضعیت فعلی</label>
  <input type="number" name="days" min="1" value="{{ $days }}" class="filter-input" style="width:90px">
  <button type="submit" class="btn btn-primary btn-sm">اجرا</button>
</form>
<div class="card">
  <div class="card-h">پرونده‌های باز که بیش از {{ $days }} روز به‌روز نشده‌اند</div>
  <div class="card-b pad0">
    <table class="tbl">
      <thead><tr><th>شماره</th><th>عنوان</th><th>وضعیت</th><th>مشتری</th><th>کارشناس</th><th>روز در وضعیت</th></tr></thead>
      <tbody>
        @forelse($cases as $c)
        <tr style="cursor:pointer" onclick="location.href='{{ route('cases.show', $c) }}'">
          <td style="font-weight:800;color:var(--brand)">{{ $c->case_number }}</td>
          <td>{{ \Illuminate\Support\Str::limit($c->title, 40) }}</td>
          <td><x-status-badge :status="$c->current_status" /></td>
          <td>{{ $c->customer?->name ?? '—' }}</td>
          <td>{{ $c->expert?->name ?? '—' }}</td>
          <td style="font-weight:800;color:var(--warning,#d97706)">{{ $c->days_in_status }}</td>
        </tr>
        @empty
        <tr><td colspan="6"><x-empty title="پرونده ماندگاری با این معیار نیست" /></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
