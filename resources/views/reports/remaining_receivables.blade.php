@extends('layouts.app')
@section('title', 'مطالبات باقی‌مانده')
@section('actions')
  <x-btn variant="ghost" href="{{ route('reports.index') }}">بازگشت</x-btn>
@endsection
@section('content')
@php
  $labels = ['EUR' => 'یورو', 'IRR' => 'ریال', 'USD' => 'دلار'];
@endphp

<div class="card mb-4">
  <div class="card-h">خلاصه به تفکیک ارز قرارداد</div>
  <div class="card-b" style="font-size:13px;color:var(--muted)">
    مبالغ هر ارز <strong>جدا</strong> جمع شده‌اند و با هم جمع نمی‌شوند. ارز همان ارز پیشنهاد مالی / پرونده است.
  </div>
</div>

<div class="rfq-grid-auto" style="gap:12px;margin-bottom:16px">
  @forelse($byCurrency as $cur => $agg)
  <div class="card" style="margin:0">
    <div class="card-h">{{ $labels[$cur] ?? $cur }}</div>
    <div class="card-b text-sm space-y-1">
      <div>تعداد پرونده: <strong>{{ $agg['count'] }}</strong></div>
      <div>قابل دریافت: <strong>{{ number_format($agg['due'], 2) }} {{ $cur }}</strong></div>
      <div>وصول‌شده: <strong style="color:var(--brand)">{{ number_format($agg['paid'], 2) }} {{ $cur }}</strong></div>
      <div>مانده: <strong style="color:var(--danger)">{{ number_format($agg['remain'], 2) }} {{ $cur }}</strong></div>
    </div>
  </div>
  @empty
  <div class="card"><div class="card-b"><x-empty title="پرونده‌ای در وصول نیست" /></div></div>
  @endforelse
</div>

@foreach($byCurrency as $cur => $agg)
@php
  $curRows = array_values(array_filter($rows, fn($r) => $r['currency'] === $cur));
@endphp
<div class="card mb-4">
  <div class="card-h">فهرست — {{ $labels[$cur] ?? $cur }}
    <span class="badge badge-info">مانده کل: {{ number_format($agg['remain'], 2) }} {{ $cur }}</span>
  </div>
  <div class="card-b pad0">
    <table class="tbl">
      <thead>
        <tr>
          <th>شماره</th>
          <th>عنوان</th>
          <th>مشتری</th>
          <th>کارشناس</th>
          <th>قابل دریافت ({{ $cur }})</th>
          <th>وصول‌شده ({{ $cur }})</th>
          <th>مانده ({{ $cur }})</th>
        </tr>
      </thead>
      <tbody>
        @foreach($curRows as $row)
        @php $c = $row['case']; @endphp
        <tr style="cursor:pointer" onclick="location.href='{{ route('cases.show', $c) }}'">
          <td style="font-weight:800;color:var(--brand)">{{ $c->case_number }}</td>
          <td>{{ \Illuminate\Support\Str::limit($c->title, 40) }}</td>
          <td>{{ $c->customer?->name ?? '—' }}</td>
          <td>{{ $c->expert?->name ?? '—' }}</td>
          <td>{{ number_format($row['due'], 2) }}</td>
          <td>{{ number_format($row['paid'], 2) }}</td>
          <td style="font-weight:800;color:{{ $row['remain'] > 0.01 ? 'var(--danger)' : 'var(--brand)' }}">{{ number_format($row['remain'], 2) }}</td>
        </tr>
        @endforeach
      </tbody>
      <tfoot>
        <tr style="background:var(--surface-2);font-weight:800">
          <td colspan="4">جمع {{ $cur }}</td>
          <td>{{ number_format($agg['due'], 2) }} {{ $cur }}</td>
          <td>{{ number_format($agg['paid'], 2) }} {{ $cur }}</td>
          <td style="color:var(--danger)">{{ number_format($agg['remain'], 2) }} {{ $cur }}</td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>
@endforeach

@if(!count($rows))
<div class="card"><div class="card-b"><x-empty title="پرونده‌ای در وضعیت دریافت مطالبات نیست" /></div></div>
@endif
@endsection
