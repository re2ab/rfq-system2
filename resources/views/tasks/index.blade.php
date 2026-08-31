@extends('layouts.app')
@section('title', 'وظایف')
@section('actions')
  <x-btn href="{{ route('tasks.create') }}">وظیفه جدید</x-btn>
@endsection
@section('content')
<form method="GET" class="rfq-filters rfq-filters-stack" id="tasksFilters">
  @csrf
  <div class="rfq-filters-search">
    <input type="text" name="q" value="{{ request('q') }}" placeholder="جستجو عنوان…" class="rfq-f-input">
    <button type="submit" class="btn btn-primary btn-sm rfq-f-btn">جستجو</button>
    <x-bulk-toolbar form-id="tasksFilters" :action="route('tasks.bulk-action')" />
  </div>
  <div class="rfq-filters-meta">
    <select name="status" class="rfq-f-select" size="1">
      <option value="">همه وضعیت‌ها</option>
      @foreach(\App\Models\Task::STATUSES as $s => $label)
        <option value="{{ $s }}" @selected(request('status')==$s)>{{ $label }}</option>
      @endforeach
    </select>
    <select name="priority" class="rfq-f-select" size="1">
      <option value="">همه اولویت‌ها</option>
      @foreach(\App\Models\Task::PRIORITIES as $pr => $label)
        <option value="{{ $pr }}" @selected(request('priority')==$pr)>{{ $label }}</option>
      @endforeach
    </select>
    <select name="scope" class="rfq-f-select" size="1" onchange="this.form.submit()">
      <option value="all" @selected(($scope ?? request('scope','all'))==='all')>همه</option>
      <option value="general" @selected(($scope ?? request('scope'))==='general')>وظایف عمومی</option>
      <option value="case" @selected(($scope ?? request('scope'))==='case')>وظایف مربوط به پرونده</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm rfq-f-btn">فیلتر</button>
  </div>
</form>

<div class="card" style="overflow:hidden">
  <div class="data-table-desktop">
    <table class="tbl">
      <thead>
        <tr>
          <th style="width:36px"><input type="checkbox" class="bulk-select-all" data-form-target="tasksFilters"></th>
          <th>عنوان</th>
          <th style="text-align:center">مسئول</th>
          <th style="text-align:center">اولویت</th>
          <th style="text-align:center">وضعیت</th>
          <th style="text-align:center">سررسید</th>
          <th style="text-align:center">پرونده</th>
          <th>نوع</th>
        </tr>
      </thead>
      <tbody>
        @forelse($tasks as $task)
        <tr style="cursor:pointer" onclick="location.href='{{ route('tasks.show', $task) }}'">
          <td onclick="event.stopPropagation()"><input type="checkbox" class="bulk-row-check" form="tasksFilters" name="ids[]" value="{{ $task->id }}"></td>
          <td style="font-weight:700;color:var(--brand)">{{ $task->title }}</td>
          <td style="text-align:center">{{ $task->assignee?->name ?? '—' }}</td>
          <td style="text-align:center">{{ $task->priority_label }}</td>
          <td style="text-align:center">{{ $task->status_label }}</td>
          <td style="text-align:center">{{ jdate($task->due_at)->format('Y/m/d') }}</td>
          <td style="text-align:center" onclick="event.stopPropagation()">
            @if($task->case)
              <a href="{{ route('cases.show', $task->case) }}">{{ $task->case->case_number }}</a>
            @else
              <span style="font-size:12px;color:var(--muted)">عمومی</span>
            @endif
          </td>
          <td>{{ $task->is_team ? 'تیمی' : 'اختصاصی' }}</td>
        </tr>
        @empty
        <tr><td colspan="8"><x-empty title="وظیفه‌ای یافت نشد" /></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="data-table-mobile">
    @forelse($tasks as $task)
    <a href="{{ route('tasks.show', $task) }}" class="mobile-list-card">
      <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start">
        <strong style="color:var(--brand)">{{ $task->title }}</strong>
        <span class="badge">{{ $task->status_label }}</span>
      </div>
      <div class="rel-meta" style="margin-top:6px">
        {{ $task->assignee?->name ?? '—' }}
        · {{ $task->priority_label }}
        @if($task->due_at)
          · سررسید {{ jdate($task->due_at)->format('Y/m/d') }}
        @endif
      </div>
      @if($task->case)
        <div class="rel-meta">پرونده {{ $task->case->case_number }}</div>
      @endif
    </a>
    @empty
      <div style="padding:16px"><x-empty title="وظیفه‌ای یافت نشد" /></div>
    @endforelse
  </div>
</div>
@if(method_exists($tasks, 'links'))
  <div class="rfq-pagination" style="margin-top:12px">{{ $tasks->withQueryString()->links() }}</div>
@endif
@endsection

