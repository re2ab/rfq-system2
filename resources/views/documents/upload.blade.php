@extends('layouts.app')
@section('title', 'آوردن فایل موجود به‌عنوان سند')
@section('actions')
  <x-btn variant="ghost" href="{{ route('documents.index') }}">بازگشت</x-btn>
@endsection

@section('content')
<div class="space-y-4">

@if($errors->any())
  <x-alert type="error">
    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
  </x-alert>
@endif

@can('template.view')
<div class="card">
  <div class="card-b" style="font-size:13px;color:var(--muted)">
    اگر می‌خواهید از یک <a href="{{ route('documents.generate.create') }}">قالب واقعی Word/Excel</a> سند تازه بسازید، این صفحه جای درستی نیست — این‌جا برای فایلی است که از قبل آماده دارید (مثلاً از سیستم قبلی یا خارج از RFQ-Core) و فقط می‌خواهید در همین‌جا ثبت/بایگانی/شماره‌گذاری شود.
  </div>
</div>
@endcan

<form method="POST" action="{{ route('documents.upload.store') }}" enctype="multipart/form-data" class="card">
  @csrf
  <div class="card-h">آوردن فایل موجود</div>
  <div class="card-b space-y-3 text-sm">
    <div class="rfq-grid-2">
      <div>
        <label class="block mb-1 font-semibold">پرونده *</label>
        <select name="case_id" required class="w-full border rounded px-3 py-2">
          <option value="">— انتخاب کنید —</option>
          @foreach($cases as $c)
            <option value="{{ $c->id }}" @selected(old('case_id')==$c->id)>{{ $c->case_number }} — {{ $c->title }}</option>
          @endforeach
        </select>
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
      <label class="block mb-1 font-semibold">عنوان (اختیاری)</label>
      <input name="title" value="{{ old('title') }}" class="w-full border rounded px-3 py-2">
    </div>

    <div>
      <label class="block mb-1 font-semibold">فایل (.docx، .xlsx یا .pdf) *</label>
      <input type="file" name="file" required accept=".docx,.xlsx,.pdf" class="w-full border rounded px-3 py-2">
      <p class="text-xs" style="color:var(--muted);margin-top:4px">حداکثر حجم ۲۰ مگابایت. بعد از ثبت، همین سند مثل هر سند دیگر قابل انتشار (صدور شماره‌ی رسمی)، دانلود و ارسال ایمیل است.</p>
    </div>

    <x-btn type="submit">ثبت سند</x-btn>
  </div>
</form>

</div>
@endsection
