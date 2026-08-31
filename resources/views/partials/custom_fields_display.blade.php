@if(isset($customFields) && $customFields->count())
@php
  $customVisible = $customVisible ?? [];
  $visibleFields = $customFields->filter(fn ($f) => $customVisible[$f->key] ?? false);
@endphp
@if($visibleFields->count())
  @foreach($visibleFields as $field)
    <div class="field-row"><span class="lbl">{{ $field->label }}</span><span class="val">{{ $customValues[$field->key] ?? '—' }}</span><span></span></div>
  @endforeach
@endif
@endif
