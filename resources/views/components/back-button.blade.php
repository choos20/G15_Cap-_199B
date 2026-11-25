@props(['href'])

@php($backLabel = trim(strip_tags((string) $slot)))

@once
    <style>
        a.pl-back-button{position:relative;display:inline-flex;align-items:center;justify-content:center;width:44px;height:44px;box-sizing:border-box;padding:0;margin:0 0 18px;border:1px solid #a7f3d0;border-radius:50%;background:#ecfdf5;color:#064e3b;text-decoration:none;vertical-align:middle;box-shadow:0 7px 18px rgba(8,48,27,.12);transition:background .18s ease,border-color .18s ease,box-shadow .18s ease,transform .18s ease}
        a.pl-back-button:hover{background:#d1fae5;border-color:#6ee7b7;color:#064e3b;text-decoration:none;transform:translateY(-2px);box-shadow:0 10px 22px rgba(8,48,27,.18)}
        a.pl-back-button:focus-visible{outline:3px solid rgba(4,120,87,.35);outline-offset:3px}
        .pl-back-button svg{width:22px;height:22px;flex:0 0 22px}
        a.pl-back-button::after{position:absolute;top:calc(100% + 8px);left:0;z-index:4;padding:6px 9px;border-radius:7px;background:rgba(8,32,19,.92);color:#fff;content:attr(data-tooltip);font-size:.72rem;font-weight:600;line-height:1;opacity:0;pointer-events:none;transform:translateY(-3px);white-space:nowrap;transition:opacity .16s ease,transform .16s ease}
        a.pl-back-button:hover::after,a.pl-back-button:focus-visible::after{opacity:1;transform:translateY(0)}
        @media(prefers-reduced-motion:reduce){a.pl-back-button{transition:none}}
    </style>
@endonce
<a href="{{ $href }}" data-tooltip="{{ $backLabel }}" title="{{ $backLabel }}" aria-label="{{ $backLabel }}" {{ $attributes->class(['pl-back-button']) }}>
    <x-ui-icon name="arrow-left" :size="22" />
</a>
