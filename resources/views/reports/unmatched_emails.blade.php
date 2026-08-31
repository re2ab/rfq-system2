@extends('layouts.app')
@section('title','ایمیل بدون پرونده')
@section('actions')<x-btn variant="ghost" href="{{ route('reports.index') }}">بازگشت</x-btn>@endsection
@section('content')
<div class="card"><div class="card-h">پیام‌های بدون لینک</div><div class="card-b pad0">
<table class="tbl"><thead><tr><th>جهت</th><th>از/به</th><th>موضوع</th><th>تاریخ</th></tr></thead>
<tbody>
@forelse($emails as $e)
<tr>
<td>{{ $e->direction }}</td>
<td style="font-size:12px">{{ $e->direction==='inbound' ? $e->from_address : $e->to_address }}</td>
<td>{{ $e->subject }}</td>
<td>{{ jdatetime($e->created_at) }}</td>
</tr>
@empty<tr><td colspan="4"><x-empty title="همه ایمیل‌ها متصل‌اند" /></td></tr>@endforelse
</tbody></table>
</div></div>
@endsection
