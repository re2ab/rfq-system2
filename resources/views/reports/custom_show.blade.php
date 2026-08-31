@extends('layouts.app')
@section('title', $customReport->name)
@section('actions')
  <form method="POST" action="{{ route('reports.custom.destroy', $customReport) }}" onsubmit="return confirm('حذف گزارش؟')">@csrf @method('DELETE')
    <button class="btn btn-ghost" style="color:var(--danger)">حذف گزارش</button>
  </form>
  <a href="{{ route('reports.index') }}" class="btn btn-ghost">بازگشت</a>
@endsection
@section('content')
<div class="card mb-3"><div class="card-b text-sm text-muted">
  موجودیت: <strong>{{ $customReport->entity }}</strong>
  · معیارها: <code>{{ json_encode($customReport->criteria, JSON_UNESCAPED_UNICODE) }}</code>
  · تعداد ردیف: {{ $rows->count() }}
</div></div>
<div class="card"><div class="card-b pad0">
<table class="tbl">
<thead><tr><th>#</th><th>عنوان / نام</th><th>جزئیات</th></tr></thead>
<tbody>
@forelse($rows as $i => $row)
<tr>
  <td>{{ $i+1 }}</td>
  <td>
    @if($customReport->entity==='case')
      <a href="{{ route('cases.show',$row) }}">{{ $row->case_number }} — {{ $row->title }}</a>
    @elseif($customReport->entity==='task')
      <a href="{{ route('tasks.show',$row) }}">{{ $row->title }}</a>
    @elseif($customReport->entity==='contact')
      <a href="{{ route('contacts.card',$row) }}">{{ $row->full_name }}</a>
    @else
      <a href="{{ route('organizations.show',$row) }}">{{ $row->name }}</a>
    @endif
  </td>
  <td class="text-xs text-muted">
    @if(isset($row->current_status)){{ $row->status_label ?? $row->current_status }}@endif
    @if(isset($row->status) && $customReport->entity==='task'){{ $row->status_label ?? $row->status }}@endif
    @if(isset($row->type)){{ $row->type_label ?? $row->type }}@endif
  </td>
</tr>
@empty
<tr><td colspan="3" style="text-align:center;padding:24px">نتیجه‌ای نیست</td></tr>
@endforelse
</tbody></table>
</div></div>
@endsection
