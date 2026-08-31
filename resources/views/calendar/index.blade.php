@extends('layouts.app')
@section('object', 'تقویم')
@section('icon', '📅')
@section('title', $monthName . ' ' . $jy)
@section('actions')
  <span class="cal-desktop-only cal-nav-actions" style="gap:4px">
    <a class="btn btn-sm {{ $view === 'month' ? 'btn-primary' : 'btn-ghost' }}" href="?view=month&jy={{ $jy }}&jm={{ $jm }}">ماهانه</a>
    <a class="btn btn-sm {{ $view === 'week' ? 'btn-primary' : 'btn-ghost' }}" href="?view=week&jy={{ $jy }}&jm={{ $jm }}&jd={{ $jd }}&wo={{ $weekOffset }}">هفتگی</a>
    <a class="btn btn-sm {{ $view === 'day' ? 'btn-primary' : 'btn-ghost' }}" href="?view=day&jy={{ $jy }}&jm={{ $jm }}&jd={{ $jd }}&do={{ $doOffset }}">روزانه</a>
  </span>

  <span class="cal-desktop-only cal-nav-actions">
    @if($view === 'week')
      <a class="btn btn-ghost" href="?view=week&jy={{ $jy }}&jm={{ $jm }}&jd={{ $jd }}&wo={{ $weekOffset - 1 }}">هفته قبل</a>
      <a class="btn btn-ghost" href="?view=week&jy={{ \Morilog\Jalali\Jalalian::now()->getYear() }}&jm={{ \Morilog\Jalali\Jalalian::now()->getMonth() }}&jd={{ \Morilog\Jalali\Jalalian::now()->getDay() }}&wo=0">این هفته</a>
      <a class="btn btn-ghost" href="?view=week&jy={{ $jy }}&jm={{ $jm }}&jd={{ $jd }}&wo={{ $weekOffset + 1 }}">هفته بعد</a>
    @elseif($view === 'day')
      <a class="btn btn-ghost" href="?view=day&jy={{ $jy }}&jm={{ $jm }}&jd={{ $jd }}&do={{ $doOffset - 1 }}">روز قبل</a>
      <a class="btn btn-ghost" href="?view=day&jy={{ \Morilog\Jalali\Jalalian::now()->getYear() }}&jm={{ \Morilog\Jalali\Jalalian::now()->getMonth() }}&jd={{ \Morilog\Jalali\Jalalian::now()->getDay() }}&do=0">امروز</a>
      <a class="btn btn-ghost" href="?view=day&jy={{ $jy }}&jm={{ $jm }}&jd={{ $jd }}&do={{ $doOffset + 1 }}">روز بعد</a>
    @else
      <a class="btn btn-ghost" href="?view=month&jy={{ $prevJy }}&jm={{ $prevJm }}">ماه قبل</a>
      <a class="btn btn-ghost" href="?view=month&jy={{ \Morilog\Jalali\Jalalian::now()->getYear() }}&jm={{ \Morilog\Jalali\Jalalian::now()->getMonth() }}">امروز</a>
      <a class="btn btn-ghost" href="?view=month&jy={{ $nextJy }}&jm={{ $nextJm }}">ماه بعد</a>
    @endif
  </span>

  {{-- موبایل: ناوبری هفته --}}
  <span class="cal-mobile-only cal-nav-actions">
    <a class="btn btn-ghost btn-sm" href="?jy={{ $jy }}&jm={{ $jm }}&jd={{ $jd }}&wo={{ $weekOffset - 1 }}">هفته قبل</a>
    <a class="btn btn-ghost btn-sm" href="?jy={{ \Morilog\Jalali\Jalalian::now()->getYear() }}&jm={{ \Morilog\Jalali\Jalalian::now()->getMonth() }}&jd={{ \Morilog\Jalali\Jalalian::now()->getDay() }}&wo=0">این هفته</a>
    <a class="btn btn-ghost btn-sm" href="?jy={{ $jy }}&jm={{ $jm }}&jd={{ $jd }}&wo={{ $weekOffset + 1 }}">هفته بعد</a>
  </span>
@endsection

