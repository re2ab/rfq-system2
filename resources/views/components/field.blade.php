@props(['label' => null, 'name' => null, 'required' => false, 'help' => null])
<div {{ $attributes->merge(['class' => 'field']) }}>
  @if($label)
    <label class="field-label" @if($name) for="{{ $name }}" @endif>
      {{ $label }}@if($required)<span class="req" aria-hidden="true">*</span>@endif
    </label>
  @endif
  {{ $slot }}
  @if($help)<div class="field-help">{{ $help }}</div>@endif
  @if($name && $errors->has($name))<div class="field-error">{{ $errors->first($name) }}</div>@endif
</div>
