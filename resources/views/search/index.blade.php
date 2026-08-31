@extends('layouts.app')
@section('title','جستجو')
@section('content')
<form method="GET" class="mb-6 flex gap-2">
  <input name="q" value="{{ $q }}" placeholder="جستجو در پرونده، مخاطب، سازمان، سند، وظیفه..." class="flex-1 border rounded px-3 py-2 text-sm">
  <button class="bg-blue-600 text-white px-4 py-2 rounded text-sm">جستجو</button>
</form>
@if(mb_strlen($q)>=2)
@foreach(['cases'=>'پرونده‌ها','contacts'=>'مخاطبان','organizations'=>'سازمان‌ها','documents'=>'اسناد','tasks'=>'وظایف'] as $key=>$label)
<div class="bg-white rounded-lg shadow p-4 mb-4 text-sm">
  <h2 class="font-semibold mb-2">{{ $label }} ({{ $results[$key]->count() }})</h2>
  @forelse($results[$key] as $item)
    <div class="border-b py-1">
      @if($key==='cases')<a class="text-blue-600" href="{{ route('cases.show',$item) }}">{{ $item->case_number }} — {{ $item->title }}</a>
      @elseif($key==='contacts')<a class="text-blue-600" href="{{ route('contacts.card',$item) }}">{{ $item->full_name ?? ($item->first_name.' '.$item->last_name) }}</a>
      @elseif($key==='organizations')<a class="text-blue-600" href="{{ route('organizations.show',$item) }}">{{ $item->name }}</a>
      @elseif($key==='documents')<a class="text-blue-600" href="{{ route('documents.show',$item) }}">{{ $item->document_number }}</a>
      @else<a class="text-blue-600" href="{{ route('tasks.show',$item) }}">{{ $item->title }}</a>
      @endif
    </div>
  @empty
    <p class="text-gray-500">موردی نیست</p>
  @endforelse
</div>
@endforeach
@endif
@endsection
