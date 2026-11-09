<a href="{{ route('student_org.dashboard') }}" class="{{ request()->routeIs('student_org.dashboard') ? 'active' : '' }}">
    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="m3 11 9-8 9 8v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
    <span>Dashboard</span>
</a>

<details class="nav-group" @if(request()->routeIs('projects.create', 'projects.edit', 'student_org.proposals', 'student_org.announcements*', 'activities.index', 'activities.show')) open @endif>
    <summary>
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 4h7l3 3h6v13H4V4z" fill="currentColor"/></svg>
        <span>Activities</span>
    </summary>
    <div class="nav-group-links">
        <a href="{{ route('projects.create') }}" class="{{ request()->routeIs('projects.create') || request()->routeIs('projects.edit') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            <span>Submit proposal</span>
        </a>
        <a href="{{ route('student_org.proposals') }}" class="{{ request()->routeIs('student_org.proposals') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 3h9l5 5v13H6V3zm8 1v5h5M9 13h6m-6 4h6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <span>My proposals</span>
        </a>
        <a href="{{ route('student_org.announcements') }}" class="{{ request()->routeIs('student_org.announcements*') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 10v4h3l10 5V5L7 10H4zm3 4 2 6h3l-2-5m10-4a3 3 0 0 1 0 6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <span>Announcements</span>
        </a>
        <a href="{{ route('activities.index') }}" class="{{ request()->routeIs('activities.index') || request()->routeIs('activities.show') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 21s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12Z" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="9" r="2.5" stroke="currentColor" stroke-width="1.8"/></svg>
            <span>Browse activities</span>
        </a>
    </div>
</details>

<details class="nav-group" @if(request()->routeIs('org.about.*', 'organizations.*')) open @endif>
    <summary>
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 21h18M5 21V8l7-5 7 5v13M9 21v-5h6v5" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
        <span>Organization</span>
    </summary>
    <div class="nav-group-links">
        <a href="{{ route('org.about.edit') }}" class="{{ request()->routeIs('org.about.*') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 21h18M5 21V8l7-5 7 5v13M9 21v-5h6v5" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>
            <span>About Us profile</span>
        </a>
        <a href="{{ route('organizations.index') }}" class="{{ request()->routeIs('organizations.*') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 21h18M5 21V8l7-5 7 5v13M9 21v-5h6v5" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>
            <span>Browse organizations</span>
        </a>
    </div>
</details>

@include('partials.nav_templates')

<a href="{{ route('student_org.volunteers') }}" class="{{ request()->routeIs('student_org.volunteers*') ? 'active' : '' }}">
    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="9" cy="8" r="3" stroke="currentColor" stroke-width="1.7"/><path d="M3.5 20v-1.5A4.5 4.5 0 0 1 8 14h2a4.5 4.5 0 0 1 4.5 4.5V20m1-8a3 3 0 1 0 0-6m1 8h1a4 4 0 0 1 4 4v2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
    <span>Volunteer Applications</span>
</a>

<details class="nav-group" @if(request()->routeIs('student_org.attendance*', 'student_org.evaluations', 'evaluations.*')) open @endif>
    <summary>
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 4h14v16H5zM8 8h8m-8 4h8m-8 4h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <span>Activity records</span>
    </summary>
    <div class="nav-group-links">
        <a href="{{ route('student_org.attendance') }}" class="{{ request()->routeIs('student_org.attendance*') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 4h14v16H5zM8 8l1.5 1.5L12 7m1 2h3m-8 4 1.5 1.5L12 12m1 2h3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <span>Attendance</span>
        </a>
        <a href="{{ route('student_org.evaluations') }}" class="{{ request()->routeIs('student_org.evaluations', 'evaluations.*') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="m12 3 2.7 5.5 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1-4.4-4.3 6.1-.9L12 3Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>
            <span>Evaluations</span>
        </a>
    </div>
</details>

<a href="{{ route('student_org.reports') }}" class="{{ request()->routeIs('student_org.reports*') ? 'active' : '' }}">
    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 19V5m0 14h15M9 15v-4m4 4V7m4 8v-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
    <span>Reports</span>
</a>

@include('partials.nav_notifications')
