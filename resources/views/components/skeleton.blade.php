@props(['lines' => 3])
<div class="skeleton-wrap" aria-hidden="true" aria-busy="true">
  @for($i = 0; $i < (int) $lines; $i++)
    <div class="skeleton-line" style="width:{{ 100 - (($i * 13) % 35) }}%"></div>
  @endfor
</div>
