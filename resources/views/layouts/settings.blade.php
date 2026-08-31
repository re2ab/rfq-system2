@extends('layouts.app')

@section('content')
@php
  $active = $settingsActive ?? '';
  if ($active === '') {
    try {
      if (request()->routeIs('settings.appearance*')) $active = 'appearance';
      elseif (request()->routeIs('settings.modules*')) $active = 'modules';
      elseif (request()->routeIs('settings.users*')) $active = 'users';
      elseif (request()->routeIs('settings.templates*')) $active = 'templates';
      elseif (request()->routeIs('settings.numbering*')) $active = 'numbering';
      elseif (request()->routeIs('settings.pipeline*')) $active = 'pipeline';
      elseif (request()->routeIs('settings.transitions*')) $active = 'transitions';
      elseif (request()->routeIs('settings.tags*')) $active = 'tags';
      elseif (request()->routeIs('settings.custom-fields*')) $active = 'customfields';
      elseif (request()->routeIs('settings.priorities*')) $active = 'priorities';
      elseif (request()->routeIs('settings.holidays*')) $active = 'holidays';
      elseif (request()->routeIs('settings.automation*')) $active = 'automation';
      elseif (request()->routeIs('settings.backup*') || request()->routeIs('settings.data.*')) $active = 'backup';
      elseif (request()->routeIs('settings.security*')) $active = 'security';
      elseif (request()->routeIs('settings.industries*')) $active = 'industries';
      elseif (request()->routeIs('settings.dashboard-layout*')) $active = 'dashboardlayout';
      elseif (request()->routeIs('twofactor.*')) $active = 'twofactor';
    } catch (\Throwable $e) {}
  }
@endphp
<div class="settings-layout">
  <div class="settings-mobile-select card hide-on-desktop" style="margin-bottom:0">
    <div class="card-b" style="padding:10px 12px">
      <label class="block text-xs mb-1" style="color:var(--muted)">بخش تنظیمات</label>
      <select class="settings-mobile-dd" style="width:100%;max-width:100%;box-sizing:border-box;height:44px" onchange="if(this.value) location.href=this.value">
        <option value="{{ route('settings.appearance') }}" {{ request()->routeIs('settings.appearance*')?'selected':'' }}>ظاهر و برند</option>
        <option value="{{ route('settings.modules') }}" {{ request()->routeIs('settings.modules*')?'selected':'' }}>ماژول‌ها</option>
        <option value="{{ route('settings.users') }}" {{ request()->routeIs('settings.users*')?'selected':'' }}>کاربران</option>
        <option value="{{ route('settings.templates') }}" {{ request()->routeIs('settings.templates*')?'selected':'' }}>قالب ایمیل</option>
        <option value="{{ route('settings.numbering') }}" {{ request()->routeIs('settings.numbering*')?'selected':'' }}>شماره‌گذاری</option>
        <option value="{{ route('settings.pipeline') }}" {{ request()->routeIs('settings.pipeline*')?'selected':'' }}>پایپ‌لاین</option>
        <option value="{{ route('settings.transitions') }}" {{ request()->routeIs('settings.transitions*')?'selected':'' }}>قوانین انتقال</option>
        <option value="{{ route('settings.tags') }}" {{ request()->routeIs('settings.tags*')?'selected':'' }}>تگ‌ها</option>
        <option value="{{ route('settings.custom-fields') }}" {{ request()->routeIs('settings.custom-fields*')?'selected':'' }}>فیلدهای سفارشی</option>
        <option value="{{ route('settings.priorities') }}" {{ request()->routeIs('settings.priorities*')?'selected':'' }}>اولویت‌ها</option>
        <option value="{{ route('settings.holidays') }}" {{ request()->routeIs('settings.holidays*')?'selected':'' }}>تعطیلات رسمی</option>
        <option value="{{ route('settings.automation') }}" {{ request()->routeIs('settings.automation*')?'selected':'' }}>اتوماسیون</option>
        <option value="{{ route('settings.backup') }}" {{ request()->routeIs('settings.backup*')?'selected':'' }}>پشتیبان</option>
        <option value="{{ route('settings.security') }}" {{ request()->routeIs('settings.security*')?'selected':'' }}>امنیت و ایمیل</option>
        <option value="{{ route('settings.industries') }}" {{ request()->routeIs('settings.industries*')?'selected':'' }}>صنایع</option>
        <option value="{{ route('settings.dashboard-layout') }}" {{ request()->routeIs('settings.dashboard-layout*')?'selected':'' }}>مدیریت داشبورد</option>
      </select>
    </div>
  </div>

  <nav class="settings-nav card hide-on-mobile-nav">
    <div class="card-h">مرکز تنظیمات</div>
    <div class="card-b pad0">
      <a class="settings-nav-item {{ $active==='appearance'?'active':'' }}" href="{{ route('settings.appearance') }}">ظاهر و برند</a>
      <a class="settings-nav-item {{ $active==='modules'?'active':'' }}" href="{{ route('settings.modules') }}">ماژول‌ها</a>
      <a class="settings-nav-item {{ $active==='users'?'active':'' }}" href="{{ route('settings.users') }}">کاربران و نقش‌ها</a>
      <a class="settings-nav-item {{ $active==='twofactor'?'active':'' }}" href="{{ route('twofactor.show') }}">احراز دو مرحله‌ای</a>
      <a class="settings-nav-item {{ $active==='templates'?'active':'' }}" href="{{ route('settings.templates') }}">قالب ایمیل</a>
      <a class="settings-nav-item {{ $active==='numbering'?'active':'' }}" href="{{ route('settings.numbering') }}">شماره‌گذاری اسناد</a>
      <a class="settings-nav-item {{ $active==='pipeline'?'active':'' }}" href="{{ route('settings.pipeline') }}">مراحل پایپ‌لاین</a>
      <a class="settings-nav-item {{ $active==='transitions'?'active':'' }}" href="{{ route('settings.transitions') }}">قوانین انتقال</a>
      <a class="settings-nav-item {{ $active==='tags'?'active':'' }}" href="{{ route('settings.tags') }}">تگ‌ها</a>
      <a class="settings-nav-item {{ $active==='customfields'?'active':'' }}" href="{{ route('settings.custom-fields') }}">فیلدهای سفارشی</a>
      <a class="settings-nav-item {{ $active==='priorities'?'active':'' }}" href="{{ route('settings.priorities') }}">اولویت‌ها</a>
      <a class="settings-nav-item {{ $active==='holidays'?'active':'' }}" href="{{ route('settings.holidays') }}">تعطیلات رسمی</a>
      <a class="settings-nav-item {{ $active==='automation'?'active':'' }}" href="{{ route('settings.automation') }}">اتوماسیون</a>
      <a class="settings-nav-item {{ $active==='backup'?'active':'' }}" href="{{ route('settings.backup') }}">پشتیبان و بازیابی</a>
      <a class="settings-nav-item {{ $active==='security'?'active':'' }}" href="{{ route('settings.security') }}">امنیت و ایمیل واقعی</a>
      <a class="settings-nav-item {{ $active==='industries'?'active':'' }}" href="{{ route('settings.industries') }}">صنایع سازمان‌ها</a>
      <a class="settings-nav-item {{ $active==='dashboardlayout'?'active':'' }}" href="{{ route('settings.dashboard-layout') }}">مدیریت داشبورد</a>
    </div>
  </nav>
  <div class="settings-main settings-main-notitle">
    @yield('settings')
  </div>
</div>
@endsection
