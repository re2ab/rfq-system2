@extends('layouts.app')
@section('title', 'گزارش وظایف')
@section('content')
<div class="flex justify-between mb-4">
    <h1 class="text-xl font-bold">گزارش وظایف</h1>
    <a href="{{ route('reports.index') }}" class="text-sm text-blue-600">بازگشت</a>
</div>
<div class="grid grid-cols-2 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4"><div class="text-sm text-gray-500">وظایف باز</div><div class="text-2xl font-bold">{{ $open }}</div></div>
    <div class="bg-white rounded-lg shadow p-4"><div class="text-sm text-gray-500">سررسیدگذشته</div><div class="text-2xl font-bold text-red-600">{{ $overdue }}</div></div>
</div>
<div class="bg-white rounded-lg shadow p-4">
    <h2 class="font-semibold mb-3 text-sm">بر اساس وضعیت</h2>
    @foreach($byStatus as $status => $count)
        <div class="flex justify-between py-2 border-b text-sm"><span>{{ $status }}</span><span>{{ $count }}</span></div>
    @endforeach
</div>
@endsection
