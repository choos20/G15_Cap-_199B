@props(['source' => [], 'size' => 42])

@php
    $source = is_array($source) ? $source : [];
    $avatarSize = max(30, (int) $size);
    $avatarUrl = is_string($source['avatar_url'] ?? null) ? $source['avatar_url'] : null;
    $initials = trim((string) ($source['initials'] ?? 'PL')) ?: 'PL';
    $fallback = (bool) ($source['fallback'] ?? false);
@endphp

<span class="notification-avatar{{ $fallback ? ' is-fallback' : '' }}" style="--notification-avatar-size: {{ $avatarSize }}px" aria-hidden="true">
    @if($avatarUrl)
        <img src="{{ $avatarUrl }}" alt="" onerror="this.hidden=true; this.nextElementSibling.hidden=false">
    @endif
    <span class="notification-avatar__initials" @if($avatarUrl) hidden @endif>{{ $initials }}</span>
</span>
