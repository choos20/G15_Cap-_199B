@php
    $accountRoles = creatable_roles_for(auth()->user());
    $orgOnly = auth()->user()->role === 'csa_director';
@endphp

@if($accountRoles)
    <a href="{{ route('users.create') }}" class="{{ request()->routeIs('users.create') ? 'active' : '' }}">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        <span>{{ $orgOnly ? 'Create org' : 'Create account' }}</span>
    </a>
    <a href="{{ route('users.index') }}" class="{{ request()->routeIs('users.index') || request()->routeIs('users.edit') ? 'active' : '' }}">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 7a3 3 0 1 1 6 0 3 3 0 0 1-6 0zm0 7a5 5 0 0 1 10 0v2H6v-2z" fill="currentColor"/></svg>
        <span>{{ $orgOnly ? 'Manage org' : 'Manage accounts' }}</span>
    </a>
@endif
