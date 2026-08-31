@extends('layouts.app')
@section('title', $contact->full_name)
@section('actions')
  <a href="{{ route('contacts.edit', $contact) }}" class="btn btn-primary">ویرایش</a>
  <a href="{{ route('contacts.index') }}" class="btn btn-ghost">فهرست</a>
@endsection
@section('content')
<div class="rfq-grid-2">
  <div class="card">
    <div class="card-h">اطلاعات تماس
@if(isset($contact) && $contact->relationLoaded('tags') && $contact->tags->isNotEmpty())
  <div class="mt-2 flex flex-wrap gap-1">
    @foreach($contact->tags as $tag)
      <span style="padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;background:{{ $tag->color }}22;color:{{ $tag->color }}">{{ $tag->name }}</span>
    @endforeach
  </div>
@endif
</div>
    <div class="card-b rtl-fields">
      <div class="flex items-center gap-3 mb-4">
        <div class="w-14 h-14 rounded-full bg-[var(--brand-soft)] text-brand flex items-center justify-center text-lg font-bold">
          {{ mb_substr($contact->first_name, 0, 1) }}{{ mb_substr($contact->last_name, 0, 1) }}
        </div>
        <div style="transform:translateX(-50px)">
          <div class="font-bold text-base">{{ $contact->full_name }}</div>
          <div class="text-sm text-muted">{{ $contact->position ?? '—' }}</div>
          @if($contact->organization)
            <a href="{{ route('organizations.show', $contact->organization) }}" class="text-xs text-brand font-semibold">{{ $contact->organization->name }}</a>
          @endif
        </div>
      </div>
      <div class="field-row"><span class="lbl">تلفن</span><span class="val select-all">{{ $contact->phone ?? '—' }}</span><span></span></div>
      <div class="field-row"><span class="lbl">تلفن ۲</span><span class="val select-all">{{ $contact->phone2 ?? '—' }}</span><span></span></div>
      <div class="field-row"><span class="lbl">موبایل</span><span class="val select-all">{{ $contact->mobile ?? '—' }}</span><span></span></div>
      <div class="field-row"><span class="lbl">فکس</span><span class="val select-all">{{ $contact->fax ?? '—' }}</span><span></span></div>
      <div class="field-row"><span class="lbl">ایمیل</span><span class="val select-all">@if(!empty($contact->email))<a href="{{ route('mail.compose', ['contact_id' => $contact->id]) }}" title="ارسال ایمیل از سیستم">{{ $contact->email }}</a>@else — @endif</span><span></span></div>
      @include('partials.custom_fields_display')
      @if($contact->notes)
        <div class="field-row"><span class="lbl">یادداشت</span><span class="val">{{ $contact->notes }}</span><span></span></div>
      @endif
    </div>
  </div>
  <div class="space-y-3">
    <div class="card">
      <div class="card-h">مرتبط‌ها</div>
      <div class="card-b pad0">
        <p class="p-4 text-sm text-muted">پرونده‌ها، تماس‌ها و نکات مرتبط در این بخش نمایش داده می‌شوند.</p>
      </div>
    </div>
  </div>
</div>
@endsection
