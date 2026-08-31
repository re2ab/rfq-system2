@extends('layouts.app')
@php
  // M33 (درخواست کاربر): عنوانِ بالای صفحه دیگر همیشه document_number نیست —
  // باید همانِ رویژنی را نشان دهد که پایینِ صفحه انتخاب شده (وگرنه در بالا
  // آخرین رویژنِ سند دیده می‌شود، در کارتِ پایین یک رویژنِ دیگر، که گیج‌کننده
  // بود). این محاسبه از داخلِ محتوا به این‌جا (بالای فایل) منتقل شد چون
  // @section('title', ...) به‌صورتِ inline زودتر از @section('content') اجرا
  // می‌شود؛ پایینِ صفحه دیگر دوباره محاسبه نمی‌شود، همین $activeRev/
  // $draftIndexMap را استفاده می‌کند.
  $activeRev = $selectedRevision ?? $document->currentRevision;
  $draftIndexMap = [];
  $draftCounter = 0;
  foreach ($document->revisions->sortBy('revision_number') as $r) {
    if (!$r->isPublished()) {
      $draftCounter++;
      $draftIndexMap[$r->id] = $draftCounter;
    }
  }
  $activeRevNumberLabel = $activeRev
    ? ($activeRev->isPublished() ? $activeRev->formatted_number : 'پیش‌نویس '.($draftIndexMap[$activeRev->id] ?? '?'))
    : $document->document_number;
@endphp
@section('title', $activeRevNumberLabel)
@section('actions')
  <a href="{{ route('documents.index') }}" class="btn btn-ghost btn-sm">بازگشت</a>
  {{-- M33 (درخواست کاربر): دکمه‌ی «حذف (فقط مدیر)» (برای سندهایی که نسخه‌ی
       قفل‌شده دارند) از بالای صفحه حذف شد — حذفِ چنین سندی حالا فقط از
       جدولِ تاریخچه‌ی پایین (حذفِ تکیِ هر رویژن، M24) ممکن است. حذفِ ساده‌ی
       سندِ بدونِ هیچ نسخه‌ی منتشرشده همچنان از همین بالا در دسترس است. --}}
  @can('document.delete')
    @php $docHasLocked = $document->revisions->contains('is_locked', true); @endphp
    @if(!$docHasLocked)
    <form method="POST" action="{{ route('documents.destroy', $document) }}" onsubmit="return confirm('این سند حذف شود؟');">@csrf @method('DELETE')
      <button type="submit" class="btn btn-danger btn-sm">حذف</button>
    </form>
    @endif
  @endcan
@endsection
@section('content')

@if($document->lines && $document->lines->count())
<div class="card mb-4">
  <div class="card-h">ردیف‌های سند</div>
  <div class="card-b pad0">
    <table class="tbl">
      <thead><tr><th>#</th><th>شرح</th><th>واحد</th><th>تعداد</th><th>قیمت واحد</th><th>جمع</th></tr></thead>
      <tbody>
        @foreach($document->lines as $i => $line)
        <tr>
          <td>{{ $i+1 }}</td>
          <td>{{ $line->description }}</td>
          <td>{{ $line->unit }}</td>
          <td>{{ number_format((float)$line->quantity, 3) }}</td>
          <td>{{ number_format((float)$line->unit_price, 2) }}</td>
          <td style="font-weight:800">{{ number_format((float)$line->line_total, 2) }}</td>
        </tr>
        @endforeach
      </tbody>
      <tfoot>
        <tr style="font-weight:700"><td colspan="5">خالص</td><td>{{ number_format((float)$document->net_amount, 2) }} {{ currency_label($document->currency) }}</td></tr>
        <tr><td colspan="5">VAT {{ number_format((float)$document->vat_percent, 2) }}٪</td><td>{{ number_format((float)$document->vat_amount, 2) }}</td></tr>
        <tr style="font-weight:800;color:var(--brand)"><td colspan="5">جمع کل</td><td>{{ number_format((float)$document->gross_amount, 2) }} {{ currency_label($document->currency) }}</td></tr>
      </tfoot>
    </table>
  </div>
</div>
@endif

<div class="space-y-4">

@if(session('error'))<x-alert type="error">{{ session('error') }}</x-alert>@endif

