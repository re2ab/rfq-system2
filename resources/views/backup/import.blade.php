@extends('layouts.app')
@section('title','بازیابی پشتیبان')
@section('content')
<div class="max-w-lg mx-auto bg-white rounded-lg shadow p-6 text-sm">
<p class="mb-3 text-gray-600">فقط جداول امن (قالب، ماژول، تنظیمات، فیلد سفارشی) از فایل JSON بازیابی می‌شوند.</p>
<form method="POST" action="{{ route('backup.import') }}" enctype="multipart/form-data" class="space-y-3">@csrf
  <input type="file" name="file" accept=".json,application/json" required>
  <button class="bg-blue-600 text-white px-4 py-2 rounded">بازیابی</button>
</form>
</div>
@endsection
