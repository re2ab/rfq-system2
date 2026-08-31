@props(['priority' => null, 'scope' => 'task'])
@php
  $meta = function_exists('priority_badge_style')
    ? priority_badge_style($priority, $scope)
    : ['label' => $priority ?: '—', 'color' => '#64748b'];
  $label = $meta['label'] ?? '—';
  $color = $meta['color'] ?? '#64748b';
@endphp
<span {{ $attributes->merge(['class' => 'badge']) }}
  style="background:{{ $color }}22;color:{{ $color }};border:1px solid {{ $color }}55;font-weight:700">
  {{ $label }}
</span>