@section('content')
@php
  $faDigits = function ($n) {
    return strtr((string)$n, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']);
  };
  $dayNames = ['شنبه','یکشنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنجشنبه','جمعه'];
@endphp

{{-- ========== موبایل: نمای هفتگی (همیشه) ========== --}}
<div class="cal-mobile-only">
  <div class="card mb-3">
    <div class="card-h" style="font-size:13px">
      <span>نمای هفتگی</span>
      <span class="muted" style="font-weight:600">{{ $faDigits($weekLabel) }}</span>
    </div>
  </div>

  <div class="cal-mobile-week-list">
    @foreach($weekDays as $i => $wd)
      @php
        $dayTasks = $tasks[$wd['key']] ?? collect();
        $holidayTitle = $holidays[$wd['key']] ?? null;
        $isOff = $wd['isFri'] || $holidayTitle;
      @endphp
      <div class="card cal-mobile-day-card" style="{{ $wd['isToday'] ? 'box-shadow:0 0 0 2px var(--brand);' : '' }}{{ $isOff ? 'background:var(--danger-soft)!important;' : '' }}">
        <div style="display:flex;gap:12px;padding:12px 14px;align-items:flex-start">
          <div style="width:52px;flex-shrink:0;text-align:center">
            <div style="font-size:11px;font-weight:700;color:{{ $isOff ? 'var(--danger)' : 'var(--muted)' }}">{{ $dayNames[$i] }}</div>
            <div style="font-size:22px;font-weight:900;line-height:1.2;color:{{ $isOff ? 'var(--danger)' : ($wd['isToday'] ? 'var(--brand)' : 'var(--text)') }}">
              {{ $faDigits($wd['day']) }}
            </div>
            <div style="font-size:10px;color:var(--muted)">{{ $wd['monthName'] }}</div>
            @if($holidayTitle)
              <div style="font-size:9px;color:var(--danger);font-weight:700;margin-top:2px">{{ $holidayTitle }}</div>
            @endif
          </div>
          <div style="flex:1;min-width:0;border-right:1px solid var(--border-soft);padding-right:12px">
            @forelse($dayTasks as $t)
              <a href="{{ route('tasks.show', $t) }}"
                 style="display:block;padding:8px 10px;margin-bottom:6px;border-radius:10px;background:var(--brand-soft-2);border:1px solid var(--border-soft);font-size:13px;font-weight:600;color:var(--brand-dark)">
                <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $t->title }}</div>
                <div style="font-size:11px;color:var(--muted);font-weight:500;margin-top:2px">
                  @if($t->assignee) {{ $t->assignee->name }} @endif
                </div>
              </a>
            @empty
              <div style="font-size:12px;color:var(--muted);padding:8px 0">بدون وظیفه</div>
            @endforelse
          </div>
        </div>
      </div>
    @endforeach
  </div>
  <p class="text-[11px] text-muted mt-3">موبایل: نمای هفته (شنبه تا جمعه) · جمعه با پس‌زمینه قرمز روشن</p>
</div>

