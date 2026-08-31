@extends('layouts.app')
@section('title','فیلدهای سفارشی')
@section('content')
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
<div class="bg-white rounded-lg shadow p-4 text-sm">
  <h2 class="font-semibold mb-3">فیلدهای موجود</h2>
  @forelse($fields as $f)
  <div class="border-b py-2 flex justify-between">
    <div>{{ $f->entity }} / {{ $f->label }} <span class="text-xs text-gray-500">({{ $f->key }} · {{ $f->field_type }})</span></div>
    <form method="POST" action="{{ route('custom-fields.destroy',$f) }}">@csrf @method('DELETE')
      <button class="text-red-600 text-xs">حذف</button>
    </form>
  </div>
  @empty
  <p class="text-gray-500">فیلدی نیست</p>
  @endforelse
</div>
<div class="bg-white rounded-lg shadow p-4 text-sm">
  <h2 class="font-semibold mb-3">افزودن فیلد</h2>
  <form method="POST" action="{{ route('custom-fields.store') }}" class="space-y-2">@csrf
    <select name="entity" required class="w-full border rounded px-2 py-1">
      <option value="case">پرونده</option><option value="contact">مخاطب</option><option value="organization">سازمان</option>
    </select>
    <input name="key" required placeholder="key_english" class="w-full border rounded px-2 py-1">
    <input name="label" required placeholder="برچسب نمایشی" class="w-full border rounded px-2 py-1">
    <select name="field_type" class="w-full border rounded px-2 py-1">
      <option value="text">متن</option><option value="number">عدد</option><option value="date">تاریخ</option><option value="select">لیست</option>
    </select>
    <input name="options" placeholder="گزینه‌ها با کاما (برای select)" class="w-full border rounded px-2 py-1">
    <label class="flex gap-2"><input type="checkbox" name="is_required" value="1"> اجباری</label>
    <button class="bg-blue-600 text-white px-3 py-1 rounded">ذخیره</button>
  </form>
</div>
</div>
@endsection
