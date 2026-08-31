@once
<link rel="stylesheet" href="https://unpkg.com/@majidh1/jalalidatepicker/dist/jalalidatepicker.min.css">
<script src="https://unpkg.com/@majidh1/jalalidatepicker/dist/jalalidatepicker.min.js"></script>
<script>
(function(){
  function faToEn(s){
    return String(s||'').replace(/[۰-۹]/g,function(d){return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d);})
      .replace(/[٠-٩]/g,function(d){return '٠١٢٣٤٥٦٧٨٩'.indexOf(d);});
  }
  function jalaliToGregorian(jy, jm, jd){
    jy=+jy; jm=+jm; jd=+jd;
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
  window.rfqSyncJalaliInput = function(input){
    var name = input.getAttribute('data-hidden-name');
    if (!name) return;
    var wrap = input.closest('.jalali-date-wrap') || input.parentElement;
    var hidden = wrap ? wrap.querySelector('input[type=hidden][name="'+name+'"]') : null;
    if (!hidden) {
      hidden = input.form && input.form.querySelector('input[type=hidden][name="'+name+'"]');
    }
    if (!hidden) return;
    var raw = faToEn(input.value).trim().replace(/-/g,'/');
    var isDt = input.getAttribute('data-datetime') === '1';
    var m = raw.match(/^(\d{4})\/(\d{1,2})\/(\d{1,2})(?:\s+(\d{1,2}):(\d{1,2}))?/);
    if (m) {
      try {
        var g = jalaliToGregorian(m[1], m[2], m[3]);
        if (false && isDt) { // RFQ: همیشه فقط تاریخ
          var hh = String(m[4]||'0').padStart(2,'0');
          var mi = String(m[5]||'0').padStart(2,'0');
          hidden.value = g + 'T' + hh + ':' + mi;
        } else {
          hidden.value = g;
        }
      } catch(e) { hidden.value = ''; }
    } else if (!raw) {
      hidden.value = '';
    }
  };
  function bindAll(){
    document.querySelectorAll('input.jdp-input, input[data-jdp]').forEach(function(inp){
      if (inp._jdpBound) return;
      inp._jdpBound = true;
      inp.addEventListener('change', function(){ window.rfqSyncJalaliInput(inp); });
      inp.addEventListener('blur', function(){ window.rfqSyncJalaliInput(inp); });
    });
    document.querySelectorAll('form').forEach(function(form){
      if (form._jdpSubmit) return;
      form._jdpSubmit = true;
      form.addEventListener('submit', function(){
        form.querySelectorAll('input.jdp-input, input[data-jdp]').forEach(window.rfqSyncJalaliInput);
      });
    });
  }
  function startPicker(){
    if (!window.jalaliDatepicker) return;
    try {
      jalaliDatepicker.startWatch({
        selector: 'input[data-jdp]',
        time: false,
        autoShow: true,
        autoHide: true,
        hideAfterChange: true,
        showTodayBtn: true,
        showEmptyBtn: true,
        persianDigits: true,
        zIndex: 999999
      });
    } catch(e) {}
    bindAll();
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function(){ setTimeout(startPicker, 50); });
  } else {
    setTimeout(startPicker, 50);
  }
})();
</script>
<style>
  .jdp-container, .jalali-datepicker, input[data-jdp], input.jdp-input {
    font-family: Vazirmatn, Tahoma, sans-serif !important;
  }
</style>
@endonce
