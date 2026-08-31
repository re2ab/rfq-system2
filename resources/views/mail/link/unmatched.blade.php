@extends('layouts.app')
@section('title','ایمیل‌های بدون پرونده')
@section('actions')
  <a href="{{ route('mail.inbox') }}" class="btn btn-ghost btn-sm">صندوق</a>
@endsection
@section('content')
<div class="card mb-3">
  <div class="card-b text-sm">تعداد بدون لینک پرونده: <strong>{{ fa_num($totalUnmatched) }}</strong></div>
</div>
<div class="card">
  <div class="card-b" style="overflow-x:auto">
    <table class="w-full text-sm" style="border-collapse:collapse">
      <thead>
        <tr style="text-align:right;border-bottom:1px solid #e5e7eb">
          <th class="py-2 px-2">تاریخ</th>
          <th class="py-2 px-2">از</th>
          <th class="py-2 px-2">موضوع</th>
          <th class="py-2 px-2">اکانت</th>
          <th class="py-2 px-2">عملیات</th>
        </tr>
      </thead>
      <tbody>
        @forelse($messages as $m)
          <tr style="border-bottom:1px solid #f3f4f6">
            <td class="py-2 px-2 text-xs">{{ $m->date_sent ? jdatetime($m->date_sent) : '—' }}</td>
            <td class="py-2 px-2" dir="ltr">{{ $m->from_address }}</td>
            <td class="py-2 px-2">{{ $m->subject ?: '(بدون موضوع)' }}</td>
            <td class="py-2 px-2 text-xs">{{ $m->account->email ?? '—' }}</td>
            <td class="py-2 px-2">
              <a class="btn btn-ghost btn-sm" href="{{ route('mail.inbox', ['account'=>$m->mail_account_id,'folder'=>$m->mail_folder_id,'msg'=>$m->id]) }}">باز کردن</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="py-6 text-center text-gray-500">همه ایمیل‌ها لینک شده‌اند یا هنوز همگام‌سازی نشده.</td></tr>
        @endforelse
      </tbody>
    </table>
    <div class="mt-2">{{ $messages->links() }}</div>
  </div>
</div>
@endsection
