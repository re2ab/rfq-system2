@props([
  'name' => 'due_at',
  'value' => null,
  'required' => false,
  'class' => 'w-full border rounded px-3 py-2 text-sm',
  'placeholder' => '۱۴۰۳/۰۱/۰۱',
])
@php
  $display = function_exists('jdate_input') ? jdate_input($value) : '';
  $g = '';
  try {
    if ($value) {
      if ($value instanceof \DateTimeInterface) {
        $g = $value->format('Y-m-d');
      } else {
        try { $g = \Carbon\Carbon::parse($value)->format('Y-m-d'); } catch (\Throwable $e) {
          $g = preg_replace('/[T\s].*$/', '', (string)$value);
        }
      }
    }
  } catch (\Throwable $e) {}
@endphp
<span class="jalali-date-wrap" style="display:block;width:100%">
  <input type="text" name="{{ $name }}_j" data-jdp data-jdp-only-date="true" autocomplete="off"
         placeholder="{{ $placeholder }}" value="{{ $display }}"
         @if($required) required @endif
         class="{{ $class }} jdp-input"
         style="font-family:Vazirmatn,Tahoma,sans-serif;direction:ltr;text-align:center"
         data-hidden-name="{{ $name }}" data-datetime="0">
  <input type="hidden" name="{{ $name }}" value="{{ $g }}">
</span>
