@extends('layouts.app')
@section('title','ایمیل‌های ارسالی')
@section('actions')
  <a href="{{ route('mailbox.inbox') }}" class="btn btn-ghost btn-sm">صندوق ورودی</a>
  <a href="{{ route('mailbox.compose') }}" class="btn btn-primary btn-sm">نامه‌ی جدید</a>
@endsection
@section('content')
<p class="text-xs text-gray-500 mb-2">این فهرست، ثبت داخلی سیستم از ایمیل‌هایی است که از همین صندوق شخصی فرستاده‌اید — تلاش هم می‌شود کپی هرکدام واقعاً در پوشه‌ی Sent سرور ایمیل شما هم ذخیره شود تا از هر کلاینت دیگری (Outlook، جیمیل واقعی و غیره) هم قابل مشاهده باشد.</p>
<div class="card"><div class="card-b pad0">
  @forelse($emails as $email)
    <div class="rel-item" style="padding:10px 14px;border-bottom:1px solid var(--border)">
      <div style="display:flex;justify-content:space-between;gap:10px;align-items:baseline">
        <div class="font-semibold text-sm">{{ $email->subject ?: '(بدون موضوع)' }}</div>
        <div class="text-xs text-gray-500" style="white-space:nowrap">{{ jdatetime($email->created_at) }}</div>
      </div>
      <div class="text-xs text-gray-500">به: <span dir="ltr">{{ $email->to_address }}</span></div>
      @if($email->attachments->count())
        <div class="text-xs text-gray-500 mt-1">پیوست‌ها: {{ $email->attachments->pluck('file_name')->join('، ') }}</div>
      @endif
    </div>
  @empty
    <p class="p-4 text-gray-500 text-sm">هنوز از این صندوق ایمیلی نفرستاده‌اید.</p>
  @endforelse
</div></div>
<div class="mt-3">{{ $emails->withQueryString()->links() }}</div>
@endsection
