@extends('layouts.app')
@section('title','صندوق ایمیل یکپارچه')
@section('content')
<div class="card">
  <div class="card-b text-sm">
    <p class="mb-3">{{ $message ?? 'اکانتی در دسترس نیست.' }}</p>
    @if(auth()->user() && ((method_exists(auth()->user(), 'can') && auth()->user()->can('settings.manage')) || (method_exists(auth()->user(), 'hasRole') && auth()->user()->hasRole('admin'))))
      <a href="{{ route('mail.accounts.index') }}" class="btn btn-primary btn-sm">مدیریت اکانت‌ها</a>
    @endif
  </div>
</div>
@endsection
