@extends('layouts.app')
@section('object', 'داشبورد')
@section('title', 'داشبورد')
@section('actions')
  <x-btn href="{{ route('cases.create') }}" class="hide-on-mobile">پرونده جدید</x-btn>
  <x-btn variant="ghost" href="{{ route('kanban.index') }}" class="hide-on-mobile">پایپ‌لاین</x-btn>
@endsection

@section('content')
@php
  $statuses = \App\Models\CaseModel::statusLabels();
  $byStatus = $stats['by_status'] ?? collect();
  if (is_object($byStatus) && method_exists($byStatus,'max')) $max = max(1,(int)$byStatus->max());
  elseif (is_array($byStatus) && count($byStatus)) $max = max(1,(int)max($byStatus));
  else $max = 1;
  $recentActivities = $stats['recent_activities'] ?? collect();
  $myTasks = $stats['my_tasks'] ?? collect();
@endphp

@php
  $kpiCount = 8;
@endphp
<div class="grid-stats kpi-8">
  <x-stat label="پرونده‌های باز" :value="$stats['open_cases'] ?? 0" tone="open" />
  <x-stat label="نرخ برد" :value="($stats['win_rate'] ?? 0).'%'" :sub="'برد '.($stats['won_count'] ?? 0).' · باخت '.($stats['lost_count'] ?? 0)" tone="rate" />
  <x-stat label="کل پرونده‌ها" :value="$stats['total_cases'] ?? 0" tone="total" />
  <x-stat label="وظایف سررسید" :value="$stats['tasks_due'] ?? 0" tone="due" />
  <x-stat label="مطالبات باز" :value="$stats['open_receivables'] ?? 0" tone="recv" />
  <x-stat label="مطالبات معوق" :value="$stats['overdue_receivables'] ?? 0" tone="danger" />
  <x-stat label="برنده شده" :value="$stats['won_pure'] ?? ($stats['won_count'] ?? 0)" tone="success" />
  <x-stat label="بازنده" :value="$stats['lost_count'] ?? 0" tone="danger" />
</div>

{{-- ردیف‌های داشبورد: ترتیب و عرض هرکدام از تنظیمات > مدیریت داشبورد قابل تغییر است --}}
<div class="dash-flow">
@foreach($dashLayout ?? [] as $item)
@php $w = $item['width'] ?? 50; @endphp
@switch($item['key'])

@case('pipeline_status')
<div class="dash-flow-item" style="--dfw:{{ $w }}%">
  <x-card title="توزیع وضعیت (پایپ‌لاین)">
    <x-slot:actions><span class="badge">نمودار</span></x-slot:actions>
    <div class="chart-bars">
      @foreach($statuses as $key => $label)
        @php $cnt = (int)($byStatus[$key] ?? 0); $pct = $max ? round($cnt / $max * 100) : 0; @endphp
        <div class="chart-row">
          <div class="chart-label">{{ $label }}</div>
          <div class="chart-track"><div class="chart-fill" style="width:{{ $pct }}%"></div></div>
          <div class="chart-val">{{ $cnt }}</div>
        </div>
      @endforeach
    </div>
  </x-card>
</div>
@break

