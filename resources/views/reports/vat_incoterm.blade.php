@extends('layouts.app')
@section('title','VAT و ترم تحویل')
@section('actions')<x-btn variant="ghost" href="{{ route('reports.index') }}">بازگشت</x-btn>@endsection
@section('content')
@include('reports._date_filter')
<div class="card"><div class="card-h">جمع اسناد بر اساس ترم تحویل</div><div class="card-b pad0">
<table class="tbl"><thead><tr><th>ترم تحویل</th><th>ارز</th><th>تعداد</th><th>خالص</th><th>VAT</th><th>ناخالص</th></tr></thead>
<tbody>
@forelse($rows as $r)
<tr><td>{{ $r->incoterm ?: '—' }}</td><td>{{ currency_label($r->currency) }}</td><td>{{ $r->total }}</td>
<td>{{ number_format($r->net ?? 0) }}</td><td>{{ number_format($r->vat ?? 0) }}</td><td style="font-weight:800">{{ number_format($r->gross ?? 0) }}</td></tr>
@empty<tr><td colspan="6"><x-empty title="داده نیست" /></td></tr>@endforelse
</tbody></table>
</div></div>
@endsection
