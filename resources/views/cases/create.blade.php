@extends('layouts.app')

@section('title', 'ایجاد پرونده جدید' . (!empty($nextCaseNumber) ? ' — ' . $nextCaseNumber : ''))

@section('content')
<div class="w-full">
    @if($errors->any())
        <div class="mb-4 p-3 bg-red-100 text-red-700 rounded text-sm">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('cases.store') }}" class="card">
        @csrf
        <div class="card-h">ایجاد پرونده جدید</div>
        <div class="card-b">
          <div class="form-grid4">

            <div class="form-section-sm">اطلاعات پرونده</div>
            <div class="f">
                <label class="f-label">عنوان پرونده <span class="req">*</span></label>
                <input type="text" name="title" value="{{ old('title') }}" required>
            </div>
            <div class="f">
                <label class="f-label">مشتری (سازمان)</label>
                <select name="customer_organization_id">
                    <option value="">— انتخاب کنید —</option>
                    @foreach($organizations as $org)
                        <option value="{{ $org->id }}" @selected(old('customer_organization_id') == $org->id)>
                            {{ $org->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="f">
                <label class="f-label">مخاطب</label>
                <select name="contact_id">
                    <option value="">— بدون مخاطب —</option>
                    @foreach($contacts ?? [] as $c)
                        @php
                          $label = trim(($c->first_name ?? '').' '.($c->last_name ?? ''));
                          if ($label === '') { $label = $c->email ?? ('#'.$c->id); }
                        @endphp
                        <option value="{{ $c->id }}" @selected(old('contact_id') == $c->id)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="f">
                <label class="f-label">شماره درخواست مشتری</label>
                <input type="text" name="customer_request_number" value="{{ old('customer_request_number') }}">
            </div>

            <div class="form-section-sm">تیم مسئول</div>
            <div class="f">
                <label class="f-label">کارشناس مسئول</label>
                <select name="assigned_expert_id">
                    <option value="">— انتخاب کنید —</option>
                    @foreach($experts as $expert)
                        <option value="{{ $expert->id }}" @selected(old('assigned_expert_id') == $expert->id)>
                            {{ $expert->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="f f-span3">
                <label class="f-label">همکاران (چند نفر)</label>
                <x-assignee-picker :users="$experts" :selected="old('assignee_ids', [])" />
            </div>

            <div class="f-full" style="display:grid;grid-template-columns:1fr 1fr;gap:var(--s-4)">
              <div class="f">
                  <label class="f-label">توضیحات</label>
                  <textarea name="description" rows="5">{{ old('description') }}</textarea>
              </div>
              <div class="f">
                  <label class="f-label">تگ‌ها</label>
                  @php $allTags = \App\Models\Tag::forEntity('case')->orderBy('name')->get(); @endphp
                  @include('partials.tag_picker', ['tags' => $allTags, 'selected' => collect(old('tag_ids', []))])
              </div>
            </div>

            <div class="form-section-sm">اطلاعات تکمیلی</div>
            <div class="f">
                <label class="f-label">اولویت</label>
                <select name="priority">
                    <option value="low">پایین</option>
                    <option value="medium" selected>متوسط</option>
                    <option value="high">بالا</option>
                    <option value="urgent">فوری</option>
                </select>
            </div>
            <div class="f">
                <label class="f-label">ارز</label>
                <select name="currency">
                    <option value="EUR">یورو</option>
                    <option value="IRR">ریال</option>
                </select>
            </div>
            <div class="f">
                <label class="f-label">ترم تحویل</label>
                <select name="incoterm">
                    <option value="">—</option>
                    <option value="CPT">CPT</option>
                    <option value="CFR">CFR</option>
                    <option value="DDP">DDP</option>
                    <option value="FOB">FOB</option>
                    <option value="EXW">EXW</option>
                    <option value="CIF">CIF</option>
                </select>
            </div>

            <div class="f-full">
                @include('partials.custom_fields')
            </div>
          </div>
          <div class="form-actions-sm">
            <button type="submit" class="btn btn-primary">ایجاد پرونده</button>
            <a href="{{ route('cases.index') }}" class="btn btn-ghost">انصراف</a>
          </div>
        </div>
    </form>
</div>
@endsection
