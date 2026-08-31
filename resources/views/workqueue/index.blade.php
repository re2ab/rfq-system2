@extends('layouts.app')
@section('title', 'صف کاری')
@section('content')
@php
  $tabs = [
    'tasks' => ['label' => 'وظایف من', 'count' => $taskCount ?? 0],
    'case_tasks' => ['label' => 'وظایف پرونده', 'count' => $caseTaskCount ?? 0],
    'cases' => ['label' => 'پرونده‌های من', 'count' => $caseCount ?? 0],
  ];
  if (!empty($showAssignedOpen)) {
    $tabs['assigned_open'] = ['label' => 'تخصیص‌داده‌شده باز', 'count' => $assignedOpenCount ?? 0];
  }
@endphp

<div class="wq-page">
  {{-- کنترل‌ها: بازه + تب‌ها (موبایل و دسکتاپ) --}}
  <div class="card wq-controls">
    <div class="card-b" style="padding:12px">
      <form method="GET" class="wq-period-row">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <label class="wq-period-label">بازه زمانی</label>
        <select name="period" class="filter-row-grow" onchange="this.form.submit()">
          @foreach($periods as $key => $label)
            <option value="{{ $key }}" @selected($period === $key)>{{ $label }}</option>
          @endforeach
        </select>
      </form>

      <div class="wq-tab-grid" role="tablist">
        @foreach($tabs as $key => $meta)
        <a href="{{ route('workqueue.index', ['period'=>$period, 'tab'=>$key]) }}"
           class="wq-tab {{ $tab === $key ? 'active' : '' }}"
           role="tab"
           aria-selected="{{ $tab === $key ? 'true' : 'false' }}">
          <span class="wq-tab-label">{{ $meta['label'] }}</span>
          <span class="wq-tab-count">{{ $meta['count'] }}</span>
        </a>
        @endforeach
        <a href="{{ route('mailbox.inbox') }}" class="wq-tab wq-tab-mail">
          <span class="wq-tab-label">ایمیل نخوانده</span>
          <span class="wq-tab-count">{{ $unreadMailCount ?? 0 }}</span>
        </a>
      </div>
    </div>
  </div>

  <div class="card wq-main">
    <div class="card-h">
      <span>
        @if($tab==='tasks') وظایف عمومی من
        @elseif($tab==='case_tasks') وظایف مربوط به پرونده‌های من
        @elseif($tab==='assigned_open') وظایف تخصیص‌داده‌شده باز
        @else پرونده‌های من
        @endif
      </span>
      <span class="badge">{{ $periods[$period] ?? $period }}</span>
    </div>
    <div class="card-b pad0">
      @if($tab === 'tasks' || $tab === 'case_tasks' || $tab === 'assigned_open')
        @php
          if ($tab === 'tasks') { $list = $tasks; }
          elseif ($tab === 'case_tasks') { $list = $caseTasks; }
          else { $list = $assignedOpenTasks ?? collect(); }
        @endphp
        <div class="data-table-desktop">
          <table class="tbl">
            <thead>
              <tr>
                <th>عنوان</th>
                <th>سررسید</th>
                <th>وضعیت</th>
                <th>اولویت</th>
                @if($tab==='case_tasks')<th>پرونده</th>@endif
                @if($tab==='assigned_open')<th>مسئول</th><th>پرونده</th>@endif
              </tr>
            </thead>
            <tbody>
              @forelse($list as $task)
              <tr style="cursor:pointer" onclick="location.href='{{ route('tasks.show', $task) }}'">
                <td style="font-weight:700">{{ $task->title }}</td>
                <td>{{ $task->due_at ? jdate($task->due_at)->format('Y/m/d') : '—' }}</td>
                <td>{{ $task->status_label ?? $task->status }}</td>
                <td>{{ $task->priority_label ?? $task->priority }}</td>
                @if($tab==='case_tasks')
                <td>
                  @if($task->case)
                    <a href="{{ route('cases.show', $task->case) }}" onclick="event.stopPropagation()">{{ $task->case->case_number }}</a>
                  @else — @endif
                </td>
                @endif
                @if($tab==='assigned_open')
                <td>{{ $task->assignee?->name ?? '—' }}</td>
                <td>
                  @if($task->case)
                    <a href="{{ route('cases.show', $task->case) }}" onclick="event.stopPropagation()">{{ $task->case->case_number }}</a>
                  @else — @endif
                </td>
                @endif
              </tr>
              @empty
              <tr><td colspan="6"><x-empty title="موردی در این بازه نیست" /></td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
        <div class="data-table-mobile">
          @forelse($list as $task)
          <a href="{{ route('tasks.show', $task) }}" class="list-card">
            <div class="list-card-title">{{ $task->title }}</div>
            <div class="list-card-meta">
              {{ $task->due_at ? jdate($task->due_at)->format('Y/m/d') : 'بدون سررسید' }}
              · {{ $task->status_label ?? $task->status }}
              · {{ $task->priority_label ?? $task->priority }}
              @if($task->case) · {{ $task->case->case_number }} @endif
            </div>
          </a>
          @empty
            <div style="padding:24px"><x-empty title="موردی نیست" /></div>
          @endforelse
        </div>
      @else
        <div class="data-table-desktop">
          <table class="tbl">
            <thead>
              <tr>
                <th>شماره</th>
                <th>عنوان</th>
                <th>وضعیت</th>
                <th>مشتری</th>
                <th>آخرین به‌روزرسانی</th>
              </tr>
            </thead>
            <tbody>
              @forelse($cases as $case)
              <tr style="cursor:pointer" onclick="location.href='{{ route('cases.show', $case) }}'">
                <td style="font-weight:800;color:var(--brand)">{{ $case->case_number }}</td>
                <td>{{ \Illuminate\Support\Str::limit($case->title, 40) }}</td>
                <td><x-status-badge :status="$case->current_status" /></td>
                <td>{{ $case->customer?->name ?? '—' }}</td>
                <td style="font-size:12px;color:var(--muted)">{{ $case->updated_at?->diffForHumans() }}</td>
              </tr>
              @empty
              <tr><td colspan="5"><x-empty title="پرونده‌ای در این بازه نیست" /></td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
        <div class="data-table-mobile">
          @forelse($cases as $case)
          <a href="{{ route('cases.show', $case) }}" class="list-card">
            <div class="list-card-top">
              <span class="list-card-id">{{ $case->case_number }}</span>
              <x-status-badge :status="$case->current_status" />
            </div>
            <div class="list-card-title">{{ $case->title }}</div>
            <div class="list-card-meta">{{ $case->customer?->name ?? '—' }}</div>
          </a>
          @empty
            <div style="padding:24px"><x-empty title="پرونده‌ای نیست" /></div>
          @endforelse
        </div>
      @endif
    </div>
  </div>
</div>
@endsection