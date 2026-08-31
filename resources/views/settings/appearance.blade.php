@extends('layouts.settings')
@section('title', 'ظاهر و هویت سازمان')
@section('settings')
<form method="POST" action="{{ route('settings.appearance.save') }}" enctype="multipart/form-data" class="card">
  @csrf
  <div class="card-b">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px" class="text-sm">
      <div>
        <label class="block mb-1 font-semibold">نام شرکت</label>
        <input name="company_name" value="{{ old('company_name', $company_name ?? '') }}" class="w-full border rounded-xl px-3 py-2">
      </div>
      <div>
        <label class="block mb-1 font-semibold">زیرعنوان سیستم</label>
        <input name="system_subtitle" value="{{ old('system_subtitle', $system_subtitle ?? '') }}" class="w-full border rounded-xl px-3 py-2">
      </div>
      <div>
        <label class="block mb-1 font-semibold">لوگوی شرکت</label>
        @if(!empty($company_logo))
          <img src="{{ asset('storage/'.$company_logo) }}" class="h-12 mb-2 object-contain" alt="logo">
        @endif
        <div class="file-input-wrap"><input type="file" name="company_logo" accept="image/*"></div>
      </div>
      <div>
        <label class="block mb-1 font-semibold">تم پیش‌فرض</label>
        <select name="theme" class="w-full border rounded-xl px-3 py-2">
          <option value="light" @selected(($theme??'light')==='light')>روشن</option>
          <option value="dark" @selected(($theme??'')==='dark')>تیره</option>
        </select>
      </div>
      <div>
        <label class="block mb-1 font-semibold">رنگ برند</label>
        <input type="color" name="primary_color" value="{{ $primary_color ?? '#b8703c' }}" class="w-full h-10 rounded-xl" style="padding:4px;max-width:120px">
        <p class="text-xs text-gray-500 mt-1">رنگ اصلی سیستم — روی دکمه‌ها (از جمله دکمه‌های نوار عنوان صفحات)، لینک‌ها و نشانگرهای فعال در کل رابط اعمال می‌شود. دکمه‌ی «حذف» همیشه قرمز می‌ماند.</p>
      </div>
      <div>
        <label class="block mb-1 font-semibold">منطقه زمانی (Time Zone)</label>
        <select name="app_timezone" class="w-full border rounded-xl px-3 py-2">
          @foreach(($timezones ?? []) as $tz => $label)
            <option value="{{ $tz }}" @selected(($app_timezone ?? 'Asia/Tehran') === $tz)>{{ $label }}</option>
          @endforeach
        </select>
        <p class="text-xs text-gray-500 mt-1">
          برای ایران معمولاً <strong>Asia/Tehran</strong> را انتخاب کنید.
          مقدار فعلی سیستم: <code dir="ltr">{{ config('app.timezone') }}</code>
        </p>
      </div>
    </div>
    <div class="pt-4"><button class="btn btn-primary">ذخیره</button></div>
  </div>
</form>
@endsection