@case('recent_cases')
<div class="dash-flow-item" style="--dfw:{{ $w }}%">
 <x-card title="آخرین پرونده‌ها" :pad="false">
    <x-slot:actions>
      <x-btn size="sm" variant="ghost" href="{{ route('cases.index') }}">همه</x-btn>
    </x-slot:actions>
    @php $recent = $stats['recent_cases'] ?? collect(); @endphp
    @if($recent->count())
      <div class="hide-on-mobile" style="overflow-x:auto">
        <table class="tbl" style="width:100%">
          <thead>
            <tr>
              <th>شماره</th>
              <th>عنوان</th>
              <th>سازمان</th>
              <th style="text-align:left">وضعیت</th>
            </tr>
          </thead>
          <tbody>
            @foreach($recent as $c)
            <tr style="cursor:pointer" onclick="location.href='{{ route('cases.show', $c) }}'">
              <td style="font-weight:700;color:var(--brand)">{{ $c->case_number }}</td>
              <td>{{ \Illuminate\Support\Str::limit($c->title, 40) }}</td>
              <td>{{ optional($c->customer)->name ?? '—' }}</td>
              <td style="text-align:left"><x-status-badge :status="$c->current_status" /></td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div class="md:hidden">
        <table class="tbl" style="width:100%;table-layout:fixed">
          <thead>
            <tr>
              <th style="width:30%">شماره</th>
              <th>عنوان / سازمان</th>
              <th style="width:28%;text-align:left">وضعیت</th>
            </tr>
          </thead>
          <tbody>
            @foreach($recent as $c)
            <tr style="cursor:pointer" onclick="location.href='{{ route('cases.show', $c) }}'">
              <td style="font-weight:700;color:var(--brand);font-size:12px;word-break:break-all">{{ $c->case_number }}</td>
              <td>
                <div style="font-weight:600;font-size:13px;line-height:1.35">{{ $c->title }}</div>
                <div style="font-size:11px;color:var(--muted);margin-top:2px">{{ optional($c->customer)->name ?? '—' }}</div>
              </td>
              <td style="text-align:left"><x-status-badge :status="$c->current_status" /></td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <div style="padding:20px;text-align:center;color:var(--muted)">
        پرونده‌ای برای نمایش نیست.
        <div style="margin-top:10px"><a href="{{ route('cases.create') }}" class="btn btn-primary btn-sm">پرونده جدید</a></div>
      </div>
    @endif
  </x-card>
</div>
@break

@case('trend_cases')
<div class="dash-flow-item" style="--dfw:{{ $w }}%">
  <div class="card">
    <div class="card-h">
      <span id="lineCasesTitle">روند پرونده‌های جدید (۱۴ روز)</span>
      <span class="chart-menu-wrap">
        <select id="lineCasesSelect" class="rfq-f-select">
          <option value="new_cases">روند پرونده‌های جدید</option>
          <option value="won">روند پرونده‌های برده‌شده</option>
          <option value="compare_new_won">مقایسهٔ پرونده جدید / برده‌شده</option>
        </select>
      </span>
    </div>
    <div class="card-b"><canvas id="lineCases" height="160" style="width:100%;display:block"></canvas></div>
  </div>
</div>
@break

@case('trend_activity')
<div class="dash-flow-item" style="--dfw:{{ $w }}%">
  <div class="card">
    <div class="card-h">
      <span id="lineActivityTitle">روند فعالیت‌ها و برد (۱۴ روز)</span>
      <span class="chart-menu-wrap">
        <select id="lineActivitySelect" class="rfq-f-select">
          <option value="activity_won">فعالیت‌ها و برد</option>
          <option value="activity_only">فقط فعالیت‌ها</option>
          <option value="compare_activity_new">مقایسهٔ فعالیت‌ها / پرونده جدید</option>
        </select>
      </span>
    </div>
    <div class="card-b"><canvas id="lineActivity" height="160" style="width:100%;display:block"></canvas></div>
  </div>
</div>
@break

@case('mgmt_chart')
<div class="dash-flow-item" style="--dfw:{{ $w }}%">
  <div class="card">
    <div class="card-h">
      <span id="mgmtChartTitle">پرونده‌های باز به تفکیک اولویت</span>
      <span class="chart-menu-wrap">
      <select id="mgmtChartSelect" class="rfq-f-select">
        <option value="priority">پرونده‌های باز به تفکیک اولویت</option>
        <option value="workload">بار کاری کارشناسان (پرونده باز)</option>
        <option value="stale">پرونده‌های راکد (+۱۴ روز بدون فعالیت)</option>
      </select>
      </span>
    </div>
    <div class="card-b" style="height:320px;position:relative"><canvas id="mgmtBar"></canvas></div>
  </div>
</div>
@break

@case('pie_chart')
<div class="dash-flow-item" style="--dfw:{{ $w }}%">
  <div class="card">
    <div class="card-h">
      <span id="pieMetricTitle">نرخ برنده شدن</span>
      <span class="chart-menu-wrap">
      <select id="pieMetricSelect" class="rfq-f-select">
        <option value="winloss">نرخ برنده شدن</option>
        <option value="tasks">نرخ انجام وظایف</option>
        <option value="receivables">نرخ وصول مطالبات</option>
      </select>
      </span>
    </div>
    <div class="card-b" style="max-width:320px;margin:0 auto">
      <canvas id="statusPie" height="220"></canvas>
      <div id="statusPieLegend" style="display:flex;flex-wrap:wrap;gap:8px;justify-content:center;margin-top:10px;font-size:11px"></div>
    </div>
  </div>
