@extends('layouts.app')
@section('title','ممیزی تغییر وضعیت')
@section('actions')<x-btn variant="ghost" href="{{ route('reports.index') }}">بازگشت</x-btn>@endsection
@section('content')
@include('reports._date_filter')
<div class="rfq-grid-2">
<div class="card"><div class="card-h">انتقال‌های پرتکرار</div><div class="card-b pad0">
<table class="tbl"><thead><tr><th>از</th><th>به</th><th>تعداد</th></tr></thead>
<tbody>
@forelse($rows as $r)
<tr><td>{{ $r->from_status ?: '—' }}</td><td>{{ $r->to_status }}</td><td style="font-weight:800">{{ $r->total }}</td></tr>
@empty<tr><td colspan="3"><x-empty title="تغییری نیست" /></td></tr>@endforelse
</tbody></table>
</div></div>
<div class="card"><div class="card-h">آخرین تغییرات</div><div class="card-b pad0">
<table class="tbl"><thead><tr><th>پرونده</th><th>از→به</th><th>زمان</th></tr></thead>
<tbody>
@foreach($recent as $r)
<tr><td>{{ $r->case_id }}</td><td>{{ $r->from_status }} → {{ $r->to_status }}</td><td>{{ jdatetime($r->created_at) }}</td></tr>
@endforeach
</tbody></table>
</div></div>
</div>
@endsection
