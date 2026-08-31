@extends('layouts.settings')
@section('title', 'پشتیبان‌گیری و ورود/خروج داده')
@section('settings')
<div class="card mb-4" style="border-color:var(--warning)">
  <div class="card-b text-sm" style="background:var(--warning-soft);color:var(--warning)">
    <strong>امنیت:</strong>
    این بخش فقط برای ادمین است. بک‌آپ‌ها به‌صورت پیش‌فرض با کلید اپلیکیشن رمزنگاری می‌شوند.
    فایل‌های بک‌آپ را روی همان سرور اپ به‌تنهایی نگه ندارید — ذخیره ابری/آینه خارجی را فعال کنید.
    قبل از بازیابی کامل حتماً یک بک‌آپ تازه بگیرید.
  </div>
</div>

{{-- ردیف ۱: راست = تهیه بک‌آپ/خروجی — چپ = حذف داده و ریست سیستم --}}
<div class="case-row-50 row-stretch mb-4">
  <div>
  {{-- Export --}}
  <div class="card backup-card">
    <div class="card-h">تهیه بک‌آپ / خروجی</div>
    <div class="card-b text-sm">
      <form method="POST" action="{{ route('settings.backup.run') }}" class="space-y-3">@csrf
        <label class="flex gap-2 items-center"><input type="checkbox" name="encrypt" value="1" @checked($encrypt_default==='1')> رمزنگاری فایل</label>
        <div>
          <div class="font-semibold mb-2">محدوده</div>
          <label class="flex gap-2 mb-1"><input type="checkbox" name="sections[]" value="full" checked id="secFull"> همه سیستم (کامل)</label>
          <div class="text-xs text-gray-500 mb-2">اگر «کامل» تیک باشد، بقیه نادیده گرفته می‌شوند.</div>
          @foreach($sections as $key => $sec)
            <label class="flex gap-2 mb-1 text-xs">
              <input type="checkbox" name="sections[]" value="{{ $key }}" class="sec-part">
              {{ $sec['label'] }}
            </label>
          @endforeach
        </div>
        <button class="btn btn-primary">دانلود بک‌آپ</button>
      </form>
    </div>
  </div>
  </div>
  <div>
  {{-- Wipe / Factory reset --}}
  <div class="card" style="border-color:color-mix(in srgb, var(--danger) 35%, transparent)">
    <div class="card-h" style="color:var(--danger)">حذف داده و ریست سیستم</div>
    <div class="card-b text-sm space-y-5">
      <div>
        <h3 class="font-semibold mb-1">حذف کامل یک بخش</h3>
        <p class="text-xs text-gray-500 mb-2">مثلاً همه مخاطبان خراب‌شده بعد از ایمپورت را یکجا پاک کنید. غیرقابل بازگشت است.</p>
        <form method="POST" action="{{ route('settings.wipe.section') }}" class="space-y-2" onsubmit="return confirm('مطمئن هستید؟ این عملیات برگشت‌ناپذیر است.')">@csrf
          <select name="section" class="w-full border rounded px-3 py-2" required>
            <option value="contacts">همه مخاطبان</option>
            <option value="organizations">همه سازمان‌ها</option>
            <option value="cases">همه پرونده‌ها</option>
            <option value="tasks">همه وظایف</option>
            <option value="documents">همه اسناد</option>
            <option value="emails">همه ایمیل‌ها</option>
          </select>
          <input type="password" name="password" required placeholder="رمز عبور ادمین" class="w-full border rounded px-3 py-2">
          <input type="text" name="confirm" required placeholder="برای تأیید عبارت DELETE را تایپ کنید" class="w-full border rounded px-3 py-2" dir="ltr">
          <button class="btn btn-sm" style="background:var(--danger);color:#fff">حذف بخش انتخاب‌شده</button>
        </form>
      </div>
      <hr>
      <div>
        <h3 class="font-semibold mb-1">ریست کارخانه‌ای (سیستم تازه)</h3>
        <p class="text-xs text-gray-500 mb-2">همه داده‌های عملیاتی (پرونده، مخاطب، سازمان، وظیفه، سند، ایمیل، …) پاک می‌شود. فقط حساب شما (ادمین فعلی) باقی می‌ماند. قبل از اجرا حتماً بک‌آپ بگیرید.</p>
        <form method="POST" action="{{ route('settings.factory.reset') }}" class="space-y-2" onsubmit="return confirm('ریست کامل سیستم؟ فقط ادمین فعلی می‌ماند.')">@csrf
          <label class="flex items-center gap-2 text-xs">
            <input type="checkbox" name="wipe_settings" value="1"> تنظیمات قالب/شماره‌گذاری را هم پاک کن
          </label>
          <input type="password" name="password" required placeholder="رمز عبور ادمین" class="w-full border rounded px-3 py-2">
          <input type="text" name="confirm" required placeholder="برای تأیید عبارت RESET را تایپ کنید" class="w-full border rounded px-3 py-2" dir="ltr">
          <button class="btn btn-sm" style="background:var(--danger-dark, var(--danger));color:#fff">اجرای ریست کارخانه‌ای</button>
        </form>
      </div>
    </div>
  </div>
  </div>
