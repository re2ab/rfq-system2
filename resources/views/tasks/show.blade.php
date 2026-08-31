@extends('layouts.app')
@section('title', $task->title)
@section('actions')
  @if(auth()->user()->hasAnyRole(['admin','technical_manager','financial_manager']))
  <a href="{{ route('tasks.edit',$task) }}" class="btn btn-primary">ویرایش</a>
  @endif
  <a href="{{ route('tasks.index') }}" class="btn btn-ghost">فهرست</a>
@endsection
@section('content')
@include('partials.jalali')
<div class="rfq-grid-2">
  <div class="card">
    <div class="card-h">جزئیات وظیفه</div>
    <div class="card-b rtl-fields">
      <div class="field-row"><span class="lbl">وضعیت</span><span class="val"><span class="badge">{{ $task->status_label }}</span></span><span></span></div>
      <div class="field-row"><span class="lbl">اولویت</span><span class="val">{{ $task->priority_label }}</span><span></span></div>
      <div class="field-row"><span class="lbl">مسئول</span><span class="val"><x-user-avatars :users="$task->allAssignees()" :size="28" /> {{ $task->assignee?->name ?? '—' }}</span><span></span></div>
      <div class="field-row"><span class="lbl">موعد</span><span class="val">{{ jdate($task->due_at)->format('Y/m/d') }}</span><span></span></div>
      @if($task->is_team)
      <div class="field-row"><span class="lbl">نوع</span><span class="val">تیمی</span><span></span></div>
      @endif
      @if($task->case)
      <div class="field-row"><span class="lbl">پرونده</span><span class="val"><a href="{{ route('cases.show',$task->case) }}">{{ $task->case->case_number }}</a></span><span></span></div>
      @endif
      @if($task->description)
      <div class="field-row"><span class="lbl">شرح</span><span class="val" style="white-space:pre-wrap">{{ $task->description }}</span><span></span></div>
      @endif
    </div>
  </div>
  <div class="space-y-3">
    <div class="card">
      <div class="card-h">چک‌لیست</div>
      <div class="card-b" style="padding:0">
        @foreach($task->checklistItems as $item)
        <div class="rel-item" style="display:flex;align-items:center;gap:10px">
          <form method="POST" action="{{ route('checklist.toggle',$item) }}" style="display:contents">@csrf
            <button type="submit" class="chk-box {{ $item->is_done ? 'checked' : '' }}" aria-label="تغییر وضعیت"></button>
            <span style="flex:1;font-size:13px;{{ $item->is_done ? 'text-decoration:line-through;color:var(--muted)' : '' }}">{{ $item->title }}</span>
          </form>
          <form method="POST" action="{{ route('checklist.destroy',$item) }}" onsubmit="return confirm('حذف این آیتم؟')">@csrf @method('DELETE')
            <button type="submit" class="text-xs text-red-600" style="flex-shrink:0">حذف</button>
          </form>
        </div>
        @endforeach
        <form method="POST" action="{{ route('tasks.checklist.store',$task) }}" class="flex gap-2" style="padding:10px 14px">@csrf
          <input name="title" required placeholder="آیتم جدید" class="flex-1 border rounded-xl px-2 py-1.5">
          <button class="btn btn-primary">افزودن</button>
        </form>
      </div>
    </div>
    @if(!in_array($task->status,['done','cancelled']))
    <div class="card">
      <div class="card-h">تکمیل وظیفه</div>
      <div class="card-b">
        <form method="POST" action="{{ route('tasks.complete',$task) }}">@csrf
          <textarea name="completion_note" rows="2" class="w-full border rounded-xl px-3 py-2 text-sm mb-2" placeholder="یادداشت انجام"></textarea>
          <button class="btn btn-primary">انجام شد</button>
        </form>
      </div>
    </div>
    @endif
  </div>
</div>
@endsection