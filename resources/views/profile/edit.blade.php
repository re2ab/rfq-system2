@extends('layouts.app')
@section('title', 'پروفایل')
@section('content')
<form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="card">
  @csrf @method('PUT')
  <div class="card-b">
    <div class="form-grid4">
      <div class="f-full" style="display:flex; align-items:center; gap:var(--s-4); flex-direction:row;">
        @if($user->avatar)
          <img src="{{ asset('storage/'.$user->avatar) }}" style="width:64px;height:64px;border-radius:50%;object-fit:cover;flex-shrink:0;">
        @else
          <div style="width:64px;height:64px;border-radius:50%;background:var(--brand-soft);color:var(--brand-dark);display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:700;flex-shrink:0;">{{ mb_substr($user->name,0,1) }}</div>
        @endif
        <div>
          <div style="font-weight:700;">{{ $user->name }}</div>
          <div class="f-help">{{ $user->email }}</div>
        </div>
      </div>

      <div class="f">
        <label class="f-label">نام</label>
        <input name="name" value="{{ old('name',$user->name) }}" required>
      </div>
      <div class="f">
        <label class="f-label">ایمیل (فقط نمایش)</label>
        <input value="{{ $user->email }}" disabled>
      </div>
      <div class="f">
        <label class="f-label">زبان</label>
        <select name="locale">
          <option value="fa" @selected(($user->locale??'fa')==='fa')>فارسی</option>
          <option value="en" @selected(($user->locale??'')==='en')>English</option>
        </select>
      </div>
      <div class="f">
        <label class="f-label">عکس پروفایل</label>
        <input type="file" name="avatar" accept="image/*">
        @if($user->avatar)
          <button type="submit" name="remove_avatar" value="1" class="btn btn-danger-soft btn-sm" style="margin-top:6px; align-self:flex-start;" onclick="return confirm('عکس پروفایل حذف شود؟')">حذف عکس</button>
        @endif
      </div>

      <div class="f-full">
        <p class="f-help" style="border-top:1px solid var(--border-soft); padding-top:var(--s-3);">تغییر ایمیل و رمز عبور فقط توسط مدیر در تنظیمات ← کاربران انجام می‌شود.</p>
      </div>
    </div>
    <div class="form-actions-sm">
      <button type="submit" class="btn btn-primary">ذخیره</button>
    </div>
  </div>
</form>
@endsection
