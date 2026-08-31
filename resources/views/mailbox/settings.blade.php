@extends('layouts.app')
@section('title','فعال‌سازی ایمیل شرکتی من')
@section('actions')
  <a href="{{ route('mailbox.inbox') }}" class="btn btn-ghost btn-sm">صندوق ورودی</a>
  <a href="{{ route('mailbox.compose') }}" class="btn btn-primary btn-sm">ارسال</a>
@endsection
@section('content')
@php $company = app(\App\Services\UserMailboxService::class)->companyDefaults(); @endphp
<div class="card mb-4"><div class="card-b text-sm">
  سرور ایمیل شرکت توسط <strong>ادمین</strong> تنظیم شده است. شما فقط ایمیل شرکتی و رمز صندوق خود را وارد کنید
  (مثل user1@mycompany.com).
</div></div>

<div class="card mb-4">
  <div class="card-h">سرور شرکت (فقط نمایش)</div>
  <div class="card-b text-xs rfq-grid-2" dir="ltr" style="gap:8px">
    <div>SMTP: {{ $company['smtp_host'] ?: '—' }}:{{ $company['smtp_port'] }} ({{ $company['smtp_encryption'] }})</div>
    <div>IMAP: {{ $company['imap_host'] ?: '—' }}:{{ $company['imap_port'] }} ({{ $company['imap_encryption'] }})</div>
    @if($company['pop3_host'])
    <div>POP3: {{ $company['pop3_host'] }}:{{ $company['pop3_port'] }}</div>
    @endif
  </div>
  @if(!$company['smtp_host'] && !$company['imap_host'])
    <div class="card-b text-sm text-red-600">ادمین هنوز سرور ایمیل شرکت را در تنظیمات امنیت مشخص نکرده است.</div>
  @endif
</div>

<form method="POST" action="{{ route('mailbox.settings.update') }}" class="card">@csrf
  <div class="card-h">حساب من</div>
  <div class="card-b text-sm space-y-3">
    <label class="block">ایمیل شرکتی
      <input name="email" type="email" required value="{{ old('email', $account->email ?? '') }}" class="w-full border rounded px-2 py-1 mt-1" dir="ltr" placeholder="user1@mycompany.com">
    </label>
    <label class="block">نام نمایشی در ارسال
      <input name="display_name" value="{{ old('display_name', $account->display_name ?? auth()->user()->name) }}" class="w-full border rounded px-2 py-1 mt-1">
    </label>
    <label class="block">نام کاربری (معمولاً همان ایمیل)
      <input name="smtp_username" value="{{ old('smtp_username', $account->smtp_username ?? $account->email ?? '') }}" class="w-full border rounded px-2 py-1 mt-1" dir="ltr">
    </label>
    <input type="hidden" name="imap_username" value="{{ old('imap_username', $account->imap_username ?? '') }}">
    <label class="block">رمز عبور صندوق ایمیل
      <input name="smtp_password" type="password" class="w-full border rounded px-2 py-1 mt-1" dir="ltr" placeholder="{{ !empty($account?->smtp_password) ? 'ذخیره شده — فقط برای تغییر پر کنید' : 'رمز صندوق' }}">
    </label>
    <p class="text-xs text-gray-500">همین رمز برای IMAP هم استفاده می‌شود مگر جداگانه در فیلد زیر بگذارید.</p>
    <label class="block">رمز IMAP (اختیاری اگر با SMTP یکی است)
      <input name="imap_password" type="password" class="w-full border rounded px-2 py-1 mt-1" dir="ltr">
    </label>
    <button class="btn btn-primary">فعال‌سازی / ذخیره</button>
  </div>
</form>
<div class="flex flex-wrap gap-2 mt-4">
  <form method="POST" action="{{ route('mailbox.test.smtp') }}" class="flex gap-2">@csrf
    <input name="to" type="email" required placeholder="ایمیل تست" class="border rounded px-2 py-1" dir="ltr">
    <button class="btn btn-sm">تست ارسال</button>
  </form>
  <form method="POST" action="{{ route('mailbox.test.imap') }}">@csrf
    <button class="btn btn-sm btn-ghost">تست دریافت</button>
  </form>
</div>
@endsection
