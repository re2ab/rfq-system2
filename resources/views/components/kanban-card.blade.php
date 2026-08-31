@props(['href' => null, 'title', 'subtitle' => null, 'meta' => []])
<{{ $href ? 'a' : 'div' }} @if($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'kanban-card']) }} style="display:block;color:inherit">
  <div class="kanban-card-title">{{ $title }}</div>
  @if($subtitle)<div class="kanban-card-sub">{{ $subtitle }}</div>@endif
  @if(count($meta) || isset($footer))
    <div class="kanban-card-meta">
      @foreach($meta as $m)<span>{{ $m }}</span>@endforeach
      @isset($footer){{ $footer }}@endisset
    </div>
  @endif
  {{ $slot }}
</{{ $href ? 'a' : 'div' }}>
