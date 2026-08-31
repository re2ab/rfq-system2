@extends('layouts.app')
@section('title','اسناد بر اساس نوع')
@section('actions')<x-btn variant="ghost" href="{{ route('reports.index') }}">بازگشت</x-btn>@endsection
@section('content')
@include('reports._date_filter')
@php $labels=['technical_proposal'=>'پیشنهاد فنی','financial_proposal'=>'پیشنهاد مالی','invoice'=>'فاکتور']; @endphp
<div class="card"><div class="card-h">جمع تعداد و مبلغ</div><div class="card-b pad0">
<table class="tbl"><thead><tr><th>نوع</th><th>ارز</th><th>تعداد</th><th>جمع ناخالص</th></tr></thead>
<tbody>
@forelse($rows as $r)
<tr><td>{{ $labels[$r->type] ?? $r->type }}</td><td>{{ currency_label($r->currency) }}</td><td style="font-weight:800">{{ $r->total }}</td><td>{{ number_format($r->amount ?? 0) }}</td></tr>
@empty<tr><td colspan="4"><x-empty title="سندی نیست" /></td></tr>@endforelse
</tbody></table>
</div></div>
@endsection