{{-- M37 (درخواست کاربر): چیدمان از ۵۰٪-۵۰٪ به ۷۰٪-۳۰٪ تغییر کرد — کارتِ
     فایل/دکمه‌ها (اول در DOM، راست در RTL) ۷۰٪، کارتِ خلاصه‌ی شماره/نوع/
     پرونده (دوم در DOM، چپ در RTL) ۳۰٪. --}}
<div class="case-row-70-30 row-stretch">
  <div>
{{-- M33: $activeRev/$draftIndexMap دیگر این‌جا دوباره محاسبه نمی‌شوند —
     بالای فایل (کنارِ @section('title')) یک‌بار محاسبه شده‌اند تا عنوانِ
     صفحه و این کارت همیشه هماهنگ باشند. --}}
@if($activeRev && $activeRev->file_path)
<div class="bg-white rounded-lg shadow p-4 text-sm space-y-3">
  <div class="flex justify-between items-center flex-wrap gap-2">
    <div>
      {{-- M33 (درخواست کاربر): برچسبِ «سند TC-...» که این‌جا بود حذف شد —
           چون همین شماره الان بالای صفحه (عنوانِ صفحه) هم نمایش داده
           می‌شود و تکراری بود. فقط وضعیتِ قفل/پیش‌نویس می‌ماند. --}}
      <div style="color:var(--muted);font-size:12px">
        @if($activeRev->is_locked)
          منتشرشده و قفل — شماره‌ی نهایی: {{ $activeRev->formatted_number }}
        @else
          پیش‌نویس — هنوز منتشر نشده، قابل ویرایش
        @endif
      </div>
    </div>
    <div class="flex gap-2">
      {{-- M33: برچسبِ «دانلود نسخه‌ی نهایی» به «دانلود Word» تغییر کرد
           (درخواست کاربر) — این دکمه همیشه فایلِ Word/Excel را می‌دهد، نه
           PDF، پس اسمش دقیق‌تر شد. --}}
      <a href="{{ route('documents.revisions.download', $activeRev) }}" class="btn btn-soft btn-sm">دانلود {{ $activeRev->is_locked ? 'Word' : 'پیش‌نویس' }}</a>
      @if($pdfAvailable ?? false)
        <a href="{{ route('documents.revisions.download-pdf', $activeRev) }}" class="btn btn-soft btn-sm">دانلود PDF</a>
      @endif
      @if(($onlyOfficeAvailable ?? false) && $activeRev->isEditable())
        <a href="{{ route('documents.revisions.edit-online', $activeRev) }}" class="btn btn-primary btn-sm" target="_blank" rel="noopener">ویرایش آنلاین</a>
      @endif
      {{-- M35 (درخواست کاربر): «آیا می‌شود برای یک پیش‌نویس، قالبی غیر از
           قالبِ سندِ مادر انتخاب کرد؟» — فقط روی Draftِ واقعاً قابل‌ویرایش
           (isEditable، نه فقط !is_locked) نمایش داده می‌شود؛ خودِ فایل
           کاملاً از نو با قالبِ تازه ساخته می‌شود. --}}
      @if($activeRev->isEditable())
        <a href="{{ route('documents.revisions.template-form', $activeRev) }}" class="btn btn-ghost btn-sm">تغییرِ قالب</a>
      @endif
      @unless($activeRev->is_locked)
        @can('document.approve_revision')
        <form method="POST" action="{{ route('documents.revisions.publish', $activeRev) }}" onsubmit="return confirm('با انتشار، شماره‌ی رسمی سند صادر و این نسخه قفل می‌شود. ادامه؟');">
          @csrf
          <button type="submit" class="btn btn-primary btn-sm">انتشار و صدور شماره</button>
        </form>
        @endcan
      @else
        {{-- M30 (رفع باگ): قبلاً این دکمه به documents.new-draft می‌رفت که
             همیشه روی $document->currentRevision کار می‌کرد — نه لزوماً روی
             همین $activeRev که کاربر بالای صفحه انتخاب/می‌بیند. اگر currentRevision
             سند از قبل یک Draft ویرایش‌نشده‌ی دیگر بود (مثلاً کاربر یک Draft
             ساخته و دارد رویش کار می‌کند)، آن اکشن با «نسخه‌ی فعلی همین الان هم
             Draft است» رد می‌شد — حتی وقتی $activeRev واقعاً منتشرشده/قفل بود.
             حالا از همان مسیرِ «ساخت کپی» (M23) با مبدأ صریحِ $activeRev استفاده
             می‌شود — که هیچ محدودیتی روی وضعیتِ Draftهای دیگر ندارد، پس از یک
             سند می‌توان چندین Draft مستقل با محتوای متفاوت ساخت. --}}
        <form method="POST" action="{{ route('documents.revisions.copy', $activeRev) }}" onsubmit="return confirm('یک نسخه‌ی Draft جدید (کپی از همین فایل) ساخته می‌شود تا بتوانید ویرایش کنید. ادامه؟');">
          @csrf
          <button type="submit" class="btn btn-soft btn-sm">ساخت نسخه‌ی جدید برای ویرایش</button>
        </form>
        {{-- M35: مسیرِ دومِ ساختِ Draftِ تازه — به‌جای کپیِ عینِ همین فایل، با
             یک قالبِ دیگر (از همان نوعِ سند) از نو رندر می‌شود. --}}
        <a href="{{ route('documents.revisions.template-form', ['revision' => $activeRev, 'mode' => 'new_draft']) }}" class="btn btn-ghost btn-sm">ساخت با قالبِ دیگر</a>
      @endunless
    </div>
  </div>

  @unless($activeRev->is_locked)
  <div style="border-top:1px solid var(--border,#eee);padding-top:12px">
    <div class="font-semibold" style="margin-bottom:4px">ویرایش روی دسکتاپ</div>
    <div style="font-size:11px;color:var(--muted);margin-bottom:6px">
      فایل را دانلود کنید، با Word/Excel واقعی روی کامپیوترتان ویرایش کنید، سپس همان فایل ویرایش‌شده را اینجا دوباره آپلود کنید — جای فایل فعلی همین Draft را می‌گیرد.
    </div>
    <form method="POST" action="{{ route('documents.revisions.upload-edit', $activeRev) }}" enctype="multipart/form-data" class="flex gap-2 flex-wrap items-center">
      @csrf
      <input type="file" name="file" required accept=".docx,.xlsx" class="border rounded px-2 py-1 text-sm">
      <button type="submit" class="btn btn-ghost btn-sm">جایگزینی با نسخه‌ی ویرایش‌شده</button>
    </form>
  </div>
  @endunless

  {{-- بخش «ارسال این سند برای مشتری» عمداً حذف شد (درخواست کاربر) — ارسال
       ایمیل حالا فقط از صفحه‌ی ایمیل (emails.create) انجام می‌شود؛ آن صفحه
       امکان انتخاب و پیوست‌کردن اسناد پرونده (فایل Word/Excel یا PDF) را
       گرفته، پس این مسیر جدا و تکراری دیگر لازم نبود. روت/کنترلر بک‌اند
       (documents.revisions.email) دست‌نخورده ماند تا چیزی نشکند. --}}
