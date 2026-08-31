@extends('layouts.app')
@section('title','خلاصه مطالبات')
@section('actions')<x-btn variant="ghost" href="{{ route('reports.index') }}">بازگشت</x-btn>@endsection
@section('content')
<div class="grid-stats">
  <x-stat label="معوق" :value="$overdue->count()" tone="danger" />
  <x-stat label="سررسید ۷ روز" :value="$thisWeek->count()" tone="warning" />
  <x-stat label="وصول‌شده این ماه" :value="$paidMonth->count()" tone="brand" />
  <x-stat label="کل مطالبات" :value="$all->count()" />
</div>
<div class="card"><div class="card-h">معوق‌ها</div><div class="card-b pad0">
<table class="tbl"><thead><tr><th>پرونده</th><th>مبلغ</th><th>پرداخت‌شده</th><th>سررسید</th><th>وضعیت</th></tr></thead>
<tbody>
@forelse($overdue as $r)
<tr><td>{{ $r->case?->case_number ?? $r->case_id }}</td>
<td>{{ number_format($r->amount) }} {{ currency_label($r->currency) }}</td>
<td>{{ number_format($r->paid_amount ?? 0) }}</td>
<td style="color:var(--danger)">{{ $r->due_date ? jdate($r->due_date) : '—' }}</td>
<td>{{ $r->status }}</td></tr>
@empty<tr><td colspan="5"><x-empty title="معوقی نیست" /></td></tr>@endforelse
</tbody></table>
</div></div>
@endsection
