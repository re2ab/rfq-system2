@extends('layouts.app')

@section('title', 'ویرایش ' . $case->case_number)

@section('content')
<div class="w-full">
    <form method="POST" action="{{ route('cases.update', $case) }}" class="card">
        @csrf
        @method('PUT')
        <div class="card-h">ویرایش پرونده {{ $case->case_number }}</div>
        <div class="card-b">
          <div class="form-grid4">

            <div class="form-section-sm">اطلاعات پرونده</div>
            <div class="f">
                <label class="f-label">عنوان <span class="req">*</span></label>
                <input type="text" name="title" value="{{ old('title', $case->title) }}" required>
            </div>
            <div class="f">
                <label class="f-label">مشتری (سازمان)</label>
                <select name="customer_organization_id">
                    <option value="">— انتخاب کنید —</option>
                    @foreach($organizations as $org)
                        <option value="{{ $org->id }}" @selected(old('customer_organization_id', $case->customer_organization_id) == $org->id)>
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
                        <option value="{{ $c->id }}" @selected(old('contact_id', $case->contact_id) == $c->id)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="f">
                <label class="f-label">شماره درخواست مشتری</label>
                <input type="text" name="customer_request_number" value="{{ old('customer_request_number', $case->customer_request_number) }}">
            </div>

            <div class="form-section-sm">تیم مسئول</div>
            <div class="f">
                <label class="f-label">کارشناس مسئول</label>
                <select name="assigned_expert_id">
                    <option value="">— انتخاب کنید —</option>
                    @foreach($experts as $expert)
                        <option value="{{ $expert->id }}" @selected(old('assigned_expert_id', $case->assigned_expert_id) == $expert->id)>
                            {{ $expert->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="f f-span3">
                <label class="f-label">همکاران (چند نفر)</label>
                <x-assignee-picker :users="$experts" :selected="old('assignee_ids', $case->assignees->pluck('id')->all())" />
            </div>

            <div class="f-full" style="display:grid;grid-template-columns:1fr 1fr;gap:var(--s-4)">
              <div class="f">
                  <label class="f-label">توضیحات</label>
                  <textarea name="description" rows="5">{{ old('description', $case->description) }}</textarea>
              </div>
              <div class="f">
                  <label class="f-label">تگ‌ها</label>
                  @php $allTags = \App\Models\Tag::forEntity('case')->orderBy('name')->get(); @endphp
                  @include('partials.tag_picker', ['tags' => $allTags, 'selected' => collect(old('tag_ids', isset($case) ? $case->tags->pluck('id')->all() : []))])
              </div>
            </div>

            <div class="form-section-sm">اطلاعات تکمیلی</div>
            <div class="f">
                <label class="f-label">اولویت</label>
                <select name="priority">
                    @foreach(['low'=>'پایین','medium'=>'متوسط','high'=>'بالا','urgent'=>'فوری'] as $k=>$v)
                        <option value="{{ $k }}" @selected(old('priority', $case->priority) == $k)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="f">
                <label class="f-label">ارز</label>
                <select name="currency">
                    <option value="EUR" @selected(old('currency', $case->currency) == 'EUR')>یورو</option>
                    <option value="IRR" @selected(old('currency', $case->currency) == 'IRR')>ریال</option>
                </select>
            </div>
            <div class="f">
                <label class="f-label">ترم تحویل</label>
                <select name="incoterm">
                    <option value="">—</option>
                    @foreach(['CPT','CFR','DDP','FOB','EXW','CIF'] as $term)
                        <option value="{{ $term }}" @selected(old('incoterm', $case->incoterm) == $term)>{{ $term }}</option>
                    @endforeach
                </select>
            </div>
            <div class="f">
                <label class="f-label">نرخ تبدیل</label>
                <input type="number" step="0.000001" name="exchange_rate" value="{{ old('exchange_rate', $case->exchange_rate) }}" dir="ltr">
            </div>

            <div class="f-full">
                @include('partials.custom_fields')
            </div>
          </div>
          <div class="form-actions-sm">
            <button type="submit" class="btn btn-primary">ذخیره تغییرات</button>
            <a href="{{ route('cases.show', $case) }}" class="btn btn-ghost">انصراف</a>
          </div>
        </div>
    </form>
</div>
@endsection