</div>

{{-- ردیف ۲: راست = زمان‌بندی و نگهداری — چپ = ذخیره خارج از سرور (ابری/آینه) --}}
<div class="case-row-50 row-stretch mb-4">
  <div>
  {{-- Schedule + retention --}}
  <div class="card">
    <div class="card-h">زمان‌بندی و نگهداری</div>
    <div class="card-b text-sm">
      <form method="POST" action="{{ route('settings.backup.schedule') }}" class="space-y-2">@csrf
        <label class="flex gap-2"><input type="checkbox" name="enabled" value="1" @checked($schedule_enabled==='1')> پشتیبان خودکار فعال</label>
        <label class="block">تناوب
          <select name="frequency" class="w-full border rounded px-3 py-2 mt-1">
            <option value="daily" @selected($schedule_frequency==='daily')>روزانه</option>
            <option value="weekly" @selected($schedule_frequency==='weekly')>هفتگی (شنبه)</option>
          </select>
        </label>
        <label class="block">حذف بک‌آپ‌های قدیمی‌تر از (روز)
          <input type="number" name="retention_days" min="1" max="365" value="{{ $retention_days }}" class="w-full border rounded px-3 py-2 mt-1">
        </label>
        <label class="flex gap-2"><input type="checkbox" name="encrypt" value="1" @checked($encrypt_default==='1')> رمزنگاری در زمان‌بندی</label>
        <p class="text-xs text-gray-500">روی سرور: <code>* * * * * php artisan schedule:run</code></p>
        <button class="btn btn-primary">ذخیره</button>
      </form>
    </div>
  </div>
  </div>
  <div>
  {{-- Cloud --}}
  <div class="card">
    <div class="card-h">ذخیره خارج از سرور (ابری / آینه)</div>
    <div class="card-b text-sm">
      <form method="POST" action="{{ route('settings.backup.cloud') }}" class="space-y-3">@csrf
        <label class="block">روش
          <select name="driver" class="w-full border rounded px-3 py-2 mt-1" id="cloudDriver">
            <option value="none" @selected($cloud_driver==='none')>غیرفعال</option>
            <option value="local_mirror" @selected($cloud_driver==='local_mirror')>آینه محلی / NAS / دیسک مونت‌شده</option>
            <option value="webhook" @selected($cloud_driver==='webhook')>Webhook امن (سرور دریافت‌کننده شما)</option>
            <option value="google_drive" @selected($cloud_driver==='google_drive')>Google Drive (با Access Token)</option>
            <option value="box" @selected($cloud_driver==='box')>Box (با Access Token)</option>
          </select>
        </label>
        <div data-cloud="local_mirror" class="cloud-panel">
          <label class="block">مسیر پوشه روی سرور (مثال: /mnt/backup-nas/rfq)
            <input name="cloud_path" value="{{ $cloud_path }}" class="w-full border rounded px-3 py-2 mt-1" dir="ltr">
          </label>
        </div>
        <div data-cloud="webhook" class="cloud-panel">
          <label class="block">URL دریافت‌کننده
            <input name="webhook_url" value="{{ $cloud_webhook_url }}" class="w-full border rounded px-3 py-2 mt-1" dir="ltr" placeholder="https://backup.example.com/ingest">
          </label>
          <label class="block mt-2">Bearer Token (اختیاری)
            <input name="webhook_token" class="w-full border rounded px-3 py-2 mt-1" dir="ltr" placeholder="فقط هنگام تغییر پر کنید">
          </label>
        </div>
        <div data-cloud="google_drive" class="cloud-panel">
          <p class="text-xs mb-2">
            Access Token: <strong>{{ !empty($has_gdrive_token) ? 'فعال' : 'نیست' }}</strong>
            · Refresh: <strong>{{ !empty($has_gdrive_refresh) ? 'بله' : 'خیر' }}</strong>
          </p>
          <p class="text-xs mb-2 text-gray-500">Redirect URI در Google Cloud:
            <code dir="ltr" style="display:block;word-break:break-all">{{ route('settings.backup.gdrive.callback') }}</code>
          </p>
          <label class="block">Client ID
            <input name="gdrive_client_id" value="{{ $gdrive_client_id ?? '' }}" class="w-full border rounded px-3 py-2 mt-1" dir="ltr">
          </label>
          <label class="block mt-2">Client Secret
            <input name="gdrive_client_secret" type="password" class="w-full border rounded px-3 py-2 mt-1" dir="ltr" placeholder="فقط هنگام تغییر">
          </label>
          <label class="block mt-2">Folder ID
            <input name="gdrive_folder" value="{{ $gdrive_folder }}" class="w-full border rounded px-3 py-2 mt-1" dir="ltr">
          </label>
          <label class="block mt-2">Google API Key (فقط برای Import سند از Drive — M9)
            <input name="gdrive_api_key" value="{{ $gdrive_api_key ?? '' }}" class="w-full border rounded px-3 py-2 mt-1" dir="ltr" placeholder="اختیاری — بدون آن هم Picker با توکن OAuth تلاش می‌کند">
          </label>
          <p class="text-xs mt-2">پس از ذخیره Client ID/Secret، از دکمه زیر OAuth را کامل کنید (خارج از این فرم با لینک جدا).</p>
        </div>
        <div data-cloud="box" class="cloud-panel">
          <p class="text-xs mb-2">وضعیت توکن Box: {{ $has_box_token ? 'ثبت شده' : 'ثبت نشده' }}</p>
          <label class="block">Access Token
            <input name="box_token" class="w-full border rounded px-3 py-2 mt-1" dir="ltr">
          </label>
          <label class="block mt-2">Folder ID (پیش‌فرض 0 = ریشه)
            <input name="box_folder" value="{{ $box_folder }}" class="w-full border rounded px-3 py-2 mt-1" dir="ltr">
          </label>
        </div>
        <button class="btn btn-primary">ذخیره تنظیمات ابری</button>
      </form>
      <div class="flex flex-wrap gap-2 mt-3">
        <a href="{{ route('settings.backup.gdrive.connect') }}" class="btn btn-primary btn-sm">اتصال OAuth به Google Drive</a>
        <form method="POST" action="{{ route('settings.backup.gdrive.disconnect') }}" onsubmit="return confirm('قطع اتصال؟')">@csrf
          <button class="btn btn-ghost btn-sm">قطع Google Drive</button>
        </form>
      </div>
    </div>
  </div>
  </div>
