<a href="{{ route('participant.activities') }}" class="{{ request()->routeIs('participant.activities*') ? 'active' : '' }}">
    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 4h7l3 3h6v13H4V4z" fill="currentColor"/></svg>
    <span>Browse Activities</span>
</a>
@include('partials.nav_organizations')
@include('partials.nav_templates')
<a href="{{ route('participant.applications') }}" class="{{ request()->routeIs('participant.applications*') ? 'active' : '' }}">
    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 3h9l6 6v12a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z" fill="currentColor"/><path d="M14 3v6h6" fill="currentColor"/></svg>
    <span>My Applications</span>
</a>
<a href="{{ route('participant.scanner') }}" class="{{ request()->routeIs('participant.scanner*') ? 'active' : '' }}">
    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 4h4v2H6v2H4V4zm12 0h4v4h-2V6h-2V4zM4 16h2v2h2v2H4v-4zm14 2v-2h2v4h-4v-2h2zM9 9h6v6H9V9z" fill="currentColor"/></svg>
    <span>Scanner</span>
</a>
<a href="{{ route('participant.attendance') }}" class="{{ request()->routeIs('participant.attendance') ? 'active' : '' }}">
    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 5h16v15H4zM8 3v4m8-4v4M7 11h3m4 0h3m-10 4h3m4 0h3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
    <span>My Attendance</span>
</a>
@include('partials.nav_notifications')
