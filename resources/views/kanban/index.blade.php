@extends('layouts.app')
@section('title', 'پایپ‌لاین (کانبان)')
@section('actions')
  <x-btn href="{{ route('cases.create') }}">پرونده جدید</x-btn>
@endsection

@section('content')

<form method="GET" action="{{ route('kanban.index') }}" class="rfq-filters">
  <div class="rfq-filters-search">
    <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="جستجوی شماره، عنوان، مشتری…" class="rfq-f-input" autocomplete="off">
    <button type="submit" class="btn btn-primary btn-sm rfq-f-btn">جستجو</button>
  </div>
  <div class="rfq-filters-meta">
    <select name="tag_id" class="rfq-f-select" size="1" style="height:40px;max-height:40px;min-height:40px">
      <option value="">همه تگ‌ها</option>
      @foreach(($tags ?? []) as $tag)
        <option value="{{ $tag->id }}" @selected(($tagId ?? request('tag_id')) == $tag->id)>{{ $tag->name }}</option>
      @endforeach
    </select>
    <button type="submit" class="btn btn-primary btn-sm rfq-f-btn">فیلتر</button>
  </div>
</form>


<div class="kanban-wrap">
<div id="kanban-board">
  @foreach($columns as $status => $cases)
  <div class="kanban-col" data-status="{{ $status }}">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-shrink:0">
      <span style="font-weight:800;font-size:13px;color:var(--text)">{{ $statusLabels[$status] ?? $status }}</span>
      <span class="badge count">{{ $cases->count() }}</span>
    </div>
    <div class="column-dropzone" data-status="{{ $status }}">
      @foreach($cases as $case)
      @php
        $searchBlob = mb_strtolower(($case->case_number ?? '').' '.($case->title ?? '').' '.($case->customer->name ?? ''));
      @endphp
      <div class="case-card" draggable="true" data-case-id="{{ $case->id }}" data-current-status="{{ $status }}"
           data-search="{{ e($searchBlob) }}"
           style="background:var(--surface);border:1px solid var(--border-soft);border-radius:12px;padding:12px;cursor:grab;box-shadow:var(--shadow-sm);flex-shrink:0">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px">
          <a href="{{ route('cases.show', $case) }}" onclick="event.stopPropagation()" style="font-weight:800;color:var(--brand);text-decoration:none">
            {{ $case->case_number }}
          </a>
          <x-user-avatars :users="$case->allAssignees()" :size="22" />
        </div>
        <div style="margin-top:4px;font-size:13px;color:var(--text);font-weight:700">{{ \Illuminate\Support\Str::limit($case->title, 48) }}</div>
        <div class="kanban-card-meta" style="margin-top:6px;font-size:11px;color:var(--muted);display:flex;flex-wrap:wrap;gap:6px;align-items:center">
          @if($case->customer)
            <span>{{ \Illuminate\Support\Str::limit($case->customer->name, 20) }}</span>
          @endif
          @foreach($case->tags as $tag)
            <span style="background:{{ $tag->color }}22;color:{{ $tag->color }};padding:1px 7px;border-radius:999px;font-weight:700">{{ $tag->name }}</span>
          @endforeach
        </div>
        @if($case->updated_at)
          <div style="margin-top:6px;font-size:10px;color:var(--muted)">آخرین فعالیت: {{ $case->updated_at->diffForHumans() }}</div>
        @endif
      </div>
      @endforeach
    </div>
  </div>
  @endforeach
</div>
</div>
<div id="kanbanToast"></div>

@push('scripts')
<script>
(function(){
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
  let dragged = null;
  let originParent = null;
  let originNext = null;

  function toast(msg, err) {
    const el = document.getElementById('kanbanToast');
    if (!el) return;
    el.textContent = msg;
    el.className = err ? 'err' : '';
    el.style.display = 'block';
    clearTimeout(el._t);
    el._t = setTimeout(() => { el.style.display = 'none'; }, 2200);
  }
  function refreshCounts() {
    document.querySelectorAll('.kanban-col').forEach(col => {
      const badge = col.querySelector('.count');
      if (badge) badge.textContent = col.querySelectorAll('.case-card').length;
    });
  }

  document.querySelectorAll('.case-card').forEach(card => {
    card.addEventListener('dragstart', e => {
      dragged = card;
      originParent = card.parentElement;
      originNext = card.nextElementSibling;
      card.classList.add('dragging');
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', card.dataset.caseId);
    });
    card.addEventListener('dragend', () => {
      if (dragged) dragged.classList.remove('dragging');
      document.querySelectorAll('.column-dropzone').forEach(z => z.classList.remove('drag-over'));
    });
  });

  document.querySelectorAll('.column-dropzone').forEach(zone => {
    zone.addEventListener('dragover', e => {
      e.preventDefault();
      e.dataTransfer.dropEffect = 'move';
      zone.classList.add('drag-over');
    });
    zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
    zone.addEventListener('drop', async e => {
      e.preventDefault();
      zone.classList.remove('drag-over');
      if (!dragged) return;
      const caseId = dragged.dataset.caseId;
      const newStatus = zone.dataset.status;
      const oldStatus = dragged.dataset.currentStatus;
      if (newStatus === oldStatus) return;

      // آنی: جابه‌جایی بصری بدون confirm
      zone.appendChild(dragged);
      dragged.dataset.currentStatus = newStatus;
      dragged.classList.remove('dragging');
      refreshCounts();

      try {
        const res = await fetch(`/kanban/${caseId}/move`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
          body: JSON.stringify({ status: newStatus, is_override: false })
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
          // برگشت
          if (originNext && originParent) originParent.insertBefore(dragged, originNext);
          else if (originParent) originParent.appendChild(dragged);
          dragged.dataset.currentStatus = oldStatus;
          refreshCounts();
          toast(data.message || 'تغییر وضعیت ناموفق بود', true);
        } else {
          toast('وضعیت به‌روز شد');
        }
      } catch (err) {
        if (originNext && originParent) originParent.insertBefore(dragged, originNext);
        else if (originParent) originParent.appendChild(dragged);
        dragged.dataset.currentStatus = oldStatus;
        refreshCounts();
        toast('خطای شبکه', true);
      }
      dragged = null;
    });
  });

  const input = document.getElementById('kanbanSearch');
  if (input) {
    input.addEventListener('input', () => {
      const q = (input.value || '').trim().toLowerCase();
      document.querySelectorAll('.case-card').forEach(card => {
        const blob = card.getAttribute('data-search') || '';
        card.style.display = (!q || blob.includes(q)) ? '' : 'none';
      });
    });
  }
})();
</script>
@endpush
@endsection
