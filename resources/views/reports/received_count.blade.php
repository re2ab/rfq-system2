@extends('layouts.app')
@section('title', 'درخواست‌های دریافتی در بازه')
@section('actions')
  <x-btn variant="ghost" href="{{ route('reports.index') }}">بازگشت</x-btn>
@endsection
@section('content')
@include('reports._date_filter')
<div class="grid-stats">
  <x-stat label="کل درخواست در بازه" :value="$total" tone="brand" />
</div>
<div class="rfq-grid-2">
  <div class="card">
    <div class="card-h">به تفکیک روز</div>
    <div class="card-b pad0">
      <table class="tbl">
        <thead><tr><th>تاریخ</th><th>تعداد</th></tr></thead>
        <tbody>
          @forelse($byDay as $row)
          <tr><td>{{ $row->d }}</td><td style="font-weight:800">{{ $row->total }}</td></tr>
          @empty
          <tr><td colspan="2"><x-empty title="صفر" /></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  <div class="card">
    <div class="card-h">وضعیت فعلی همان پرونده‌ها</div>
    <div class="card-b pad0">
      <table class="tbl">
        <thead><tr><th>وضعیت</th><th>تعداد</th></tr></thead>
        <tbody>
          @foreach(\App\Models\CaseModel::STATUSES as $k => $lab)
            @if(($byStatus[$k] ?? 0) > 0)
            <tr><td>{{ $lab }}</td><td style="font-weight:800">{{ $byStatus[$k] }}</td></tr>
            @endif
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
