@props([
  'variant' => 'primary',   {{-- primary|secondary|soft|ghost|danger|danger-soft --}}
  'size' => 'md',           {{-- sm|md|lg --}}
  'type' => 'button',
  'href' => null,
  'icon' => null,           {{-- true => icon-only square button --}}
  'block' => false,
])
@php
  $classes = 'btn btn-'.($variant ?: 'primary');
  if ($size === 'sm') $classes .= ' btn-sm';
  if ($size === 'lg') $classes .= ' btn-lg';
  if ($icon) $classes .= ' btn-icon';
  if ($block) $classes .= ' btn-block';
@endphp
@if($href)
  <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
  <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
