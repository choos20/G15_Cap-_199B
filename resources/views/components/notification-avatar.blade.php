@props(['source' => [], 'size' => 44])

@php
    $source = is_array($source) ? $source : [];
    $avatarSize = max(30, (int) $size);
    $avatarType = (string) ($source['avatar_type'] ?? (($source['fallback'] ?? false) ? 'fallback' : 'person'));
    $isLogo = in_array($avatarType, ['organization', 'fallback'], true);
    $avatarUrl = is_string($source['avatar_url'] ?? null) ? $source['avatar_url'] : null;
    $initials = trim((string) ($source['initials'] ?? 'PL')) ?: 'PL';
    $fallback = (bool) ($source['fallback'] ?? false);
    // Keep the neutral ProjectLink identity for deleted/unknown senders, but
    // let organizations without a logo use their own initials as the fallback.
    $usesProjectLinkFallback = $avatarType === 'fallback' && $avatarUrl === null;
    if ($usesProjectLinkFallback) {
        $avatarUrl = asset('images/Logo_nobg.png');
        $initials = 'PL';
        $fallback = true;
    }
@endphp

<span class="notification-avatar{{ $isLogo ? ' is-logo' : '' }}{{ $fallback ? ' is-fallback' : '' }}" style="--notification-avatar-size: {{ $avatarSize }}px" aria-hidden="true">
    @if($avatarUrl)
        <img src="{{ $avatarUrl }}" alt="" data-fallback-initials="{{ $initials }}" onerror="this.onerror=null; const fallback=document.createElement('span'); fallback.className='notification-avatar__initials'; fallback.textContent=this.dataset.fallbackInitials; this.replaceWith(fallback)">
    @else
        <span class="notification-avatar__initials">{{ $initials }}</span>
    @endif
</span>
