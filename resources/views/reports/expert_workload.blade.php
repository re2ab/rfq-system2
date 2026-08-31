@extends('layouts.app')
@section('title','بار کارشناس')
@section('actions')<x-btn variant="ghost" href="{{ route('reports.index') }}">بازگشت</x-btn>@endsection
@section('content')
<div class="card"><div class="card-h">پرونده و وظیفه به ازای کاربر</div><div class="card-b pad0">
<table class="tbl"><thead><tr><th>کاربر</th><th>پرونده باز</th><th>برد</th><th>وظیفه باز</th><th>وظیفه معوق</th></tr></thead>
<tbody>
@foreach($users as $u)
<tr>
<td>{{ $u->name }}</td>
<td style="font-weight:800">{{ $u->open_cases }}</td>
<td>{{ $u->won_cases }}</td>
<td>{{ $u->open_tasks }}</td>
<td style="color:var(--danger);font-weight:800">{{ $u->overdue_tasks }}</td>
</tr>
@endforeach
</tbody></table>
</div></div>
@endsection
