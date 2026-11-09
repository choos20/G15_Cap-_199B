{{--
    Templates link. CELP personnel and the CSA Director get the management page;
    everyone else gets the read-only forms list. The item is never rendered
    disabled — each role sees the one destination it can actually use.
--}}
@php
    $tplCanManage = auth()->user()?->canManageTemplates() ?? false;
@endphp

<a href="{{ $tplCanManage ? route('templates.manage') : route('templates.index') }}"
   class="{{ request()->routeIs('templates.*') ? 'active' : '' }}">
    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 3h9l5 5v13H6V3zm8 1v5h5" fill="currentColor"/><path d="M9 12h6M9 16h4" stroke="#0e2a2b" stroke-width="1.6" stroke-linecap="round"/></svg>
    <span>{{ $tplCanManage ? 'Manage Templates' : 'Forms & Requirements' }}</span>
</a>
