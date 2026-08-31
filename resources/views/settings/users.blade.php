@extends('layouts.settings')
@section('title', 'کاربران سیستم')
@section('settings')
@php
  $editUser = $editUser ?? null;
  $roleIcons = [
    'admin' => '👑',
    'financial_manager' => '💼',
    'technical_manager' => '🛠️',
    'finance_manager' => '💼',
    'financial_expert' => '📊',
    'technical_expert' => '🔧',
    'expert' => '👤',
  ];
@endphp
<div class="case-row-70-30">
  {{-- در RTL، اولین div در DOM سمت راست صفحه قرار می‌گیرد --}}
  <div class="card">
    <div class="card-h">کاربران موجود</div>
    <div class="card-b pad0 text-sm">
      <div class="user-table-head">
        <span>نام کاربر</span><span>ایمیل</span><span>نقش</span>
      </div>
      @foreach($users as $user)
        @php $roles = method_exists($user, 'getRoleNames') ? $user->getRoleNames() : collect(); @endphp
        <div class="user-row">
          <div class="user-row-main">
            <span class="user-row-name">{{ $user->name }}</span>
            <span class="user-row-email" dir="ltr">{{ $user->email }}</span>
            <span class="user-row-roles">
              @forelse($roles as $rn)
                <span class="badge">{{ $roleIcons[$rn] ?? '•' }} {{ role_label($rn) }}</span>
              @empty
                <span class="badge">بدون نقش</span>
              @endforelse
            </span>
          </div>
          <div class="user-row-actions">
            <a href="{{ route('settings.users', ['edit' => $user->id]) }}" class="btn btn-ghost btn-sm">ویرایش</a>
            @if(auth()->id() !== $user->id)
            <form method="POST" action="{{ route('settings.users.destroy', $user) }}" onsubmit="return confirm('حذف این کاربر؟')">@csrf @method('DELETE')
              <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)">حذف</button>
            </form>
            @endif
          </div>
        </div>
      @endforeach
    </div>
  </div>

  <div class="card">
    <div class="card-h">{{ $editUser ? 'ویرایش کاربر' : 'ایجاد کاربر جدید' }}</div>
    <div class="card-b text-sm">
      @if($editUser)
      <form method="POST" action="{{ route('settings.users.update', $editUser) }}" class="space-y-2">@csrf @method('PUT')
        <label class="block">نام<input name="name" value="{{ old('name', $editUser->name) }}" required class="w-full border rounded px-2 py-1 mt-1"></label>
        <label class="block">ایمیل<input name="email" type="email" value="{{ old('email', $editUser->email) }}" required class="w-full border rounded px-2 py-1 mt-1" dir="ltr"></label>
        <label class="block">رمز جدید (اختیاری)<input name="password" type="password" class="w-full border rounded px-2 py-1 mt-1" dir="ltr"></label>
        <label class="block">نقش
          <select name="role" class="w-full border rounded px-2 py-1 mt-1">
            @foreach($rolesList ?? ['admin','technical_manager','financial_manager','technical_expert','financial_expert','expert'] as $r)
              <option value="{{ $r }}" @selected(($editUser->roles->first()->name ?? '')===$r)>{{ $roleIcons[$r] ?? '' }} {{ role_label($r) }}</option>
            @endforeach
          </select>
        </label>
        <div class="flex gap-2">
          <button class="btn btn-primary">ذخیره تغییرات</button>
          <a href="{{ route('settings.users') }}" class="btn btn-ghost">انصراف</a>
        </div>
      </form>
      @else
      <form method="POST" action="{{ route('settings.users.store') }}" class="space-y-2">@csrf
        <label class="block">نام<input name="name" required class="w-full border rounded px-2 py-1 mt-1"></label>
        <label class="block">ایمیل<input name="email" type="email" required class="w-full border rounded px-2 py-1 mt-1" dir="ltr"></label>
        <label class="block">رمز<input name="password" type="password" required class="w-full border rounded px-2 py-1 mt-1" dir="ltr"></label>
        <label class="block">نقش
          <select name="role" class="w-full border rounded px-2 py-1 mt-1">
            @foreach($rolesList ?? ['admin','technical_manager','financial_manager','technical_expert','financial_expert','expert'] as $r)
              <option value="{{ $r }}">{{ $roleIcons[$r] ?? '' }} {{ role_label($r) }}</option>
            @endforeach
          </select>
        </label>
        <button class="btn btn-primary">ایجاد کاربر</button>
      </form>
      @endif
    </div>
  </div>
</div>
@endsection
