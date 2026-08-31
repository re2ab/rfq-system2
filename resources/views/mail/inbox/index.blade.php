@extends('layouts.app')
@section('title','صندوق ایمیل')
@section('actions')
  <a href="{{ route('mail.compose', ['account' => $account->id]) }}" class="btn btn-primary btn-sm">نامه جدید</a>
  <form method="POST" action="{{ route('mail.inbox.sync') }}" class="inline">@csrf
    <input type="hidden" name="account" value="{{ $account->id }}">
    <button type="submit" class="btn btn-ghost btn-sm">همگام‌سازی</button>
  </form>
  <a href="{{ route('mail.signature') }}" class="btn btn-ghost btn-sm">امضا</a>
@endsection

@section('content')
<style>
  .mail-shell{display:grid;grid-template-columns:200px 320px 1fr;gap:0;min-height:70vh;border:1px solid var(--border,#e5e7eb);border-radius:12px;overflow:hidden;background:var(--surface,#fff)}
  .mail-col{overflow:auto;max-height:75vh}
  .mail-folders{border-left:1px solid var(--border,#e5e7eb);background:var(--bg-soft,#f8fafc);padding:8px}
  .mail-list{border-left:1px solid var(--border,#e5e7eb)}
  .mail-folder-item,.mail-msg-item{display:block;padding:8px 10px;border-radius:8px;text-decoration:none;color:inherit;margin-bottom:2px}
  .mail-folder-item:hover,.mail-msg-item:hover{background:rgba(0,0,0,.04)}
  .mail-folder-item.active,.mail-msg-item.active{background:rgba(46,117,182,.12)}
  .mail-msg-item.unseen{font-weight:700}
  .mail-msg-meta{font-size:11px;color:#6b7280;display:flex;justify-content:space-between;gap:8px}
  .mail-msg-sub{font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .mail-read{padding:16px}
  .mail-toolbar{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px}
  .mail-thread-item{border:1px solid var(--border,#e5e7eb);border-radius:10px;padding:12px;margin-bottom:10px}
  .mail-body{line-height:1.7;font-size:14px;overflow-wrap:anywhere}
  .mail-body img{max-width:100%;height:auto}
  .mail-topbar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:10px}
  @media(max-width:960px){
    .mail-shell{grid-template-columns:1fr;min-height:auto}
    .mail-col{max-height:none}
    .mail-folders,.mail-list{border-left:0;border-bottom:1px solid var(--border,#e5e7eb)}
  }
</style>

<div class="mail-topbar text-sm">
  <form method="GET" action="{{ route('mail.inbox') }}" class="flex flex-wrap gap-2 items-center" style="flex:1">
    <select name="account" class="border rounded px-2 py-1" onchange="this.form.submit()">
      @foreach($accounts as $a)
        <option value="{{ $a->id }}" @selected($a->id===$account->id)>{{ $a->name }} — {{ $a->email }}</option>
      @endforeach
    </select>
    <input type="hidden" name="folder" value="{{ $folder?->id }}">
    <input type="search" name="q" value="{{ $q }}" placeholder="جستجو در موضوع، فرستنده، متن…" class="border rounded px-2 py-1" style="min-width:200px;flex:1">
    <select name="filter" class="border rounded px-2 py-1" onchange="this.form.submit()">
      <option value="">همه</option>
      <option value="unseen" @selected($filter==='unseen')>نخوانده</option>
      <option value="flagged" @selected($filter==='flagged')>ستاره‌دار</option>
    </select>
    <button class="btn btn-ghost btn-sm" type="submit">جستجو</button>
  </form>
</div>

<div class="mail-shell">
  {{-- فولدرها --}}
  <div class="mail-col mail-folders text-sm">
    <div class="font-bold mb-2 px-1">فولدرها</div>
    @forelse($folders as $f)
      <a class="mail-folder-item {{ $folder && $folder->id===$f->id ? 'active' : '' }}"
         href="{{ route('mail.inbox', ['account'=>$account->id,'folder'=>$f->id,'q'=>$q,'filter'=>$filter]) }}">
        <span>{{ $f->name }}</span>
        @if($f->unseen_count)
          <span class="text-xs" style="float:left;background:#2E75B6;color:#fff;border-radius:999px;padding:0 6px">{{ fa_num($f->unseen_count) }}</span>
        @endif
      </a>
    @empty
      <div class="text-xs text-gray-500 px-1">هنوز فولدری sync نشده. دکمه همگام‌سازی را بزنید.</div>
    @endforelse
  </div>

  {{-- لیست --}}
  <div class="mail-col mail-list text-sm">
    @forelse($messages as $m)
      <a class="mail-msg-item {{ !$m->is_seen ? 'unseen' : '' }} {{ $selected && $selected->id===$m->id ? 'active' : '' }}"
         href="{{ route('mail.inbox', ['account'=>$account->id,'folder'=>$folder?->id,'msg'=>$m->id,'q'=>$q,'filter'=>$filter]) }}">
        <div class="mail-msg-meta">
          <span dir="ltr">{{ $m->from_name ?: $m->from_address ?: '—' }}</span>
          <span>{{ $m->date_sent ? jdatetime($m->date_sent) : '—' }}</span>
        </div>
        <div class="mail-msg-sub">
          @if($m->is_flagged)★ @endif
          {{ $m->subject ?: '(بدون موضوع)' }}
        </div>
      </a>
    @empty
      <div class="p-4 text-gray-500 text-sm">پیامی در این فولدر نیست.</div>
    @endforelse
    <div class="p-2">{{ $messages->links() }}</div>
  </div>

  {{-- خواندن / thread --}}
  <div class="mail-col mail-read">
    @if($selected)
      <div class="mail-toolbar">
        <a class="btn btn-primary btn-sm" href="{{ route('mail.compose', ['account'=>$account->id,'reply_to_msg'=>$selected->id,'mode'=>'reply']) }}">پاسخ</a>
        <a class="btn btn-ghost btn-sm" href="{{ route('mail.compose', ['account'=>$account->id,'reply_to_msg'=>$selected->id,'mode'=>'forward']) }}">فوروارد</a>
        <form method="POST" action="{{ route('mail.message.flag', $selected) }}" class="inline">@csrf
          <button class="btn btn-ghost btn-sm" type="submit">{{ $selected->is_flagged ? 'برداشتن ستاره' : 'ستاره' }}</button>
        </form>
        <form method="POST" action="{{ route('mail.message.archive', $selected) }}" class="inline">@csrf
          <button class="btn btn-ghost btn-sm" type="submit">آرشیو</button>
        </form>
        @if($selected->case_id)
          <a class="btn btn-ghost btn-sm" href="{{ route('cases.show', $selected->case_id) }}">پرونده #{{ $selected->case_id }}</a>
        @endif
      </div>


      {{-- فاز D: لینک به پرونده / پیشنهاد تطبیق --}}
      <div class="border rounded-lg p-3 mb-3 text-sm" style="background:var(--bg-soft,#f8fafc)">
        <div class="font-bold mb-2">ارتباط با RFQ</div>
        @if($selected->case_id)
          <div class="mb-2">
            لینک به پرونده:
            <a href="{{ route('cases.show', $selected->case_id) }}" class="font-semibold">#{{ $selected->case_id }}</a>
            <form method="POST" action="{{ route('mail.message.unlink-case', $selected) }}" class="inline">@csrf
              <button type="submit" class="btn btn-ghost btn-sm">برداشتن لینک</button>
            </form>
          </div>
        @else
          <form method="POST" action="{{ route('mail.message.link-case', $selected) }}" class="flex flex-wrap gap-2 items-end mb-2">@csrf
            <label class="text-xs">شناسه پرونده
              <input type="number" name="case_id" required class="border rounded px-2 py-1" style="width:110px" placeholder="ID">
            </label>
            <button type="submit" class="btn btn-primary btn-sm">لینک به پرونده</button>
          </form>
        @endif

        @if(!empty($suggestions))
          @if($suggestions['cases']->count())
            <div class="mb-2">
              <div class="text-xs text-gray-500 mb-1">پیشنهاد پرونده</div>
              <div class="flex flex-wrap gap-1">
                @foreach($suggestions['cases'] as $sc)
                  <form method="POST" action="{{ route('mail.message.link-case', $selected) }}">@csrf
                    <input type="hidden" name="case_id" value="{{ $sc->id }}">
                    <button type="submit" class="btn btn-ghost btn-sm" title="{{ $sc->title }}">
                      {{ $sc->case_number ?: ('#'.$sc->id) }}
                    </button>
                  </form>
                @endforeach
              </div>
            </div>
          @endif
          @if($suggestions['contacts']->count())
            <div class="mb-2">
              <div class="text-xs text-gray-500 mb-1">پیشنهاد مخاطب</div>
              <div class="flex flex-wrap gap-1">
                @foreach($suggestions['contacts'] as $sc)
                  <form method="POST" action="{{ route('mail.message.link-contact', $selected) }}">@csrf
                    <input type="hidden" name="contact_id" value="{{ $sc->id }}">
                    <button type="submit" class="btn btn-ghost btn-sm">{{ $sc->full_name }} ({{ $sc->email }})</button>
                  </form>
                @endforeach
              </div>
            </div>
          @endif
          @if($suggestions['organizations']->count())
            <div class="text-xs text-gray-500">سازمان‌های مرتبط:
              {{ $suggestions['organizations']->pluck('name')->join('، ') }}
            </div>
          @endif
        @endif
        <div class="mt-2">
          <a href="{{ route('mail.compose', ['account'=>$account->id,'case_id'=>$selected->case_id, 'contact_id'=>$selected->contact_id]) }}" class="btn btn-ghost btn-sm">نامه جدید با همین زمینه</a>
          <a href="{{ route('mail.unmatched') }}" class="btn btn-ghost btn-sm">ایمیل‌های بدون پرونده</a>
        </div>
      </div>

      <h2 class="text-lg font-bold mb-2">{{ $selected->subject ?: '(بدون موضوع)' }}</h2>

      @foreach($thread as $t)
        <div class="mail-thread-item">
          <div class="mail-msg-meta mb-2">
            <span><strong>{{ $t->from_name ?: $t->from_address }}</strong> <span dir="ltr" class="text-xs">{{ $t->from_address }}</span></span>
            <span>{{ $t->date_sent ? jdatetime($t->date_sent) : '' }}</span>
          </div>
          @if($t->to_json)
            <div class="text-xs text-gray-500 mb-2" dir="ltr">To: {{ collect($t->to_json)->pluck('email')->filter()->join(', ') }}</div>
          @endif
          <div class="mail-body">
            @if($t->body_html)
              {!! $t->body_html !!}
            @else
              {!! nl2br(e($t->body_text ?: '')) !!}
            @endif
          </div>
          @if($t->has_attachments && $t->attachments && $t->attachments->count())
            <div class="mt-2 text-xs">
              پیوست‌ها:
              @foreach($t->attachments as $att)
                <span class="inline-block border rounded px-2 py-0.5 ml-1">{{ $att->filename }} ({{ $att->mime }})</span>
              @endforeach
            </div>
          @elseif($t->has_attachments)
            <div class="mt-2 text-xs text-gray-500">این پیام پیوست دارد (دانلود مستقیم در فاز بعد کامل می‌شود).</div>
          @endif
        </div>
      @endforeach
    @else
      <div class="text-gray-500 text-sm p-6">یک پیام از لیست انتخاب کنید.</div>
    @endif
  </div>
</div>
@endsection