</div>

{{-- ردیف ۳: راست = بازیابی (Restore) — چپ = ورود داده (Import) --}}
<div class="case-row-50 row-stretch mb-4">
  <div>
  {{-- Restore --}}
  <div class="card">
    <div class="card-h">بازیابی (Restore)</div>
    <div class="card-b text-sm">
      <form method="POST" action="{{ route('settings.backup.restore') }}" enctype="multipart/form-data" class="space-y-2">@csrf
        <input type="file" name="file" required accept=".json,.enc,.bak,.zip">
        <select name="mode" class="w-full border rounded px-3 py-2" id="restoreMode">
          <option value="settings_only">فقط تنظیمات / قالب / ماژول</option>
          <option value="sections">فقط بخش‌های انتخاب‌شده</option>
          <option value="full">کامل (خطرناک — داده فعلی بخش‌ها جایگزین می‌شود)</option>
        </select>
        <div id="restoreSections" class="hidden text-xs space-y-1 border rounded p-2">
          @foreach($sections as $key => $sec)
            <label class="flex gap-2"><input type="checkbox" name="restore_sections[]" value="{{ $key }}"> {{ $sec['label'] }}</label>
          @endforeach
        </div>
        <label class="flex gap-2 text-xs"><input type="checkbox" name="restore_files" value="1" checked> بازیابی فایل‌های پیوست داخل ZIP</label>
        <button class="btn btn-danger" style="background:var(--danger);color:#fff" onclick="return confirm('از بازیابی مطمئن هستید؟ این عمل برگشت‌ناپذیر است مگر بک‌آپ جدید داشته باشید.')">اجرای بازیابی</button>
      </form>
    </div>
  </div>
  </div>
  <div>
  {{-- Imports hub --}}
  <div class="card">
    <div class="card-h">ورود داده (Import) از فایل / سیستم‌های دیگر</div>
    <div class="card-b text-sm space-y-4">
      <div>
        <h3 class="font-semibold mb-1">مخاطبان (CSV/Excel)</h3>
        <p class="text-xs text-gray-500 mb-2">قالب استاندارد ستون‌ها — ایمپورت مخاطبان از این بخش انجام می‌شود (نه از لیست مخاطبان).</p>
        <a href="{{ route('settings.data.contacts.template') }}" class="text-sm" style="color:var(--brand)">دانلود قالب</a>
        <form method="POST" action="{{ route('settings.data.import.contacts') }}" enctype="multipart/form-data" class="mt-2 space-y-2">@csrf
          <input type="file" name="file" required accept=".csv,.txt,.xlsx">
          <button class="btn btn-primary btn-sm">ورود مخاطبان</button>
        </form>
      </div>
      <hr>
      <div>
        <h3 class="font-semibold mb-1">ترلو (JSON Export)</h3>
        <p class="text-xs text-gray-500 mb-2">از ترلو: منوی بورد → More → Export as JSON. کارت‌ها به‌صورت پرونده با منبع trello وارد می‌شوند.</p>
        <form method="POST" action="{{ route('settings.data.import.trello') }}" enctype="multipart/form-data" class="space-y-2">@csrf
          <input type="file" name="file" required accept=".json,application/json">
          <button class="btn btn-primary btn-sm">ورود از ترلو</button>
        </form>
      </div>
      <hr>
      <div>
        <h3 class="font-semibold mb-1">اسناد قدیمی (Migration — CSV)</h3>
        <p class="text-xs text-gray-500 mb-2">
          ثبت گروهی شماره/فراداده‌ی اسناد سیستم قبلی به‌عنوان اسناد منتشرشده — فقط رکورد و شماره ثبت می‌شود، نه فایل.
          ستون‌های لازم: <code dir="ltr">case_number, document_type, document_number</code> — اختیاری:
          <code dir="ltr">revision_number, serial, title, published_date</code>.
          شمارنده‌ی خودکار هر نوع سند بعد از این Import فقط جلو می‌رود (هرگز عقب نمی‌کشد) تا با شماره‌های قدیمی برخورد نکند.
          فایل واقعی هر سند را بعداً می‌توان از «آوردن فایل موجود» روی همان سند آپلود کرد.
        </p>
        <form method="POST" action="{{ route('settings.data.import.documents') }}" enctype="multipart/form-data" class="space-y-2">@csrf
          <input type="file" name="file" required accept=".csv,.txt">
          <button class="btn btn-primary btn-sm">ورود اسناد قدیمی</button>
        </form>
      </div>
      <hr>
      <p class="text-xs text-gray-500">مهاجرت از CRMهای دیگر: فعلاً از بک‌آپ JSON همین سیستم یا CSV مخاطبان و JSON ترلو پشتیبانی می‌شود. برای مپینگ اختصاصی CRM دیگر می‌توان کانکتور جدا اضافه کرد.</p>
    </div>
  </div>
  </div>