</div>
@break

@case('industry_chart')
<div class="dash-flow-item" style="--dfw:{{ $w }}%">
  <div class="card">
    <div class="card-h"><span>پرونده بر حسب صنعت مشتری</span><span class="badge">Pie</span></div>
    <div class="card-b" style="max-width:320px;margin:0 auto">
      <canvas id="industryPie" height="220"></canvas>
      <div id="industryPieLegend" style="display:flex;flex-wrap:wrap;gap:8px;justify-content:center;margin-top:10px;font-size:11px;color:var(--muted)"></div>
    </div>
  </div>
</div>
@break

@case('assigned_open_tasks')
@if(!empty($stats['show_assigned_open_panel']))
<div class="dash-flow-item" style="--dfw:{{ $w }}%">
  <x-card title="وظایف اختصاص‌داده‌شده باز" :pad="false">
    @php $assignedOpen = $stats['assigned_open_tasks'] ?? collect(); @endphp
    <div class="card-b text-xs text-gray-500" style="padding:8px 14px;border-bottom:1px solid var(--border)">
      · تعداد در این فهرست: {{ $stats['assigned_open_count'] ?? $assignedOpen->count() }}
    </div>
    @forelse($assignedOpen as $task)
      <a href="{{ route('tasks.show', $task) }}" class="rel-item block hover:bg-[var(--brand-soft-2)]" style="padding:10px 14px;border-bottom:1px solid var(--border);text-decoration:none;color:inherit">
        <div class="flex justify-between gap-2 items-start">
          <div>
            <div class="font-semibold text-sm">{{ $task->title }}</div>
            <div class="text-xs text-gray-500">
              مسئول: {{ $task->assignee->name ?? '—' }}
              @if($task->case) · پرونده {{ $task->case->case_number ?? $task->case_id }} @endif
              @if(auth()->user()?->hasRole('admin') && $task->creator)
                · تخصیص‌دهنده: {{ $task->creator->name }}
              @endif
            </div>
          </div>
          <div class="text-left text-xs whitespace-nowrap">
            <div>{{ $task->status_label ?? $task->status }}</div>
            @if($task->due_at)
              @php $od = $task->due_at->lt(now()->startOfDay()); @endphp
              <div style="{{ $od ? 'color:var(--danger);font-weight:700' : 'color:var(--muted)' }}">
                {{ jdate($task->due_at)->format('Y/m/d') }}
              </div>
            @endif
          </div>
        </div>
      </a>
    @empty
      <div class="p-4 text-sm text-gray-500">وظیفه باز تخصیص‌داده‌شده‌ای نیست.</div>
    @endforelse
  </x-card>
</div>
@endif
@break

@case('my_tasks')
<div class="dash-flow-item" style="--dfw:{{ $w }}%">
  <x-card title="وظایف من" :pad="false">
    <x-slot:actions>
      <x-btn size="sm" variant="ghost" href="{{ route('tasks.index') }}">مشاهده</x-btn>
    </x-slot:actions>
    @forelse($myTasks as $task)
      <div class="rel-item">
        <a href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a>
        <div class="rel-meta">
          <x-badge :tone="($task->priority ?? '') === 'high' ? 'warn' : 'default'">{{ $task->priority_label ?? $task->priority ?? '—' }}</x-badge>
          · {{ $task->status_label ?? $task->status }}
        </div>
      </div>
    @empty
      <div style="padding:20px"><x-empty title="وظیفه بازی ندارید" /></div>
    @endforelse
  </x-card>
</div>
@break

