@extends('layouts.app')
@section('title','فاکتور / مطالبه ناقص')
@section('actions')<x-btn variant="ghost" href="{{ route('reports.index') }}">بازگشت</x-btn>@endsection
@section('content')
<div class="rfq-grid-2">
<div class="card"><div class="card-h">فاکتور بدون مطالبه</div><div class="card-b pad0">
<table class="tbl"><thead><tr><th>شماره</th><th>عنوان</th><th>مبلغ</th></tr></thead>
<tbody>
@forelse($invoiceNoRec as $d)
<tr><td>{{ $d->document_number }}</td><td>{{ $d->title }}</td><td>{{ number_format($d->gross_amount ?? 0) }}</td></tr>
@empty<tr><td colspan="3"><x-empty title="همه فاکتورها مطالبه دارند" /></td></tr>@endforelse
</tbody></table>
</div></div>
<div class="card"><div class="card-h">مطالبه بدون پرداخت</div><div class="card-b pad0">
<table class="tbl"><thead><tr><th>پرونده</th><th>مبلغ</th><th>سررسید</th></tr></thead>
<tbody>
@forelse($recNoPay as $r)
<tr><td>{{ $r->case?->case_number ?? $r->case_id }}</td><td>{{ number_format($r->amount) }}</td><td>{{ $r->due_date ? jdate($r->due_date) : '—' }}</td></tr>
@empty<tr><td colspan="3"><x-empty title="موردی نیست" /></td></tr>@endforelse
</tbody></table>
</div></div>
</div>
@endsection
