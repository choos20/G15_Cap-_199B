<a href="{{ route('projects.secretary_review') }}" class="{{ request()->routeIs('projects.secretary_review*') ? 'active' : '' }}">
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
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 21s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12Z" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="9" r="2.5" stroke="currentColor" stroke-width="1.8"/></svg>
            <span>Browse Activities</span>
        </a>
        @include('partials.nav_organizations')
        @include('partials.nav_templates')
    </div>
</details>

<details class="nav-group" @if(request()->routeIs('celp.secretary.announcements*', 'student_org.evaluations')) open @endif>
    <summary>
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 9h2v6H4V9zm3 2h10l3-3v8l-3-3H7v-2z" fill="currentColor"/></svg>
        <span>Engagement</span>
    </summary>
    <div class="nav-group-links">
        <a href="{{ route('celp.secretary.announcements') }}" class="{{ request()->routeIs('celp.secretary.announcements*') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 10v4h3l10 5V5L7 10H4zm3 4 2 6h3l-2-5m10-4a3 3 0 0 1 0 6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <span>Announcements</span>
        </a>
        <a href="{{ route('student_org.evaluations') }}" class="{{ request()->routeIs('student_org.evaluations') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="m12 3 2.7 5.5 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1-4.4-4.3 6.1-.9L12 3Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>
            <span>Evaluations</span>
        </a>
    </div>
</details>

<a href="{{ route('reports.secretary') }}" class="{{ request()->routeIs('reports.secretary*') ? 'active' : '' }}">
    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 17h3v-6H5v6zm5 0h3v-10h-3v10zm5 0h3v-4h-3v4z" fill="currentColor"/></svg>
    <span>Reports</span>
</a>
@include('partials.nav_accounts')
@include('partials.nav_notifications')
