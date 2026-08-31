@extends('layouts.app')
@section('title','سازمان‌های تک‌درخواست')
@section('actions')<x-btn variant="ghost" href="{{ route('reports.index') }}">بازگشت</x-btn>@endsection
@section('content')
<div class="card"><div class="card-h">فقط یک پرونده در کل سیستم</div><div class="card-b pad0">
<table class="tbl"><thead><tr><th>سازمان</th><th>تعداد</th></tr></thead>
<tbody>
@forelse($rows as $row)
<tr><td>@if($row['organization'])<a href="{{ route('organizations.show',$row['organization']) }}">{{ $row['organization']->name }}</a>@else — @endif</td>
<td>{{ $row['total'] }}</td></tr>
@empty<tr><td colspan="2"><x-empty title="موردی نیست" /></td></tr>@endforelse
</tbody></table>
</div></div>
@endsection
