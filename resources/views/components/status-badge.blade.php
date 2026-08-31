@props(['status'])
@php
  $won = ['won','برنده','برنده شده'];
  $lost = ['lost','بازنده','بازنده شده','closed','بسته شده'];
  $stop = ['stopped','متوقف'];
  $s = (string)$status;
  $tone = 'default';
  if (in_array($s, $won, true) || str_contains($s, 'won') || str_contains($s, 'برنده')) $tone = 'ok';
  elseif (in_array($s, $lost, true) || str_contains($s, 'lost') || str_contains($s, 'بازنده')) $tone = 'danger';
  elseif (in_array($s, $stop, true) || str_contains($s, 'stop') || str_contains($s, 'متوقف')) $tone = 'warn';
  $label = \App\Models\CaseModel::statusLabels()[$s] ?? $s;
@endphp
<x-badge :tone="$tone">{{ $label }}</x-badge>
