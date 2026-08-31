@props(['label', 'value', 'sub' => null, 'tone' => 'default'])
@php
  $tone = $tone ?: 'default';
@endphp
<div class="stat stat-tone-{{ $tone }}" data-tone="{{ $tone }}">
  <div class="lbl">{{ $label }}</div>
  <div class="num">{{ $value }}</div>
  @if($sub)<div class="sub">{{ $sub }}</div>@endif
</div>
