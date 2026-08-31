@extends('layouts.app')
@section('title','گزارش مطالبات')
@section('content')
<h1 class="text-xl font-bold mb-4">Aging مطالبات</h1>
<div class="bg-white rounded-lg shadow overflow-hidden">
<table class="w-full text-sm">
<thead class="bg-gray-50 border-b"><tr>
<th class="text-right p-3">پرونده</th><th class="text-right p-3">مبلغ</th><th class="text-right p-3">باقی</th><th class="text-right p-3">سررسید</th><th class="text-right p-3">سطل</th>
</tr></thead>
<tbody>
@foreach($items as $row)
@php $r=$row['receivable']; @endphp
<tr class="border-b {{ $row['bucket']==='overdue'?'bg-red-50':'' }}">
<td class="p-3">{{ $r->case?->case_number }}</td>
<td class="p-3">{{ number_format($r->amount,2) }}</td>
<td class="p-3">{{ number_format($r->remaining,2) }}</td>
<td class="p-3">{{ isset($r->due_date?) && $r->due_date? ? jdate($r->due_date?) : '—' }}</td>
<td class="p-3">{{ $row['bucket'] }}</td>
</tr>
@endforeach
</tbody></table></div>
@endsection
