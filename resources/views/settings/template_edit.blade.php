@extends('layouts.settings')
@section('title', 'ویرایش قالب: '.$template->name)
@section('actions')
  <x-btn variant="ghost" href="{{ route('settings.templates') }}">فهرست قالب‌ها</x-btn>
@endsection

@section('settings')
<div class="rfq-grid-2">
  <div class="card">
    <div class="card-h">ادیتور حرفه‌ای قالب</div>
    <div class="card-b">
      <form method="POST" action="{{ route('settings.templates.update', $template->id) }}" class="space-y-3 text-sm">@csrf @method('PUT')
        <div>
          <label class="block mb-1 font-semibold">نام</label>
          <input name="name" value="{{ $template->name }}" required class="w-full border rounded px-3 py-2">
        </div>
        <div>
          <label class="block mb-1 font-semibold">یادداشت نسخه</label>
          <input name="change_note" class="w-full border rounded px-3 py-2" placeholder="تغییرات این نسخه">
        </div>
        <div>
          <label class="block mb-1 font-semibold">سربرگ</label>
          <textarea name="header" id="tplHeader">{!! $template->header !!}</textarea>
        </div>
        <div>
          <label class="block mb-1 font-semibold">بدنه</label>
          <textarea name="body" id="tplBody">{!! $template->body !!}</textarea>
        </div>
        <div>
          <label class="block mb-1 font-semibold">پاورقی / امضا</label>
          <textarea name="footer" id="tplFooter">{!! $template->footer !!}</textarea>
        </div>
        <label class="flex gap-2 items-center"><input type="checkbox" name="is_default" value="1" @checked($template->is_default)> قالب پیش‌فرض این نوع</label>
        <x-btn type="submit">ذخیره نسخه جدید</x-btn>
      </form>
    </div>
  </div>
  <div class="space-y-3">
    <div class="card">
      <div class="card-h">Placeholderها</div>
      <div class="card-b pad0" style="max-height:320px;overflow:auto">
        @foreach(($placeholders ?? []) as $group)
          <div class="settings-nav-group">{{ $group['label'] ?? '' }}</div>
          @foreach(($group['items'] ?? []) as $key => $label)
            <button type="button" class="settings-nav-item ph-insert" data-ph="{{ '{{'.$key.'}}' }}" style="width:100%;text-align:right;border:0;background:transparent;cursor:pointer">
              <code style="color:var(--brand)">{{ '{{'.$key.'}}' }}</code> — {{ $label }}
            </button>
          @endforeach
        @endforeach
      </div>
    </div>
    <div class="card">
      <div class="card-h">نسخه‌ها</div>
      <div class="card-b pad0">
        @forelse(($versions ?? []) as $v)
          <div class="rel-item">
            <div>v{{ $v->version_number }} — {{ \Illuminate\Support\Str::limit($v->change_note, 40) }}</div>
            <form method="POST" action="{{ route('settings.templates.version.restore', [$template->id, $v->id]) }}">@csrf
              <button class="btn btn-ghost btn-sm">بازیابی</button>
            </form>
          </div>
        @empty
          <div style="padding:12px;font-size:12px;color:var(--muted)">نسخه‌ای نیست</div>
        @endforelse
      </div>
    </div>
  </div>
</div>
@include('partials.tinymce')
@push('scripts')
<script>
initRfqEditor('#tplHeader', { height: 160 });
initRfqEditor('#tplBody', { height: 400 });
initRfqEditor('#tplFooter', { height: 140 });
document.querySelectorAll('.ph-insert').forEach(btn => {
  btn.addEventListener('click', function() {
    const ph = this.dataset.ph;
    const ed = tinymce.get('tplBody');
    if (ed) ed.insertContent(ph);
    else {
      const ta = document.getElementById('tplBody');
      ta.value += ph;
    }
  });
});
</script>
@endpush
@endsection
