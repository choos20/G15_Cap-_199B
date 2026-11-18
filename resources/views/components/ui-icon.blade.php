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
        @case('chevron-right') <path d="m9 5 7 7-7 7"/> @break
        @case('location') <path d="M20 10c0 6-8 11-8 11S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/> @break
        @case('chart') <path d="M4 4v16h16M8 15v-4m5 4V7m5 8v-6"/> @break
        @case('bell') <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21a2 2 0 0 0 4 0"/> @break
        @case('building') <path d="M3 21h18M5 21V4h10v17m0-11h4v11M8 8h3m-3 4h3m-3 4h3"/> @break
        @case('file') <path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9Zm0 0v6h6M8 13h8m-8 4h5"/> @break
        @case('home') <path d="m3 10 9-7 9 7v10H3Zm6 10v-7h6v7"/> @break
        @case('settings') <circle cx="12" cy="12" r="3"/><path d="m9 3-.7 2.3-2.4.5L4 9l1.6 1.8v2.4L4 15l1.9 3.2 2.4.5L9 21h6l.7-2.3 2.4-.5L20 15l-1.6-1.8v-2.4L20 9l-1.9-3.2-2.4-.5L15 3Z"/> @break
        @default <circle cx="12" cy="12" r="9"/><path d="M12 8v4m0 4h.01"/>
    @endswitch
</svg>
