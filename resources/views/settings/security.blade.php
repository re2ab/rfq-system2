@extends('layouts.settings')
@section('title','امنیت، ایمیل واقعی و دسترسی فیلدها')
@section('settings')
<div class="card mb-4"><div class="card-b text-sm">
  فعال/غیرفعال کردن قابلیت‌های اختیاری از
  <a href="{{ route('settings.modules') }}" style="color:var(--brand)">مدیریت ماژول‌ها</a>
  انجام می‌شود. این صفحه پیکربندی جزئی همان ماژول‌هاست.
</div></div>

<div style="gap:16px" class="rfq-sec-grid rfq-grid-2">
<div class="card mb-4" style="grid-column:1/-1">
  <div class="card-h">سرور ایمیل شرکت (برای صندوق هر کاربر)</div>
  <div class="card-b text-sm">
    <p class="text-xs text-gray-500 mb-2">این مقادیر برای همه کارکنان مشترک است. هر کاربر فقط یوزرنیم و رمز خودش را در «صندوق من» وارد می‌کند.</p>
    <form method="POST" action="{{ route('settings.security.mail') }}" class="space-y-2">@csrf
      <div class="rfq-grid-3" style="gap:8px">
        <label>SMTP Host<input name="company_smtp_host" value="{{ $company_smtp_host ?? '' }}" class="w-full border rounded px-2 py-1" dir="ltr"></label>
        <label>SMTP Port<input name="company_smtp_port" value="{{ $company_smtp_port ?? 587 }}" class="w-full border rounded px-2 py-1" dir="ltr"></label>
        <label>SMTP Encryption
          <select name="company_smtp_encryption" class="w-full border rounded px-2 py-1">
            @foreach(['tls','ssl','none'] as $e)
              <option value="{{ $e }}" @selected(($company_smtp_encryption ?? 'tls')===$e)>{{ $e }}</option>
            @endforeach
          </select>
        </label>
        <label>IMAP Host<input name="company_imap_host" value="{{ $company_imap_host ?? '' }}" class="w-full border rounded px-2 py-1" dir="ltr"></label>
        <label>IMAP Port<input name="company_imap_port" value="{{ $company_imap_port ?? 993 }}" class="w-full border rounded px-2 py-1" dir="ltr"></label>
        <label>IMAP Encryption
          <select name="company_imap_encryption" class="w-full border rounded px-2 py-1">
            @foreach(['ssl','tls','none'] as $e)
              <option value="{{ $e }}" @selected(($company_imap_encryption ?? 'ssl')===$e)>{{ $e }}</option>
            @endforeach
          </select>
        </label>
        <label>POP3 Host (اختیاری)<input name="company_pop3_host" value="{{ $company_pop3_host ?? '' }}" class="w-full border rounded px-2 py-1" dir="ltr"></label>
        <label>POP3 Port<input name="company_pop3_port" value="{{ $company_pop3_port ?? 995 }}" class="w-full border rounded px-2 py-1" dir="ltr"></label>
        <label>نام پوشه‌ی Sent (اختیاری)
          <input name="company_imap_sent_folder" value="{{ $company_imap_sent_folder ?? '' }}" class="w-full border rounded px-2 py-1" dir="ltr" placeholder="خالی = تشخیص خودکار">
        </label>
      </div>
      <p class="text-xs text-gray-500 mt-1">
        وقتی کاربری از «صندوق من» ایمیل می‌فرستد، کپی آن هم در سیستم ثبت می‌شود هم تلاش می‌شود در همین پوشه روی سرور واقعی ذخیره شود (تا در Outlook/جیمیل واقعی هم دیده شود). اگر خالی بگذارید سیستم خودش با نام‌های رایج (Sent، INBOX.Sent، Sent Items، [Gmail]/Sent Mail) تشخیص می‌دهد؛ اگر سرویس‌دهنده‌ی شما نام دیگری دارد همین‌جا دقیق وارد کنید.
      </p>
      <button class="btn btn-primary mt-2">ذخیره سرور شرکت</button>
    </form>
  </div>
