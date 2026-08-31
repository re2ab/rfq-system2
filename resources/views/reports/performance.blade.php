@extends('layouts.app')
@section('title', 'عملکرد کارشناسان')
@section('content')
<div class="flex justify-between mb-4">
    <h1 class="text-xl font-bold">عملکرد کارشناسان</h1>
    <a href="{{ route('reports.index') }}" class="text-sm text-blue-600">بازگشت</a>
</div>
<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b"><tr>
            <th class="text-right p-3">کارشناس</th>
            <th class="text-right p-3">پرونده باز</th>
            <th class="text-right p-3">برد</th>
            <th class="text-right p-3">باخت</th>
        </tr></thead>
        <tbody>
            @foreach($experts as $e)
            <tr class="border-b">
                <td class="p-3">{{ $e->name }}</td>
                <td class="p-3">{{ $e->open_cases_count }}</td>
                <td class="p-3 text-green-700">{{ $e->won_cases_count }}</td>
                <td class="p-3 text-red-600">{{ $e->lost_cases_count }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
