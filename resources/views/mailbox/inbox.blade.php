@extends('layouts.app')
@section('title','صندوق ورودی شرکتی')
@section('actions')
  <a href="{{ route('mailbox.sent') }}" class="btn btn-ghost btn-sm">ارسالی‌ها</a>
  <a href="{{ route('mailbox.settings') }}" class="btn btn-ghost btn-sm">تنظیمات</a>
  <a href="{{ route('mailbox.compose') }}" class="btn btn-primary btn-sm">نامه‌ی جدید</a>
@endsection
@section('content')
@if(!$imapReady)
  <div class="card"><div class="card-b text-sm">IMAP هنوز کامل تنظیم نشده.
    <a href="{{ route('mailbox.settings') }}">تنظیم صندوق</a>
  </div></div>
@else
  <p class="text-xs text-gray-500 mb-2">{{ $acc->email }} · آخرین همگام‌سازی: {{ $acc->last_synced_at ?? '—' }} · {{ $total }} پیام</p>
  <div class="card"><div class="card-b pad0">
    @forelse($messages as $m)
      <a href="{{ route('mailbox.show', $m['uid']) }}" class="rel-item" style="display:block;padding:10px 14px;border-bottom:1px solid var(--border);text-decoration:none;color:inherit">
        <div style="display:flex;justify-content:space-between;gap:10px;align-items:baseline">
          <div class="text-sm" style="{{ $m['seen'] ? '' : 'font-weight:800' }};overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
            {{ $m['subject'] }}
            @if($m['answered'])<span class="text-xs" style="color:var(--muted)">(پاسخ داده‌شده)</span>@endif
          </div>
          <div class="text-xs text-gray-500" style="white-space:nowrap;flex-shrink:0">{{ $m['date'] }}</div>
        </div>
        <div class="text-xs text-gray-500" style="{{ $m['seen'] ? '' : 'font-weight:700;color:var(--fg)' }}">{{ $m['from'] }}</div>
      </a>
    @empty
      <p class="p-4 text-gray-500 text-sm">پیامی دریافت نشد یا صندوق خالی است.</p>
    @endforelse
  </div></div>
  @if($total > $perPage)
    <div class="flex gap-2 mt-3">
      @if($offset + $perPage < $total)
        <a href="{{ route('mailbox.inbox', ['offset' => $offset + $perPage]) }}" class="btn btn-ghost btn-sm">قدیمی‌ترها ←</a>
      @endif
      @if($offset > 0)
        <a href="{{ route('mailbox.inbox', ['offset' => max(0, $offset - $perPage)]) }}" class="btn btn-ghost btn-sm">→ جدیدترها</a>
      @endif
    </div>
  @endif
@endif
@endsection
