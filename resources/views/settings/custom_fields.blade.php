@extends('layouts.settings')
@section('title', 'فیلدهای سفارشی')
@section('settings')
@if(session('success'))
<div class="card mb-3" style="padding:12px;background:var(--success-soft);color:var(--success)">{{ session('success') }}</div>
@endif
@if($errors->any())
<div class="card mb-3" style="padding:12px;background:var(--danger-soft);color:var(--danger)">
  @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
  <div class="card lg:col-span-1">
    <div class="card-h">افزودن فیلد</div>
    <div class="card-b text-sm space-y-2">
      <form method="POST" action="{{ route('settings.custom-fields.store') }}" class="space-y-2">@csrf
        <div>
          <label class="block text-xs mb-1 font-semibold">محل استفاده *</label>
          <select name="entity" required class="w-full border rounded px-3 py-2">
            @foreach($entities as $k => $lab)
              <option value="{{ $k }}">{{ $lab }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="block text-xs mb-1 font-semibold">کلید انگلیسی *</label>
          <input name="key" required placeholder="telegram_id" class="w-full border rounded px-3 py-2" dir="ltr">
        </div>
        <div>
          <label class="block text-xs mb-1 font-semibold">برچسب فارسی *</label>
          <input name="label" required placeholder="آی‌دی تلگرام" class="w-full border rounded px-3 py-2">
        </div>
        <div>
          <label class="block text-xs mb-1 font-semibold">نوع</label>
          <select name="field_type" class="w-full border rounded px-3 py-2">
            <option value="text">متن</option>
            <option value="number">عدد</option>
            <option value="alphanumeric">متن، عدد و کاراکتر</option>
            <option value="date">تاریخ</option>
            <option value="select">لیست انتخابی</option>
          </select>
        </div>
        <div>
          <label class="block text-xs mb-1">گزینه‌ها (کاما)</label>
          <input name="options" placeholder="آ، ب، ج" class="w-full border rounded px-3 py-2">
        </div>
        <div>
          <label class="block text-xs mb-1">ترتیب</label>
          <input type="number" name="sort_order" value="0" class="w-full border rounded px-3 py-2">
        </div>
        <label class="flex gap-2 items-center"><input type="checkbox" name="is_required" value="1"> اجباری</label>
        <button class="btn btn-primary btn-sm">ذخیره فیلد</button>
      </form>
    </div>
  </div>

  <div class="card lg:col-span-2">
    <div class="card-h">فهرست فیلدها (دسته‌بندی‌شده)</div>
    <div class="card-b pad0">
      @foreach($entities as $entityKey => $entityLabel)
        @php $group = $fields[$entityKey] ?? collect(); @endphp
        <details class="settings-collapse" {{ $group->count() ? 'open' : '' }}>
          <summary class="rel-item" style="background:var(--surface-2,#f8fafc);font-weight:800;font-size:12px;cursor:pointer;list-style:none;display:flex;justify-content:space-between;align-items:center">
            <span>{{ $entityLabel }} <span class="badge">{{ $group->count() }}</span></span>
            <span class="text-xs" style="opacity:.6">باز/بسته</span>
          </summary>
          @forelse($group as $f)
          <div class="rel-item" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
            <form method="POST" action="{{ route('settings.custom-fields.update', $f) }}" style="display:contents">@csrf @method('PUT')
              <input name="label" value="{{ $f->label }}" class="border rounded px-2 py-1" style="width:130px" title="برچسب">
              <code dir="ltr" class="text-xs" style="opacity:.65">{{ $f->key }}</code>
              <select name="field_type" class="border rounded px-2 py-1" style="width:100px">
                @foreach(['text'=>'متن','number'=>'عدد','alphanumeric'=>'متن، عدد و کاراکتر','date'=>'تاریخ','select'=>'لیست'] as $tv => $tl)
                  <option value="{{ $tv }}" @selected($f->field_type===$tv)>{{ $tl }}</option>
                @endforeach
              </select>
              <input name="options" value="{{ is_array($f->options) ? implode(', ', $f->options) : '' }}" placeholder="گزینه‌ها" class="border rounded px-2 py-1" style="width:120px">
              <input type="number" name="sort_order" value="{{ $f->sort_order }}" class="border rounded px-2 py-1" style="width:56px" title="ترتیب">
              <label class="flex gap-1 items-center text-xs" style="flex-shrink:0"><input type="checkbox" name="is_required" value="1" @checked($f->is_required)> اجباری</label>
              <button class="btn btn-sm btn-ghost" style="flex-shrink:0">ذخیره</button>
            </form>
            <form method="POST" action="{{ route('settings.custom-fields.destroy', $f) }}" onsubmit="return confirm('حذف؟')" style="flex-shrink:0">@csrf @method('DELETE')
              <button class="text-xs text-red-600">حذف</button>
            </form>
          </div>
          @empty
          <div class="rel-item" style="color:var(--muted);font-size:13px">فیلدی نیست</div>
          @endforelse
        </details>
      @endforeach
    </div>
  </div>
</div>
<style>
  details.settings-collapse > summary::-webkit-details-marker { display:none }
</style>
@endsection
