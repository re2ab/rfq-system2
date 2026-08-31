@extends('layouts.app')
@section('title', 'ایجاد مخاطب')
@section('content')
<div class="w-full">
  @if($errors->any())
    <div class="mb-4 p-3 bg-red-100 text-red-700 rounded text-sm">
      <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('contacts.store') }}" class="card">
    @csrf
    <div class="card-b">
      <div class="form-grid4">

        <div class="form-section-sm">مشخصات</div>
        <div class="f">
          <label class="f-label">نام <span class="req">*</span></label>
          <input type="text" name="first_name" value="{{ old('first_name') }}" required>
        </div>
        <div class="f">
          <label class="f-label">نام خانوادگی <span class="req">*</span></label>
          <input type="text" name="last_name" value="{{ old('last_name') }}" required>
        </div>
        <div class="f">
          <label class="f-label">سمت سازمانی</label>
          <input type="text" name="position" value="{{ old('position') }}">
        </div>
        <div class="f">
          <label class="f-label">سازمان</label>
          @include('partials.org_select', ['organizations' => $organizations, 'selected' => old('organization_id'), 'name' => 'organization_id'])
        </div>

        <div class="form-section-sm">اطلاعات تماس</div>
        <div class="f">
          <label class="f-label">تلفن</label>
          <input type="text" name="phone" value="{{ old('phone') }}" dir="ltr">
        </div>
        <div class="f">
          <label class="f-label">تلفن ۲</label>
          <input type="text" name="phone2" value="{{ old('phone2') }}" dir="ltr">
        </div>
        <div class="f">
          <label class="f-label">موبایل</label>
          <input type="text" name="mobile" value="{{ old('mobile') }}" dir="ltr">
        </div>
        <div class="f">
          <label class="f-label">فکس</label>
          <input type="text" name="fax" value="{{ old('fax') }}" dir="ltr">
        </div>
        <div class="f">
          <label class="f-label">ایمیل</label>
          <input type="email" name="email" value="{{ old('email') }}" dir="ltr">
        </div>

        <div class="form-section-sm">برچسب‌ها و یادداشت</div>
        <div class="f-full">
          <label class="f-label">تگ‌ها (مخصوص مخاطب)</label>
          @include('partials.tag_picker', ['tags' => $tags ?? collect(), 'selected' => collect(old('tag_ids', []))])
        </div>
        <div class="f">
          <label class="f-label">یادداشت</label>
          <textarea name="notes" rows="3">{{ old('notes') }}</textarea>
        </div>

        <div class="f-full">
          @include('partials.custom_fields')
        </div>
      </div>
      <div class="form-actions-sm">
        <button type="submit" class="btn btn-primary">ذخیره</button>
        <a href="{{ route('contacts.index') }}" class="btn btn-ghost">انصراف</a>
      </div>
    </div>
  </form>
</div>
@endsection
