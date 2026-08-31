@extends('layouts.app')
@section('title', 'اسناد')
@section('actions')
  @can('template.view')
    <x-btn variant="ghost" href="{{ route('templates.index') }}">قالب‌های سند</x-btn>
  @endcan
  <x-dropdown label="سند جدید" variant="ghost" size="md">
    <div style="padding:6px 12px 2px;font-size:11px;font-weight:700;color:var(--muted)">سند Word</div>
    <a href="{{ route('documents.blank.create', ['format' => 'docx']) }}" role="menuitem">ایجاد سند خالی</a>
    <a href="{{ route('documents.generate.create', ['file_type' => 'docx']) }}" role="menuitem">ایجاد از تمپلیت آماده</a>
    <div class="sep"></div>
    <div style="padding:6px 12px 2px;font-size:11px;font-weight:700;color:var(--muted)">سند Excel</div>
    <a href="{{ route('documents.blank.create', ['format' => 'xlsx']) }}" role="menuitem">ایجاد سند خالی</a>
    <a href="{{ route('documents.generate.create', ['file_type' => 'xlsx']) }}" role="menuitem">ایجاد از تمپلیت آماده</a>
    <div class="sep"></div>
    <a href="{{ route('documents.upload.create') }}" role="menuitem">آپلود فایل</a>
    <a href="{{ route('documents.drive.create') }}" role="menuitem">وارد کردن از فضای ابری</a>
  </x-dropdown>
@endsection

@section('content')
@php
  // این‌ها فقط برای فیلتر «وضعیت» بالای جدول به کار می‌روند (وضعیتِ سطح سند،
  // ستون documents.status) — نه نمایش داخل ردیف‌ها؛ هر ردیف حالا یک Revision
  // است و وضعیت خودِ همان Revision را نشان می‌دهد (زیر).
  $statusLabels = [
    'draft' => 'پیش‌نویس',
    'published' => 'منتشرشده',
    'archived' => 'بایگانی',
  ];
  // درخواست کاربر: به‌جای «جایگزین‌شده» برای رویژنِ قبلاً‌منتشرشده هم همان
  // برچسب «منتشر شده» نمایش داده شود (تمایز draft/in_review/published کافی
  // است؛ تفاوت published/superseded دیگر در این ستون دیده نمی‌شود).
  $revStatusLabels = ['draft' => 'پیش‌نویس', 'in_review' => 'در بررسی', 'published' => 'منتشر شده', 'superseded' => 'منتشر شده'];
  $revStatusTones = ['draft' => 'muted', 'in_review' => 'warn', 'published' => 'ok', 'superseded' => 'ok'];
  // ستون «عنوان» دیگر عنوانِ خودِ سند را نشان نمی‌دهد — طبق درخواست کاربر،
  // برای نسخه‌ی پایه «نسخه اصلی» و برای هر رویژنِ دیگر «نسخه XX» (شماره‌ی
  // همان رویژن، دو رقمی) نمایش داده می‌شود.
  $revTitleDisplay = function ($rev, $isBaseRow) {
      return $isBaseRow ? 'نسخه اصلی' : 'نسخه '.str_pad((string) $rev->revision_number, 2, '0', STR_PAD_LEFT);
  };
  // شماره‌ی نمایشیِ یک Revision: اگر منتشر شده، formatted_number واقعی؛ اگر
  // پیش‌نویس است، شماره‌ای که با انتشار بعدی خواهد گرفت را از روی number_base
  // موجود سند پیش‌بینی می‌کند (بدون مصرف سریال) و برچسب «پیش‌نویس» می‌زند.
  // (در این صفحه عملاً همیشه شاخه‌ی اول اجرا می‌شود چون Draftها اصلاً اینجا
  // نمایش داده نمی‌شوند — طبق درخواست کاربر؛ منطق برای اطمینان کامل می‌ماند.)
  $revDisplay = function ($rev, $document) {
      if (!$rev) return '—';
      if ($rev->formatted_number) return $rev->formatted_number;
      return $document->number_base
          ? app(\App\Services\Documents\DocumentNumberingService::class)->formatRevisionNumber($document->number_base, $rev->revision_number).' (پیش‌نویس)'
          : 'پیش‌نویس (شماره‌ی رسمی هنوز صادر نشده)';
  };
