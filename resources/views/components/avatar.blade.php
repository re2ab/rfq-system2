@props(['user' => null, 'name' => null, 'size' => 28])
@php
  $label = $name ?? ($user->name ?? '?');
  $url = $user && method_exists($user, 'avatarUrl') ? $user->avatarUrl() : null;
  $ini = $user && method_exists($user, 'initials') ? $user->initials() : mb_substr($label, 0, 1);
  $s = (int) $size;
@endphp
<span {{ $attributes->merge(['class' => 'avatar']) }} title="{{ $label }}" style="width:{{ $s }}px;height:{{ $s }}px;font-size:{{ max(9, (int)($s * .4)) }}px">
  @if($url)<img src="{{ $url }}" alt="{{ $label }}">@else{{ $ini }}@endif
</span>
