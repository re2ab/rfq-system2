@props(['label' => null, 'icon' => false, 'align' => 'end', 'variant' => 'secondary', 'size' => 'sm'])
{{-- Row / page action menu. Slot holds <a> / <button> items.
     وقتی label دارد (یعنی خودش یک دکمه‌ی معمولی با متن است، نه یک آیکون
     «سه‌نقطه»ی کنار ردیف)، فلش رو‌به‌پایین نشان می‌دهد تا شبیه بقیه‌ی دکمه‌های
     صفحه باشد ولی معنی «این یک منوی کشویی است» را هم برساند؛ حالت icon-only
     (بدون label) همان آیکون سه‌نقطه‌ی قبلی را نگه می‌دارد. --}}
<div class="dropdown" data-dropdown>
  <button type="button" class="btn btn-{{ $variant }}{{ $size === 'sm' ? ' btn-sm' : ($size === 'lg' ? ' btn-lg' : '') }} {{ $icon ? 'btn-icon' : '' }}" data-dropdown-toggle aria-haspopup="menu" aria-expanded="false">
    @if($label){{ $label }}@endif
    @if($label)
      <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
    @else
      <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/></svg>
    @endif
  </button>
  <div class="dropdown-menu" role="menu" style="inset-inline-{{ $align }}:0">{{ $slot }}</div>
</div>
@once
@push('scripts')
<script>
(function () {
  // M21: از این به بعد این کامپوننت داخل ردیف‌های جدول (فهرست اسناد) هم
  // استفاده می‌شود — یعنی ممکن است داخل والدهایی با overflow:hidden/auto
  // (مثلاً .card یا .data-table-desktop، برای گردکردن گوشه‌ها/اسکرول افقی)
  // قرار بگیرد که menu باز‌شونده‌ی position:absolute را می‌بُرند و نامرئی
  // می‌کنند. تا زمانی که یک منو باز است، overflow آن والدها موقتاً به
  // visible تغییر می‌کند و با بسته‌شدن منو به حالت قبل برمی‌گردد.
  var patchedAncestors = [];

  function restoreAncestors() {
    patchedAncestors.forEach(function (p) { p.node.style.overflow = p.prev; });
    patchedAncestors = [];
  }

  function patchAncestors(dropdownEl) {
    var node = dropdownEl.parentElement;
    while (node && node !== document.body) {
      var cs = window.getComputedStyle(node);
      if (cs.overflowX !== 'visible' || cs.overflowY !== 'visible') {
        patchedAncestors.push({ node: node, prev: node.style.overflow });
        node.style.overflow = 'visible';
      }
      node = node.parentElement;
    }
  }

  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-dropdown-toggle]');
    var targetMenu = t ? t.parentElement.querySelector('.dropdown-menu') : null;
    var wasOpen = targetMenu ? targetMenu.classList.contains('open') : false;

    document.querySelectorAll('.dropdown-menu.open').forEach(function (m) {
      m.classList.remove('open');
      var toggle = m.parentElement.querySelector('[data-dropdown-toggle]');
      if (toggle) toggle.setAttribute('aria-expanded', 'false');
    });
    restoreAncestors();

    if (t && !wasOpen) {
      e.preventDefault();
      targetMenu.classList.add('open');
      t.setAttribute('aria-expanded', 'true');
      patchAncestors(t.closest('.dropdown'));
    } else if (t) {
      e.preventDefault();
    }
  });
})();
</script>
@endpush
@endonce
