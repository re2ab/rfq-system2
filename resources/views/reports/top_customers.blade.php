@extends('layouts.app')
@section('title', 'بیشترین درخواست‌ها بر اساس مشتری')
@section('actions')
  <x-btn variant="ghost" href="{{ route('reports.index') }}">بازگشت</x-btn>
@endsection
@section('content')
@include('reports._date_filter')
<div class="card">
  <div class="card-h">سازمان‌هایی با بیشترین پرونده در بازه</div>
  <div class="card-b pad0">
    <table class="tbl">
      <thead><tr><th>#</th><th>سازمان</th><th>نوع</th><th>تعداد درخواست</th></tr></thead>
      <tbody>
        @forelse($rows as $i => $row)
        <tr>
          <td>{{ $i+1 }}</td>
          <td>
            @if($row['organization'])
              <a href="{{ route('organizations.show', $row['organization']) }}">{{ $row['organization']->name }}</a>
            @else — @endif
          </td>
          <td>{{ $row['organization']->type_label ?? ($row['organization']->type ?? '—') }}</td>
          <td style="font-weight:800;color:var(--brand)">{{ $row['total'] }}</td>
        </tr>
        @empty
        <tr><td colspan="4"><x-empty title="در این بازه پرونده‌ای نیست" /></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
