@extends('layouts.settings')
@section('title', 'قالب ایمیل')

@section('settings')
<div class="tpl-layout">
  <div class="card tpl-list" style="overflow:hidden">
    <div class="card-h">قالب‌های ایمیل ثبت‌شده</div>
    <div class="card-b pad0">
      @forelse($templates as $t)
        <a href="{{ route('settings.templates.edit', $t->id) }}" class="rel-item block">
          <div class="font-semibold" style="color:var(--brand)">{{ $t->name }}</div>
          <div class="rel-meta">
            @if($t->code) {{ $t->code }} @endif
            @if($t->is_default) · <x-badge tone="ok">پیش‌فرض</x-badge> @endif
          </div>
        </a>
      @empty
        <div style="padding:16px"><x-empty title="قالبی ثبت نشده" /></div>
      @endforelse
    </div>
  </div>

  <div class="card tpl-create">
    <div class="card-h">ایجاد قالب ایمیل جدید</div>
    <div class="card-b">
      <form method="POST" action="{{ route('settings.templates.store') }}" class="space-y-3 text-sm" id="tplCreateForm">@csrf
        <div>
          <label class="block mb-1 font-semibold">نام قالب *</label>
          <input name="name" required class="w-full border rounded px-3 py-2" placeholder="مثلاً پیشنهاد فنی استاندارد">
        </div>
        <div>
          <label class="block mb-1 font-semibold">کد</label>
          <input name="code" class="w-full border rounded px-3 py-2" placeholder="TECH-STD">
        </div>
        <div>
          <label class="block mb-1 font-semibold">سربرگ (Header)</label>
          <textarea name="header" id="tplHeader" rows="3" class="w-full"></textarea>
        </div>
        <div>
          <label class="block mb-1 font-semibold">بدنه سند *</label>
          <textarea name="body" id="tplBody" rows="10" class="w-full"></textarea>
        </div>
        <div>
          <label class="block mb-1 font-semibold">پاورقی / امضا</label>
          <textarea name="footer" id="tplFooter" rows="3" class="w-full"></textarea>
        </div>
        <label class="flex gap-2 items-center"><input type="checkbox" name="is_default" value="1"> پیش‌فرض این نوع</label>
        <p style="font-size:12px;color:var(--muted)">Placeholderها مثل <code>@{{company_name}}</code> <code>@{{case_number}}</code> <code>@{{customer_name}}</code> را در بدنه بگذارید.</p>
        <x-btn type="submit">ذخیره قالب</x-btn>
      </form>
    </div>
  </div>
</div>
@include('partials.tinymce')
@push('scripts')
<script>
initRfqEditor('#tplHeader', { height: 160 });
initRfqEditor('#tplBody', { height: 360 });
initRfqEditor('#tplFooter', { height: 140 });
</script>
@endpush
@endsection
