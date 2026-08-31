@props(['title', 'subtitle' => null, 'count' => null])
<div {{ $attributes->merge(['class' => 'rfq-page-head']) }}>
  <div>
    <h2 class="rfq-page-title">{{ $title }}@if($count !== null)<span class="rfq-page-count">{{ $count }}</span>@endif</h2>
    @if($subtitle)<p class="rfq-page-sub">{{ $subtitle }}</p>@endif
  </div>
  <div class="rfq-page-actions">{{ $slot }}</div>
</div>
