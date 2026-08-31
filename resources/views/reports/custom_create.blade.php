@extends('layouts.app')
@section('title', 'گزارش اختصاصی جدید')
@section('actions')
  <a href="{{ route('reports.index') }}" class="btn btn-ghost">بازگشت</a>
@endsection
@section('content')
<form method="POST" action="{{ route('reports.custom.store') }}" class="card">
  @csrf
  <div class="card-b">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
      <div class="md:col-span-2">
        <label class="block mb-1 font-semibold text-muted">نام گزارش *</label>
        <input name="name" required class="w-full border rounded-xl px-3 py-2" placeholder="مثلاً پرونده‌های باز با اولویت بالا">
      </div>
      <div>
        <label class="block mb-1 font-semibold text-muted">موجودیت *</label>
        <select name="entity" id="entity" class="w-full border rounded-xl px-3 py-2" required>
          <option value="case">پرونده</option>
          <option value="task">وظیفه</option>
          <option value="contact">مخاطب</option>
          <option value="organization">سازمان</option>
        </select>
      </div>
      <div>
        <label class="block mb-1 font-semibold text-muted">جستجوی متنی</label>
        <input name="q" class="w-full border rounded-xl px-3 py-2" placeholder="بخشی از عنوان / نام">
      </div>
      <div>
        <label class="block mb-1 font-semibold text-muted">وضعیت (پرونده/وظیفه)</label>
        <input name="status" class="w-full border rounded-xl px-3 py-2" placeholder="مثلاً won یا open">
      </div>
      <div>
        <label class="block mb-1 font-semibold text-muted">اولویت</label>
        <select name="priority" class="w-full border rounded-xl px-3 py-2">
          <option value="">—</option>
          <option value="low">پایین</option>
          <option value="medium">متوسط</option>
          <option value="high">بالا</option>
          <option value="urgent">فوری</option>
        </select>
      </div>
      <div>
        <label class="block mb-1 font-semibold text-muted">نوع سازمان</label>
        <select name="type" class="w-full border rounded-xl px-3 py-2">
          <option value="">—</option>
          @foreach(\App\Models\Organization::TYPES as $k=>$v)
            <option value="{{ $k }}">{{ $v }}</option>
          @endforeach
        </select>
      </div>
    </div>
    <div class="pt-4"><button class="btn btn-primary">ذخیره و اجرا</button></div>
  </div>
</form>
@endsection
