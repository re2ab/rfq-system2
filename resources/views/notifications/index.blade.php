@extends('layouts.app')
@section('title','اعلان‌ها')
@section('content')
<h1 class="text-xl font-bold mb-4">اعلان‌ها</h1>
<div class="bg-white rounded-lg shadow divide-y text-sm">
@forelse($items as $n)
<div class="p-4 flex justify-between {{ $n->is_read ? 'opacity-60' : '' }}">
  <div>
    <div class="font-medium">{{ $n->title }}</div>
    <div class="text-gray-600">{{ $n->body }}</div>
    <div class="text-xs text-gray-400 mt-1">{{ jdatetime($n->created_at) }}</div>
  </div>
  @if(!$n->is_read)
  <form method="POST" action="{{ route('notifications.read',$n) }}">@csrf
    <button class="text-blue-600 text-xs">خوانده شد</button>
  </form>
  @endif
</div>
@empty
<div class="p-6 text-center text-gray-500">اعلانی نیست. (اعلان‌ها هنگام رویدادهای بعدی سیستم ثبت می‌شوند)</div>
@endforelse
</div>
<div class="mt-4">{{ $items->links() }}</div>
@endsection
