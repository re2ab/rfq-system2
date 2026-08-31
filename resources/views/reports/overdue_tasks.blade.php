@extends('layouts.app')
@section('title','وظایف معوق')
@section('actions')<x-btn variant="ghost" href="{{ route('reports.index') }}">بازگشت</x-btn>@endsection
@section('content')
<div class="card mb-4"><div class="card-h">تعداد به تفکیک مسئول</div><div class="card-b pad0">
<table class="tbl"><thead><tr><th>کاربر ID</th><th>تعداد معوق</th></tr></thead>
<tbody>@foreach($byUser as $uid=>$cnt)<tr><td>{{ $uid ?: '—' }}</td><td style="font-weight:800">{{ $cnt }}</td></tr>@endforeach</tbody></table>
</div></div>
<div class="card"><div class="card-h">فهرست</div><div class="card-b pad0">
<table class="tbl"><thead><tr><th>عنوان</th><th>مسئول</th><th>موعد</th><th>پرونده</th></tr></thead>
<tbody>
@forelse($tasks as $t)
<tr><td><a href="{{ route('tasks.show',$t) }}">{{ $t->title }}</a></td>
<td>{{ $t->assignee?->name ?? '—' }}</td>
<td style="color:var(--danger)">{{ $t->due_at }}</td>
<td>{{ $t->case?->case_number ?? '—' }}</td></tr>
@empty<tr><td colspan="4"><x-empty title="وظیفه معوقی نیست" /></td></tr>@endforelse
</tbody></table>
</div></div>
@endsection
