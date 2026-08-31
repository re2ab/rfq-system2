@extends('layouts.app')
@section('title', 'ایمیل‌ها')
@section('actions')
  <x-btn href="{{ route('emails.create') }}">ایمیل جدید</x-btn>
@endsection

@section('content')
@php
  $folder = request('folder', 'all');
  $all = $emails;
@endphp
<div class="email-layout">
  <nav class="email-folders card">
    <div class="card-h">پوشه‌ها</div>
    <div class="card-b pad0">
      <a class="settings-nav-item {{ $folder==='all' ? 'active' : '' }}" href="{{ route('emails.index') }}">همه</a>
      <a class="settings-nav-item {{ $folder==='inbound' ? 'active' : '' }}" href="{{ route('emails.index', ['folder'=>'inbound']) }}">صندوق ورودی</a>
      <a class="settings-nav-item {{ $folder==='outbound' ? 'active' : '' }}" href="{{ route('emails.index', ['folder'=>'outbound']) }}">ارسال‌شده</a>
      <a class="settings-nav-item {{ $folder==='unmatched' ? 'active' : '' }}" href="{{ route('emails.index', ['folder'=>'unmatched']) }}">بدون پرونده</a>
    </div>
  </nav>
  <div class="email-main">
    <x-card title="ورود دستی (شبیه‌ساز IMAP)">
      <form method="POST" action="{{ route('emails.import') }}" class="space-y-2 text-sm">@csrf
        <div class="rfq-grid-2">
          <input name="from_address" type="email" required placeholder="از" class="w-full border rounded px-2 py-1">
          <input name="to_address" type="email" placeholder="به" class="w-full border rounded px-2 py-1">
        </div>
        <input name="subject" placeholder="موضوع — CASE-000001 برای لینک خودکار" class="w-full border rounded px-2 py-1">
        <textarea name="body" rows="2" class="w-full border rounded px-2 py-1" placeholder="متن"></textarea>
        <x-btn type="submit" size="sm">وارد کردن و Matching</x-btn>
      </form>
    </x-card>

    <div class="card" style="margin-top:12px;overflow:hidden">
      <div class="card-h"><span>پیام‌ها</span></div>
      <div class="data-table-desktop">
        <table class="tbl">
          <thead>
            <tr>
              <th>جهت</th>
              <th>از / به</th>
              <th>موضوع</th>
              <th>پرونده</th>
              <th>وضعیت لینک</th>
            </tr>
          </thead>
          <tbody>
            @forelse($emails as $e)
            <tr>
              <td><x-badge :tone="$e->direction==='inbound' ? 'info' : 'muted'">{{ $e->direction==='inbound' ? 'ورودی' : 'خروجی' }}</x-badge></td>
              <td style="font-size:12px">{{ $e->direction==='inbound' ? $e->from_address : $e->to_address }}</td>
              <td style="font-weight:600">{{ $e->subject }}</td>
              <td>
                @if($e->case)
                  <a href="{{ route('cases.show', $e->case) }}">{{ $e->case->case_number }}</a>
                @else — @endif
              </td>
              <td>{{ $e->is_linked ? '✓ متصل' : '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="5"><x-empty title="ایمیلی در این پوشه نیست" /></td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      <div class="data-table-mobile">
        @forelse($emails as $e)
        <div class="mobile-list-card">
          <div style="display:flex;justify-content:space-between">
            <x-badge :tone="$e->direction==='inbound' ? 'info' : 'muted'">{{ $e->direction==='inbound' ? 'ورودی' : 'خروجی' }}</x-badge>
            <span style="font-size:11px;color:var(--muted)">{{ $e->is_linked ? 'متصل' : 'بدون پرونده' }}</span>
          </div>
          <div style="font-weight:700;margin-top:6px">{{ $e->subject }}</div>
          <div class="rel-meta">{{ $e->direction==='inbound' ? $e->from_address : $e->to_address }}</div>
        </div>
        @empty
          <x-empty title="ایمیلی نیست" />
        @endforelse
      </div>
    </div>
    @if(method_exists($emails, 'links'))
      <div style="margin-top:12px">{{ $emails->withQueryString()->links() }}</div>
    @endif
  </div>
</div>
@endsection
