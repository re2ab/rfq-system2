@extends('layouts.settings')
@section('title', 'تعطیلات رسمی')
@section('settings')
@if(session('success'))
<div class="card mb-3" style="padding:12px;background:var(--success-soft);color:var(--success)">{{ session('success') }}</div>
@endif
@if($errors->any())
<div class="card mb-3" style="padding:12px;background:var(--danger-soft);color:var(--danger)">
  @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
</div>
@endif

<div class="card mb-4">
  <div class="card-b text-sm" style="color:var(--muted);line-height:1.7">
    روزهای جمعه به‌طور خودکار در تقویم قرمز نمایش داده می‌شوند. بقیه‌ی تعطیلات رسمی را می‌توانید به‌جای ورود دستی،
    برای هر سال شمسی با یک کلیک از پایین دریافت کنید — یا در صورت نیاز (تعطیلی محلی/سازمانی خاص) دستی اضافه کنید.
    گزینه‌ی «هرسال تکرار شود» فقط برای موارد دستیِ ثابت (مثل ۱ فروردین) کاربرد دارد؛ تعطیلات دریافت‌شده از اینترنت
    چون مخصوص همان سال‌اند، بدون این گزینه ثبت می‌شوند و برای سال بعد دوباره باید دریافت شوند.
  </div>
</div>

<div class="card mb-4">
  <div class="card-h">دریافت خودکار از اینترنت</div>
  <div class="card-b text-sm">
    <div style="color:var(--muted);margin-bottom:10px;line-height:1.7">
      فهرست تعطیلات رسمی تقویم شمسی برای هر سال، از یک مخزن عمومی و رایگان دریافت می‌شود:
      <a href="https://github.com/hasan-ahani/shamsi-holidays" target="_blank" rel="noopener" dir="ltr" style="display:inline-block">github.com/hasan-ahani/shamsi-holidays</a>
      (منبع اصلی داده: time.ir). این نیاز به اتصال اینترنت سرور دارد؛ برای هر سال جدید یک‌بار کافی است.
    </div>
    <form method="POST" action="{{ route('settings.holidays.sync') }}" class="flex flex-wrap gap-2 items-end">
      @csrf
      <div>
        <label class="block text-xs mb-1">سال شمسی</label>
        <input name="sync_year" type="number" min="1390" max="1450" required value="{{ old('sync_year', $currentJalaliYear ?? '') }}" class="filter-input" style="width:120px" dir="ltr">
      </div>
      <button class="btn btn-primary btn-sm">دریافت و ثبت تعطیلات این سال</button>
    </form>
  </div>
</div>

<div class="card mb-4">
  <div class="card-h">افزودن دستی</div>
  <div class="card-b text-sm">
    <form method="POST" action="{{ route('settings.holidays.store') }}" class="flex flex-wrap gap-2 items-end">@csrf
      <div>
        <label class="block text-xs mb-1">تاریخ شمسی</label>
        <input name="jalali_date" data-jdp autocomplete="off" required placeholder="۱۴۰۳/۰۱/۰۱" class="filter-input" style="width:140px;font-family:Vazirmatn,tahoma,sans-serif" dir="ltr">
      </div>
      <div style="flex:1;min-width:160px">
        <label class="block text-xs mb-1">عنوان</label>
        <input name="title" placeholder="مثلاً عید نوروز" class="w-full border rounded px-2 py-1.5">
      </div>
      <label class="flex gap-2 items-center text-xs" style="height:34px">
        <input type="checkbox" name="recurring_yearly" value="1"> هرسال تکرار شود
      </label>
      <button class="btn btn-primary btn-sm">افزودن</button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-h">تعطیلات ثبت‌شده ({{ $holidays->count() }})</div>
  <div class="card-b pad0 text-sm">
    @forelse($holidays as $h)
      <div class="rel-item" style="display:flex;align-items:center;justify-content:space-between;gap:8px">
        <div>
          <span class="badge" style="background:var(--danger-soft);color:var(--danger);font-weight:700" dir="ltr">{{ $h->jalali_date }}</span>
          <span style="margin-inline-start:8px">{{ $h->title ?: '—' }}</span>
          @if($h->recurring_yearly)
            <span class="badge" style="margin-inline-start:6px">هرسال</span>
          @endif
        </div>
        <form method="POST" action="{{ route('settings.holidays.destroy', $h) }}" onsubmit="return confirm('حذف این تعطیلی؟')">@csrf @method('DELETE')
          <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)">حذف</button>
        </form>
      </div>
    @empty
      <p class="p-4 text-gray-500">هنوز تعطیلی‌ای ثبت نشده — تقویم فقط جمعه‌ها را قرمز نشان می‌دهد.</p>
    @endforelse
  </div>
</div>

@once
<link rel="stylesheet" href="https://unpkg.com/@majidh1/jalalidatepicker@1.0.0/dist/jalalidatepicker.min.css">
<script src="https://unpkg.com/@majidh1/jalalidatepicker@1.0.0/dist/jalalidatepicker.min.js"></script>
<script>
(function(){
  if (window.jalaliDatepicker) {
    jalaliDatepicker.startWatch({
      selector: 'input[data-jdp]', time: false, hasSecond: false,
      hideAfterChange: true, autoShow: true, autoHide: true,
      showTodayBtn: true, zIndex: 999999, persianDigits: true
    });
  }
})();
</script>
@endonce
@endsection
