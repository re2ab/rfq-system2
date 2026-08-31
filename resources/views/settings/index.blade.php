@extends('layouts.settings')
@section('title', 'تنظیمات')
@section('settings')
@php
  $cards = [
    ['route' => 'settings.appearance', 'title' => 'ظاهر و برند', 'desc' => 'لوگو، نام، تم، رنگ، منطقه زمانی'],
    ['route' => 'settings.modules', 'title' => 'ماژول‌ها', 'desc' => 'فعال/غیرفعال کردن قابلیت‌ها'],
    ['route' => 'settings.users', 'title' => 'کاربران و نقش‌ها', 'desc' => 'حساب کاربری و دسترسی'],
    ['route' => 'twofactor.show', 'title' => 'احراز دو مرحله‌ای', 'desc' => 'امنیت ورود'],
    ['route' => 'settings.templates', 'title' => 'قالب اسناد / ایمیل', 'desc' => 'سربرگ، بدنه، امضا'],
    ['route' => 'settings.numbering', 'title' => 'شماره‌گذاری اسناد', 'desc' => 'پیشوند و آخرین شماره'],
    ['route' => 'settings.pipeline', 'title' => 'مراحل پایپ‌لاین', 'desc' => 'ستون‌ها و ترتیب'],
    ['route' => 'settings.transitions', 'title' => 'قوانین انتقال', 'desc' => 'شرط جابه‌جایی مراحل'],
    ['route' => 'settings.tags', 'title' => 'تگ‌ها', 'desc' => 'برچسب پرونده، مخاطب، سازمان، وظیفه'],
    ['route' => 'settings.custom-fields', 'title' => 'فیلدهای سفارشی', 'desc' => 'فیلد اضافه بر اساس بخش'],
    ['route' => 'settings.priorities', 'title' => 'اولویت‌ها', 'desc' => 'اولویت وظایف و پرونده‌ها با رنگ'],
    ['route' => 'settings.automation', 'title' => 'اتوماسیون', 'desc' => 'قوانین خودکار'],
    ['route' => 'settings.industries', 'title' => 'صنایع سازمان‌ها', 'desc' => 'دسته‌بندی صنعت مشتری'],
    ['route' => 'settings.backup', 'title' => 'پشتیبان و بازیابی', 'desc' => 'بک‌آپ، ایمپورت، ریست'],
    ['route' => 'settings.security', 'title' => 'امنیت و ایمیل واقعی', 'desc' => 'SMTP/IMAP و امنیت'],
    ['route' => 'mail.accounts.index', 'title' => 'اکانت‌های ایمیل یکپارچه', 'desc' => 'فاز A: چند اکانت، دسترسی کاربران، همگام‌سازی IMAP'],
  ];
@endphp
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 mb-4">
  @foreach($cards as $c)
    @php
      try { $url = route($c['route']); } catch (\Throwable $e) { $url = '#'; }
    @endphp
    <a href="{{ $url }}" class="stat" style="text-decoration:none;color:inherit;display:block;padding:14px 16px">
      <div class="lbl" style="font-weight:800;font-size:14px;margin-bottom:4px">{{ $c['title'] }}</div>
      <div class="num" style="font-size:12px;font-weight:500;color:var(--muted);line-height:1.4">{{ $c['desc'] }}</div>
    </a>
  @endforeach
</div>
<div class="card">
  <div class="card-h">راهنما</div>
  <div class="card-b" style="font-size:13px;color:var(--muted);line-height:1.7">
    از منوی کناری یا کارت‌های بالا بخش مورد نظر را باز کنید. محتوا در همین صفحه نمایش داده می‌شود.
  </div>
</div>
@endsection
