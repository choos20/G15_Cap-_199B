@props(['name', 'size' => 20])
<svg {{ $attributes->merge(['width' => $size, 'height' => $size, 'viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.8', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round', 'aria-hidden' => 'true', 'focusable' => 'false']) }}>
    @switch($name)
        @case('clock') <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/> @break
        @case('check-circle') <circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/> @break
        @case('calendar') <rect x="3" y="5" width="18" height="16" rx="3"/><path d="M7 3v4m10-4v4M3 11h18m-14 4h2m6 0h2"/> @break
        @case('calendar-check') <rect x="3" y="5" width="18" height="16" rx="3"/><path d="M7 3v4m10-4v4M3 11h18M9 16l2 2 4-4"/> @break
        @case('desktop') <rect x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21h8m-4-4v4"/> @break
        @case('search') <circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/> @break
        @case('qr') <path d="M8 3H3v5m13-5h5v5M3 16v5h5m13-5v5h-5"/><rect x="8" y="8" width="8" height="8" rx="1"/><path d="M12 8v8m-4-4h8"/> @break
        @case('clipboard') <rect x="5" y="5" width="14" height="16" rx="2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 12h6m-6 4h4"/> @break
        @case('arrow-right') <path d="M4 12h16m-6-6 6 6-6 6"/> @break
        @case('arrow-left') <path d="M20 12H4m6-6-6 6 6 6"/> @break
        @case('chevron-right') <path d="m9 5 7 7-7 7"/> @break
        @case('chevron-down') <path d="m5 9 7 7 7-7"/> @break
        @case('shield') <path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6Z"/><path d="m8 12 3 3 5-6"/> @break
        @case('mail') <rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/> @break
        @case('send') <path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/> @break
        @case('location') <path d="M20 10c0 6-8 11-8 11S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/> @break
        @case('chart') <path d="M4 4v16h16M8 15v-4m5 4V7m5 8v-6"/> @break
        @case('bell') <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21a2 2 0 0 0 4 0"/> @break
        @case('user') <circle cx="12" cy="8" r="3.5"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/> @break
        @case('lock') <rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/> @break
        @case('user-lock') <circle cx="8.5" cy="8" r="3"/><path d="M3.5 20v-1.4A4.6 4.6 0 0 1 8.1 14h.8a4.6 4.6 0 0 1 4.6 4.6V20"/><rect x="14" y="13" width="7" height="6.5" rx="1.2"/><path d="M15.8 13v-1.4a1.7 1.7 0 0 1 3.4 0V13"/> @break
        @case('archive') <path d="M4 7h16v13H4z"/><path d="M3 4h18v3H3zM9 11h6"/> @break
        @case('building') <path d="M3 21h18M5 21V4h10v17m0-11h4v11M8 8h3m-3 4h3m-3 4h3"/> @break
        @case('file') <path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9Zm0 0v6h6M8 13h8m-8 4h5"/> @break
        @case('eye') <path d="M2.5 12s3.5-5.5 9.5-5.5 9.5 5.5 9.5 5.5-3.5 5.5-9.5 5.5S2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="2.5"/> @break
        @case('download') <path d="M12 3v11m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/> @break
        @case('cloud-upload') <path d="M7 18h10a4 4 0 0 0 .7-7.94A6 6 0 0 0 6.2 9.2 4.5 4.5 0 0 0 7 18Z"/><path d="M12 12v7m0-7-3 3m3-3 3 3"/> @break
        @case('paperclip') <path d="m20.5 11.5-7.6 7.6a5 5 0 0 1-7.1-7.1l8.1-8.1a3.5 3.5 0 0 1 5 5l-8.2 8.2a2 2 0 0 1-2.8-2.8l7.6-7.6"/> @break
        @case('home') <path d="m3 10 9-7 9 7v10H3Zm6 10v-7h6v7"/> @break
        @case('settings') <circle cx="12" cy="12" r="3"/><path d="m9 3-.7 2.3-2.4.5L4 9l1.6 1.8v2.4L4 15l1.9 3.2 2.4.5L9 21h6l.7-2.3 2.4-.5L20 15l-1.6-1.8v-2.4L20 9l-1.9-3.2-2.4-.5L15 3Z"/> @break
        @default <circle cx="12" cy="12" r="9"/><path d="M12 8v4m0 4h.01"/>
    @endswitch
</svg>
