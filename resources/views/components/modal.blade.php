@props(['id', 'title' => null, 'size' => 'md'])
{{-- Usage: <x-modal id="createCase" title="..."><form>...</form><x-slot:footer>...</x-slot:footer></x-modal>
     Open with:  <button data-modal-open="createCase"> --}}
@php $max = ['sm' => '400px', 'md' => '520px', 'lg' => '760px'][$size] ?? '520px'; @endphp
<div class="modal-backdrop" id="{{ $id }}" hidden data-modal role="dialog" aria-modal="true" aria-label="{{ $title }}">
  <div class="modal" style="max-width:{{ $max }}">
    <div class="modal-h">
      <span>{{ $title }}</span>
      <button type="button" class="rfq-icon-btn" data-modal-close aria-label="بستن">
        <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="modal-b">{{ $slot }}</div>
    @isset($footer)<div class="modal-f">{{ $footer }}</div>@endisset
  </div>
</div>
@once
@push('scripts')
<script>
(function () {
  function open(id) { var m = document.getElementById(id); if (m) { m.hidden = false; document.body.style.overflow = 'hidden'; } }
  function closeAll() { document.querySelectorAll('[data-modal]').forEach(function (m) { m.hidden = true; }); document.body.style.overflow = ''; }
  document.addEventListener('click', function (e) {
    var o = e.target.closest('[data-modal-open]');
    if (o) { e.preventDefault(); open(o.getAttribute('data-modal-open')); return; }
    if (e.target.closest('[data-modal-close]') || e.target.matches('[data-modal]')) closeAll();
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeAll(); });
})();
</script>
@endpush
@endonce
