@extends('layouts.app')
@section('title', 'گزارش‌ها')
@section('actions')
  <x-btn href="{{ route('reports.custom.create') }}">گزارش اختصاصی جدید</x-btn>
@endsection
@section('content')

@php
$sections = [
  'پایه' => [
    ['reports.pipeline','پایپ‌لاین / وضعیت','تعداد در هر وضعیت'],
    ['reports.performance','عملکرد کارشناسان','باز، برد، باخت'],
    ['reports.tasks','وضعیت وظایف','باز و سررسید'],
    ['reports.aging','Aging مطالبات','سررسید و معوق'],
  ],
  'عملیاتی (درخواست مشتری)' => [
    ['reports.top-customers','بیشترین درخواست مشتری','رتبه سازمان در بازه'],
    ['reports.top-contacts','مخاطبان پرتقاضا','مرتبط با پرونده‌ها'],
    ['reports.top-followups','بیشترین تماس و پیگیری','سازمان و مخاطب'],
    ['reports.stuck-cases','ماندگار در وضعیت','بیش از N روز'],
    ['reports.received-count','درخواست دریافتی بازه','کل + روزانه'],
    ['reports.lost-count','بازنده در بازه','تعداد و فهرست'],
  ],
  'اولویت مدیریتی' => [
    ['reports.conversion-funnel','قیف تبدیل','٪ در هر مرحله + نرخ برد'],
    ['reports.win-loss-monthly','برد/باخت ماهانه','روند ماهانه'],
    ['reports.pipeline-value','ارزش پایپ‌لاین باز','جمع مبلغ اسناد'],
    ['reports.expert-workload','بار کارشناس','پرونده + وظیفه'],
    ['reports.cycle-time','زمان چرخه','میانگین روز تا بستن'],
    ['reports.documents-by-type','اسناد بر اساس نوع','تعداد و مبلغ'],
    ['reports.overdue-tasks','وظایف معوق','به تفکیک مسئول'],
  ],
  'مالی و قرارداد' => [
    ['reports.vat-incoterm','VAT و ترم تحویل','CPT/CFR در برابر DDP'],
    ['reports.receivables-summary','خلاصه مطالبات','معوق / این هفته / وصول'],
    ['reports.invoice-gaps','فاکتور و مطالبه ناقص','شکاف داده'],
    ['reports.payments-period','پرداخت‌ها در بازه','جریان نقد'],
    ['reports.remaining-receivables','مطالبات باقی‌مانده (در جریان وصول)','پرونده‌های وضعیت دریافت مطالبات'],
  ],
  'کیفیت پیگیری' => [
    ['reports.inactive-cases','بدون فعالیت N روز','بر اساس تایم‌لاین'],
    ['reports.call-ratio','نسبت تماس به پرونده','شدت پیگیری'],
    ['reports.unmatched-emails','ایمیل بدون پرونده','matching'],
    ['reports.status-audit','ممیزی تغییر وضعیت','انتقال‌ها'],
  ],
  'مشتری و بازار' => [
    ['reports.one-time-orgs','سازمان تک‌درخواست','فرصت تکرار'],
    ['reports.won-customers','مشتریان برده‌شده','N ماه اخیر'],
    ['reports.top-suppliers','تأمین‌کننده پرتکرار','case_suppliers'],
  ],
];
@endphp

@foreach($sections as $title => $items)
<div class="card mb-4">
  <div class="card-h">{{ $title }}</div>
  <div class="card-b pad0">
    @foreach($items as [$route, $name, $meta])
      <a href="{{ route($route) }}" class="rel-item block">
        <div class="font-semibold" style="color:var(--brand)">{{ $name }}</div>
        <div class="rel-meta">{{ $meta }}</div>
      </a>
    @endforeach
  </div>
</div>
@endforeach

@php
  try { $customs = \App\Models\CustomReport::orderByDesc('id')->limit(50)->get(); }
  catch (\Throwable $e) { $customs = collect(); }
@endphp
<div class="card">
  <div class="card-h">گزارش‌های اختصاصی ذخیره‌شده</div>
  <div class="card-b pad0">
    @forelse($customs as $r)
      <div class="rel-item flex justify-between items-center gap-2">
        <a href="{{ route('reports.custom.show', $r) }}" class="font-semibold" style="color:var(--brand)">{{ $r->name }}</a>
        <form method="POST" action="{{ route('reports.custom.destroy', $r) }}" onsubmit="return confirm('حذف؟')">@csrf @method('DELETE')
          <button class="text-xs text-red-600">حذف</button>
        </form>
      </div>
    @empty
      <p class="p-4 text-sm" style="color:var(--muted)">گزارش اختصاصی ذخیره نشده.</p>
    @endforelse
  </div>
</div>
@endsection
