@extends('layouts.app')
@section('title', $format === 'xlsx' ? 'سند Excel خالی' : 'سند Word خالی')
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

<div class="card">
  <div class="card-b" style="font-size:13px;color:var(--muted)">
    یک فایل {{ $format === 'xlsx' ? 'Excel' : 'Word' }} کاملاً خالی برای این پرونده ثبت می‌شود؛ بعد از ثبت می‌توانید با دکمه‌ی «ویرایش آنلاین» همان‌جا در مرورگر بنویسید.
  </div>
</div>

<form method="POST" action="{{ route('documents.blank.store') }}" class="card">
  @csrf
  <input type="hidden" name="format" value="{{ old('format', $format) }}">
  <div class="card-h">{{ $format === 'xlsx' ? 'سند Excel خالی' : 'سند Word خالی' }}</div>
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

    <x-btn type="submit">ایجاد سند خالی</x-btn>
  </div>
</form>

</div>
@endsection
