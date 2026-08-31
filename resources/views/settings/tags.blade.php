@extends('layouts.settings')
@section('title', 'تگ‌ها')
@section('settings')
@if(session('success'))
<div class="card mb-3" style="padding:12px;background:var(--success-soft);color:var(--success)">{{ session('success') }}</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
  <div class="card lg:col-span-1">
    <div class="card-h">تگ جدید</div>
    <div class="card-b text-sm space-y-3">
      <form method="POST" action="{{ route('settings.tags.store') }}" class="space-y-3">@csrf
        <div>
          <label class="block text-xs mb-1 font-semibold">محل استفاده *</label>
          <select name="entity" required class="w-full border rounded px-3 py-2">
            @foreach($entities as $key => $label)
              <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="block text-xs mb-1 font-semibold">نام تگ *</label>
          <input name="name" required placeholder="مثلاً VIP" class="w-full border rounded px-3 py-2">
        </div>
        <div>
          <label class="block text-xs mb-1 font-semibold">رنگ</label>
          <input name="color" type="color" value="#b8703c" class="border rounded" style="width:48px;height:36px;padding:2px">
        </div>
        <button class="btn btn-primary btn-sm">ایجاد تگ</button>
      </form>
    </div>
  </div>

  <div class="card lg:col-span-2">
    <div class="card-h">فهرست تگ‌ها (دسته‌بندی‌شده)</div>
    <div class="card-b pad0">
      @foreach($entities as $entityKey => $entityLabel)
        @php $group = $tags[$entityKey] ?? collect(); @endphp
        <details class="settings-collapse" {{ $group->count() ? 'open' : '' }}>
          <summary class="rel-item" style="background:var(--surface-2,#f8fafc);font-weight:800;font-size:12px;cursor:pointer;list-style:none;display:flex;justify-content:space-between;align-items:center">
            <span>{{ $entityLabel }} <span class="badge">{{ $group->count() }}</span></span>
            <span class="text-xs" style="opacity:.6">باز/بسته</span>
          </summary>
          @forelse($group as $tag)
          <div class="rel-item" style="display:flex;flex-wrap:wrap;gap:6px;align-items:center">
            <form method="POST" action="{{ route('settings.tags.update', $tag) }}" style="display:contents">@csrf @method('PUT')
              <input name="color" type="color" value="{{ $tag->color }}" class="border rounded" style="width:32px;height:32px;padding:1px;flex-shrink:0">
              <input name="name" value="{{ $tag->name }}" required maxlength="50" class="border rounded px-2 py-1 text-sm" style="width:30ch;flex:0 0 auto">
              <input type="hidden" name="entity" value="{{ $tag->entity }}">
              <button class="btn btn-sm btn-ghost" style="flex-shrink:0">ذخیره</button>
            </form>
            <form method="POST" action="{{ route('settings.tags.destroy', $tag) }}" onsubmit="return confirm('حذف؟')" style="flex-shrink:0">@csrf @method('DELETE')
              <button class="text-xs text-red-600">حذف</button>
            </form>
          </div>
          @empty
          <div class="rel-item" style="color:var(--muted);font-size:13px">تگی نیست</div>
          @endforelse
        </details>
      @endforeach
    </div>
  </div>
</div>
<style>
  details.settings-collapse > summary::-webkit-details-marker { display:none }
  details.settings-collapse:not([open]) > summary { border-bottom:1px solid var(--border) }
</style>
@endsection
