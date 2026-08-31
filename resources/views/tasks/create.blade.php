@extends('layouts.app')
@section('title', 'ایجاد وظیفه')
@section('content')
<form method="POST" action="{{ route('tasks.store') }}" class="card">
  @csrf
  <div class="card-b">
    <div class="form-grid4">

      <div class="form-section-sm">مشخصات وظیفه</div>
      <div class="f f-span2">
        <label class="f-label">عنوان <span class="req">*</span></label>
        <input type="text" name="title" value="{{ old('title') }}" required>
      </div>
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
        <label class="f-label">موعد</label>
        <x-jalali-datetime name="due_at" :value="old('due_at')" />
      </div>
      <div class="f-full">
        <label class="f-label">توضیحات</label>
        <textarea name="description" rows="3">{{ old('description') }}</textarea>
      </div>

      <div class="form-section-sm">ارجاع</div>
      <div class="f f-span2">
        <label class="f-label">مسئول اصلی</label>
        <select name="assigned_to">
          <option value="">—</option>
          @foreach($users as $u)
            <option value="{{ $u->id }}" @selected(old('assigned_to')==$u->id)>{{ $u->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="f f-span2" style="justify-content:center">
        <label class="f-label" style="display:flex;align-items:center;gap:6px;margin-bottom:0">
          <input type="checkbox" name="is_team" value="1" @checked(old('is_team'))> وظیفه همگانی / تیمی
        </label>
      </div>
      <div class="f-full">
        <label class="f-label">همکاران وظیفه (چند نفر)</label>
        <x-assignee-picker :users="$users ?? $experts ?? []" :selected="old('assignee_ids', [])" />
      </div>

      <div class="form-section-sm">مرتبط با</div>
      <div class="f f-span2">
        <label class="f-label">پرونده مرتبط</label>
        <select name="case_id">
          <option value="">—</option>
          @foreach($cases as $c)
            <option value="{{ $c->id }}" @selected(old('case_id')==$c->id)>{{ $c->case_number }} — {{ \Illuminate\Support\Str::limit($c->title, 40) }}</option>
          @endforeach
        </select>
      </div>
      <div class="f f-span2">
        <label class="f-label">مخاطب مرتبط</label>
        <select name="contact_id">
          <option value="">—</option>
          @foreach($contacts as $ct)
            <option value="{{ $ct->id }}" @selected(old('contact_id')==$ct->id)>{{ $ct->full_name }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <div class="form-actions-sm">
      <button type="submit" class="btn btn-primary">ایجاد</button>
      <a href="{{ route('tasks.index') }}" class="btn btn-ghost">انصراف</a>
    </div>
  </div>
</form>
@endsection