</div>
@endif
  </div>
  <div>
    <div class="bg-white rounded-lg shadow p-6 text-sm space-y-2">
    <div class="flex justify-between"><span class="text-gray-500">شماره</span><span class="font-medium">{{ $document->document_number }}</span></div>
    <div class="flex justify-between"><span class="text-gray-500">نوع</span><span>{{ $document->documentType->name_fa ?? $document->type }}</span></div>
    <div class="flex justify-between"><span class="text-gray-500">پرونده</span>
    <a class="text-blue-600" href="{{ route('cases.show',$document->case) }}">{{ $document->case?->case_number }}</a></div>
    @if($document->typeSupportsLines())
    {{-- این ۴ ردیف فقط برای اسناد قیمت‌دار (پیشنهاد مالی/فاکتور و مانند آن)
         معنا دارند؛ برای انواع دیگر (مثل پیشنهاد فنی) که ردیف/مبلغی ندارند
         همیشه صفر بودند و فقط سردرگم‌کننده بودند. --}}
    <div class="flex justify-between"><span class="text-gray-500">ترم تحویل</span><span>{{ $document->incoterm ?? '—' }}</span></div>
    <div class="flex justify-between"><span class="text-gray-500">خالص</span><span>{{ number_format($document->net_amount,2) }} {{ currency_label($document->currency) }}</span></div>
    <div class="flex justify-between"><span class="text-gray-500">VAT ({{ $document->vat_percent }}%)</span><span>{{ number_format($document->vat_amount,2) }}</span></div>
    <div class="flex justify-between font-semibold"><span>جمع</span><span>{{ number_format($document->gross_amount,2) }}</span></div>
    @endif
    </div>
  </div>
