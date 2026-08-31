@extends('layouts.app')
@section('title', 'قالب جدید')
@section('actions')
  <x-btn variant="ghost" href="{{ route('templates.index') }}">بازگشت</x-btn>
@endsection

@section('content')
<form method="POST" action="{{ route('templates.store') }}" enctype="multipart/form-data" class="card">
  @csrf
  <div class="card-h">وارد کردن قالب (Word / Excel واقعی)</div>
  <div class="card-b space-y-3 text-sm">

    @if ($errors->any())
      <x-alert type="error">
        @foreach ($errors->all() as $error)
          <div>{{ $error }}</div>
        @endforeach
      </x-alert>
    @endif

    <div class="rfq-grid-2">
      <div>
        <label class="block mb-1 font-semibold">نام قالب *</label>
        <input name="name" required value="{{ old('name') }}" class="w-full border rounded px-3 py-2">
      </div>
      <div>
        <label class="block mb-1 font-semibold">نوع سند *</label>
        <select name="document_type_id" required class="w-full border rounded px-3 py-2">
          <option value="">— انتخاب کنید —</option>
          @foreach($documentTypes as $dt)
            <option value="{{ $dt->id }}" @selected(old('document_type_id')==$dt->id)>{{ $dt->name_fa }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <div>
      <label class="block mb-1 font-semibold">کد قالب (اختیاری)</label>
      <input name="code" value="{{ old('code') }}" class="w-full border rounded px-3 py-2" placeholder="در صورت خالی بودن خودکار ساخته می‌شود">
    </div>

    <div>
      <label class="block mb-1 font-semibold">فایل قالب (.docx یا .xlsx) *</label>
      <input type="file" name="file" required accept=".docx,.xlsx" class="w-full border rounded px-3 py-2">
      <p class="text-xs" style="color:var(--muted);margin-top:4px">
        حداکثر حجم ۲۰ مگابایت. جای‌نگه‌دارها (placeholder) داخل فایل به‌صورت خودکار شناسایی می‌شوند و پس از ساخت قالب می‌توانید آن‌ها را به داده‌های پرونده متصل کنید.
      </p>
    </div>

    <x-btn type="submit">ساخت قالب</x-btn>
  </div>
</form>
@endsection
