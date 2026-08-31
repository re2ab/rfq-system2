@props(['action' => null, 'reset' => null, 'label' => 'اعمال فیلتر'])
<form method="GET" @if($action) action="{{ $action }}" @endif {{ $attributes->merge(['class' => 'filter-bar']) }}>
  {{ $slot }}
  <button type="submit" class="btn btn-secondary btn-sm">{{ $label }}</button>
  @if($reset)
    <a href="{{ $reset }}" class="btn btn-ghost btn-sm">حذف فیلترها</a>
  @endif
</form>
