@props(['title' => 'موردی یافت نشد', 'action' => null, 'actionLabel' => 'ایجاد', 'icon' => 'box'])
@php
  $svgs = [
    'box' => '<path d="M20 13V7a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v6"/><path d="M12 17v-4"/><rect x="2" y="13" width="20" height="8" rx="2"/>',
    'search' => '<circle cx="11" cy="11" r="8"/><path d="M21 21l-4.3-4.3"/>',
    'lock' => '<rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
    'offline' => '<path d="M1 1l22 22"/><path d="M16.7 13.7A5 5 0 0 0 12 10"/><path d="M5 12.5a9 9 0 0 1 3-2.2"/><path d="M12 20h.01"/>',
  ];
@endphp
<div {{ $attributes->merge(['class' => 'empty-state']) }}>
  <div class="empty-ico" aria-hidden="true">
    <svg viewBox="0 0 24 24">{!! $svgs[$icon] ?? $svgs['box'] !!}</svg>
  </div>
  <div class="empty-title">{{ $title }}</div>
  @if(trim($slot) !== '')<div class="empty-desc">{{ $slot }}</div>@endif
  @if($action)
    <a href="{{ $action }}" class="btn btn-primary btn-sm" style="margin-top:12px">{{ $actionLabel }}</a>
  @endif
</div>
