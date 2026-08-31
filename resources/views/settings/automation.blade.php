@extends('layouts.settings')
@section('title', 'اتوماسیون')
@section('actions')
  <form method="POST" action="{{ route('settings.automation.run') }}" class="inline">@csrf
    <button class="btn btn-ghost btn-sm">اجرای دستی قوانین عدم‌فعالیت</button>
  </form>
@endsection
@section('settings')
@if(session('success'))
<div class="card mb-3" style="padding:12px;background:var(--success-soft);color:var(--success)">{{ session('success') }}</div>
@endif
<div class="case-row-50">
<div class="card">
  <div class="card-h">قوانین موجود</div>
  <div class="card-b pad0">
    @forelse($rules as $rule)
    <div class="rel-item flex justify-between items-center gap-2">
      <div>
        <div class="font-semibold">{{ $rule->name }}</div>
        <div class="rel-meta">
          @if($rule->trigger === 'inactive_days')
            بدون فعالیت {{ $rule->conditions['days'] ?? '?' }} روز
          @else
            تغییر وضعیت
            @if(!empty($rule->conditions['to_status'])) → {{ $statuses[$rule->conditions['to_status']] ?? $rule->conditions['to_status'] }} @endif
          @endif
          · {{ $rule->action }} · {{ $rule->is_active ? 'فعال' : 'غیرفعال' }}
        </div>
      </div>
      <div class="flex gap-2">
        <form method="POST" action="{{ route('settings.automation.toggle', $rule) }}">@csrf
          <button class="text-xs">{{ $rule->is_active ? 'غیرفعال' : 'فعال' }}</button>
        </form>
        <form method="POST" action="{{ route('settings.automation.destroy', $rule) }}" onsubmit="return confirm('حذف؟')">@csrf @method('DELETE')
          <button class="text-xs text-red-600">حذف</button>
        </form>
      </div>
    </div>
    @empty
    <div style="padding:16px"><x-empty title="قانونی تعریف نشده" /></div>
    @endforelse
  </div>
</div>
<div class="card">
  <div class="card-h">قانون جدید</div>
  <div class="card-b text-sm">
    <form method="POST" action="{{ route('settings.automation.store') }}" class="auto-form-grid rfq-grid-2" style="gap:12px" >@csrf
      <input name="name" required placeholder="نام قانون" class="w-full border rounded px-3 py-2">
      <label class="block text-xs font-semibold">محرک (Trigger)</label>
      <select name="trigger" id="autoTrigger" class="w-full border rounded px-3 py-2">
        <option value="status_changed">تغییر وضعیت پرونده</option>
        <option value="inactive_days">N روز بدون فعالیت</option>
      </select>
      <div id="boxStatus">
        <label class="block text-xs font-semibold">وقتی وضعیت شد</label>
        <select name="to_status" class="w-full border rounded px-3 py-2">
          <option value="">هر وضعیتی</option>
          @foreach($statuses as $k=>$lab)
            <option value="{{ $k }}">{{ $lab }}</option>
          @endforeach
        </select>
      </div>
      <div id="boxInactive" style="display:none">
        <label class="block text-xs font-semibold">تعداد روز بدون فعالیت</label>
        <input type="number" name="inactive_days" value="7" min="1" max="365" class="w-full border rounded px-3 py-2">
        <p class="text-[11px] text-slate-500 mt-1">روزانه ساعت ۷:۳۰ با دستور scheduled اجرا می‌شود. پرونده‌های بسته/باخت/متوقف نادیده گرفته می‌شوند.</p>
      </div>
      <label class="block text-xs font-semibold">عملیات</label>
      <select name="action" id="autoAction" class="w-full border rounded px-3 py-2">
        <option value="create_task">ایجاد وظیفه برای کارشناس پرونده</option>
        <option value="notify_assignees">اعلان به تخصیص‌یافته‌ها</option>
        <option value="notify_role">اعلان به نقش</option>
      </select>
      <div id="actTask">
        <input name="task_title" placeholder="عنوان وظیفه" class="w-full border rounded px-3 py-2 mt-2">
        <input type="number" name="due_days" value="3" min="1" max="90" class="w-full border rounded px-3 py-2 mt-2" placeholder="سررسید وظیفه (روز)">
      </div>
      <div id="actNotify" style="display:none">
        <input name="notify_message" placeholder="متن اعلان" class="w-full border rounded px-3 py-2 mt-2">
        <input name="role" placeholder="نقش (مثلاً admin)" class="w-full border rounded px-3 py-2 mt-2" value="admin">
      </div>
      <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" checked> فعال</label>
      <button class="btn btn-primary btn-sm">ذخیره قانون</button>
    </form>
  </div>
</div>
</div>
<script>
(function(){
  const tr = document.getElementById('autoTrigger');
  const act = document.getElementById('autoAction');
  function sync(){
    const inactive = tr.value === 'inactive_days';
    document.getElementById('boxStatus').style.display = inactive ? 'none' : 'block';
    document.getElementById('boxInactive').style.display = inactive ? 'block' : 'none';
    const t = act.value === 'create_task';
    document.getElementById('actTask').style.display = t ? 'block' : 'none';
    document.getElementById('actNotify').style.display = t ? 'none' : 'block';
  }
  tr.addEventListener('change', sync);
  act.addEventListener('change', sync);
  sync();
})();
</script>
@endsection
