@extends('layouts.settings')
@section('title', 'احراز هویت دو مرحله‌ای')
@section('settings')
<div class="card" style="max-width:520px">
  <div class="card-h">احراز هویت دو مرحله‌ای (2FA)</div>
  <div class="card-b text-sm space-y-3">
  @if($enabled)
    <p style="color:var(--success);font-weight:700">فعال است.</p>
    <p class="font-mono" style="background:var(--surface-2);padding:8px;border-radius:8px;word-break:break-all" dir="ltr">Secret: {{ $secret }}</p>
    <p style="font-size:12px;color:var(--muted)">برای production پکیج Google2FA را وصل کنید. این نسخه ساده secret را در پروفایل نگه می‌دارد.</p>
    <form method="POST" action="{{ route('twofactor.disable') }}">@csrf
      <input type="password" name="password" required placeholder="رمز عبور فعلی" class="rfq-f-input" style="width:100%;margin-bottom:8px">
      <button class="btn btn-danger">غیرفعال کردن</button>
    </form>
  @else
    <p style="color:var(--muted)">با فعال‌سازی، ورود به سیستم نیازمند کد دومرحله‌ای خواهد بود.</p>
    <form method="POST" action="{{ route('twofactor.enable') }}">@csrf
      <button class="btn btn-primary">فعال‌سازی 2FA</button>
    </form>
  @endif
  </div>
</div>
@endsection
