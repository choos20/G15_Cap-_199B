<details class="nav-group" @if(request()->routeIs('projects.celp_head_review*', 'projects.all', 'audit.index')) open @endif>
    <summary>
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 4h7l3 3h6v13H4V4z" fill="currentColor"/></svg>
        <span>Project oversight</span>
    </summary>
    <div class="nav-group-links">
        <a href="{{ route('projects.celp_head_review') }}" class="{{ request()->routeIs('projects.celp_head_review*') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 4h12v16H6zM9 8h6m-6 4h6m-6 4h4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <span>Projects</span>
        </a>
        <a href="{{ route('projects.all') }}" class="{{ request()->routeIs('projects.all') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 5h16v4H4zM4 11h16v4H4zM4 17h16v3H4z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
            <span>All Projects</span>
        </a>
        <a href="{{ route('audit.index') }}" class="{{ request()->routeIs('audit.index') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 3h9l5 5v13H6V3zm8 1v5h5M9 13h7m-7 4h5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <span>Audit Log</span>
        </a>
    </div>
</details>

<details class="nav-group" @if(request()->routeIs('activities.index', 'activities.show', 'organizations.*', 'templates.*')) open @endif>
    <summary>
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2a7 7 0 0 0-7 7c0 5 7 13 7 13s7-8 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 0 0 5z" fill="currentColor"/></svg>
        <span>Activities &amp; resources</span>
    </summary>
    <div class="nav-group-links">
        <a href="{{ route('activities.index') }}" class="{{ request()->routeIs('activities.index', 'activities.show') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 21s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12Z" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="9" r="2.5" stroke="currentColor" stroke-width="1.8"/></svg>
            <span>Browse Activities</span>
        </a>
        @include('partials.nav_organizations')
        @include('partials.nav_templates')
    </div>
</details>

<a href="{{ route('reports.operational') }}" class="{{ request()->routeIs('reports.*') ? 'active' : '' }}">
    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 17h3v-6H5v6zm5 0h3v-10h-3v10zm5 0h3v-4h-3v4z" fill="currentColor"/></svg>
    <span>Reports</span>
</a>
@include('partials.nav_accounts')
@include('partials.nav_notifications')
