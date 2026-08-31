@props([
  'name' => 'datetime',
  'value' => null,
  'required' => false,
  'class' => 'w-full border rounded px-3 py-2 text-sm',
  'placeholder' => '۱۴۰۳/۰۱/۰۱',
])
@php
  $g = '';
  $j = '';
  try {
    if ($value) {
      // فقط بخش تاریخ — بدون جابه‌جایی timezone
      $raw = is_string($value) ? $value : (string) $value;
      if ($value instanceof \DateTimeInterface) {
        $g = $value->format('Y-m-d');
      } else {
        $raw = preg_replace('/[T\s].*$/', '', trim($raw));
        $g = $raw;
        try {
          $g = \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {}
      }
      if (function_exists('jdate')) {
        $j = jdate($g . ' 12:00:00', 'Y/m/d');
      } elseif (class_exists(\Morilog\Jalali\Jalalian::class)) {
        $j = \Morilog\Jalali\Jalalian::fromDateTime($g . ' 12:00:00')->format('Y/m/d');
      } else {
        $j = $g;
      }
    }
  } catch (\Throwable $e) {}
@endphp
<span class="jalali-date-wrap" style="display:block;width:100%">
  <input type="text" data-jdp data-jdp-only-date="true" autocomplete="off" placeholder="{{ $placeholder }}"
         value="{{ $j }}" @if($required) required @endif
         class="{{ $class }} jdp-input" style="font-family:Vazirmatn,Tahoma,sans-serif;direction:ltr;text-align:center"
         data-hidden-name="{{ $name }}" data-datetime="0">
  <input type="hidden" name="{{ $name }}" value="{{ $g }}" @if($required) data-jdp-required="1" @endif>
</span>
