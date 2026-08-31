@extends('layouts.app')
@section('title', 'ویرایش وظیفه')
@section('content')
<form method="POST" action="{{ route('tasks.update', $task) }}" class="card">
  @csrf
  @method('PUT')
  <div class="card-b">
    <div class="form-grid4">

      <div class="form-section-sm">مشخصات وظیفه</div>
      <div class="f f-span2">
        <label class="f-label">عنوان <span class="req">*</span></label>
        <input type="text" name="title" value="{{ old('title', $task->title) }}" required>
      </div>
      <div class="f">
        <label class="f-label">اولویت</label>
        <select name="priority">
          @foreach(\App\Models\Task::PRIORITIES as $p => $label)
            <option value="{{ $p }}" @selected(old('priority', $task->priority)==$p)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="f">
        <label class="f-label">وضعیت</label>
        <select name="status">
          @foreach(\App\Models\Task::STATUSES as $s => $label)
            <option value="{{ $s }}" @selected(old('status', $task->status)==$s)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="f">
        <label class="f-label">موعد</label>
        <x-jalali-datetime name="due_at" :value="old('due_at', $task->getRawOriginal('due_at') ?? $task->due_at)" />
      </div>
      <div class="f-full">
        <label class="f-label">توضیحات</label>
        <textarea name="description" rows="3">{{ old('description', $task->description) }}</textarea>
      </div>

      <div class="form-section-sm">ارجاع</div>
      <div class="f f-span2">
        <label class="f-label">مسئول اصلی</label>
        <select name="assigned_to">
          <option value="">—</option>
          @foreach($users as $u)
            <option value="{{ $u->id }}" @selected(old('assigned_to', $task->assigned_to)==$u->id)>{{ $u->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="f f-span2" style="justify-content:center">
        <label class="f-label" style="display:flex;align-items:center;gap:6px;margin-bottom:0">
          <input type="checkbox" name="is_team" value="1" @checked(old('is_team', $task->is_team))> وظیفه همگانی / تیمی
        </label>
      </div>
      <div class="f-full">
        <label class="f-label">همکاران وظیفه (چند نفر)</label>
        <x-assignee-picker :users="$users ?? $experts ?? []" :selected="old('assignee_ids', $task->assignees->pluck('id')->all())" />
      </div>

      <div class="form-section-sm">مرتبط با</div>
      <div class="f f-span2">
        <label class="f-label">پرونده مرتبط</label>
        <select name="case_id">
          <option value="">—</option>
          @foreach($cases as $c)
            <option value="{{ $c->id }}" @selected(old('case_id', $task->case_id)==$c->id)>{{ $c->case_number }} — {{ \Illuminate\Support\Str::limit($c->title, 40) }}</option>
          @endforeach
        </select>
      </div>
      <div class="f f-span2">
        <label class="f-label">مخاطب مرتبط</label>
        <select name="contact_id">
          <option value="">—</option>
          @foreach($contacts as $ct)
            <option value="{{ $ct->id }}" @selected(old('contact_id', $task->contact_id)==$ct->id)>{{ $ct->full_name }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <div class="form-actions-sm">
      <button type="submit" class="btn btn-primary">ذخیره</button>
      <a href="{{ route('tasks.show', $task) }}" class="btn btn-ghost">انصراف</a>
    </div>
  </div>
</form>
@endsection
