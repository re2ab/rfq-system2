@props(['title' => null, 'subtitle' => null, 'pad' => true, 'hover' => false])
<div {{ $attributes->merge(['class' => 'card'.($hover ? ' card-hover' : '')]) }}>
  @if($title || isset($actions))
    <div class="card-h">
      <span>
        {{ $title }}
        @if($subtitle)<small style="display:block;font-weight:500;color:var(--muted)">{{ $subtitle }}</small>@endif
      </span>
      @isset($actions)<span style="display:flex;align-items:center;gap:8px">{{ $actions }}</span>@endisset
    </div>
  @endif
  <div class="{{ $pad ? 'card-b' : 'card-b pad0' }}">{{ $slot }}</div>
  @isset($footer)<div class="card-f">{{ $footer }}</div>@endisset
</div>
