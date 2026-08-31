@extends('layouts.app')
@section('title', 'مخاطبان با بیشترین درخواست')
@section('actions')
  <x-btn variant="ghost" href="{{ route('reports.index') }}">بازگشت</x-btn>
@endsection
@section('content')
@include('reports._date_filter')
<div class="card">
  <div class="card-h">مخاطبان سازمان‌هایی که بیشترین پرونده را داشته‌اند</div>
  <div class="card-b pad0">
    <table class="tbl">
      <thead><tr><th>#</th><th>مخاطب</th><th>سازمان</th><th>تعداد پرونده مرتبط</th></tr></thead>
      <tbody>
        @forelse($rows as $i => $row)
        <tr>
          <td>{{ $i+1 }}</td>
          <td>
            @if($row['contact'])
              <a href="{{ route('contacts.show', $row['contact']) }}">{{ trim(($row['contact']->first_name??'').' '.($row['contact']->last_name??'')) ?: $row['contact']->name ?? '—' }}</a>
            @else — @endif
          </td>
          <td>{{ $row['contact']->organization->name ?? '—' }}</td>
          <td style="font-weight:800;color:var(--brand)">{{ $row['total'] }}</td>
        </tr>
        @empty
        <tr><td colspan="4"><x-empty title="موردی نیست" /></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