@case('recent_activities')
<div class="dash-flow-item" style="--dfw:{{ $w }}%">
<div class="card dash-activity-full" style="margin-top:0">
  <div class="card-h"><span>فعالیت‌های اخیر</span></div>
  <div class="card-b pad0">
    @if($recentActivities->count())
      <div class="data-table-desktop">
        <table class="tbl activity-table">
          <thead>
            <tr>
              <th>کاربر</th>
              <th>موضوع فعالیت</th>
              <th>پرونده</th>
              <th>شرح فعالیت</th>
              <th>زمان انجام</th>
            </tr>
          </thead>
          <tbody>
            @foreach($recentActivities as $act)
            @php
              $typeLabel = match($act->type ?? '') {
                'phone_call_report', 'phone_call' => 'گزارش تماس',
                'status_change' => 'تغییر وضعیت',
                'note', 'comment' => 'یادداشت',
                default => 'فعالیت',
              };
            @endphp
            <tr>
              <td>{{ $act->user->name ?? 'سیستم' }}</td>
              <td>{{ $typeLabel }}</td>
              <td>@if($act->case ?? null)<a href="{{ route('cases.show', $act->case) }}">{{ $act->case->case_number }}</a>@else — @endif</td>
              <td>{{ \Illuminate\Support\Str::limit($act->body ?? '', 80) }}</td>
              <td style="white-space:nowrap">{{ optional($act->created_at)->diffForHumans() }}</td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div class="data-table-mobile">
        @foreach($recentActivities as $act)
          @php
            $typeLabel = match($act->type ?? '') {
              'phone_call_report', 'phone_call' => 'گزارش تماس',
              'status_change' => 'تغییر وضعیت',
              'note', 'comment' => 'یادداشت',
              default => 'فعالیت',
            };
          @endphp
          <div class="mobile-list-card">
            <div style="font-weight:600">{{ $act->user->name ?? 'سیستم' }} · {{ $typeLabel }}
              @if($act->case ?? null)· <a href="{{ route('cases.show', $act->case) }}">{{ $act->case->case_number }}</a>@endif
            </div>
            <div class="rel-meta">{{ \Illuminate\Support\Str::limit($act->body ?? '', 100) }}</div>
            <div class="rel-meta">{{ optional($act->created_at)->diffForHumans() }}</div>
          </div>
        @endforeach
      </div>
    @else
      <div style="padding:20px"><x-empty title="هنوز فعالیتی ثبت نشده" /></div>
    @endif
  </div>
</div>
</div>
@break

