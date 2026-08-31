@props(['href' => 'javascript:history.back()','label' => 'بازگشت'])
<a href="{{ $href }}" {{ $attributes->merge(['class' => 'btn btn-ghost btn-sm']) }} style="display:inline-flex;align-items:center;gap:6px">
  <span aria-hidden="true">←</span> {{ $label }}
</a>
