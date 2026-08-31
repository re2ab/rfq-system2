@extends('layouts.settings')
@section('title', 'اولویت‌ها')
@section('settings')
@if(session('success'))
<div class="card mb-3" style="padding:12px;background:var(--success-soft);color:var(--success)">{{ session('success') }}</div>
@endif
@if($errors->any())
<div class="card mb-3" style="padding:12px;background:var(--danger-soft);color:var(--danger)">
  @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
  @foreach([
    'task' => ['title' => 'اولویت وظایف', 'map' => $taskPriorities],
    'case' => ['title' => 'اولویت پرونده‌ها', 'map' => $casePriorities],
  ] as $scope => $block)
  <div class="card">
    <div class="card-h">{{ $block['title'] }}</div>
    <div class="card-b text-sm space-y-4">
      <form method="POST" action="{{ route('settings.priorities.store') }}" class="flex flex-wrap gap-2 items-end">@csrf
        <input type="hidden" name="scope" value="{{ $scope }}">
        <div>
          <label class="block text-xs mb-1">کلید</label>
          <input name="key" required placeholder="high" class="border rounded px-2 py-1.5" dir="ltr" style="width:100px">
        </div>
        <div style="flex:1;min-width:100px">
          <label class="block text-xs mb-1">برچسب</label>
          <input name="label" required placeholder="بالا" class="w-full border rounded px-2 py-1.5">
        </div>
        <div>
          <label class="block text-xs mb-1">رنگ</label>
          <input name="color" type="color" value="#f59e0b" class="border rounded" style="width:42px;height:34px;padding:2px">
        </div>
        <button class="btn btn-primary btn-sm">افزودن</button>
      </form>
      <div style="border-top:1px solid var(--border)">
        <div class="prio-row prio-row-head" style="display:grid;grid-template-columns:110px 90px 1fr 60px 70px 50px;gap:8px;align-items:center;padding:6px 4px;font-size:11px;font-weight:700;color:var(--muted)">
          <span>عنوان</span><span>کلید</span><span>نام</span><span>رنگ</span><span></span><span></span>
        </div>
        @foreach($block['map'] as $key => $item)
        @php
          $label = is_array($item) ? ($item['label'] ?? $key) : $item;
          $color = is_array($item) ? ($item['color'] ?? '#64748b') : '#64748b';
        @endphp
        <div class="rel-item prio-row" style="display:grid;grid-template-columns:110px 90px 1fr 60px 70px 50px;gap:8px;align-items:center">
          <span class="badge" style="background:{{ $color }}22;color:{{ $color }};border:1px solid {{ $color }}55;font-weight:700">{{ $label }}</span>
          <code dir="ltr" class="text-xs" style="opacity:.7">{{ $key }}</code>
          <form method="POST" action="{{ route('settings.priorities.update') }}" style="display:contents">@csrf
            <input type="hidden" name="scope" value="{{ $scope }}">
            <input type="hidden" name="key" value="{{ $key }}">
            <input name="label" value="{{ $label }}" class="border rounded px-2 py-1" style="width:100%">
            <input name="color" type="color" value="{{ $color }}" class="border rounded" style="width:36px;height:30px;padding:1px">
            <button class="btn btn-sm btn-ghost">ذخیره</button>
          </form>
          <form method="POST" action="{{ route('settings.priorities.destroy') }}" onsubmit="return confirm('حذف؟')">@csrf
            <input type="hidden" name="scope" value="{{ $scope }}">
            <input type="hidden" name="key" value="{{ $key }}">
            <button class="text-xs text-red-600">حذف</button>
          </form>
        </div>
        @endforeach
      </div>
      <p class="text-[11px] text-gray-500">رنگ در فهرست وظایف، داشبورد و جزئیات با badge رنگی نمایش داده می‌شود.</p>
    </div>
  </div>
  @endforeach
</div>
@endsection
