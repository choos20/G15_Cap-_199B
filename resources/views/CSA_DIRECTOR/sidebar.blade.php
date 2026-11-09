<a href="{{ route('projects.csa_review') }}" class="{{ request()->routeIs('projects.csa_review*') ? 'active' : '' }}">
    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 4h7l3 3h6v13H4V4z" fill="currentColor"/></svg>
    <span>Projects</span>
</a>
<details class="nav-group" @if(request()->routeIs('activities.index', 'activities.show', 'organizations.*', 'templates.*')) open @endif>
    <summary>
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2a7 7 0 0 0-7 7c0 5 7 13 7 13s7-8 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 0 0 5z" fill="currentColor"/></svg>
        <span>Activities &amp; resources</span>
    </summary>
    <div class="nav-group-links">
        <a href="{{ route('activities.index') }}" class="{{ request()->routeIs('activities.index', 'activities.show') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2a7 7 0 0 0-7 7c0 5 7 13 7 13s7-8 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 0 0 5z" fill="currentColor"/></svg>
            <span>Browse Activities</span>
        </a>
        @include('partials.nav_organizations')
        @include('partials.nav_templates')
    </div>
</details>
<a href="{{ route('csa.reports.index') }}" class="{{ request()->routeIs('csa.reports.*') ? 'active' : '' }}">
    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 17h3v-6H5v6zm5 0h3v-10h-3v10zm5 0h3v-4h-3v4z" fill="currentColor"/></svg>
    <span>Reports</span>
</a>
@include('partials.nav_accounts')
@include('partials.nav_notifications')
