@props(['organization' => null, 'size' => 32])

@php
    $organizationName = $organization?->organization_name ?: $organization?->name ?: 'Organization';
    $logoUrl = $organization?->orgLogoUrl();
    $avatarSize = max(24, (int) $size);
@endphp

<span aria-hidden="true" style="display:inline-flex; align-items:center; justify-content:center; width:{{ $avatarSize }}px; height:{{ $avatarSize }}px; flex:0 0 {{ $avatarSize }}px; overflow:hidden; border:1px solid #d1e7dc; border-radius:10px; background:#ecfdf5; color:#047857; font-size:{{ max(12, (int) ($avatarSize * 0.42)) }}px; font-weight:800; line-height:1">
    @if($logoUrl)
        <img src="{{ $logoUrl }}" alt="" style="width:100%; height:100%; object-fit:cover" onerror="this.hidden=true; this.nextElementSibling.hidden=false">
    @endif
    <span @if($logoUrl) hidden @endif>{{ mb_strtoupper(mb_substr($organizationName, 0, 1)) }}</span>
</span>
