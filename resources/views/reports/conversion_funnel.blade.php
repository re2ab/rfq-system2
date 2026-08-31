@extends('layouts.app')
@section('title','قیف تبدیل')
@section('actions')<x-btn variant="ghost" href="{{ route('reports.index') }}">بازگشت</x-btn>@endsection
@section('content')
@include('reports._date_filter')
<div class="grid-stats">
  <x-stat label="کل در بازه" :value="$total" />
  <x-stat label="مسیر برد" :value="$won" tone="brand" />
  <x-stat label="باخت" :value="$lost" tone="danger" />
  <x-stat label="نرخ برد از تصمیم‌گرفته" :value="(($won+$lost)>0?round($won/($won+$lost)*100,1):0).'%'" />
</div>
<div class="card"><div class="card-h">وضعیت فعلی پرونده‌های بازه</div><div class="card-b pad0">
<table class="tbl"><thead><tr><th>مرحله</th><th>تعداد</th><th>٪ از کل</th></tr></thead>
<tbody>@foreach($rows as $r)<tr><td>{{ $r['label'] }}</td><td style="font-weight:800">{{ $r['count'] }}</td><td>{{ $r['pct'] }}%</td></tr>@endforeach</tbody></table>
</div></div>
@endsection
