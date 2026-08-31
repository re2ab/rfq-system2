@props(['type' => 'success']) {{-- success|error|warning|info --}}
@php
  $t = match($type) { 'error', 'danger' => 'error', 'warning' => 'warning', 'info' => 'info', default => 'success' };
@endphp
<div {{ $attributes->merge(['class' => 'alert alert-'.$t]) }} role="{{ $t === 'error' ? 'alert' : 'status' }}">{{ $slot }}</div>
