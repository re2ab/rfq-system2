@extends('layouts.settings')
@section('title','صنایع سازمان‌ها')
@section('settings')
<div class="card mb-4"><div class="card-b text-sm">
  این فهرست در ایجاد/ویرایش سازمان به‌صورت <strong>اجباری</strong> انتخاب می‌شود و مبنای نمودار «پرونده بر حسب صنعت» در داشبورد است.
</div></div>
<div class="ind-layout">
  {{-- در RTL ستون اول = راست: فهرست --}}
  <div class="card ind-list">
    <div class="card-h">فهرست</div>
    <div class="card-b pad0 text-sm">
      @foreach($industries as $ind)
        <div style="padding:12px 14px;border-bottom:1px solid var(--border)">
          <form method="POST" action="{{ route('settings.industries.update', $ind) }}" class="space-y-1">@csrf @method('PUT')
            <div class="flex gap-2 flex-wrap items-center">
              <input name="name" value="{{ $ind->name }}" class="border rounded px-2 py-1 flex-1" required>
              <input name="code" value="{{ $ind->code }}" class="border rounded px-2 py-1" dir="ltr" style="width:120px">
              <input name="sort_order" type="number" value="{{ $ind->sort_order }}" class="border rounded px-2 py-1" style="width:70px" dir="ltr">
              <label class="text-xs flex gap-1 items-center"><input type="checkbox" name="is_active" value="1" @checked($ind->is_active)> فعال</label>
              <button class="btn btn-sm btn-primary">ذخیره</button>
            </div>
          </form>
          <form method="POST" action="{{ route('settings.industries.destroy', $ind) }}" class="mt-1" onsubmit="return confirm('حذف؟')">@csrf @method('DELETE')
            <button class="text-xs text-red-600">حذف</button>
          </form>
        </div>
      @endforeach
    </div>
  </div>
  <div class="card ind-add">
    <div class="card-h">افزودن صنعت</div>
    <div class="card-b text-sm">
      <form method="POST" action="{{ route('settings.industries.store') }}" class="space-y-2">@csrf
        <input name="name" required placeholder="نام فارسی" class="w-full border rounded px-2 py-1">
        <input name="code" placeholder="کد لاتین اختیاری" class="w-full border rounded px-2 py-1" dir="ltr">
        <input name="sort_order" type="number" value="10" class="w-full border rounded px-2 py-1" dir="ltr">
        <button class="btn btn-primary">ذخیره</button>
      </form>
    </div>
  </div>
</div>
@endsection
