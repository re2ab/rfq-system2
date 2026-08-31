@extends('layouts.app')
@section('title', $message['subject'])
@section('actions')
  <a href="{{ route('mailbox.inbox') }}" class="btn btn-ghost btn-sm">بازگشت به صندوق</a>
  <a href="{{ route('mailbox.compose', ['reply_uid' => $message['uid']]) }}" class="btn btn-primary btn-sm">پاسخ</a>
  <a href="{{ route('mailbox.compose', ['forward_uid' => $message['uid']]) }}" class="btn btn-ghost btn-sm">فوروارد</a>
@endsection
@section('content')
<div class="card mb-4">
  <div class="card-b">
    <div class="font-semibold" style="font-size:16px;margin-bottom:8px">{{ $message['subject'] }}</div>
    <div class="text-xs text-gray-500 space-y-1">
      <div><strong>از:</strong> <span dir="ltr">{{ $message['from'] }}</span></div>
      <div><strong>به:</strong> <span dir="ltr">{{ $message['to'] }}</span></div>
      <div><strong>تاریخ:</strong> {{ $message['date'] }}</div>
    </div>
  </div>
</div>

@if(count($message['attachments']))
<div class="card mb-4">
  <div class="card-h">پیوست‌ها ({{ count($message['attachments']) }})</div>
  <div class="card-b pad0">
    @foreach($message['attachments'] as $att)
      <div class="rel-item" style="display:flex;justify-content:space-between;align-items:center;padding:8px 14px;border-bottom:1px solid var(--border)">
        <div class="text-sm">{{ $att['filename'] }} <span class="text-xs text-gray-500">({{ $att['mime'] }}{{ $att['size'] ? ' · '.number_format($att['size']/1024, 0).' KB' : '' }})</span></div>
        <a href="{{ route('mailbox.attachment', ['uid' => $message['uid'], 'part' => $att['part']]) }}" class="btn btn-ghost btn-sm">دانلود</a>
      </div>
    @endforeach
  </div>
</div>
@endif

<div class="card">
  <div class="card-b" style="padding:0">
    @if($message['html'])
      {{-- بدنه‌ی HTML نامه‌ی دریافتی در یک iframe کاملاً sandbox‌شده رندر می‌شود (بدون اجازه‌ی
           اسکریپت/فرم/same-origin) — دقیقاً همان روشی که کلاینت‌های واقعی وب (جیمیل/Outlook) هم
           برای جلوگیری از اجرای کد مخرب داخل نامه‌های دریافتی استفاده می‌کنند. srcdoc از طریق
           {{ }} بلید (htmlspecialchars) escape می‌شود تا breakout از attribute ممکن نباشد. --}}
      <iframe sandbox="" srcdoc="{{ $message['html'] }}" style="width:100%;min-height:420px;border:0;background:#fff;border-radius:0 0 var(--r-lg,8px) var(--r-lg,8px)"></iframe>
    @elseif($message['plain'])
      <pre style="white-space:pre-wrap;word-break:break-word;font-family:inherit;padding:16px;margin:0">{{ $message['plain'] }}</pre>
    @else
      <p class="text-gray-500 text-sm" style="padding:16px">این نامه متنی برای نمایش ندارد.</p>
    @endif
  </div>
</div>
@endsection
