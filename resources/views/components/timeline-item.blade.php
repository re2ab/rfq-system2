@props(['type' => 'comment', 'author' => null, 'time' => null, 'title' => null])
@php
  $kind = in_array($type, ['call', 'email', 'status'], true) ? $type : '';
  $ini = $author ? mb_substr($author, 0, 1) : '•';
@endphp
<div {{ $attributes->merge(['class' => 'timeline-item']) }}>
  <div class="timeline-avatar {{ $kind }}">{{ $ini }}</div>
  <div class="timeline-content">
    @if($title)<div class="timeline-title">{{ $title }}</div>@endif
    <div class="timeline-meta">{{ $author }}@if($author && $time) • @endif{{ $time }}</div>
    <div class="timeline-body">{{ $slot }}</div>
    @isset($attachments)<div class="timeline-card">{{ $attachments }}</div>@endisset
  </div>
</div>
