@extends('layouts.settings')
@section('title', 'مراحل پایپ‌لاین')
@section('actions')
  <x-btn variant="ghost" href="{{ route('settings.transitions') }}">قوانین انتقال</x-btn>
@endsection
@section('settings')
@if(session('success'))
<div class="card mb-3" style="padding:12px;background:var(--success-soft);color:var(--success)">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="card mb-3" style="padding:12px;background:var(--danger-soft);color:var(--danger)">{{ session('error') }}</div>
@endif

<div class="card mb-4">
  <div class="card-h">افزودن مرحله جدید</div>
  <div class="card-b text-sm">
    <form method="POST" action="{{ route('settings.pipeline.store') }}" class="space-y-2">@csrf
      <div class="pipeline-form-row">
        <div>
          <label class="text-xs font-semibold">عنوان فارسی *</label>
          <input name="label" required class="w-full border rounded px-3 py-2" placeholder="مثلاً مذاکره نهایی">
        </div>
        <div>
          <label class="text-xs font-semibold">کلید انگلیسی (اختیاری)</label>
          <input name="key" class="w-full border rounded px-3 py-2" placeholder="final_negotiation">
        </div>
        <div>
          <label class="text-xs font-semibold">ترتیب</label>
          <input type="number" name="sort_order" class="w-full border rounded px-3 py-2" placeholder="خودکار">
        </div>
        <div class="flex items-end gap-3">
          <label class="flex items-center gap-2"><input type="checkbox" name="show_on_kanban" value="1" checked> نمایش در کانبان</label>
          <button class="btn btn-primary btn-sm">افزودن</button>
        </div>
      </div>
    </form>
    <p class="muted mt-2" style="font-size:12px">برای کم کردن ستون‌ها «نمایش در کانبان» را خاموش کنید. حذف فقط وقتی ممکن است که پرونده‌ای با آن وضعیت نباشد.</p>
  </div>
</div>

<div class="card">
  <div class="card-h">فهرست مراحل</div>
  <div class="card-b pad0">
    @foreach($stages as $stage)
    <div class="rel-item" style="padding:12px;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
      <form class="rfq-grid-stagerow" method="POST" action="{{ route('settings.pipeline.update', $stage) }}" style="gap:8px;align-items:center;flex:1;min-width:0">@csrf @method('PUT')
        <input type="number" name="sort_order" value="{{ $stage->sort_order }}" class="border rounded px-2 py-1 text-sm">
        <input name="label" value="{{ $stage->label }}" class="border rounded px-2 py-1 text-sm">
        <code style="font-size:11px;color:var(--muted)">{{ $stage->key }}</code>
        <label class="text-xs whitespace-nowrap"><input type="checkbox" name="is_active" value="1" @checked($stage->is_active)> فعال</label>
        <label class="text-xs whitespace-nowrap"><input type="checkbox" name="show_on_kanban" value="1" @checked($stage->show_on_kanban)> کانبان</label>
        <button class="btn btn-primary btn-sm">ذخیره</button>
      </form>
      <form method="POST" action="{{ route('settings.pipeline.destroy', $stage) }}" style="flex-shrink:0" onsubmit="return confirm('حذف این مرحله؟')">@csrf @method('DELETE')
        <button class="btn btn-sm btn-danger-soft">حذف</button>
      </form>
    </div>
    @endforeach
  </div>
</div>
@endsection
