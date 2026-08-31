@extends('layouts.app')
@section('title','زمان چرخه پرونده')
@section('actions')<x-btn variant="ghost" href="{{ route('reports.index') }}">بازگشت</x-btn>@endsection
@section('content')
@include('reports._date_filter')
<div class="grid-stats">
  <x-stat label="میانگین روز تا بستن/برد/باخت" :value="round($avg ?? 0,1)" tone="brand" />
</div>
<div class="card"><div class="card-h">نمونه‌ها</div><div class="card-b pad0">
<table class="tbl"><thead><tr><th>شماره</th><th>وضعیت</th><th>ایجاد</th><th>آخرین</th><th>روز</th></tr></thead>
<tbody>
@forelse($cases as $c)
<tr><td><a href="{{ route('cases.show',$c) }}">{{ $c->case_number }}</a></td>
<td>{{ $c->status_label }}</td>
<td>{{ $c->created_at?->format('Y-m-d') }}</td>
<td>{{ $c->updated_at?->format('Y-m-d') }}</td>
<td style="font-weight:800">{{ $c->cycle_days }}</td></tr>
@empty<tr><td colspan="5"><x-empty title="موردی نیست" /></td></tr>@endforelse
</tbody></table>
</div></div>
@endsection
