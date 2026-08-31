@php
  if (!function_exists('rfq_fa_digits')) {
    function rfq_fa_digits($n) {
      return strtr((string) $n, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']);
    }
  }
  if (!function_exists('rfq_jalali')) {
    function rfq_jalali($dt, $fmt = 'Y/m/d') {
      if (!$dt) return '—';
      try {
        $j = \Morilog\Jalali\Jalalian::fromCarbon(\Illuminate\Support\Carbon::parse($dt));
        return rfq_fa_digits($j->format($fmt));
      } catch (\Throwable $e) {
        return rfq_fa_digits(\Illuminate\Support\Carbon::parse($dt)->format('Y/m/d'));
      }
    }
  }
@endphp