</div>

  <div class="card">
    <div class="card-h">ایمیل واقعی SMTP / IMAP @if(!$real_email_on)<span class="text-xs text-red-600">(ماژول خاموش)</span>@endif</div>
    <div class="card-b text-sm space-y-2">
      <form method="POST" action="{{ route('settings.security.mail') }}" class="space-y-2">@csrf
        <input name="mail_smtp_host" value="{{ $mail_smtp_host }}" placeholder="SMTP host" class="w-full border rounded px-2 py-1" dir="ltr">
        <div class="grid grid-cols-2 gap-2">
          <input name="mail_smtp_port" value="{{ $mail_smtp_port }}" placeholder="Port" class="border rounded px-2 py-1" dir="ltr">
          <input name="mail_smtp_encryption" value="{{ $mail_smtp_encryption }}" placeholder="tls/ssl" class="border rounded px-2 py-1" dir="ltr">
        </div>
        <input name="mail_smtp_username" value="{{ $mail_smtp_username }}" placeholder="SMTP user" class="w-full border rounded px-2 py-1" dir="ltr">
        <input name="mail_smtp_password" type="password" placeholder="SMTP password (فقط هنگام تغییر)" class="w-full border rounded px-2 py-1" dir="ltr">
        <input name="mail_from_address" value="{{ $mail_from_address }}" placeholder="From email" class="w-full border rounded px-2 py-1" dir="ltr">
        <input name="mail_from_name" value="{{ $mail_from_name }}" placeholder="From name" class="w-full border rounded px-2 py-1">
        <hr>
        <input name="mail_imap_host" value="{{ $mail_imap_host }}" placeholder="IMAP host" class="w-full border rounded px-2 py-1" dir="ltr">
        <div class="grid grid-cols-2 gap-2">
          <input name="mail_imap_port" value="{{ $mail_imap_port }}" placeholder="993" class="border rounded px-2 py-1" dir="ltr">
          <select name="mail_imap_encryption" class="border rounded px-2 py-1" dir="ltr">
            @foreach(['ssl','tls','none'] as $e)
              <option value="{{ $e }}" @selected(($mail_imap_encryption ?? 'ssl')===$e)>{{ $e }}</option>
            @endforeach
          </select>
        </div>
        <input name="mail_imap_username" value="{{ $mail_imap_username ?? '' }}" placeholder="IMAP username (معمولاً همان ایمیل کامل)" class="w-full border rounded px-2 py-1" dir="ltr">
        <input name="mail_imap_password" type="password" placeholder="IMAP password (فقط هنگام تغییر)" class="w-full border rounded px-2 py-1" dir="ltr">
        <button class="btn btn-primary">ذخیره ایمیل</button>
      </form>
      <form method="POST" action="{{ route('settings.security.mail.test') }}" class="flex gap-2 mt-2">@csrf
        <input name="to" type="email" required placeholder="ایمیل تست" class="border rounded px-2 py-1 flex-1" dir="ltr">
        <button class="btn btn-sm">تست SMTP</button>
      </form>
      <form method="POST" action="{{ route('settings.security.imap.test') }}">@csrf
        <button class="btn btn-sm btn-ghost">تست اتصال IMAP</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-h">تبدیل PDF واقعی (LibreOffice)</div>
    <div class="card-b text-sm space-y-2">
      <p class="text-xs text-gray-500">
        این دکمه دقیقاً همان چیزی را چک می‌کند که موقعِ نمایشِ دکمه‌ی «دانلود PDF» روی صفحه‌ی سند چک می‌شود: آیا باینریِ <code dir="ltr">soffice</code> روی همین سرور پیدا می‌شود یا نه. اگر «فعال نیست» نشان داد، معمولاً یعنی <code dir="ltr">nixpacks.toml</code> در بیلدِ آخر اعمال نشده — یک Redeploy روی Railway بزنید و دوباره اینجا چک کنید.
      </p>
      <form method="POST" action="{{ route('settings.security.pdf.test') }}">@csrf
        <button class="btn btn-sm btn-ghost">بررسی وضعیت تبدیل PDF</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-h">یادآوری هوشمند @if(!$reminders_on)<span class="text-xs text-red-600">(ماژول خاموش)</span>@endif</div>
    <div class="card-b text-sm">
      <form method="POST" action="{{ route('settings.security.reminders') }}" class="space-y-2">@csrf
        <label>پرونده بدون تغییر بیش از چند روز هشدار داده شود؟
          <input type="number" name="reminder_stuck_days" value="{{ $reminder_stuck_days }}" min="1" max="90" class="w-full border rounded px-2 py-1 mt-1">
        </label>
        <p class="text-xs text-gray-500">کرون: <code>php artisan rfq:smart-reminders</code> (روزانه ۰۸:۰۰ در schedule)</p>
        <button class="btn btn-primary">ذخیره</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-h">دسترسی فیلدهای حساس @if(!$field_acl_on)<span class="text-xs text-red-600">(ماژول خاموش)</span>@endif</div>
    <div class="card-b text-sm">
      <ul class="text-xs mb-3 space-y-1">
        @foreach($fields as $f)
          <li><code>{{ $f->field_key }}</code> — {{ $f->label }} → {{ is_string($f->allowed_roles) ? $f->allowed_roles : json_encode($f->allowed_roles) }}</li>
        @endforeach
      </ul>
      <form method="POST" action="{{ route('settings.security.field') }}" class="space-y-2">@csrf
        <input name="field_key" placeholder="field_key مثلا proposal_amount" class="w-full border rounded px-2 py-1" dir="ltr" required>
        <input name="label" placeholder="برچسب فارسی" class="w-full border rounded px-2 py-1">
        <input name="allowed_roles" placeholder="admin, finance_manager" class="w-full border rounded px-2 py-1" dir="ltr" required>
        <button class="btn btn-primary">ذخیره قانون فیلد</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-h">حاکمیت داده و خروجی</div>
    <div class="card-b text-sm space-y-3">
      <form method="POST" action="{{ route('settings.security.retention') }}" class="space-y-2">@csrf
        <label>نگهداری پرونده‌های بسته‌شده (سال)
          <input type="number" name="retention_years" value="{{ $retention_years }}" min="1" max="30" class="w-full border rounded px-2 py-1 mt-1">
        </label>
        <button class="btn btn-primary">ذخیره سیاست</button>
      </form>
      <a class="btn btn-primary btn-sm" href="{{ route('export.accounting.payments') }}">دانلود CSV حسابداری (پرداخت‌ها)</a>
      <p class="text-xs text-gray-500">ماژول accounting_export باید فعال باشد. 2FA از ماژول two_factor و فیلدهای users.</p>
    </div>
  </div>

  <div class="card" style="grid-column:1/-1">
    <div class="card-h">آخرین لاگ حسابرسی @if(!$audit_on)<span class="text-xs text-red-600">(ماژول خاموش)</span>@endif</div>
    <div class="card-b pad0 text-xs">
      @forelse($audits as $a)
        <div class="rel-item" style="padding:8px 12px;border-bottom:1px solid var(--border)">
          #{{ $a->id }} · {{ $a->action }} · user {{ $a->user_id }} · {{ $a->auditable_type }} {{ $a->auditable_id }} · {{ jdatetime($a->created_at) }}
        </div>
      @empty
        <p class="p-4 text-gray-500">موردی نیست</p>
      @endforelse
    </div>
  </div>
</div>
@endsection