{{-- ========== دسکتاپ: نمای ماه ========== --}}
<div class="cal-desktop-only">
@if($view === 'month')
  <div class="cal-month-head">
    @foreach($dayNames as $i => $d)
      <div class="p-2 rounded {{ $i===6 ? 'bg-[var(--danger-soft)!important] text-[var(--danger)]' : 'bg-[var(--surface-2)] text-muted' }}">{{ $d }}</div>
    @endforeach
  </div>

  <div class="cal-month-grid">
    @for($i = 0; $i < $startWeekday; $i++)
      <div class="min-h-[88px] rounded bg-[var(--surface-2)]"></div>
    @endfor

    @for($day = 1; $day <= $daysInMonth; $day++)
      @php
        $key = sprintf('%04d-%02d-%02d', $jy, $jm, $day);
        $dayTasks = $tasks[$key] ?? collect();
        $col = ($startWeekday + $day - 1) % 7;
        $isFri = $col === 6;
        $holidayTitle = $holidays[$key] ?? null;
        $isOff = $isFri || $holidayTitle;
        $isToday = $key === $todayKey;
      @endphp
      <div class="min-h-[88px] border rounded-xl p-1.5 text-xs
        {{ $isOff ? 'bg-[var(--danger-soft)!important] border-[var(--border-soft)]' : 'bg-white border-[var(--border-soft)]' }}
        {{ $isToday ? 'ring-2 ring-[var(--brand)]' : '' }}" @if($holidayTitle) title="{{ $holidayTitle }}" @endif>
        <div class="font-bold mb-1 {{ $isOff ? 'text-[var(--danger)]' : 'text-[var(--text)]' }}">{{ $faDigits($day) }}</div>
        @if($holidayTitle)
          <div class="truncate" style="font-size:10px;font-weight:700;color:var(--danger)">{{ $holidayTitle }}</div>
        @endif
        @foreach($dayTasks->take(3) as $t)
          <a href="{{ route('tasks.show', $t) }}" class="block truncate text-brand hover:underline mb-0.5">{{ $t->title }}</a>
        @endforeach
        @if($dayTasks->count() > 3)
          <div class="text-[var(--muted)]">+{{ $faDigits($dayTasks->count() - 3) }}</div>
        @endif
      </div>
    @endfor
  </div>
  <p class="text-[11px] text-muted mt-3">دسکتاپ: تقویم ماهانه شمسی · شروع از شنبه · جمعه تعطیل</p>

@elseif($view === 'week')
  <div class="cal-week-grid">
    @foreach($weekDays as $i => $wd)
      @php
        $dayTasks = $tasks[$wd['key']] ?? collect();
        $holidayTitle = $holidays[$wd['key']] ?? null;
      @endphp
      <div class="cal-week-col {{ ($wd['isFri'] || $holidayTitle) ? 'is-fri' : '' }} {{ $wd['isToday'] ? 'is-today' : '' }}">
        <div class="cal-week-col-head">
          <span>{{ $dayNames[$i] }}</span>
          <strong>{{ $faDigits($wd['day']) }}</strong>
          @if($holidayTitle)<small style="display:block;color:var(--danger);font-weight:700">{{ $holidayTitle }}</small>@endif
        </div>
        <div class="cal-week-col-body">
          @forelse($dayTasks as $t)
            <a href="{{ route('tasks.show', $t) }}" class="cal-task-pill">
              <span class="truncate-1">{{ $t->title }}</span>
              @if($t->assignee)<small>{{ $t->assignee->name }}</small>@endif
            </a>
          @empty
            <div class="cal-empty">بدون وظیفه</div>
          @endforelse
        </div>
      </div>
    @endforeach
  </div>
  <p class="text-[11px] text-muted mt-3">دسکتاپ: نمای هفتگی · {{ $faDigits($weekLabel) }}</p>

@else
  @php $holidayTitle = $holidays[$dayInfo['key']] ?? null; @endphp
  <div class="cal-day-view {{ ($dayInfo['isFri'] || $holidayTitle) ? 'is-fri' : '' }}">
    <div class="cal-day-view-head">
      <span>{{ $dayInfo['dayName'] }}</span>
      <strong>{{ $faDigits($dayInfo['day']) }} {{ $dayInfo['monthName'] }} {{ $faDigits($dayInfo['year']) }}</strong>
      @if($dayInfo['isToday'])<span class="badge">امروز</span>@endif
      @if($holidayTitle)<span class="badge" style="background:var(--danger-soft)!important;color:var(--danger)">{{ $holidayTitle }}</span>@endif
    </div>
    <div class="cal-day-view-body">
      @php $dayTasksList = $tasks[$dayInfo['key']] ?? collect(); @endphp
      @forelse($dayTasksList as $t)
        <a href="{{ route('tasks.show', $t) }}" class="cal-task-row">
          <span class="truncate-1">{{ $t->title }}</span>
          @if($t->assignee)<small>{{ $t->assignee->name }}</small>@endif
        </a>
      @empty
        <div class="cal-empty">بدون وظیفه برای این روز</div>
      @endforelse
    </div>
  </div>
  <p class="text-[11px] text-muted mt-3">دسکتاپ: نمای روزانه</p>
@endif
</div>
@endsection
