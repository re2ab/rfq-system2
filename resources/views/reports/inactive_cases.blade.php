@extends('layouts.app')
@section('title','پرونده بدون فعالیت')
@section('actions')<x-btn variant="ghost" href="{{ route('reports.index') }}">بازگشت</x-btn>@endsection
@section('content')
<form method="GET" class="filter-bar" style="margin-bottom:12px">
<label class="text-sm">حداقل روز بدون فعالیت</label>
<input type="number" name="days" value="{{ $days }}" min="1" class="filter-input" style="width:90px">
<button class="btn btn-primary btn-sm">اجرا</button>
</form>
<div class="card"><div class="card-h">باز بدون یادداشت/تماس در {{ $days }} روز</div><div class="card-b pad0">
<table class="tbl"><thead><tr><th>شماره</th><th>عنوان</th><th>مشتری</th><th>کارشناس</th><th>وضعیت</th></tr></thead>
<tbody>
@forelse($cases as $c)
<tr style="cursor:pointer" onclick="location.href='{{ route('cases.show',$c) }}'">
<td style="font-weight:800;color:var(--brand)">{{ $c->case_number }}</td>
<td>{{ \Illuminate\Support\Str::limit($c->title,40) }}</td>
<td>{{ $c->customer?->name ?? '—' }}</td>
<td>{{ $c->expert?->name ?? '—' }}</td>
<td><x-status-badge :status="$c->current_status" /></td>
</tr>
@empty<tr><td colspan="5"><x-empty title="موردی نیست" /></td></tr>@endforelse
</tbody></table>
</div></div>
@endsection
