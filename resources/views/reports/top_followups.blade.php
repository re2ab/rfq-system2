@extends('layouts.app')
@section('title', 'بیشترین تماس و پیگیری')
@section('actions')
  <x-btn variant="ghost" href="{{ route('reports.index') }}">بازگشت</x-btn>
@endsection
@section('content')
@include('reports._date_filter')
<div class="rfq-grid-2">
  <div class="card">
    <div class="card-h">سازمان‌ها — فعالیت و تماس</div>
    <div class="card-b pad0">
      <table class="tbl">
        <thead><tr><th>سازمان</th><th>کل فعالیت</th><th>تماس تلفنی</th></tr></thead>
        <tbody>
          @forelse($byOrg as $row)
          <tr>
            <td>@if($row['organization'])<a href="{{ route('organizations.show', $row['organization']) }}">{{ $row['organization']->name }}</a>@else — @endif</td>
            <td style="font-weight:800">{{ $row['total'] }}</td>
            <td>{{ $row['calls'] }}</td>
          </tr>
          @empty
          <tr><td colspan="3"><x-empty title="داده‌ای نیست" /></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  <div class="card">
    <div class="card-h">مخاطبان — فعالیت و تماس</div>
    <div class="card-b pad0">
      <table class="tbl">
        <thead><tr><th>مخاطب</th><th>کل فعالیت</th><th>تماس</th></tr></thead>
        <tbody>
          @forelse($byContact as $row)
          <tr>
            <td>
              @if($row['contact'])
                <a href="{{ route('contacts.show', $row['contact']) }}">{{ $row['contact']->full_name ?? ($row['contact']->first_name.' '.$row['contact']->last_name) }}</a>
              @else — @endif
            </td>
            <td style="font-weight:800">{{ $row['total'] }}</td>
            <td>{{ $row['calls'] }}</td>
          </tr>
          @empty
          <tr><td colspan="3"><x-empty title="فعالیت با مخاطب ثبت نشده" /></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
