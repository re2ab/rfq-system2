@extends('layouts.settings')
@section('title', 'قوانین انتقال پایپ‌لاین')
@section('actions')
  <x-btn variant="ghost" href="{{ route('settings.pipeline') }}">مراحل</x-btn>
@endsection
@section('settings')
@if(session('success'))
<div class="card mb-3" style="padding:12px;background:var(--success-soft);color:var(--success)">{{ session('success') }}</div>
@endif

<div class="card mb-4">
  <div class="card-b text-sm" style="color:var(--muted)">
    برای هر مرحله مبدأ مشخص کنید به کدام مراحل می‌توان رفت و در صورت نیاز یک <strong>شرط</strong> بگذارید.
    مثال: از «دریافت مطالبات» به «بسته» با شرط «مطالبات کامل وصول شده باشد».
  </div>
</div>

<form method="POST" action="{{ route('settings.transitions.save') }}">@csrf
@php $i = 0; @endphp
@foreach($stages as $from)
@php
  $fromKey = is_object($from) ? $from->key : $from;
  $fromLabel = is_object($from) ? ($from->label ?? $fromKey) : $from;
@endphp
<details class="card mb-3 settings-collapse">
  <summary class="card-h" style="display:flex;justify-content:space-between;align-items:center">
    <span>از: {{ $fromLabel }} <code style="font-size:11px;opacity:.7">{{ $fromKey }}</code></span>
  </summary>
  <div class="card-b text-sm">
    <div style="display:grid;gap:8px">
      @foreach($stages as $to)
      @php
        $toKey = is_object($to) ? $to->key : $to;
        $toLabel = is_object($to) ? ($to->label ?? $toKey) : $to;
        if ($fromKey === $toKey) continue;
        $k = $fromKey.'|'.$toKey;
        $row = $transitions[$k] ?? null;
        $allowed = $row ? $row->is_allowed : false;
        $cond = $row->condition_code ?? '';
      @endphp
      <div class="rfq-grid-rowform" style="gap:10px;align-items:center;padding:8px;border:1px solid var(--border-soft);border-radius:10px">
        <label class="flex items-center gap-2 whitespace-nowrap">
          <input type="hidden" name="edges[{{ $i }}][from]" value="{{ $fromKey }}">
          <input type="hidden" name="edges[{{ $i }}][to]" value="{{ $toKey }}">
          <input type="checkbox" name="edges[{{ $i }}][allowed]" value="1" @checked($allowed)>
          به {{ $toLabel }}
        </label>
        <select name="edges[{{ $i }}][condition]" class="border rounded px-2 py-1.5 text-xs">
          @foreach($conditions as $code => $lab)
            <option value="{{ $code }}" @selected($cond === $code || ($cond === null && $code === ''))>{{ $lab }}</option>
          @endforeach
        </select>
        <span class="muted text-xs">{{ $toKey }}</span>
      </div>
      @php $i++; @endphp
      @endforeach
    </div>
  </div>
</details>
@endforeach
<button type="submit" class="btn btn-primary">ذخیره همه قوانین</button>
</form>
@endsection
