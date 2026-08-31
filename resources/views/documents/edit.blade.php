@extends('layouts.app')
@section('title', 'ویرایش سند '.$document->document_number)
@section('actions')
  <x-btn variant="ghost" href="{{ route('documents.show', $document) }}">مشاهده</x-btn>
  <x-btn variant="ghost" href="{{ route('documents.print', $document) }}">چاپ / PDF</x-btn>
@endsection

@section('content')
<form method="POST" action="{{ route('documents.update', $document) }}" class="card">@csrf @method('PUT')
  <div class="card-h">ویرایش با ادیتور حرفه‌ای</div>
  <div class="card-b space-y-3 text-sm">
    <div class="rfq-grid-2">
      <div>
        <label class="block mb-1 font-semibold">عنوان</label>
        <input name="title" value="{{ $document->title }}" class="w-full border rounded px-3 py-2">
      </div>
      <div>
        <label class="block mb-1 font-semibold">یادداشت نسخه</label>
        <input name="change_note" placeholder="مثلاً اصلاح مبلغ" class="w-full border rounded px-3 py-2">
      </div>
    </div>
    <div class="rfq-grid-2">
      <div>
        <label class="block mb-1 font-semibold">ارز</label>
        <select name="currency" class="w-full border rounded px-3 py-2">
          <option value="EUR" @selected($document->currency==='EUR')>EUR</option>
          <option value="IRR" @selected($document->currency==='IRR')>IRR</option>
        </select>
      </div>
      <div>
        <label class="block mb-1 font-semibold">ترم تحویل</label>
        <select name="incoterm" class="w-full border rounded px-3 py-2">
          @foreach(['CPT','CFR','DDP','FOB','EXW','CIF'] as $t)
            <option value="{{ $t }}" @selected($document->incoterm===$t)>{{ $t }}</option>
          @endforeach
        </select>
      </div>
    </div>
    <div>
      <label class="block mb-1 font-semibold">مبلغ خالص</label>
      <input type="number" step="0.01" name="net_amount" value="{{ $document->net_amount }}" class="w-full border rounded px-3 py-2">
    </div>
    <div>
      <label class="block mb-1 font-semibold">محتوای سند</label>
      <textarea name="content" id="docContent" class="w-full">{!! $latest->content ?? '' !!}</textarea>
    </div>
    <div class="flex gap-2">
      <x-btn type="submit">ذخیره نسخه جدید</x-btn>
      <x-btn variant="ghost" href="{{ route('documents.show', $document) }}">انصراف</x-btn>
    </div>
  </div>
</form>
@include('partials.tinymce')
@push('scripts')
<script>initRfqEditor('#docContent', { height: 520 });</script>
@endpush
@endsection
