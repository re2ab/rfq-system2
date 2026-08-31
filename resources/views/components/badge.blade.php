@props(['tone' => 'default', 'dot' => true]) {{-- default|ok|warn|danger|info|brand|muted --}}
@php
  $cls = match($tone) {
    'ok', 'success' => 'badge badge-ok',
    'warn', 'warning' => 'badge badge-warn',
    'danger', 'error' => 'badge badge-danger',
    'info' => 'badge badge-info',
    'brand' => 'badge badge-brand',
    'muted' => 'badge badge-muted',
    default => 'badge',
  };
  if (! $dot) $cls .= ' badge-plain';
@endphp
<span {{ $attributes->merge(['class' => $cls]) }}>{{ $slot }}</span>
