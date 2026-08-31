@if(isset($customFields) && $customFields->count())
@php $customVisible = $customVisible ?? []; @endphp
<div class="f-full form-section-sm" style="margin-top:var(--s-2)">{{ __('app.custom_fields') }}</div>
@foreach($customFields as $field)
@php $name = 'cf_'.$field->key; $val = old($name, $customValues[$field->key] ?? ''); $isVisible = old('cf_visible') ? in_array($field->key, (array) old('cf_visible')) : ($customVisible[$field->key] ?? false); @endphp
<div class="f-full" style="display:flex;gap:var(--s-3);align-items:flex-end;flex-wrap:wrap">
  <div style="width:100%;max-width:260px">
    <label class="f-label">{{ $field->label }} @if($field->is_required)<span class="req">*</span>@endif</label>
    @if($field->field_type === 'select')
      <select name="{{ $name }}" @if($field->is_required) required @endif>
        <option value="">—</option>
        @foreach(($field->options ?? []) as $opt)
          <option value="{{ $opt }}" @selected((string)$val === (string)$opt)>{{ $opt }}</option>
        @endforeach
      </select>
    @elseif($field->field_type === 'date')
      <x-jalali-date :name="$name" :value="$val" :required="(bool)$field->is_required" />
    @elseif($field->field_type === 'number')
      <input type="number" step="any" name="{{ $name }}" value="{{ $val }}" @if($field->is_required) required @endif>
    @else
      <input type="text" name="{{ $name }}" value="{{ $val }}" @if($field->is_required) required @endif>
    @endif
  </div>
  <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--muted);white-space:nowrap;padding-bottom:8px">
    <input type="checkbox" name="cf_visible[]" value="{{ $field->key }}" @checked($isVisible)>
    نمایش در بخش اطلاعات تماس
  </label>
</div>
@endforeach
@endif
