@extends('layouts.app')
@section('title','ارزش پایپ‌لاین باز')
@section('actions')<x-btn variant="ghost" href="{{ route('reports.index') }}">بازگشت</x-btn>@endsection
@section('content')
<div class="grid-stats">
@foreach($byCurrency as $cur=>$sum)
  <x-stat :label="'جمع '.$cur" :value="number_format($sum)" tone="brand" />
@endforeach
</div>
<div class="card"><div class="card-h">پرونده‌های باز (مبلغ از اسناد)</div><div class="card-b pad0">
<table class="tbl"><thead><tr><th>شماره</th><th>عنوان</th><th>مشتری</th><th>وضعیت</th><th>مبلغ اسناد</th></tr></thead>
<tbody>
@forelse($open as $c)
<tr style="cursor:pointer" onclick="location.href='{{ route('cases.show',$c) }}'">
<td style="font-weight:800;color:var(--brand)">{{ $c->case_number }}</td>
<td>{{ \Illuminate\Support\Str::limit($c->title,36) }}</td>
<td>{{ $c->customer?->name ?? '—' }}</td>
<td><x-status-badge :status="$c->current_status" /></td>
<td>{{ number_format($c->pipeline_amount) }} {{ $c->currency ?? '' }}</td>
</tr>
@empty<tr><td colspan="5"><x-empty title="پرونده باز نیست" /></td></tr>@endforelse
</tbody></table>
</div></div>
@endsection
