@php
  $orgSelName = $name ?? 'organization_id';
  $orgSelSelected = $selected ?? null;
  $orgSelId = 'orgpick_' . uniqid();
@endphp
<div class="org-picker" data-picker>
  <div class="org-picker-search-wrap" style="display:none">
    <input type="text" class="org-picker-search" placeholder="جستجوی سازمان…" autocomplete="off">
  </div>
  <select name="{{ $orgSelName }}" id="{{ $orgSelId }}" class="org-picker-select">
    <option value="">— بدون سازمان —</option>
    @foreach(($organizations ?? []) as $org)
      <option value="{{ $org->id }}" @selected($orgSelSelected == $org->id)>{{ $org->name }}</option>
    @endforeach
  </select>
</div>
@once
<style>
.org-picker { position: relative; }
.org-picker-search-wrap { padding: 6px; border: 1px solid var(--border); border-bottom: 0; border-radius: var(--r-md) var(--r-md) 0 0; background: var(--surface); }
.org-picker.is-open .org-picker-search-wrap { display: block !important; }
.org-picker-search { width: 100%; height: 34px; border: 1px solid var(--border); border-radius: var(--r-sm); padding: 0 10px; font-size: 13px; box-sizing: border-box; }
</style>
<script>
(function(){
  function initPicker(root){
    var select = root.querySelector('.org-picker-select');
    var wrap = root.querySelector('.org-picker-search-wrap');
    var input = root.querySelector('.org-picker-search');
    if (!select || select.dataset.pickerReady) return;
    select.dataset.pickerReady = '1';
    var options = Array.prototype.slice.call(select.options);
    select.addEventListener('mousedown', function(){ root.classList.add('is-open'); input.focus(); });
    select.addEventListener('blur', function(){ setTimeout(function(){ root.classList.remove('is-open'); }, 150); });
    input.addEventListener('mousedown', function(e){ e.stopPropagation(); });
    input.addEventListener('keydown', function(e){ e.stopPropagation(); });
    input.addEventListener('input', function(){
      var term = input.value.trim().toLowerCase();
      options.forEach(function(opt, i){
        if (i === 0) return; // keep "بدون سازمان" always visible
        var match = opt.text.toLowerCase().indexOf(term) !== -1;
        opt.hidden = !match;
      });
    });
  }
  document.querySelectorAll('.org-picker').forEach(initPicker);
  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.org-picker').forEach(initPicker);
  });
})();
</script>
@endonce
