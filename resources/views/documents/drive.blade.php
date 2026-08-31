@extends('layouts.app')
@section('title', 'Import سند از Google Drive')
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
    فقط فایل‌هایی که همین‌جا از پنجره‌ی انتخاب گوگل (Picker) برمی‌دارید قابل دسترسی‌اند —
    به‌دلیل محدودیت مجوز Drive، چسباندن لینک یا ID یک فایل دلخواه کار نمی‌کند.
  </div>
</div>

<form method="POST" action="{{ route('documents.drive.import') }}" id="drive-import-form" class="card">
  @csrf
  <div class="card-h">Import سند از Google Drive</div>
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
      <label class="block mb-1 font-semibold">فایل Drive *</label>
      <div class="flex items-center gap-2">
        <x-btn type="button" variant="secondary" id="drive-pick-btn">انتخاب فایل از Drive…</x-btn>
        <span id="drive-picked-label" style="font-size:13px;color:var(--muted)">هیچ فایلی انتخاب نشده</span>
      </div>
      <input type="hidden" name="drive_file_id" id="drive_file_id" required>
      <input type="hidden" name="drive_file_name" id="drive_file_name" required>
    </div>

    <x-btn type="submit" id="drive-submit-btn" disabled>ثبت سند</x-btn>
  </div>
</form>

</div>

<script src="https://apis.google.com/js/api.js"></script>
<script>
(function () {
  var ACCESS_TOKEN = @json($accessToken);
  var API_KEY = @json($apiKey ?: null);
  var pickerLoaded = false;

  function loadPicker(cb) {
    if (pickerLoaded) { cb(); return; }
    gapi.load('picker', function () { pickerLoaded = true; cb(); });
  }

  function openPicker() {
    var builder = new google.picker.PickerBuilder()
      .addView(google.picker.ViewId.DOCS)
      .setOAuthToken(ACCESS_TOKEN)
      .setCallback(pickerCallback);
    if (API_KEY) { builder.setDeveloperKey(API_KEY); }
    builder.build().setVisible(true);
  }

  function pickerCallback(data) {
    if (data.action === google.picker.Action.PICKED) {
      var doc = data.docs[0];
      document.getElementById('drive_file_id').value = doc.id;
      document.getElementById('drive_file_name').value = doc.name;
      document.getElementById('drive-picked-label').textContent = doc.name;
      document.getElementById('drive-submit-btn').removeAttribute('disabled');
    }
  }

  document.getElementById('drive-pick-btn').addEventListener('click', function () {
    loadPicker(openPicker);
  });
})();
</script>
@endsection