</div>

{{-- تاریخچه‌ی نسخه‌ها — فهرست همه‌ی Revisionهای واقعی این سند (شماره، وضعیت،
     ایجادکننده، تاریخ، دانلود فایل واقعی همان نسخه). نسخه‌ی جدید همیشه از
     دکمه‌های بالا (ساخت نسخه‌ی جدید برای ویرایش) ساخته می‌شود — نه از این
     بخش؛ فرم متنی قدیمی «نسخه جدید» که اینجا بود حذف شد چون نسخه‌ای بدون
     فایل واقعی می‌ساخت و دکمه‌ی «تأیید و قفل»‌اش، نسخه را برای همیشه قفل
     می‌کرد بدون آنکه هرگز شماره‌ی رسمی بگیرد یا فایلی برای دانلود داشته باشد. --}}
<div class="card">
  <div class="card-h">تاریخچه‌ی نسخه‌ها ({{ $document->revisions->count() }})</div>
  <div class="card-b pad0">
    @php
      $revStatusLabels = ['draft' => 'پیش‌نویس', 'in_review' => 'در بررسی', 'published' => 'منتشرشده', 'superseded' => 'جایگزین‌شده'];
      $revStatusTones = ['draft' => 'muted', 'in_review' => 'warn', 'published' => 'ok', 'superseded' => 'muted'];
      $revCanDelete = $document->revisions->count() > 1;
      // $draftIndexMap بالای همین صفحه (کنارِ $activeRev) یک‌بار محاسبه شده؛
      // این‌جا دوباره حساب نمی‌شود، همان مقدار استفاده می‌شود.
      // M29 (درخواست کاربر): ترتیبِ نمایش از بالا به پایین عوض شد — دیگر
      // نزولی (آخرین رویژن اول) نیست. حالا اول همه‌ی رویژن‌های منتشرشده به
      // ترتیبِ صعودی (نسخه‌ی ۰۰، ۰۱، ۰۲، ...) و بعد از همه‌ی آن‌ها،
      // پیش‌نویس‌ها هم به ترتیبِ صعودیِ ساخت (پیش‌نویس ۱، ۲، ۳...) پایین‌تر.
      $sortedRevisionsForHistory = $document->revisions->filter(fn ($r) => $r->isPublished())->sortBy('revision_number')->values()
        ->concat($document->revisions->filter(fn ($r) => !$r->isPublished())->sortBy('revision_number')->values());
    @endphp
    <table class="tbl">
      <thead><tr><th>نسخه</th><th>بر اساس</th><th>وضعیت</th><th>ایجادکننده</th><th>تاریخ</th><th></th></tr></thead>
      <tbody>
        @forelse($sortedRevisionsForHistory as $rev)
        @php
          /* برای نسخه‌ی منتشرشده (یا قبلاً منتشرشده/جایگزین‌شده)، formatted_number
             واقعی و نهایی است. برای نسخه‌ای که هنوز هیچ‌وقت منتشر نشده
             (isPublished() == false)، به‌جای حدس‌زدنِ شماره‌ی رسمیِ آینده،
             طبق درخواست کاربر فقط برچسب ترتیبیِ «پیش‌نویس X» نمایش داده
             می‌شود — X شماره‌ی این پیش‌نویس در میان پیش‌نویس‌های همین سند است. */
          $revNumberDisplay = $rev->isPublished()
            ? $rev->formatted_number
            : 'پیش‌نویس '.($draftIndexMap[$rev->id] ?? '?');
          // M34 (درخواست کاربر): ستونِ «بر اساس» — اگر این Draft محتوایش کپیِ
          // یک Revisionِ مشخص است (source_revision_id)، شماره‌ی داخلیِ همان
          // Revisionِ مبدأ (revision_number، همیشه پایدار و مستقل از اینکه
          // بعداً منتشر شود یا نه) نمایش داده می‌شود. چون $document->revisions
          // از قبل کامل روی همین صفحه لود شده، مبدأ از همان مجموعه در حافظه
          // پیدا می‌شود — بدون کوئری اضافه. اگر مبدأ نداشت (اولین Draft سند)
          // یا مبدأ حذف شده باشد (source_revision_id با nullOnDelete خودکار
          // null می‌شود)، خط‌تیره نمایش داده می‌شود.
          $revSourceLabel = '—';
          if ($rev->source_revision_id) {
            $srcRev = $document->revisions->firstWhere('id', $rev->source_revision_id);
            if ($srcRev) {
              $revSourceLabel = 'نسخه '.str_pad((string) $srcRev->revision_number, 2, '0', STR_PAD_LEFT);
            }
          }
          $revIsLocked = (bool) $rev->is_locked;
          $revIsEditableNow = $rev->isEditable();
          // اصلاح M27 (بازخورد کاربر روی نسخه‌ی قبلی): دکمه‌ی جدای «انتخاب»/
          // «در حال نمایش» و پس‌زمینه‌ی رنگیِ ردیف حذف شد — به‌جایش، خودِ
          // برچسبِ ستونِ «نسخه» لینک است. ردیفی که همین الان بالای صفحه
          // نمایش داده می‌شود (isSelectedRow)، فقط متنِ ساده است — بدون
          // لینک، چون از قبل همان چیزی است که کاربر می‌بیند؛ بقیه‌ی ردیف‌ها
          // با کلیک روی همان اسم («پیش‌نویس ۱»، «پیش‌نویس ۲» و ...) انتخاب
          // می‌شوند.
          $isSelectedRow = $selectedRevision && (int) $selectedRevision->id === (int) $rev->id;
        @endphp
        <tr>
          <td style="font-weight:700">
            @if($isSelectedRow)
              {{ $revNumberDisplay }}
            @else
              <a href="{{ route('documents.show', $document) }}?revision={{ $rev->id }}">{{ $revNumberDisplay }}</a>
            @endif
          </td>
          <td style="color:var(--muted);font-size:12px">{{ $revSourceLabel }}</td>
          <td><x-badge :tone="$revStatusTones[$rev->status] ?? 'muted'">{{ $revStatusLabels[$rev->status] ?? $rev->status }}</x-badge></td>
          <td>{{ $rev->creator?->name ?? '—' }}</td>
          <td style="font-size:12px;color:var(--muted)">{{ jdate($rev->created_at)->format('Y/m/d') }}</td>
          <td style="white-space:nowrap">
            @if($rev->file_path)
              <a href="{{ route('documents.revisions.download', $rev) }}" class="btn btn-ghost btn-sm">دانلود Word{{ $revIsEditableNow ? ' (پیش‌نویس)' : '' }}</a>
              @if($pdfAvailable ?? false)
                <a href="{{ route('documents.revisions.download-pdf', $rev) }}" class="btn btn-ghost btn-sm">PDF</a>
              @endif
            @else
              <span style="color:var(--muted);font-size:12px">بدون فایل</span>
            @endif
            @can('document.delete')
              @if($revCanDelete)
                @if(!$revIsLocked)
                <form method="POST" action="{{ route('documents.revisions.destroy', $rev) }}" onsubmit="return confirm('همین یک نسخه حذف شود؟ بقیه‌ی نسخه‌های این سند دست‌نخورده می‌مانند.');" style="display:inline">@csrf @method('DELETE')
                  <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger,#c0392b)">حذف نسخه</button>
                </form>
                @elseif(auth()->user()?->hasRole('admin'))
                  {{-- فقط مدیر: این نسخه منتشرشده/قفل‌شده است. --}}
                  <form method="POST" action="{{ route('documents.revisions.destroy', $rev) }}" onsubmit="return confirm('این نسخه منتشرشده/قفل‌شده است — حذف آن فقط برای پاک‌سازی داده‌ی اشتباه/تستی توصیه می‌شود و قابل بازگشت نیست. مطمئنید؟');" style="display:inline">@csrf @method('DELETE')
                    <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger,#c0392b)">حذف نسخه (فقط مدیر)</button>
                  </form>
                @endif
              @endif
            @endcan
          </td>
        </tr>
        @empty
        <tr><td colspan="6"><x-empty title="نسخه‌ای ثبت نشده" /></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

</div>
@endsection
