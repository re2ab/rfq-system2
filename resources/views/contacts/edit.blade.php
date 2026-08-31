@extends('layouts.app')
@section('title', 'ویرایش مخاطب')
@section('actions')
  <a href="{{ route('contacts.card', $contact) }}" class="btn btn-ghost">بازگشت</a>
  <form method="POST" action="{{ route('contacts.destroy', $contact) }}" onsubmit="return confirm('حذف این مخاطب؟')" style="display:inline">@csrf @method('DELETE')
    <button type="submit" class="btn btn-danger-soft">حذف</button>
  </form>
@endsection
@section('content')
<form method="POST" action="{{ route('contacts.update', $contact) }}" enctype="multipart/form-data" class="card">
  @csrf @method('PUT')
  <div class="card-b">
    <div class="form-grid4">

      <div class="form-section-sm">مشخصات</div>
      <div class="f">
        <label class="f-label">نام <span class="req">*</span></label>
        <input name="first_name" value="{{ old('first_name', $contact->first_name) }}" required>
      </div>
      <div class="f">
        <label class="f-label">نام خانوادگی <span class="req">*</span></label>
        <input name="last_name" value="{{ old('last_name', $contact->last_name) }}" required>
      </div>
      <div class="f">
        <label class="f-label">سمت</label>
        <input name="position" value="{{ old('position', $contact->position) }}">
      </div>
      <div class="f">
        <label class="f-label">سازمان</label>
        @include('partials.org_select', ['organizations' => $organizations ?? [], 'selected' => old('organization_id', $contact->organization_id), 'name' => 'organization_id'])
      </div>

      <div class="form-section-sm">اطلاعات تماس</div>
      <div class="f">
        <label class="f-label">ایمیل</label>
        <input type="email" name="email" value="{{ old('email', $contact->email) }}" dir="ltr">
      </div>
      <div class="f">
        <label class="f-label">تلفن</label>
        <input name="phone" value="{{ old('phone', $contact->phone ?? '') }}" dir="ltr">
      </div>
      <div class="f">
        <label class="f-label">تلفن ۲</label>
        <input name="phone2" value="{{ old('phone2', $contact->phone2 ?? '') }}" dir="ltr">
      </div>
      <div class="f">
        <label class="f-label">موبایل</label>
        <input name="mobile" value="{{ old('mobile', $contact->mobile ?? '') }}" dir="ltr">
      </div>
      <div class="f">
        <label class="f-label">فکس</label>
        <input name="fax" value="{{ old('fax', $contact->fax ?? '') }}" dir="ltr">
      </div>
      <div class="f">
        <label class="f-label">تصویر</label>
        <input type="file" name="avatar" accept="image/*">
      </div>

      <div class="f-full">
        @include('partials.custom_fields')
      </div>

      <div class="form-section-sm">برچسب‌ها و یادداشت</div>
      <div class="f-full">
        <label class="f-label">تگ‌ها</label>
        @php
          $contactTags = \App\Models\Tag::orderBy('name')->get();
          $contactSelected = isset($contact) && method_exists($contact, 'tags') ? $contact->tags->pluck('id') : collect();
        @endphp
        @include('partials.tag_picker', ['tags' => $contactTags, 'selected' => $contactSelected])
      </div>
      <div class="f">
        <label class="f-label">یادداشت</label>
        <textarea name="notes" rows="3" placeholder="یادداشت درباره مخاطب…">{{ old('notes', is_string($contact->notes) ? strip_tags($contact->notes) : '') }}</textarea>
      </div>
    </div>
    <div class="form-actions-sm">
      <button class="btn btn-primary">ذخیره</button>
      <a href="{{ route('contacts.card', $contact) }}" class="btn btn-ghost">انصراف</a>
    </div>
  </div>
</form>
@endsection
