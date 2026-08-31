@props(['compact' => false])
{{--
  Enterprise data table wrapper.
  Slots: $toolbar (search/filters/bulk actions), default (thead/tbody), $mobileCards, $footer (pagination)
--}}
<div {{ $attributes->merge(['class' => 'card data-table-wrap']) }}>
  @isset($toolbar)
    <div class="table-toolbar">{{ $toolbar }}</div>
  @endisset
  <div class="data-table-desktop">
    <table class="tbl {{ $compact ? 'tbl-compact' : '' }}">{{ $slot }}</table>
  </div>
  @isset($mobileCards)
    <div class="data-table-mobile">{{ $mobileCards }}</div>
  @endisset
  @isset($footer)
    <div class="card-f">{{ $footer }}</div>
  @endisset
</div>
