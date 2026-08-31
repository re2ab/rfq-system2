{{--
  Colored, toggleable tag chips — replaces the plain <select multiple> box.
  Usage: @include('partials.tag_picker', ['tags' => $tags, 'selected' => $selectedIds, 'name' => 'tag_ids[]'])
--}}
@php
  $selected = collect($selected ?? []);
  $inputName = $name ?? 'tag_ids[]';
@endphp
<div class="tag-picker">
  @forelse($tags as $tag)
    <label class="tag-chip" style="--tc: {{ $tag->color ?? 'var(--muted)' }}">
      <input type="checkbox" name="{{ $inputName }}" value="{{ $tag->id }}" @checked($selected->contains($tag->id))>
      <span class="dot"></span>{{ $tag->name }}
    </label>
  @empty
    <span class="tag-picker-empty">تگی تعریف نشده است.</span>
  @endforelse
</div>
