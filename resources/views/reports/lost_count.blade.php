@extends('layouts.app')
@section('title', 'پرونده‌های بازنده در بازه')
@section('actions')
  <x-btn variant="ghost" href="{{ route('reports.index') }}">بازگشت</x-btn>
@endsection
@section('content')
@include('reports._date_filter')
<div class="grid-stats">
  <x-stat label="تعداد بازنده در بازه" :value="$total" tone="danger" />
</div>
<div class="card">
  <div class="card-h">فهرست</div>
  <div class="card-b pad0">
    <table class="tbl">
      <thead><tr><th>شماره</th><th>عنوان</th><th>مشتری</th><th>کارشناس</th><th>آخرین به‌روزرسانی</th></tr></thead>
      <tbody>
        @forelse($cases as $c)
        <tr style="cursor:pointer" onclick="location.href='{{ route('cases.show', $c) }}'">
          <td style="font-weight:800;color:var(--brand)">{{ $c->case_number }}</td>
          <td>{{ \Illuminate\Support\Str::limit($c->title, 40) }}</td>
          <td>{{ $c->customer?->name ?? '—' }}</td>
          <td>{{ $c->expert?->name ?? '—' }}</td>
          <td>{{ $c->updated_at?->format('Y-m-d') }}</td>
        </tr>
        @empty
        <tr><td colspan="5"><x-empty title="بازنده‌ای در این بازه نیست" /></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
