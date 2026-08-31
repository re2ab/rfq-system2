@extends('layouts.app')
@section('title','امضای ایمیل')
@section('actions')
  <a href="{{ route('mail.inbox') }}" class="btn btn-ghost btn-sm">صندوق</a>
@endsection
@section('content')
<form method="POST" action="{{ route('mail.signature.save') }}" class="max-w-2xl mx-auto card">
  @csrf
  <div class="card-h">امضای کاربر</div>
  <div class="card-b space-y-4 text-sm">
    <p class="text-gray-600">امضا هنگام نامه جدید و پاسخ به‌صورت خودکار اضافه می‌شود. محتوای HTML تمیز و متن‌محور توصیه می‌شود.</p>
    <label class="block">امضای فارسی
      <textarea name="body_html_fa" rows="6" class="w-full border rounded px-2 py-1.5 mt-1" dir="rtl">{{ old('body_html_fa', $fa->body_html ?? '') }}</textarea>
    </label>
    <label class="block">امضای انگلیسی
      <textarea name="body_html_en" rows="6" class="w-full border rounded px-2 py-1.5 mt-1" dir="ltr">{{ old('body_html_en', $en->body_html ?? '') }}</textarea>
    </label>
    <button type="submit" class="btn btn-primary">ذخیره</button>
  </div>
</form>
@endsection
