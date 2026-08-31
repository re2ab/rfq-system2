@php
  $setting = function (string $key, $default = null) {
      try { return \App\Models\AppSetting::get($key, $default); }
      catch (\Throwable $e) { return $default; }
  };
  $companyName = $setting('company_name', 'شرکت');
  $subtitle    = $setting('system_subtitle', 'سیستم مدیریت درخواست خرید صنعتی');
@endphp
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#16191f">
  <title>ورود به سامانه — {{ $companyName }}</title>
  <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('css/rfq-ui.css') }}?v=21.1">
</head>
<body class="auth-body">
  <main class="auth-split">
    <section class="auth-aside" aria-hidden="true">
      <div class="auth-aside-top">
        <div class="auth-mark">RFQ</div>
        <div>
          <div class="auth-brand">{{ $companyName }}</div>
          <div class="auth-brand-sub">{{ $subtitle }}</div>
        </div>
      </div>
      <div class="auth-aside-body">
        <h2>مدیریت یکپارچه پرونده‌های خرید</h2>
        <p>از استعلام و پیشنهاد فنی تا فاکتور و مطالبات — همه در یک پایپ‌لاین شفاف.</p>
        <ul class="auth-points">
          <li>پایپ‌لاین کانبان با ردگیری کامل تغییرات</li>
          <li>پیشنهاد فنی و مالی، تحویل، فاکتور و مطالبات</li>
          <li>گزارش‌های مدیریتی و صف کاری روزانه</li>
        </ul>
      </div>
      <div class="auth-aside-foot">نسخه {{ trim(@file_get_contents(base_path('VERSION')) ?: '') ?: '20.0' }}</div>
    </section>

    <section class="auth-form-side">
      <div class="auth-card">
        <div class="auth-mark auth-mark-sm">RFQ</div>
        <h1>ورود به سامانه</h1>
        <p class="auth-sub">با حساب سازمانی خود وارد شوید</p>

        @if($errors->any())
          <div class="alert alert-error" role="alert" style="margin-bottom:16px">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login') }}" novalidate>
          @csrf
          <div class="field">
            <label class="field-label" for="email">ایمیل سازمانی<span class="req">*</span></label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                   autocomplete="username" placeholder="name@company.com" dir="ltr"
                   class="{{ $errors->has('email') ? 'is-invalid' : '' }}">
          </div>
          <div class="field">
            <label class="field-label" for="password">رمز عبور<span class="req">*</span></label>
            <input id="password" type="password" name="password" required autocomplete="current-password"
                   class="{{ $errors->has('password') ? 'is-invalid' : '' }}">
          </div>
          <label class="auth-remember">
            <input type="checkbox" name="remember"> مرا به خاطر بسپار
          </label>
          <button type="submit" class="btn btn-primary btn-lg btn-block">ورود</button>
        </form>

        <p class="auth-note">دسترسی محدود به کاربران مجاز است. در صورت فراموشی رمز با مدیر سیستم تماس بگیرید.</p>
      </div>
    </section>
  </main>
</body>
</html>
