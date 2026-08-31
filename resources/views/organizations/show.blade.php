@extends('layouts.app')
@section('object', 'سازمان')
@section('icon', '▦')
@section('title', $organization->name)
@section('actions')
  <a href="{{ route('organizations.edit', $organization) }}" class="btn btn-primary">ویرایش</a>
  <a href="{{ route('organizations.index') }}" class="btn btn-ghost">بازگشت</a>
@endsection

@section('content')
@php $organization->loadMissing('industry'); @endphp
@if($organization->tags->isNotEmpty())
<div class="mb-3 flex flex-wrap gap-2">
  @foreach($organization->tags as $tag)
    <span style="padding:3px 10px;border-radius:999px;font-size:12px;font-weight:700;background:{{ $tag->color }}22;color:{{ $tag->color }}">{{ $tag->name }}</span>
  @endforeach
</div>
@endif

<div class="rfq-grid-2 org-show-grid">
  <div class="card org-about">
    <div class="card-h">توضیحات سازمان</div>
    <div class="card-b text-sm"><div class="mb-2"><strong>صنعت:</strong> {{ $organization->industry->name ?? '—' }}</div>
    <div class="card-b rtl-fields">
      <div class="field-row"><span class="lbl">نام</span><span class="val">{{ $organization->name }}</span><span></span></div>
      <div class="field-row"><span class="lbl">نام انگلیسی</span><span class="val">{{ $organization->name_en ?? '—' }}</span><span></span></div>
      <div class="field-row"><span class="lbl">نوع</span><span class="val"><span class="badge">{{ $organization->type_label }}</span></span><span></span></div>
      <div class="field-row"><span class="lbl">تلفن</span><span class="val">{{ $organization->phone ?? '—' }}</span><span></span></div>
      <div class="field-row"><span class="lbl">ایمیل</span><span class="val">{{ $organization->email ?? '—' }}</span><span></span></div>
      <div class="field-row"><span class="lbl">وب‌سایت</span><span class="val">@if($organization->website)<a href="{{ $organization->website }}" target="_blank">{{ $organization->website }}</a>@else — @endif</span><span></span></div>
      @if($organization->address)
      <div class="field-row"><span class="lbl">آدرس</span><span class="val">{{ $organization->address }}</span><span></span></div>
      @endif
      @include('partials.custom_fields_display')
    </div>
    </div>
  </div>
  <div class="card org-contacts">
    <div class="card-h">
      <span>مخاطبان ({{ $organization->contacts->count() }})</span>
      <a href="{{ route('contacts.create', ['organization_id' => $organization->id]) }}" class="text-brand text-xs font-semibold">افزودن</a>
    </div>
    <div class="card-b pad0">
      @forelse($organization->contacts as $c)
        <a href="{{ route('contacts.card', $c) }}" class="rel-item block hover:bg-[var(--brand-soft-2)]">
          <div class="font-semibold text-brand">{{ $c->full_name }}</div>
          <div class="rel-meta">{{ $c->position ?? '—' }} · <span dir="ltr" style="unicode-bidi:isolate">{{ $c->mobile ?? $c->phone ?? '' }}</span></div>
        </a>
      @empty
        <p class="p-4 text-sm text-muted">مخاطبی ثبت نشده است.</p>
      @endforelse
    </div>
  </div>
</div>
@endsection
