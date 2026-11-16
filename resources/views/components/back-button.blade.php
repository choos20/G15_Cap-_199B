@props(['href'])

@once
    <style>
        a.pl-back-button{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:42px;box-sizing:border-box;padding:10px 14px;margin-bottom:18px;border:1px solid #a7f3d0;border-radius:10px;background:#ecfdf5;color:#047857;font-size:.9rem;font-weight:700;line-height:1.4;text-decoration:none;vertical-align:middle;transition:background .15s,border-color .15s}
        a.pl-back-button:hover{background:#d1fae5;border-color:#6ee7b7;color:#065f46;text-decoration:none}
        a.pl-back-button:focus-visible{outline:3px solid #047857;outline-offset:3px}
        .pl-back-button svg{width:18px;height:18px;flex:0 0 18px}
        @media(prefers-reduced-motion:reduce){a.pl-back-button{transition:none}}
    </style>
@endonce
<a href="{{ $href }}" {{ $attributes->class(['pl-back-button']) }}>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5m7-7-7 7 7 7"/></svg>
    <span>{{ $slot }}</span>
</a>
