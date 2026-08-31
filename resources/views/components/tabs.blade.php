@props(['items' => [], 'current' => null])
{{-- $items: [ ['label' => '...', 'href' => '...', 'key' => 'overview', 'count' => 3], ... ] --}}
<div class="rfq-tabs" role="tablist">
  @foreach($items as $item)
    @php $active = ($item['key'] ?? null) === $current; @endphp
    <a href="{{ $item['href'] ?? '#' }}" class="rfq-tab {{ $active ? 'active' : '' }}" role="tab" aria-selected="{{ $active ? 'true' : 'false' }}">
      {{ $item['label'] ?? '' }}
      @if(isset($item['count']))<span class="rfq-page-count">{{ $item['count'] }}</span>@endif
    </a>
  @endforeach
</div>
