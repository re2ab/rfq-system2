@extends('layouts.app')
@section('title','نسبت تماس به پرونده')
@section('actions')<x-btn variant="ghost" href="{{ route('reports.index') }}">بازگشت</x-btn>@endsection
@section('content')
@include('reports._date_filter')
<div class="grid-stats">
  <x-stat label="پرونده در بازه" :value="$cases" />
  <x-stat label="کل فعالیت" :value="$acts" />
  <x-stat label="گزارش تماس" :value="$calls" tone="brand" />
  <x-stat label="تماس / پرونده" :value="$ratio" />
  <x-stat label="فعالیت / پرونده" :value="$actRatio" />
</div>
@endsection
