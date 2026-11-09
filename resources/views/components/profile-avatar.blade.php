@props(['name' => 'User', 'photoUrl' => null, 'size' => 40])

@php
    $avatarSize = max(24, (int) $size);
    $initials = collect(preg_split('/\s+/', trim($name)))
        ->filter()
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->take(2)
        ->implode('');
@endphp

<span aria-hidden="true" style="display:inline-flex; align-items:center; justify-content:center; width:{{ $avatarSize }}px; height:{{ $avatarSize }}px; flex:0 0 {{ $avatarSize }}px; overflow:hidden; border:1px solid rgba(209,231,220,0.8); border-radius:50%; background:#0b7656; color:#fff; font-size:{{ max(12, (int) ($avatarSize * 0.38)) }}px; font-weight:800; line-height:1">
    @if($photoUrl)
        <img src="{{ $photoUrl }}" alt="" style="width:100%; height:100%; object-fit:cover" onerror="this.hidden=true; this.nextElementSibling.hidden=false">
    @endif
    <span @if($photoUrl) hidden @endif>{{ $initials }}</span>
</span>
