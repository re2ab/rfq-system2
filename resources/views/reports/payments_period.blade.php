@extends('layouts.app')
@section('title','پرداخت‌ها در بازه')
@section('actions')<x-btn variant="ghost" href="{{ route('reports.index') }}">بازگشت</x-btn>@endsection
@section('content')
@include('reports._date_filter')
<div class="grid-stats"><x-stat label="جمع پرداخت‌ها" :value="number_format($sum)" tone="brand" /></div>
<div class="card"><div class="card-h">فهرست</div><div class="card-b pad0">
<table class="tbl"><thead><tr><th>ID</th><th>مبلغ</th><th>تاریخ</th></tr></thead>
<tbody>
@forelse($payments as $p)
<tr><td>{{ $p->id }}</td><td>{{ number_format($p->amount ?? 0) }}</td><td>{{ $p->paid_at ?? $p->created_at ?? '—' }}</td></tr>
@empty<tr><td colspan="3"><x-empty title="پرداختی نیست" /></td></tr>@endforelse
</tbody></table>
</div></div>
@endsection
