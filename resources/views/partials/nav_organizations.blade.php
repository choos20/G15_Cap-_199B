{{--
    Organization directory link. Shared by every role — participants discover
    who runs the activities they join, staff get oversight, and organizations
    can see their peers.
--}}
<a href="{{ route('organizations.index') }}"
   class="{{ request()->routeIs('organizations.*') ? 'active' : '' }}">
    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 21h18M5 21V8l7-5 7 5v13M9 21v-5h6v5" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
    <span>Organizations</span>
</a>
