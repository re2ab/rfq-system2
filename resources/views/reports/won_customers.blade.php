@extends('layouts.app')
@section('title','مشتریان برده‌شده')
@section('actions')<x-btn variant="ghost" href="{{ route('reports.index') }}">بازگشت</x-btn>@endsection
@section('content')
<form method="GET" class="filter-bar" style="margin-bottom:12px">
<label class="text-sm">ماه اخیر</label>
<input type="number" name="months" value="{{ $months }}" min="1" max="24" class="filter-input" style="width:80px">
<button class="btn btn-primary btn-sm">اجرا</button>
</form>
<div class="card"><div class="card-h">سازمان‌های دارای پرونده برد در {{ $months }} ماه</div><div class="card-b pad0">
<table class="tbl"><thead><tr><th>سازمان</th><th>تعداد برد</th></tr></thead>
<tbody>
@forelse($rows as $row)
<tr><td>{{ $row['organization']->name ?? '—' }}</td><td style="font-weight:800;color:var(--brand)">{{ $row['count'] }}</td></tr>
@empty<tr><td colspan="2"><x-empty title="بردی نیست" /></td></tr>@endforelse
</tbody></table>
</div></div>
@endsection
