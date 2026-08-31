@extends('layouts.app')
@section('title','اکانت‌های ایمیل یکپارچه')
@section('actions')
  <a href="{{ route('mail.accounts.create') }}" class="btn btn-primary btn-sm">اکانت جدید</a>
@endsection
@section('content')
<div class="card mb-4">
  <div class="card-b text-sm">
    فاز A: تعریف اکانت‌های SMTP/IMAP، تخصیص کاربر توسط ادمین، و همگام‌سازی به دیتابیس محلی.
    صندوق قدیمی <code>/mailbox</code> همچنان کار می‌کند. UI کامل Inbox در فاز B می‌آید.
  </div>
</div>

<div class="card">
  <div class="card-h">فهرست اکانت‌ها ({{ $accounts->count() }})</div>
  <div class="card-b" style="overflow-x:auto">
    <table class="w-full text-sm" style="border-collapse:collapse">
      <thead>
        <tr style="text-align:right;border-bottom:1px solid #e5e7eb">
          <th class="py-2 px-2">نام</th>
          <th class="py-2 px-2">ایمیل</th>
          <th class="py-2 px-2">نوع</th>
          <th class="py-2 px-2">وضعیت</th>
          <th class="py-2 px-2">کاربران</th>
          <th class="py-2 px-2">آخرین sync</th>
          <th class="py-2 px-2">عملیات</th>
        </tr>
      </thead>
      <tbody>
        @forelse($accounts as $a)
          <tr style="border-bottom:1px solid #f3f4f6">
            <td class="py-2 px-2">{{ $a->name }}</td>
            <td class="py-2 px-2" dir="ltr">{{ $a->email }}</td>
            <td class="py-2 px-2">{{ $a->is_shared ? 'مشترک/مرکزی' : 'شخصی' }}</td>
            <td class="py-2 px-2">
              @if($a->is_active)
                <span class="text-green-700">فعال</span>
              @else
                <span class="text-red-600">غیرفعال</span>
              @endif
            </td>
            <td class="py-2 px-2">
              @if($a->users->isEmpty())
                <span class="text-gray-400">—</span>
              @else
                {{ $a->users->pluck('name')->join('، ') }}
              @endif
            </td>
            <td class="py-2 px-2 text-xs">
              {{ $a->last_synced_at ? jdatetime($a->last_synced_at) : '—' }}
              @if($a->last_sync_error)
                <div class="text-red-600" title="{{ $a->last_sync_error }}">خطای sync</div>
              @endif
            </td>
            <td class="py-2 px-2 whitespace-nowrap">
              <a href="{{ route('mail.accounts.edit', $a) }}" class="btn btn-ghost btn-sm">ویرایش</a>
              <form method="POST" action="{{ route('mail.accounts.test', $a) }}" class="inline">@csrf
                <button class="btn btn-ghost btn-sm" type="submit">تست IMAP</button>
              </form>
              <form method="POST" action="{{ route('mail.accounts.sync', $a) }}" class="inline">@csrf
                <button class="btn btn-ghost btn-sm" type="submit">Sync الآن</button>
              </form>
              <form method="POST" action="{{ route('mail.accounts.destroy', $a) }}" class="inline" onsubmit="return confirm('حذف این اکانت و پیام‌های همگام‌شده؟')">@csrf @method('DELETE')
                <button class="btn btn-ghost btn-sm text-red-600" type="submit">حذف</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="py-6 text-center text-gray-500">هنوز اکانتی تعریف نشده است.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="card mt-4">
  <div class="card-h">دستورات سرور</div>
  <div class="card-b text-sm" dir="ltr" style="font-family:monospace">
    php artisan mail:sync<br>
    php artisan mail:sync --account=1<br>
    php artisan migrate
  </div>
</div>
@endsection
