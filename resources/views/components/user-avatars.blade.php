@props(['users' => collect(), 'size' => 24])
@php
  $list = collect($users)->filter()->unique('id')->values();
  $sz = (int) $size;
@endphp
@if($list->isNotEmpty())
<span {{ $attributes->merge(['style' => "display:inline-flex;align-items:center;flex-direction:row-reverse"]) }}>
  @foreach($list as $i => $u)
    @php
      $url = method_exists($u, 'avatarUrl') ? $u->avatarUrl() : null;
      $ini = method_exists($u, 'initials') ? $u->initials() : mb_substr($u->name ?? '?', 0, 1);
      $margin = $i === 0 ? '0' : '-6px';
    @endphp
    @if($url)
      <img src="{{ $url }}" alt="{{ $u->name }}" title="{{ $u->name }}"
           width="{{ $sz }}" height="{{ $sz }}"
           style="width:{{ $sz }}px;height:{{ $sz }}px;border-radius:50%;object-fit:cover;border:2px solid #fff;margin-left:{{ $margin }};background:var(--border-soft)">
    @else
      <span title="{{ $u->name }}"
            style="width:{{ $sz }}px;height:{{ $sz }}px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:{{ max(9, $sz*0.4) }}px;font-weight:800;background:var(--brand);color:#fff;border:2px solid #fff;margin-left:{{ $margin }}">{{ $ini }}</span>
    @endif
  @endforeach
</span>
@endif
