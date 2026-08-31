@extends('layouts.app')
@section('title', $account->exists ? 'ویرایش اکانت ایمیل' : 'اکانت ایمیل جدید')
@section('actions')
  <a href="{{ route('mail.accounts.index') }}" class="btn btn-ghost btn-sm">بازگشت</a>
@endsection
@section('content')
<form method="POST" action="{{ $account->exists ? route('mail.accounts.update', $account) : route('mail.accounts.store') }}" class="space-y-4">
  @csrf
  @if($account->exists) @method('PUT') @endif

  <div class="card">
    <div class="card-h">اطلاعات کلی</div>
    <div class="card-b text-sm space-y-3">
      <label class="block">نام داخلی
        <input name="name" required value="{{ old('name', $account->name) }}" class="w-full border rounded px-2 py-1 mt-1">
      </label>
      <label class="block">آدرس ایمیل
        <input name="email" type="email" required dir="ltr" value="{{ old('email', $account->email) }}" class="w-full border rounded px-2 py-1 mt-1">
      </label>
      <label class="block">نام نمایشی در ارسال
        <input name="display_name" value="{{ old('display_name', $account->display_name) }}" class="w-full border rounded px-2 py-1 mt-1">
      </label>
      <div class="flex gap-4 flex-wrap">
        <label class="inline-flex items-center gap-2">
          <input type="checkbox" name="is_shared" value="1" @checked(old('is_shared', $account->is_shared))>
          اکانت مشترک / مرکزی
        </label>
        <label class="inline-flex items-center gap-2">
          <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $account->is_active ?? true))>
          فعال
        </label>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-h">SMTP (ارسال)</div>
    <div class="card-b text-sm rfq-grid-2" style="gap:12px">
      <label class="block">Host
        <input name="smtp_host" dir="ltr" value="{{ old('smtp_host', $account->smtp_host) }}" class="w-full border rounded px-2 py-1 mt-1" placeholder="خالی = تنظیمات شرکت">
      </label>
      <label class="block">Port
        <input name="smtp_port" type="number" value="{{ old('smtp_port', $account->smtp_port ?? 587) }}" class="w-full border rounded px-2 py-1 mt-1">
      </label>
      <label class="block">Encryption
        <select name="smtp_encryption" class="w-full border rounded px-2 py-1 mt-1">
          @foreach(['tls','ssl','none'] as $enc)
            <option value="{{ $enc }}" @selected(old('smtp_encryption', $account->smtp_encryption ?? 'tls')===$enc)>{{ $enc }}</option>
          @endforeach
        </select>
      </label>
      <label class="block">Username
        <input name="smtp_username" dir="ltr" value="{{ old('smtp_username', $account->smtp_username) }}" class="w-full border rounded px-2 py-1 mt-1">
      </label>
      <label class="block" style="grid-column:1/-1">Password {{ $account->exists ? '(خالی بگذارید تا عوض نشود)' : '' }}
        <input name="smtp_password" type="password" dir="ltr" class="w-full border rounded px-2 py-1 mt-1" autocomplete="new-password">
      </label>
    </div>
  </div>

  <div class="card">
    <div class="card-h">IMAP (دریافت)</div>
    <div class="card-b text-sm rfq-grid-2" style="gap:12px">
      <label class="block">Host
        <input name="imap_host" dir="ltr" value="{{ old('imap_host', $account->imap_host) }}" class="w-full border rounded px-2 py-1 mt-1" placeholder="خالی = تنظیمات شرکت">
      </label>
      <label class="block">Port
        <input name="imap_port" type="number" value="{{ old('imap_port', $account->imap_port ?? 993) }}" class="w-full border rounded px-2 py-1 mt-1">
      </label>
      <label class="block">Encryption
        <select name="imap_encryption" class="w-full border rounded px-2 py-1 mt-1">
          @foreach(['ssl','tls','none'] as $enc)
            <option value="{{ $enc }}" @selected(old('imap_encryption', $account->imap_encryption ?? 'ssl')===$enc)>{{ $enc }}</option>
          @endforeach
        </select>
      </label>
      <label class="block">Username
        <input name="imap_username" dir="ltr" value="{{ old('imap_username', $account->imap_username) }}" class="w-full border rounded px-2 py-1 mt-1">
      </label>
      <label class="block">Password {{ $account->exists ? '(خالی = بدون تغییر؛ اگر خالی و SMTP پر شود کپی می‌شود)' : '' }}
        <input name="imap_password" type="password" dir="ltr" class="w-full border rounded px-2 py-1 mt-1" autocomplete="new-password">
      </label>
      <label class="block">پوشه Sent (اختیاری)
        <input name="imap_sent_folder" dir="ltr" value="{{ old('imap_sent_folder', $account->imap_sent_folder) }}" class="w-full border rounded px-2 py-1 mt-1" placeholder="Sent / INBOX.Sent">
      </label>
    </div>
  </div>

  <div class="card">
    <div class="card-h">دسترسی کاربران</div>
    <div class="card-b text-sm">
      <p class="mb-2 text-gray-600">ادمین مشخص می‌کند چه کسانی این اکانت را ببینند و از آن ارسال کنند.</p>
      <div class="space-y-1" style="max-height:240px;overflow:auto;border:1px solid #e5e7eb;border-radius:8px;padding:8px">
        @foreach($users as $u)
          <label class="flex items-center gap-2 py-1">
            <input type="checkbox" name="user_ids[]" value="{{ $u->id }}" @checked(in_array($u->id, old('user_ids', $selectedUserIds)))>
            <span>{{ $u->name }}</span>
            <span class="text-gray-400 text-xs" dir="ltr">{{ $u->email }}</span>
          </label>
        @endforeach
      </div>
    </div>
  </div>

  <div class="flex gap-2">
    <button type="submit" class="btn btn-primary">ذخیره</button>
    <a href="{{ route('mail.accounts.index') }}" class="btn btn-ghost">انصراف</a>
  </div>
</form>
@endsection