@endphp

<form method="GET" class="rfq-filters rfq-filters-stack">
  <div class="rfq-filters-search">
    <input type="text" name="q" value="{{ request('q') }}" placeholder="شماره، عنوان…" class="rfq-f-input">
    <button type="submit" class="btn btn-primary btn-sm rfq-f-btn">جستجو</button>
  </div>
  <div class="rfq-filters-meta">
    <select name="type" class="rfq-f-select" size="1">
      <option value="">همه انواع</option>
      @foreach($documentTypes as $dt)
        <option value="{{ $dt->key }}" @selected(request('type')===$dt->key)>{{ $dt->name_fa }}</option>
      @endforeach
    </select>
    <select name="status" class="rfq-f-select" size="1">
      <option value="">همه وضعیت‌ها</option>
      @foreach($statusLabels as $key => $label)
        <option value="{{ $key }}" @selected(request('status')===$key)>{{ $label }}</option>
      @endforeach
    </select>
    <button type="submit" class="btn btn-primary btn-sm rfq-f-btn">فیلتر</button>
  </div>
</form>


<div class="card docs-list-card" style="overflow:hidden">
  <div class="data-table-desktop">
    <table class="tbl">
      <thead>
        <tr>
          <th>شماره</th>
          <th style="text-align:center">نوع</th>
          <th style="text-align:center">وضعیت نسخه</th>
          <th style="text-align:center">عنوان</th>
          <th style="text-align:center">پرونده</th>
          <th style="text-align:center">تاریخ</th>
          <th style="text-align:center">گزینه‌ها</th>
        </tr>
      </thead>
      <tbody>
        @forelse($documents as $doc)
          @php
            // M24 (درخواست کاربر): رویژن‌های پیش‌نویس اصلاً در این فهرست نمایش
            // داده نمی‌شوند — فقط رویژن‌هایی که حداقل یک‌بار منتشر شده‌اند
            // (isPublished(): status===published یا is_locked — این دومی هم
            // Supersededهای واقعاً‌صادرشده‌ی قبلی را پوشش می‌دهد). «نسخه‌ی
            // پایه»/hasSiblings اما روی مجموعه‌ی *کامل* رویژن‌ها (نه فقط
            // نمایان‌ها) محاسبه می‌شود — چون حذفِ کل سند، رویژن‌های پنهانِ
            // پیش‌نویس را هم واقعاً پاک می‌کند و باید هشدارش درست باشد.
            $allRevsSorted = $doc->revisions->sortBy('revision_number')->values();
            $trueBaseRev = $allRevsSorted->first();
            $hasSiblings = $allRevsSorted->count() > 1;
            $orderedRevs = $allRevsSorted->filter(fn ($r) => $r->isPublished())->values();
          @endphp
          @forelse($orderedRevs as $rev)
            @php
              $isBaseRow = $trueBaseRev && $rev->id === $trueBaseRev->id;
              $revPublished = $rev->isPublished();
              // منطق حذف: اگر همین ردیف، رویژنِ پایه‌ی سند باشد و رویژن‌های
              // دیگری (پنهان یا نمایان) هم داشته باشد، حذف یعنی کل سند —
              // چون آن رویژن‌های دیگر هم همراهش پاک می‌شوند (هشدار قوی‌تر).
              // در غیر این صورت، حذف فقط همین یک رویژن است — قابلیت تازه‌ای
              // که بقیه‌ی سند را دست‌نخورده نگه می‌دارد.
              if ($isBaseRow && $hasSiblings) {
                  $deleteRoute = route('documents.destroy', $doc);
                  $deleteLabel = 'حذف (کل سند)';
                  $deleteMsg = 'این نسخه‌ی پایه (نسخه‌ی اصلی) دارای رویژن‌های دیگری هم هست — با حذف آن، تمامی رویژن‌های این سند نیز حذف خواهند شد. ادامه می‌دهید؟';
              } elseif ($hasSiblings) {
                  $deleteRoute = route('documents.revisions.destroy', $rev);
                  $deleteLabel = 'حذف همین نسخه';
                  $deleteMsg = 'همین یک نسخه حذف شود؟ بقیه‌ی نسخه‌های این سند دست‌نخورده می‌مانند.';
              } else {
                  $deleteRoute = route('documents.destroy', $doc);
                  $deleteLabel = 'حذف';
                  $deleteMsg = 'این سند حذف شود؟';
              }
            @endphp
            <tr style="cursor:pointer" data-row-href="{{ route('documents.show', $doc) }}"
                onclick="if(!event.target.closest('[data-row-actions]')) location.href=this.dataset.rowHref">
              <td style="font-weight:800;color:var(--brand);{{ $isBaseRow ? '' : 'padding-inline-start:15px' }}">{{ $revDisplay($rev, $doc) }}</td>
              <td style="text-align:center"><x-badge tone="info">{{ $doc->documentType->name_fa ?? $doc->type }}</x-badge></td>
              <td style="text-align:center"><x-badge :tone="$revStatusTones[$rev->status] ?? 'muted'" :dot="false">{{ $revStatusLabels[$rev->status] ?? $rev->status }}</x-badge></td>
              <td style="text-align:center">{{ $revTitleDisplay($rev, $isBaseRow) }}</td>
              <td style="text-align:center">@if($doc->case)<a href="{{ route('cases.show', $doc->case) }}" onclick="event.stopPropagation()">{{ $doc->case->case_number }}</a>@else — @endif</td>
              <td style="text-align:center;font-size:12px;color:var(--muted)">{{ $rev->created_at?->diffForHumans() }}</td>
              <td data-row-actions style="text-align:center">
                <x-dropdown label="گزینه‌ها" variant="ghost" size="sm">
                  @if($rev->file_path)
                    <a href="{{ route('documents.revisions.download', $rev) }}" role="menuitem">دانلود Word</a>
                    @if($pdfAvailable ?? false)
                      <a href="{{ route('documents.revisions.download-pdf', $rev) }}" role="menuitem">دانلود PDF</a>
                    @endif
                  @else
                    <span role="menuitem" style="opacity:.5;cursor:default">دانلود (بدون فایل)</span>
                  @endif
                  <div class="sep"></div>
                  @if(!$revPublished)
                    <a href="{{ route('documents.edit', $doc) }}" role="menuitem">ویرایش</a>
                  @else
                    <span role="menuitem" style="opacity:.5;cursor:default;display:block;width:100%;padding:8px 10px;text-align:right" title="سند منتشرشده مستقیم قابل ویرایش نیست">ویرایش</span>
                  @endif
                  <form method="POST" action="{{ route('documents.revisions.copy', $rev) }}" role="none" style="margin:0">
                    @csrf
                    <button type="submit" role="menuitem">ساخت کپی</button>
                  </form>
                  @can('document.delete')
                    <div class="sep"></div>
                    <form method="POST" action="{{ $deleteRoute }}"
                          onsubmit="return confirm('{{ $deleteMsg }}');" role="none" style="margin:0">
                      @csrf @method('DELETE')
                      <button type="submit" role="menuitem" class="danger">{{ $deleteLabel }}</button>
                    </form>
                  @endcan
                </x-dropdown>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" style="text-align:center;color:var(--muted);font-size:12px">این سند نسخه‌ی منتشرشده‌ای ندارد.</td></tr>
          @endforelse
        @empty
        <tr><td colspan="7"><x-empty title="سندی یافت نشد" /></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="data-table-mobile">
    @forelse($documents as $doc)
      @php
        $allRevsSortedM = $doc->revisions->sortBy('revision_number')->values();
        $trueBaseRevM = $allRevsSortedM->first();
        $hasSiblingsM = $allRevsSortedM->count() > 1;
        $orderedRevsM = $allRevsSortedM->filter(fn ($r) => $r->isPublished())->values();
      @endphp
      @foreach($orderedRevsM as $rev)
        @php
          $isBaseRowM = $trueBaseRevM && $rev->id === $trueBaseRevM->id;
          $revPublishedM = $rev->isPublished();
          if ($isBaseRowM && $hasSiblingsM) {
              $deleteRouteM = route('documents.destroy', $doc);
              $deleteLabelM = 'حذف (کل سند)';
              $deleteMsgM = 'این نسخه‌ی پایه (نسخه‌ی اصلی) دارای رویژن‌های دیگری هم هست — با حذف آن، تمامی رویژن‌های این سند نیز حذف خواهند شد. ادامه می‌دهید؟';
          } elseif ($hasSiblingsM) {
              $deleteRouteM = route('documents.revisions.destroy', $rev);
              $deleteLabelM = 'حذف همین نسخه';
              $deleteMsgM = 'همین یک نسخه حذف شود؟ بقیه‌ی نسخه‌های این سند دست‌نخورده می‌مانند.';
          } else {
              $deleteRouteM = route('documents.destroy', $doc);
              $deleteLabelM = 'حذف';
              $deleteMsgM = 'این سند حذف شود؟';
          }
        @endphp
        <div class="mobile-list-card" style="position:relative">
          <a href="{{ route('documents.show', $doc) }}" style="display:block;color:inherit;text-decoration:none">
            <div style="display:flex;justify-content:space-between">
              <strong style="color:var(--brand);{{ $isBaseRowM ? '' : 'padding-inline-start:15px' }}">{{ $revDisplay($rev, $doc) }}</strong>
              <x-badge tone="info">{{ $doc->documentType->name_fa ?? $doc->type }}</x-badge>
            </div>
            <div style="font-weight:700;margin-top:4px">{{ $revTitleDisplay($rev, $isBaseRowM) }}</div>
            <div class="rel-meta">
              {{ $doc->case?->case_number ?? '—' }} ·
              <x-badge :tone="$revStatusTones[$rev->status] ?? 'muted'" :dot="false">{{ $revStatusLabels[$rev->status] ?? $rev->status }}</x-badge>
            </div>
          </a>
          <div style="margin-top:8px">
            <x-dropdown label="گزینه‌ها" variant="ghost" size="sm">
              @if($rev->file_path)
                <a href="{{ route('documents.revisions.download', $rev) }}" role="menuitem">دانلود Word</a>
                @if($pdfAvailable ?? false)
                  <a href="{{ route('documents.revisions.download-pdf', $rev) }}" role="menuitem">دانلود PDF</a>
                @endif
              @else
                <span role="menuitem" style="opacity:.5;cursor:default">دانلود (بدون فایل)</span>
              @endif
              <div class="sep"></div>
              @if(!$revPublishedM)
                <a href="{{ route('documents.edit', $doc) }}" role="menuitem">ویرایش</a>
              @else
                <span role="menuitem" style="opacity:.5;cursor:default;display:block;width:100%;padding:8px 10px;text-align:right" title="سند منتشرشده مستقیم قابل ویرایش نیست">ویرایش</span>
              @endif
              <form method="POST" action="{{ route('documents.revisions.copy', $rev) }}" role="none" style="margin:0">
                @csrf
                <button type="submit" role="menuitem">ساخت کپی</button>
              </form>
              @can('document.delete')
                <div class="sep"></div>
                <form method="POST" action="{{ $deleteRouteM }}"
                      onsubmit="return confirm('{{ $deleteMsgM }}');" role="none" style="margin:0">
                  @csrf @method('DELETE')
                  <button type="submit" role="menuitem" class="danger">{{ $deleteLabelM }}</button>
                </form>
              @endcan
            </x-dropdown>
          </div>
        </div>
      @endforeach
    @empty
      <x-empty title="سندی نیست" />
    @endforelse
  </div>
</div>
@if(method_exists($documents, 'links'))
  <div class="rfq-pagination">{{ $documents->withQueryString()->links() }}</div>
@endif
@endsection
