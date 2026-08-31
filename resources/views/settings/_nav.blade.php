@php
  $active = $active ?? '';
@endphp
<div class="settings-layout mb-4">
  <nav class="settings-nav card" style="position:sticky;top:72px;align-self:start;max-height:calc(100vh - 90px);overflow:auto">
    <div class="card-h">مرکز تنظیمات</div>
    <div class="card-b pad0">
      <a class="settings-nav-item {{ $active==='index'?'active':'' }}" href="{{ route('settings.index') }}">نمای کلی</a>
      <div class="settings-nav-group">عمومی</div>
      <a class="settings-nav-item {{ $active==='appearance'?'active':'' }}" href="{{ route('settings.appearance') }}">ظاهر و برند</a>
      <a class="settings-nav-item {{ $active==='modules'?'active':'' }}" href="{{ route('settings.modules') }}">ماژول‌ها</a>
      <div class="settings-nav-group">کاربران و امنیت</div>
      <a class="settings-nav-item {{ $active==='users'?'active':'' }}" href="{{ route('settings.users') }}">کاربران و نقش‌ها</a>
      <a class="settings-nav-item" href="{{ route('twofactor.show') }}">احراز دو مرحله‌ای</a>
      <div class="settings-nav-group">اسناد و ایمیل</div>
      <a class="settings-nav-item {{ $active==='templates'?'active':'' }}" href="{{ route('settings.templates') }}">قالب اسناد / ایمیل</a>
      <a class="settings-nav-item {{ $active==='numbering'?'active':'' }}" href="{{ route('settings.numbering') }}">شماره‌گذاری اسناد</a>
      <a class="settings-nav-item {{ $active==='pipeline'?'active':'' }}" href="{{ route('settings.pipeline') }}">مراحل پایپ‌لاین</a>
      <a class="settings-nav-item {{ $active==='transitions'?'active':'' }}" href="{{ route('settings.transitions') }}">قوانین انتقال</a>
      <a class="settings-nav-item {{ $active==='tags'?'active':'' }}" href="{{ route('settings.tags') }}">تگ‌ها</a>
      <a class="settings-nav-item {{ $active==='custom-fields'?'active':'' }}" href="{{ route('settings.custom-fields') }}">فیلدهای سفارشی</a>
      <a class="settings-nav-item {{ $active==='priorities'?'active':'' }}" href="{{ route('settings.priorities') }}">اولویت‌ها</a>
      <a class="settings-nav-item {{ $active==='automation'?'active':'' }}" href="{{ route('settings.automation') }}">اتوماسیون</a>
      <div class="settings-nav-group">سیستم</div>
      <a class="settings-nav-item {{ $active==='backup'?'active':'' }}" href="{{ route('settings.backup') }}">پشتیبان و بازیابی</a>
      <a class="settings-nav-item {{ $active==='security'?'active':'' }}" href="{{ route('settings.security') }}">امنیت و ایمیل واقعی</a>
      <a class="settings-nav-item {{ $active==='industries'?'active':'' }}" href="{{ route('settings.industries') }}">صنایع سازمان‌ها</a>
    </div>
  </nav>
  <div class="settings-main" style="min-width:0">
