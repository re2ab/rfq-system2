@extends('layouts.app')
@section('title','تأمین‌کنندگان پرتکرار')
@section('actions')<x-btn variant="ghost" href="{{ route('reports.index') }}">بازگشت</x-btn>@endsection
@section('content')
<div class="card"><div class="card-h">از جدول case_suppliers</div><div class="card-b pad0">
<table class="tbl"><thead><tr><th>سازمان</th><th>تعداد اتصال به پرونده</th></tr></thead>
<tbody>
@forelse($rows as $row)
<tr><td>{{ $row['organization']->name ?? '—' }}</td><td style="font-weight:800">{{ $row['total'] }}</td></tr>
@empty<tr><td colspan="2"><x-empty title="تأمین‌کننده‌ای ثبت نشده" /></td></tr>@endforelse
</tbody></table>
</div></div>
@endsection
