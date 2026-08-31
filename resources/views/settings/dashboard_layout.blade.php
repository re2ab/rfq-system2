@extends('layouts.settings')
@section('title', 'مدیریت داشبورد')
@section('settings')
@if(session('success'))
<div class="card mb-4" style="border-color:var(--success)"><div class="card-b text-sm">{{ session('success') }}</div></div>
@endif
<div class="card">
  <div class="card-h">ترتیب و اندازهٔ ردیف‌های داشبورد</div>
  <div class="card-b text-sm">
    <p class="text-xs text-muted mb-3">هر آیتم را بکشید تا ترتیب نمایش در داشبورد تغییر کند؛ با دکمه‌های سمت راست هر آیتم عرض آن را انتخاب کنید.</p>
    <form method="POST" action="{{ route('settings.dashboard-layout.save') }}" id="dashLayoutForm">
      @csrf
      <div id="dashLayoutList">
        @foreach($layout as $item)
        <div class="dash-layout-row" draggable="true" data-key="{{ $item['key'] }}">
          <span class="dash-layout-handle" title="جابجایی">⠿</span>
          <span class="dash-layout-label">{{ $catalog[$item['key']] ?? $item['key'] }}</span>
          <div class="dash-layout-widths" role="group">
            @foreach([30=>'۳۰٪', 50=>'۵۰٪', 70=>'۷۰٪', 100=>'تمام عرض'] as $w => $wl)
              <button type="button" class="dash-w-btn {{ (int)$item['width']===$w ? 'active' : '' }}" data-width="{{ $w }}">{{ $wl }}</button>
            @endforeach
          </div>
          <div class="dash-layout-preview-box" style="--pw:{{ $item['width'] }}%"></div>
        </div>
        @endforeach
      </div>
      <p class="text-xs text-muted" style="margin-top:8px">پیش‌نمایش عرض نسبی هر آیتم در نوار رنگی سمت چپ همان ردیف نمایش داده می‌شود؛ ردیف‌هایی که پشت‌سرهم قرار می‌گیرند و مجموع عرضشان به ۱۰۰٪ برسد، در داشبورد کنار هم (هم‌ردیف) نشان داده می‌شوند.</p>
      <input type="hidden" name="items_json" id="dashLayoutJson">
      <div class="form-actions-sm" style="margin-top:16px">
        <button type="submit" class="btn btn-primary">ذخیره چیدمان</button>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function(){
  var list = document.getElementById('dashLayoutList');
  var form = document.getElementById('dashLayoutForm');
  var dragEl = null;

  list.querySelectorAll('.dash-layout-row').forEach(function(row){
    row.addEventListener('dragstart', function(){ dragEl = row; row.classList.add('dragging'); });
    row.addEventListener('dragend', function(){ dragEl = null; row.classList.remove('dragging'); });
    row.addEventListener('dragover', function(e){
      e.preventDefault();
      if (!dragEl || dragEl === row) return;
      var rect = row.getBoundingClientRect();
      var before = (e.clientY - rect.top) < rect.height / 2;
      row.parentNode.insertBefore(dragEl, before ? row : row.nextSibling);
    });
    row.querySelectorAll('.dash-w-btn').forEach(function(btn){
      btn.addEventListener('click', function(){
        row.querySelectorAll('.dash-w-btn').forEach(function(b){ b.classList.remove('active'); });
        btn.classList.add('active');
        var box = row.querySelector('.dash-layout-preview-box');
        if (box) box.style.setProperty('--pw', btn.dataset.width + '%');
      });
    });
  });

  form.addEventListener('submit', function(){
    var items = [];
    list.querySelectorAll('.dash-layout-row').forEach(function(row){
      var activeBtn = row.querySelector('.dash-w-btn.active') || row.querySelector('.dash-w-btn');
      items.push({ key: row.dataset.key, width: parseInt(activeBtn.dataset.width, 10) });
    });
    // به‌جای فیلد مخفی واحد، فیلدهای array استاندارد Laravel می‌سازیم
    document.querySelectorAll('.dash-layout-hidden-input').forEach(function(el){ el.remove(); });
    items.forEach(function(it, i){
      var k = document.createElement('input');
      k.type = 'hidden'; k.name = 'items['+i+'][key]'; k.value = it.key; k.className = 'dash-layout-hidden-input';
      var w = document.createElement('input');
      w.type = 'hidden'; w.name = 'items['+i+'][width]'; w.value = it.width; w.className = 'dash-layout-hidden-input';
      form.appendChild(k); form.appendChild(w);
    });
  });
})();
</script>
@endpush
