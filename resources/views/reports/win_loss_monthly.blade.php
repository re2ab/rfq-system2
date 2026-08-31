@extends('layouts.app')
@section('title','نرخ برد/باخت ماهانه')
@section('actions')<x-btn variant="ghost" href="{{ route('reports.index') }}">بازگشت</x-btn>@endsection
@section('content')
<form method="GET" class="filter-bar" style="margin-bottom:12px">
  <label class="text-sm">تعداد ماه</label>
  <input type="number" name="months" min="3" max="24" value="{{ $months }}" class="filter-input" style="width:80px">
  <button class="btn btn-primary btn-sm">اجرا</button>
</form>
<div class="card"><div class="card-h">برد در برابر باخت</div><div class="card-b pad0">
<table class="tbl"><thead><tr><th>ماه</th><th>برد</th><th>باخت</th><th>نرخ برد</th></tr></thead>
<tbody>
@foreach($labels as $i=>$lab)
@php $w=$won[$i]; $l=$lost[$i]; $d=$w+$l; @endphp
<tr><td>{{ $lab }}</td><td style="color:var(--brand);font-weight:800">{{ $w }}</td><td style="color:var(--danger);font-weight:800">{{ $l }}</td><td>{{ $d?round($w/$d*100,1):0 }}%</td></tr>
@endforeach
</tbody></table>
</div></div>
@endsection
