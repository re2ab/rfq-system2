@php
  $fromVal = '';
  $toVal = '';
  $fromG = request('from', '');
  $toG = request('to', '');
  try {
    if (isset($from) && $from) { $fromG = $from->format('Y-m-d'); $fromVal = jdate_input($from); }
    elseif ($fromG) { $fromVal = jdate_input($fromG); }
    if (isset($to) && $to) { $toG = $to->format('Y-m-d'); $toVal = jdate_input($to); }
    elseif ($toG) { $toVal = jdate_input($toG); }
  } catch (\Throwable $e) {}
@endphp
<form method="GET" class="filter-bar jalali-filter-bar" style="margin-bottom:12px;gap:8px;align-items:center;display:flex;flex-wrap:wrap;row-gap:10px">
  <span style="display:flex;align-items:center;gap:8px;flex-wrap:nowrap">
    <label class="text-sm font-semibold" style="white-space:nowrap">از تاریخ</label>
    <input type="text" class="filter-input jdp" data-jdp autocomplete="off" placeholder="۱۴۰۳/۰۱/۰۱"
           value="{{ $fromVal }}" style="min-width:130px;max-width:160px;height:36px;flex-shrink:0;font-family:Vazirmatn,tahoma,sans-serif"
           data-hidden="from">
    <input type="hidden" name="from" value="{{ $fromG }}">
  </span>

  <span style="display:flex;align-items:center;gap:8px;flex-wrap:nowrap">
    <label class="text-sm font-semibold" style="white-space:nowrap">تا تاریخ</label>
    <input type="text" class="filter-input jdp" data-jdp autocomplete="off" placeholder="۱۴۰۳/۱۲/۲۹"
           value="{{ $toVal }}" style="min-width:130px;max-width:160px;height:36px;flex-shrink:0;font-family:Vazirmatn,tahoma,sans-serif"
           data-hidden="to">
    <input type="hidden" name="to" value="{{ $toG }}">
  </span>

  @isset($extra)
    {{ $extra }}
  @endisset
  <button type="submit" class="btn btn-primary btn-sm" style="height:36px;flex-shrink:0;margin-inline-start:auto">اجرای گزارش</button>
</form>
@once
<link rel="stylesheet" href="https://unpkg.com/@majidh1/jalalidatepicker@1.0.0/dist/jalalidatepicker.min.css">
<script src="https://unpkg.com/@majidh1/jalalidatepicker@1.0.0/dist/jalalidatepicker.min.js"></script>
<script>
(function(){
  function faToEn(s){
    return String(s||'').replace(/[۰-۹]/g,function(d){return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)})
      .replace(/[٠-٩]/g,function(d){return '٠١٢٣٤٥٦٧٨٩'.indexOf(d)});
  }
  function jalaliToGregorian(jy, jm, jd){
    jy = parseInt(jy,10); jm = parseInt(jm,10); jd = parseInt(jd,10);
    var gy, gm, gd, days;
    jy -= 979; jm -= 1; jd -= 1;
    days = 365*jy + Math.floor(jy/33)*8 + Math.floor(((jy%33)+3)/4);
    for (var i=0;i<jm;++i) days += [31,31,31,31,31,31,30,30,30,30,30,29][i];
    days += jd;
    gy = 1600 + 400*Math.floor(days/146097);
    days %= 146097;
    if (days > 36524) { gy += 100*Math.floor(--days/36524); days %= 36524; if (days >= 365) days++; }
    gy += 4*Math.floor(days/1461); days %= 1461;
    if (days > 365) { gy += Math.floor((days-1)/365); days = (days-1)%365; }
    gd = days + 1;
    var sal_a = [0,31,(gy%4===0&&gy%100!==0)||gy%400===0?29:28,31,30,31,30,31,31,30,31,30,31];
    for (gm=1; gm<13 && gd>sal_a[gm]; gm++) gd -= sal_a[gm];
    return gy + '-' + String(gm).padStart(2,'0') + '-' + String(gd).padStart(2,'0');
  }
  function syncHidden(input){
    var name = input.getAttribute('data-hidden');
    if (!name) return;
    var hidden = input.form.querySelector('input[type=hidden][name="'+name+'"]');
    if (!hidden) return;
    var v = faToEn(input.value).trim().replace(/-/g,'/');
    var m = v.match(/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/);
    if (m) {
      try { hidden.value = jalaliToGregorian(m[1], m[2], m[3]); }
      catch(e) { hidden.value = ''; }
    } else if (!v) {
      hidden.value = '';
    }
  }
  document.querySelectorAll('input.jdp').forEach(function(inp){
    inp.addEventListener('change', function(){ syncHidden(inp); });
    inp.addEventListener('blur', function(){ syncHidden(inp); });
  });
  // مقدار رندرشده‌ی سرور (شمسی، بدون ساعت) را قبل از راه‌اندازی کتابخانه
  // نگه می‌داریم — اگر startWatch هنگام مقداردهی اولیه، مقدار فیلد را با
  // فرمت داخلی خودش (که می‌تواند ISO/با ساعت باشد) جایگزین کند، همان لحظه
  // برش می‌گردانیم تا کاربر هرگز مقدار خام/اشتباه را نبیند.
  var jdpOriginalValues = new WeakMap();
  document.querySelectorAll('input[data-jdp]').forEach(function(inp){
    jdpOriginalValues.set(inp, inp.value);
  });
  if (window.jalaliDatepicker) {
    jalaliDatepicker.startWatch({
      selector: 'input[data-jdp]',
      time: false,
      hasSecond: false,
      hideAfterChange: true,
      autoShow: true,
      autoHide: true,
      showTodayBtn: true,
      showEmptyBtn: true,
      zIndex: 999999,
      persianDigits: true
    });
    document.querySelectorAll('input[data-jdp]').forEach(function(inp){
      var orig = jdpOriginalValues.get(inp);
      if (orig && inp.value !== orig) { inp.value = orig; }
      inp.addEventListener('jdp:change', function(){ syncHidden(inp); });
    });
  }
  document.querySelectorAll('.jalali-filter-bar').forEach(function(form){
    form.addEventListener('submit', function(){
      form.querySelectorAll('input.jdp').forEach(syncHidden);
    });
  });
})();
</script>
@endonce
