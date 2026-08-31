@props(['name' => null, 'type' => 'text', 'value' => null])
<input type="{{ $type }}" @if($name) name="{{ $name }}" id="{{ $name }}" @endif
       value="{{ old($name, $value) }}"
       {{ $attributes->merge(['class' => 'input'.($name && $errors->has($name) ? ' is-invalid' : '')]) }}>
