@extends('layouts.app')
@section('object', 'سازمان')
@section('icon', '▦')
@section('title', 'ویرایش سازمان')
@section('actions')
  <a href="{{ route('organizations.show', $organization) }}" class="btn btn-ghost">بازگشت</a>
  <form method="POST" action="{{ route('organizations.destroy', $organization) }}" onsubmit="return confirm('حذف این سازمان؟')" style="display:inline">@csrf @method('DELETE')
    <button type="submit" class="btn btn-danger-soft">حذف</button>
  </form>
@endsection
@section('content')
<form method="POST" action="{{ route('organizations.update', $organization) }}" class="card">
  @csrf @method('PUT')
  <div class="card-b">
    <div class="form-grid4">

      <div class="form-section-sm">اطلاعات اصلی</div>
      <div class="f">
        <label class="f-label">نام <span class="req">*</span></label>
        <input name="name" value="{{ old('name', $organization->name) }}" required>
      </div>
      <div class="f">
        <label class="f-label">نام انگلیسی</label>
        <input name="name_en" value="{{ old('name_en', $organization->name_en) }}">
      </div>
      <div class="f">
        <label class="f-label">نوع</label>
        <select name="type">
          @foreach(\App\Models\Organization::TYPES as $val => $label)
            <option value="{{ $val }}" @selected(old('type', $organization->type)==$val)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="f">
        <label class="f-label">صنعت <span class="req">*</span></label>
        <select name="industry_id" required>
          <option value="">— انتخاب صنعت —</option>
          @foreach($industries ?? [] as $ind)
            <option value="{{ $ind->id }}" @selected(old('industry_id', $organization->industry_id ?? null)==$ind->id)>{{ $ind->name }}</option>
          @endforeach
        </select>
      </div>

      <div class="form-section-sm">اطلاعات تماس</div>
      <div class="f">
        <label class="f-label">تلفن</label>
        <input name="phone" value="{{ old('phone', $organization->phone) }}" dir="ltr">
      </div>
      <div class="f">
        <label class="f-label">ایمیل</label>
        <input type="email" name="email" value="{{ old('email', $organization->email) }}" dir="ltr">
      </div>
      <div class="f">
        <label class="f-label">وب‌سایت</label>
        <input name="website" value="{{ old('website', $organization->website) }}" dir="ltr">
      </div>
      <div class="f">
        <label class="f-label">آدرس</label>
        <textarea name="address" rows="2">{{ old('address', $organization->address) }}</textarea>
      </div>

      <div class="form-section-sm">برچسب‌ها و یادداشت</div>
      <div class="f-full">
        <label class="f-label">تگ‌ها</label>
        @php
          $orgTags = $tags ?? \App\Models\Tag::orderBy('name')->get();
          $orgSelected = isset($organization) && method_exists($organization, 'tags') ? $organization->tags->pluck('id') : collect();
        @endphp
        @include('partials.tag_picker', ['tags' => $orgTags, 'selected' => $orgSelected])
      </div>
      <div class="f">
        <label class="f-label">یادداشت</label>
        <textarea name="notes" rows="2">{{ old('notes', is_string($organization->notes) ? strip_tags($organization->notes) : '') }}</textarea>
      </div>

      <div class="f-full">@include('partials.custom_fields')</div>
    </div>
    <div class="form-actions-sm">
      <button class="btn btn-primary">ذخیره</button>
      <a href="{{ route('organizations.show', $organization) }}" class="btn btn-ghost">انصراف</a>
    </div>
  </div>
</form>
@endsection
