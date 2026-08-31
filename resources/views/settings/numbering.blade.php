@extends('layouts.settings')
@section('title', 'شماره‌گذاری اسناد')
@section('settings')
<div data-no-fa-num>
@if(session('success'))
  <div class="card mb-4" style="border-color:var(--success);background:var(--success-soft);padding:12px 16px;font-size:13px;color:var(--success)">{{ session('success') }}</div>
@endif
<div class="card mb-4">
  <div class="card-h">راهنما</div>
  <div class="card-b text-sm" style="color:var(--muted);line-height:1.7">
    پیشوند، تعداد رقم، شماره شروع و آخرین شماره را تنظیم کنید. اعداد این صفحه انگلیسی نمایش داده می‌شوند.
  </div>
</div>
<form method="POST" action="{{ route('settings.numbering.save') }}">@csrf
  <div class="card">
    <div class="card-h">فرمت شماره‌گذاری</div>
    <div class="card-b pad0">
      <div class="tbl-scroll">
      <table class="tbl" style="min-width:640px">
        <thead>
          <tr>
            <th>نوع سند</th>
            <th>پیشوند</th>
            <th>تعداد رقم</th>
            <th>شروع از</th>
            <th>آخرین شماره</th>
            <th>بعدی</th>
          </tr>
        </thead>
        <tbody>
          @foreach($rows as $i => $r)
          <tr>
            <td style="font-weight:700;white-space:nowrap">{{ $r->label }}
              <input type="hidden" name="sequences[{{ $i }}][type]" value="{{ $r->type }}">
            </td>
            <td><input name="sequences[{{ $i }}][prefix]" value="{{ $r->prefix }}" required class="filter-input" style="width:100px" dir="ltr"></td>
            <td><input type="number" name="sequences[{{ $i }}][pad_length]" value="{{ $r->pad_length }}" min="1" max="12" required class="filter-input" style="width:80px" dir="ltr"></td>
            <td><input type="number" name="sequences[{{ $i }}][start_number]" value="{{ $r->start_number }}" min="0" required class="filter-input" style="width:100px" dir="ltr"></td>
            <td><input type="number" name="sequences[{{ $i }}][last_number]" value="{{ $r->last_number }}" min="0" required class="filter-input" style="width:100px" dir="ltr"></td>
            <td style="font-weight:800;color:var(--brand);white-space:nowrap" dir="ltr">{{ $r->preview_next }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
      </div>
    </div>
  </div>
  <div style="margin-top:14px"><button type="submit" class="btn btn-primary">ذخیره تنظیمات شماره‌گذاری</button></div>
</form>

{{-- M39 (رفعِ باگ): سندهای حذف‌شده‌ی قدیمی که شماره‌شان آزاد شده بود
     (reclaim) اما ردیفِ خودشان (soft-deleted) هنوز همان شماره را داشت،
     می‌توانستند با سندِ تازه‌ای که همان شماره را دوباره می‌گرفت تداخل کنند
     («UNIQUE constraint failed: documents.number_base»). این دکمه فقط
     داده‌های قدیمیِ از قبل خراب‌شده را پاک می‌کند؛ حذف‌های تازه از این پس
     خودکار این مشکل را ندارند. --}}
<div class="card mt-4">
  <div class="card-h">پاک‌سازیِ شماره‌های معلق</div>
  <div class="card-b text-sm" style="color:var(--muted);line-height:1.7">
    اگر موقعِ انتشارِ یک سند با خطای «UNIQUE constraint failed: documents.number_base» مواجه شدید، احتمالاً یک سندِ حذف‌شده‌ی قدیمی هنوز همان شماره را در پس‌زمینه نگه داشته. این دکمه چنین شماره‌هایی را از سندهای حذف‌شده آزاد می‌کند — روی سندهای زنده هیچ اثری ندارد.
    <form method="POST" action="{{ route('settings.numbering.cleanup-orphaned') }}" style="margin-top:8px" onsubmit="return confirm('شماره‌های معلق‌ماندهٔ سندهای حذف‌شده پاک شود؟');">
      @csrf
      <button type="submit" class="btn btn-soft btn-sm">پاک‌سازیِ شماره‌های معلق</button>
    </form>
  </div>
</div>
</div>
@endsection
