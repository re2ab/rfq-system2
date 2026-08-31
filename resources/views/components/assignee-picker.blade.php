@props(['users' => [], 'selected' => [], 'name' => 'assignee_ids[]'])
@php $selectedIds = collect($selected)->map(fn($v) => (int) $v)->all(); @endphp
<div class="assignee-picker">
  @forelse($users as $u)
    @php $checked = in_array((int) $u->id, $selectedIds, true); @endphp
    <label class="assignee-chip" title="{{ $u->name }}">
      <input type="checkbox" name="{{ $name }}" value="{{ $u->id }}" @checked($checked)>
      <span class="ava">
        @if(method_exists($u, 'avatarUrl') && $u->avatarUrl())
          <img src="{{ $u->avatarUrl() }}" alt="{{ $u->name }}">
        @else
          {{ method_exists($u, 'initials') ? $u->initials() : mb_substr($u->name ?? '?', 0, 1) }}
        @endif
      </span>
      <span class="tick">✓</span>
    </label>
  @empty
    <span class="tag-picker-empty">کاربری برای انتخاب وجود ندارد</span>
  @endforelse
</div>
