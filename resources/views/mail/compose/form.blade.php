@extends('layouts.app')
@section('title', $mode==='reply' ? 'پاسخ' : ($mode==='forward' ? 'فوروارد' : 'نامه جدید'))
@section('actions')
  <a href="{{ route('mail.inbox', ['account'=>$account->id]) }}" class="btn btn-ghost btn-sm">صندوق</a>
  <a href="{{ route('mail.signature') }}" class="btn btn-ghost btn-sm">امضا</a>
@endsection

@section('content')
<div class="max-w-3xl mx-auto">
  @if($drafts->count())
    <div class="card mb-3">
      <div class="card-h text-sm">پیش‌نویس‌های اخیر</div>
      <div class="card-b text-xs flex flex-wrap gap-2">
        @foreach($drafts as $d)
          <a href="{{ route('mail.compose', ['draft'=>$d->id]) }}" class="border rounded px-2 py-1">
            {{ $d->subject ?: '(بدون موضوع)' }} — {{ jdatetime($d->updated_at) }}
          </a>
        @endforeach
      </div>
    </div>
  @endif

  <form method="POST" action="{{ route('mail.compose.send') }}" enctype="multipart/form-data" class="card" id="mailComposeForm">
    @csrf
    <input type="hidden" name="draft_id" value="{{ $draft->id ?? '' }}">
    <input type="hidden" name="in_reply_to" value="{{ old('in_reply_to', $prefill['in_reply_to']) }}">
    <input type="hidden" name="references" value="{{ old('references', $prefill['references']) }}">
    <input type="hidden" name="case_id" value="{{ old('case_id', $prefill['case_id']) }}">
    <input type="hidden" name="contact_id" value="{{ old('contact_id', $prefill['contact_id']) }}">
    <input type="hidden" name="mode" value="{{ $mode }}">

    <div class="card-h">
      {{ $mode==='reply' ? 'پاسخ' : ($mode==='forward' ? 'فوروارد' : 'نامه جدید') }}
      @if(!empty($prefill['case_id']))
        <span class="text-xs font-normal text-gray-500"> — پرونده #{{ $prefill['case_id'] }} (پس از ارسال در تایم‌لاین ثبت می‌شود)</span>
      @endif
    </div>
    <div class="card-b space-y-3 text-sm">
      <label class="block">از اکانت
        <select name="mail_account_id" class="w-full border rounded px-2 py-1.5 mt-1" required>
          @foreach($accounts as $a)
            <option value="{{ $a->id }}" @selected(old('mail_account_id', $account->id)==$a->id)>{{ $a->email }} ({{ $a->name }})</option>
          @endforeach
        </select>
      </label>

      <label class="block">گیرنده *
        <input type="email" name="to" required dir="ltr" value="{{ old('to', $prefill['to']) }}" class="w-full border rounded px-2 py-1.5 mt-1">
      </label>
      <div class="rfq-grid-2" style="gap:12px">
        <label class="block">CC
          <input name="cc" dir="ltr" value="{{ old('cc', $prefill['cc']) }}" class="w-full border rounded px-2 py-1.5 mt-1" placeholder="email1, email2">
        </label>
        <label class="block">BCC
          <input name="bcc" dir="ltr" value="{{ old('bcc', $prefill['bcc']) }}" class="w-full border rounded px-2 py-1.5 mt-1">
        </label>
      </div>
      <label class="block">Reply-To
        <input type="email" name="reply_to" dir="ltr" value="{{ old('reply_to', $prefill['reply_to']) }}" class="w-full border rounded px-2 py-1.5 mt-1">
      </label>
      <label class="block">موضوع *
        <input name="subject" required value="{{ old('subject', $prefill['subject']) }}" class="w-full border rounded px-2 py-1.5 mt-1">
      </label>

      <div>
        <div class="flex gap-2 mb-1 text-xs">
          <button type="button" class="btn btn-ghost btn-sm" onclick="doc.execCommand('bold')">ضخیم</button>
          <button type="button" class="btn btn-ghost btn-sm" onclick="doc.execCommand('italic')">ایتالیک</button>
          <button type="button" class="btn btn-ghost btn-sm" onclick="doc.execCommand('insertUnorderedList')">فهرست</button>
          <button type="button" class="btn btn-ghost btn-sm" onclick="doc.execCommand('createLink', false, prompt('آدرس لینک'))">لینک</button>
        </div>
        <div id="mailEditor" contenteditable="true" class="border rounded px-3 py-2 min-h-[200px] bg-white" style="min-height:220px;line-height:1.7">
          {!! old('body_html', $prefill['body_html']) !!}
        </div>
        <textarea name="body_html" id="bodyHtml" class="hidden">{{ old('body_html', $prefill['body_html']) }}</textarea>
      </div>

      <label class="block">پیوست از سیستم
        <input type="file" name="attachments[]" multiple class="w-full border rounded px-2 py-1.5 mt-1">
      </label>

      @if($caseDocuments->count())
        <div class="border rounded p-2">
          <div class="font-semibold mb-1">پیوست از اسناد پرونده</div>
          @foreach($caseDocuments as $doc)
            <label class="flex items-center gap-2 py-0.5">
              <input type="checkbox" name="document_ids[]" value="{{ $doc->id }}">
              <span>{{ $doc->title ?? $doc->number ?? ('سند #'.$doc->id) }}</span>
            </label>
          @endforeach
        </div>
      @endif

      <div class="flex flex-wrap gap-2 pt-2">
        <button type="submit" class="btn btn-primary">ارسال</button>
        <button type="submit" formaction="{{ route('mail.compose.draft') }}" class="btn btn-ghost">ذخیره پیش‌نویس</button>
        <a href="{{ route('mail.inbox', ['account'=>$account->id]) }}" class="btn btn-ghost">انصراف</a>
      </div>
    </div>
  </form>
</div>
<script>
  const editor = document.getElementById('mailEditor');
  const hidden = document.getElementById('bodyHtml');
  const form = document.getElementById('mailComposeForm');
  form.addEventListener('submit', function () {
    hidden.value = editor.innerHTML;
  });
  // alias for toolbar
  window.doc = document;
  // simpler toolbar targeting editor
  document.querySelectorAll('[onclick^="doc.execCommand"]').forEach(function (btn) {
    btn.onclick = function (e) {
      e.preventDefault();
      const cmd = this.getAttribute('onclick').match(/execCommand\('([^']+)'/);
      if (!cmd) return;
      if (cmd[1] === 'createLink') {
        const url = prompt('آدرس لینک');
        if (url) document.execCommand('createLink', false, url);
      } else {
        document.execCommand(cmd[1], false, null);
      }
      editor.focus();
    };
  });
</script>
@endsection