@endswitch
@endforeach
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
<script>
(function(){
  if (typeof Chart === 'undefined') return;
  Chart.defaults.font.family = 'Vazirmatn, Tahoma, sans-serif';
  Chart.defaults.datasets.pie.borderWidth = 0;
  if (Chart.defaults.plugins && Chart.defaults.plugins.legend && Chart.defaults.plugins.legend.labels) {
    Chart.defaults.plugins.legend.labels.boxBorderWidth = 0;
  }

  const palette = ['#b8703c','#8a4f27','#d99b6c','#3a4149','#2f7d5b','#33608e','#a9791b','#b23a2c','#c9a27e','#6d737e','#5c6b73','#e0c3a8','#1f242b'];

  const lineLabels = @json($stats['line_labels'] ?? []);
  const lineCases = @json($stats['line_cases'] ?? []);
  const lineActs = @json($stats['line_activities'] ?? []);
  const lineWon = @json($stats['line_won'] ?? []);

  const lineOpts = {
    responsive: true,
    interaction: { mode: 'index', intersect: false },
    plugins: { legend: { labels: { font: { family: 'Vazirmatn, Tahoma, sans-serif' } } } },
    scales: {
      x: { ticks: { font: { family: 'Vazirmatn, Tahoma, sans-serif' } }, grid: { color: 'rgba(15,118,110,.08)' } },
      y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: 'rgba(15,118,110,.08)' } }
    }
  };

  const el1 = document.getElementById('lineCases');
  var chart1 = null;
  function renderLine1(mode){
    if (chart1) { chart1.destroy(); }
    var t = document.getElementById('lineCasesTitle');
    var datasets = [];
    if (mode === 'won') {
      if (t) t.textContent = 'روند پرونده‌های برده‌شده (۱۴ روز)';
      datasets = [{ label: 'پرونده برده‌شده', data: lineWon, borderColor: '#33608e', backgroundColor: 'rgba(51,96,142,.14)', fill: true, tension: .35, pointRadius: 3, pointBackgroundColor: '#33608e' }];
    } else if (mode === 'compare_new_won') {
      if (t) t.textContent = 'مقایسهٔ پرونده جدید / برده‌شده (۱۴ روز)';
      datasets = [
        { label: 'پرونده جدید', data: lineCases, borderColor: '#b8703c', backgroundColor: 'transparent', fill: false, tension: .35, pointRadius: 3 },
        { label: 'برده‌شده', data: lineWon, borderColor: '#33608e', backgroundColor: 'transparent', fill: false, tension: .35, pointRadius: 3, borderDash: [4,4] }
      ];
    } else {
      if (t) t.textContent = 'روند پرونده‌های جدید (۱۴ روز)';
      datasets = [{ label: 'پرونده جدید', data: lineCases, borderColor: '#b8703c', backgroundColor: 'rgba(184,112,60,.14)', fill: true, tension: .35, pointRadius: 3, pointBackgroundColor: '#b8703c' }];
    }
    chart1 = new Chart(el1, { type: 'line', data: { labels: lineLabels, datasets: datasets }, options: lineOpts });
  }
  if (el1) {
    renderLine1('new_cases');
    var sel1 = document.getElementById('lineCasesSelect');
    if (sel1) sel1.addEventListener('change', function(){ renderLine1(this.value); });
  }

  const el2 = document.getElementById('lineActivity');
  var chart2 = null;
  function renderLine2(mode){
    if (chart2) { chart2.destroy(); }
    var t = document.getElementById('lineActivityTitle');
    var datasets = [];
    if (mode === 'activity_only') {
      if (t) t.textContent = 'روند فعالیت‌ها (۱۴ روز)';
      datasets = [{ label: 'فعالیت‌ها', data: lineActs, borderColor: '#8a4f27', backgroundColor: 'rgba(138,79,39,.12)', fill: true, tension: .35, pointRadius: 3 }];
    } else if (mode === 'compare_activity_new') {
      if (t) t.textContent = 'مقایسهٔ فعالیت‌ها / پرونده جدید (۱۴ روز)';
      datasets = [
        { label: 'فعالیت‌ها', data: lineActs, borderColor: '#8a4f27', backgroundColor: 'transparent', fill: false, tension: .35, pointRadius: 3 },
        { label: 'پرونده جدید', data: lineCases, borderColor: '#b8703c', backgroundColor: 'transparent', fill: false, tension: .35, pointRadius: 3, borderDash: [4,4] }
      ];
    } else {
      if (t) t.textContent = 'روند فعالیت‌ها و برد (۱۴ روز)';
      datasets = [
        { label: 'فعالیت‌ها', data: lineActs, borderColor: '#8a4f27', backgroundColor: 'rgba(138,79,39,.12)', fill: true, tension: .35, pointRadius: 3 },
        { label: 'برد', data: lineWon, borderColor: '#33608e', backgroundColor: 'transparent', fill: false, tension: .35, pointRadius: 3, borderDash: [4, 4] }
      ];
    }
    chart2 = new Chart(el2, { type: 'line', data: { labels: lineLabels, datasets: datasets }, options: lineOpts });
  }
  if (el2) {
    renderLine2('activity_won');
    var sel2 = document.getElementById('lineActivitySelect');
    if (sel2) sel2.addEventListener('change', function(){ renderLine2(this.value); });
  }
  const labels = @json(array_values(\App\Models\CaseModel::statusLabels()));
  const keys = @json(array_keys(\App\Models\CaseModel::statusLabels()));
  const byRaw = @json(($stats['by_status'] ?? []) instanceof \Illuminate\Support\Collection ? ($stats['by_status'])->toArray() : (array)($stats['by_status'] ?? []));
  const data = keys.map(function(k){ return Number(byRaw[k] || 0); });

  // نمودار میله‌ای مدیریتی (قابل انتخاب)
  const mgmtEl = document.getElementById('mgmtBar');
  if (mgmtEl) {
    const priorityMeta = @json(function_exists('case_priorities_meta') ? case_priorities_meta() : []);
    const workloadRaw = @json($stats['expert_workload'] ?? []);
    const staleRaw = @json($stats['stale_by_expert'] ?? []);
    const byPriorityRaw = @json(($stats['by_priority'] ?? []) instanceof \Illuminate\Support\Collection ? ($stats['by_priority'])->toArray() : (array)($stats['by_priority'] ?? []));

    const priorityKeys = Object.keys(priorityMeta);
    const mgmtDatasets = {
      priority: {
        labels: priorityKeys.map(function(k){ return priorityMeta[k].label || k; }),
        data: priorityKeys.map(function(k){ return Number(byPriorityRaw[k] || 0); }),
        colors: priorityKeys.map(function(k){ return priorityMeta[k].color || '#64748b'; })
      },
      workload: {
        labels: Object.keys(workloadRaw),
        data: Object.values(workloadRaw).map(Number),
        colors: Object.keys(workloadRaw).map(function(_, i){ return palette[i % palette.length]; })
      },
      stale: {
        labels: Object.keys(staleRaw),
        data: Object.values(staleRaw).map(Number),
        colors: Object.keys(staleRaw).map(function(){ return '#b23a2c'; })
      }
    };

    let mgmtChart = null;
    function renderMgmt(key){
      const cfg = mgmtDatasets[key] || mgmtDatasets.status;
      if (mgmtChart) mgmtChart.destroy();
      mgmtChart = new Chart(mgmtEl, {
        type: 'bar',
        data: {
          labels: cfg.labels,
          datasets: [{
            label: 'تعداد',
            data: cfg.data,
            backgroundColor: cfg.colors,
            borderRadius: 8,
            borderSkipped: false,
            maxBarThickness: 36
          }]
        },
        options: {
          indexAxis: 'y',
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: { callbacks: { title: function(items){ return items[0] && items[0].label; } } }
          },
          scales: {
            x: {
              beginAtZero: true,
              ticks: { stepSize: 1, font: { family: 'Vazirmatn, Tahoma, sans-serif', size: 11 } },
              grid: { color: 'rgba(15,118,110,.08)' }
            },
            y: {
              ticks: { font: { family: 'Vazirmatn, Tahoma, sans-serif', size: 11 }, autoSkip: false, crossAlign: 'far' },
              grid: { display: false }
            }
          }
        }
      });
    }
    renderMgmt('priority');
    const mgmtSel = document.getElementById('mgmtChartSelect');
    const mgmtTitle = document.getElementById('mgmtChartTitle');
    if (mgmtSel) mgmtSel.addEventListener('change', function(){
      renderMgmt(this.value);
      if (mgmtTitle) mgmtTitle.textContent = this.options[this.selectedIndex].text;
    });
  }

  // نمودار پای (کلاسیک، نه doughnut) — چند حالته، با دراپ‌دان بالای کارت
  (function(){
    var canvas = document.getElementById('statusPie');
    if (!canvas) return;

    var pieDatasets = {
      status:      { labels: labels, data: data, colors: palette },
      receivables: { labels: ['وصول‌شده', 'معوق'], data: [@json($stats['receivables_paid'] ?? 0), @json($stats['overdue_receivables'] ?? 0)], colors: ['#2f7d5b', '#b23a2c'] },
      winloss:     { labels: ['برنده', 'بازنده'], data: [@json($stats['won_count'] ?? 0), @json($stats['lost_count'] ?? 0)], colors: ['#2f7d5b', '#b23a2c'] },
      tasks:       { labels: ['انجام‌شده', 'انجام‌نشده'], data: [@json($stats['tasks_done'] ?? 0), @json($stats['tasks_not_done'] ?? 0)], colors: ['#2f7d5b', '#a9791b'] }
    };

    var pieChart = null;
    var hasDatalabels = typeof ChartDataLabels !== 'undefined';

    function renderPie(key){
      var cfg = pieDatasets[key] || pieDatasets.receivables;
      if (pieChart) { pieChart.destroy(); }
      var legend = document.getElementById('statusPieLegend');
      if (legend) {
        var sum = cfg.data.reduce(function(a,b){return a+Number(b);},0) || 1;
        legend.innerHTML = cfg.labels.map(function(lab, i){
          var pct = Math.round(cfg.data[i] * 1000 / sum) / 10;
          return '<span style="display:inline-flex;align-items:center;gap:4px"><span style="width:9px;height:9px;border-radius:50%;background:' + cfg.colors[i] + ';display:inline-block"></span>' + lab + ' (' + pct + '٪)</span>';
        }).join('');
      }
      pieChart = new Chart(canvas, {
        type: 'pie',
        data: {
          labels: cfg.labels,
          datasets: [{
            data: cfg.data,
            backgroundColor: cfg.colors,
            borderWidth: 0,
            borderColor: 'transparent'
          }]
        },
        options: {
          responsive: true,
          plugins: Object.assign({
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: function(ctx) {
                  var sum = ctx.dataset.data.reduce(function(a,b){return a+Number(b);},0) || 1;
                  var pct = Math.round(ctx.raw * 1000 / sum) / 10;
                  return ctx.label + ': ' + ctx.raw + ' (' + pct + '٪)';
                }
              }
            }
          }, hasDatalabels ? {
            datalabels: {
              color: '#fff',
              font: { family: 'Vazirmatn, Tahoma, sans-serif', weight: '700', size: 11 },
              formatter: function(value, ctx){
                var arr = ctx.chart.data.datasets[0].data;
                var sum = arr.reduce(function(a,b){return a+Number(b);},0) || 1;
                var pct = Math.round(value * 1000 / sum) / 10;
                return pct >= 4 ? (pct + '٪') : '';
              }
            }
          } : {})
        },
        plugins: hasDatalabels ? [ChartDataLabels] : []
      });
    }

    renderPie('winloss');
    var sel = document.getElementById('pieMetricSelect');
    if (sel) {
      sel.addEventListener('change', function(){
        renderPie(this.value);
        var t = document.getElementById('pieMetricTitle');
        if (t) t.textContent = this.options[this.selectedIndex].text;
      });
    }
  })();

  // نمودار پای «پرونده بر حسب صنعت مشتری» — باید بعد از بارگذاری Chart.js
  // اجرا شود (این بلوک داخل push('scripts') است، پس این ترتیب تضمین شده است)
  (function(){
    var el = document.getElementById('industryPie');
    if (!el) return;
    var indLabels = @json($stats['industry_labels'] ?? []);
    var indData = @json($stats['industry_counts'] ?? []);
    if (!indLabels.length) {
      el.parentNode.insertAdjacentHTML('beforeend', '<p class="text-sm text-muted text-center">داده‌ای برای صنعت ثبت نشده است.</p>');
      return;
    }
    var indColors = ['#b8703c','#33608e','#a9791b','#3a4149','#b23a2c','#6d737e','#2f7d5b','#d99b6c'];
    var indLegend = document.getElementById('industryPieLegend');
    if (indLegend) {
      var indSum = indData.reduce(function(a,b){return a+Number(b);},0) || 1;
      indLegend.innerHTML = indLabels.map(function(lab, i){
        var pct = Math.round(indData[i] * 1000 / indSum) / 10;
        var color = indColors[i % indColors.length];
        return '<span style="display:inline-flex;align-items:center;gap:4px"><span style="width:9px;height:9px;border-radius:50%;background:' + color + ';display:inline-block"></span>' + lab + ' (' + pct + '٪)</span>';
      }).join('');
    }
    new Chart(el, {
      type: 'pie',
      data: {
        labels: indLabels,
        datasets: [{
          data: indData,
          borderWidth: 0,
          borderColor: 'transparent',
          backgroundColor: indColors
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function(ctx) {
                var sum = ctx.dataset.data.reduce(function(a,b){return a+Number(b);},0) || 1;
                var pct = Math.round(ctx.raw * 1000 / sum) / 10;
                return ctx.label + ': ' + ctx.raw + ' (' + pct + '٪)';
              }
            }
          },
          tooltip: {
            callbacks: {
              label: function(ctx){
                var arr = ctx.dataset.data;
                var sum = arr.reduce(function(a,b){ return a + Number(b); }, 0) || 1;
                var pct = Math.round(ctx.raw * 1000 / sum) / 10;
                return ctx.label + ': ' + ctx.raw + ' (' + pct + '٪)';
              }
            }
          }
        }
      }
    });
  })();
})();
</script>
@endpush

@endsection
