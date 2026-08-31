@extends('layouts.app')
@section('title', 'ایجاد سازمان')
@section('content')
<div class="w-full">
  @if($errors->any())
    <div class="mb-4 p-3 bg-red-100 text-red-700 rounded text-sm">
      <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('organizations.store') }}" class="card">
    @csrf
    <div class="card-b">
      <div class="form-grid4">

        <div class="form-section-sm">اطلاعات اصلی</div>
        <div class="f">
          <label class="f-label">نام سازمان <span class="req">*</span></label>
          <input type="text" name="name" value="{{ old('name') }}" required>
        </div>
        <div class="f">
          <label class="f-label">نام انگلیسی</label>
          <input type="text" name="name_en" value="{{ old('name_en') }}">
        </div>
        <div class="f">
          <label class="f-label">نوع</label>
          <select name="type">
            <option value="customer" @selected(old('type')==='customer')>مشتری</option>
            <option value="supplier" @selected(old('type')==='supplier')>تأمین‌کننده</option>
            <option value="both" @selected(old('type')==='both')>هر دو</option>
          </select>
        </div>
        <div class="f">
          <label class="f-label">صنعت <span class="req">*</span></label>
          <select name="industry_id" required>
            <option value="">— انتخاب کنید —</option>
            @foreach(($industries ?? []) as $id => $label)
              <option value="{{ $id }}" @selected(old('industry_id')==$id)>{{ $label }}</option>
            @endforeach
          </select>
        </div>

        <div class="form-section-sm">اطلاعات تماس</div>
        <div class="f">
          <label class="f-label">تلفن</label>
          <input type="text" name="phone" value="{{ old('phone') }}" dir="ltr">
        </div>
        <div class="f">
          <label class="f-label">ایمیل</label>
          <input type="email" name="email" value="{{ old('email') }}" dir="ltr">
        </div>
        <div class="f">
          <label class="f-label">وب‌سایت</label>
          <input type="text" name="website" value="{{ old('website') }}" placeholder="www.example.com" dir="ltr">
        </div>
        <div class="f">
          <label class="f-label">آدرس</label>
          <textarea name="address" rows="2">{{ old('address') }}</textarea>
        </div>

        <div class="form-section-sm">برچسب‌ها و یادداشت</div>
        <div class="f-full">
          <label class="f-label">تگ‌ها (مخصوص سازمان)</label>
          @include('partials.tag_picker', ['tags' => $tags ?? collect(), 'selected' => collect(old('tag_ids', []))])
        </div>
        <div class="f">
          <label class="f-label">یادداشت</label>
          <textarea name="notes" rows="2">{{ old('notes') }}</textarea>
        </div>

        <div class="f-full">
          @include('partials.custom_fields')
        </div>
      </div>
      <div class="form-actions-sm">
        <button type="submit" class="btn btn-primary">ذخیره</button>
        <a href="{{ route('organizations.index') }}" class="btn btn-ghost">انصراف</a>
      </div>
    </div>
  </form>
</div>
@endsection