</div>

{{-- ردیف ۴: تاریخچه — تمام عرض --}}
<div class="card">
  <div class="card-h">تاریخچه بک‌آپ‌های ثبت‌شده</div>
  <div class="card-b pad0 text-sm">
    @forelse($jobs as $j)
      <div class="rel-item flex justify-between gap-2 text-xs" style="padding:10px 14px;border-bottom:1px solid var(--border)">
        <span>{{ $j->filename }} @if($j->encrypted)🔒@endif
          @if(!empty($j->scope)) <span class="text-gray-500">({{ $j->scope }})</span>@endif
        </span>
        <span>{{ $j->type }} · {{ number_format(($j->size_bytes ?? 0)/1024,1) }} KB · {{ jdatetime($j->created_at) }}</span>
      </div>
    @empty
      <p class="p-4 text-gray-500">هنوز بک‌آپ ثبت نشده.</p>
    @endforelse
  </div>
</div>

<script>
(function(){
  var full = document.getElementById('secFull');
  if (full) {
    full.addEventListener('change', function(){
      document.querySelectorAll('.sec-part').forEach(function(cb){ cb.disabled = full.checked; });
    });
    document.querySelectorAll('.sec-part').forEach(function(cb){ cb.disabled = full.checked; });
  }
  var mode = document.getElementById('restoreMode');
  var box = document.getElementById('restoreSections');
  function syncMode(){ if(box) box.classList.toggle('hidden', mode.value !== 'sections'); }
  if (mode) { mode.addEventListener('change', syncMode); syncMode(); }
  function syncCloud(){
    var d = document.getElementById('cloudDriver');
    if (!d) return;
    document.querySelectorAll('.cloud-panel').forEach(function(p){
      p.style.display = (p.getAttribute('data-cloud') === d.value) ? 'block' : 'none';
    });
  }
  var cd = document.getElementById('cloudDriver');
  if (cd) { cd.addEventListener('change', syncCloud); syncCloud(); }
})();
</script>
@endsection
