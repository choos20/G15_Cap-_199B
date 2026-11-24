@extends('layouts.admin')

@section('title', 'Login audit logs')
@section('subtitle', $scopeLabel)

@section('content')
    <style>
        .la-hero { display:flex; align-items:flex-start; justify-content:space-between; gap:18px; padding:22px; margin-bottom:16px; border:1px solid #dbeafe; border-radius:18px; background:linear-gradient(135deg,#eff6ff 0%,#fff 70%); }
        .la-hero h3 { margin:0; color:#1e3a8a; font-size:1.1rem; }
        .la-hero p { max-width:720px; margin:7px 0 0; color:#526886; line-height:1.55; }
        .la-mark { display:flex; align-items:center; justify-content:center; flex:0 0 48px; width:48px; height:48px; border-radius:15px; background:#dbeafe; color:#2563eb; }
        .la-mark svg { width:25px; height:25px; }
        .la-stats { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; margin-bottom:16px; }
        .la-stat { padding:16px 18px; border:1px solid #e5e7eb; border-radius:15px; background:#fff; box-shadow:0 6px 18px rgba(15,23,42,.04); }
        .la-stat-label { color:#52665d; font-size:.76rem; font-weight:800; letter-spacing:.04em; text-transform:uppercase; }
        .la-stat-value { margin-top:8px; color:#0f172a; font-size:1.35rem; font-weight:800; }
        .la-filter { display:grid; grid-template-columns:minmax(180px,1.5fr) repeat(4,minmax(120px,1fr)) auto; align-items:end; gap:10px; padding:15px; margin-bottom:16px; border:1px solid #e5e7eb; border-radius:16px; background:#fff; box-shadow:0 6px 18px rgba(15,23,42,.04); }
        .la-field label { display:block; margin:0 0 6px; color:#52665d; font-size:.72rem; font-weight:800; letter-spacing:.04em; text-transform:uppercase; }
        .la-field input,.la-field select { width:100%; min-height:40px; padding:9px 10px; border:1px solid #dbe3ea; border-radius:9px; background:#fff; color:#0f172a; font:inherit; font-size:.84rem; outline:none; }
        .la-field input:focus,.la-field select:focus { border-color:#60a5fa; box-shadow:0 0 0 3px rgba(59,130,246,.12); }
        .la-filter button { min-height:40px; padding:9px 14px; border:1px solid #2563eb; border-radius:9px; background:#2563eb; color:#fff; font:inherit; font-size:.84rem; font-weight:800; cursor:pointer; }
        .la-filter button:hover { background:#1d4ed8; }
        .la-check { display:flex; align-items:center; gap:7px; min-height:40px; padding:0 8px; color:#475569; font-size:.78rem; font-weight:700; white-space:nowrap; }
        .la-table-panel { overflow:hidden; padding:0; border:1px solid #e5e7eb; border-radius:16px; background:#fff; box-shadow:0 6px 18px rgba(15,23,42,.04); }
        .la-table-wrap { overflow-x:auto; }
        .la-table { width:100%; border-collapse:collapse; }
        .la-table th { padding:12px 16px; color:#52665d; background:#f8fafc; border-bottom:1px solid #eef2f7; text-align:left; font-size:.7rem; letter-spacing:.04em; text-transform:uppercase; }
        .la-table td { padding:14px 16px; color:#334155; border-bottom:1px solid #f1f5f9; vertical-align:top; font-size:.81rem; }
        .la-table tbody tr:last-child td { border-bottom:0; }
        .la-table tbody tr:hover { background:#fbfdff; }
        .la-user strong { display:block; color:#0f172a; font-size:.84rem; }
        .la-user small,.la-sub { display:block; margin-top:4px; color:#5b7067; font-size:.73rem; }
        .la-role { display:inline-flex; padding:4px 8px; border-radius:999px; background:#eff6ff; color:#1d4ed8; font-size:.7rem; font-weight:800; white-space:nowrap; }
        .la-status { display:inline-flex; align-items:center; gap:5px; padding:5px 8px; border-radius:999px; font-size:.7rem; font-weight:800; white-space:nowrap; }
        .la-status-dot { width:6px; height:6px; border-radius:50%; background:currentColor; }
        .la-flag { display:block; margin-top:6px; color:#b91c1c; font-size:.7rem; font-weight:800; }
        .la-empty { padding:48px 22px; color:#52665d; text-align:center; }
        .la-empty strong { display:block; margin-bottom:5px; color:#334155; }
        .la-pagination { padding:16px; border-top:1px solid #eef2f7; }
        @media (max-width:1100px) { .la-filter { grid-template-columns:repeat(3,minmax(0,1fr)); } .la-filter .la-search-field { grid-column:span 2; } }
        @media (max-width:760px) {
            .la-hero { flex-direction:column; }
            .la-stats { grid-template-columns:1fr; }
            .la-filter,.la-filter .la-search-field { grid-template-columns:1fr; grid-column:auto; }
            .la-table thead { display:none; }
            .la-table,.la-table tbody,.la-table tr,.la-table td { display:block; width:100%; }
            .la-table tr { padding:13px 16px; border-bottom:1px solid #eef2f7; }
            .la-table tbody tr:last-child { border-bottom:0; }
            .la-table td { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; padding:7px 0; border:0; text-align:right; }
            .la-table td::before { flex:0 0 34%; color:#5b7067; content:attr(data-label); font-size:.69rem; font-weight:800; letter-spacing:.04em; text-align:left; text-transform:uppercase; }
            .la-table td > * { max-width:66%; }
        }
    </style>

    <section class="la-hero">
        <div style="display:flex; align-items:flex-start; gap:13px; min-width:0;">
            <div class="la-mark" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3h11A2.5 2.5 0 0 1 20 5.5v13a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 18.5v-13Z" stroke="currentColor" stroke-width="1.7"/><path d="M8 8h8M8 12h5M8 16h3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><circle cx="17" cy="16" r="2.5" stroke="currentColor" stroke-width="1.5"/></svg>
            </div>
            <div>
                <h3>Authorized security monitoring</h3>
                <p>Review sign-in patterns for the accounts covered by your role. Passwords and submitted login identifiers are never stored in these records.</p>
            </div>
        </div>
        <a href="{{ route('account.security') }}" style="display:inline-flex; align-items:center; gap:7px; padding:9px 12px; border:1px solid #bfdbfe; border-radius:10px; color:#1d4ed8; background:#fff; text-decoration:none; font-size:.82rem; font-weight:800;">My security</a>
    </section>

    <div class="la-stats">
        <div class="la-stat"><div class="la-stat-label">Successful</div><div class="la-stat-value" style="color:#047857;">{{ $successCount }}</div></div>
        <div class="la-stat"><div class="la-stat-label">Failed sign-ins</div><div class="la-stat-value" style="color:{{ $failedCount > 0 ? '#b45309' : '#0f172a' }};">{{ $failedCount }}</div></div>
        <div class="la-stat"><div class="la-stat-label">Flagged for review</div><div class="la-stat-value" style="color:{{ $suspiciousCount > 0 ? '#b91c1c' : '#047857' }};">{{ $suspiciousCount }}</div></div>
    </div>

    <form class="la-filter" method="GET" action="{{ route('login-audit.index') }}">
        <div class="la-field la-search-field"><label for="login-audit-q">Search</label><input id="login-audit-q" type="search" name="q" value="{{ $term }}" placeholder="Name, email, device, location..."></div>
        <div class="la-field"><label for="login-audit-role">Role</label><select id="login-audit-role" name="role"><option value="">All roles</option>@foreach(($visibleRoles ?? \App\Services\LoginActivityAccess::ALL_ROLES) as $option)<option value="{{ $option }}" @selected($role === $option)>{{ role_label($option) }}</option>@endforeach</select></div>
        <div class="la-field"><label for="login-audit-status">Status</label><select id="login-audit-status" name="status"><option value="">All statuses</option><option value="success" @selected($status === 'success')>Successful</option><option value="failed_password" @selected($status === 'failed_password')>Wrong password</option><option value="failed_mfa" @selected($status === 'failed_mfa')>MFA failed</option><option value="inactive_account" @selected($status === 'inactive_account')>Inactive account</option><option value="email_unverified" @selected($status === 'email_unverified')>Email not verified</option></select></div>
        <div class="la-field"><label for="login-audit-from">From</label><input id="login-audit-from" class="pl-date-control" type="date" name="from" value="{{ $from }}"></div>
        <div class="la-field"><label for="login-audit-to">To</label><input id="login-audit-to" class="pl-date-control" type="date" name="to" value="{{ $to }}"></div>
        <label class="la-check"><input type="checkbox" name="suspicious" value="1" @checked($suspiciousOnly)> Flagged only</label>
        <button class="pl-date-action pl-date-action-primary" type="submit">Apply filters</button>
    </form>

    <section class="la-table-panel">
        @if($activities->isEmpty())
            <div class="la-empty"><strong>No login records match these filters</strong>Try clearing a filter or wait for the next sign-in event.</div>
        @else
            <div class="la-table-wrap">
                <table class="la-table">
                    <thead><tr><th>Date &amp; time</th><th>User</th><th>Role</th><th>Location</th><th>Device</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach($activities as $activity)
                            @php [$statusBg, $statusFg] = $activity->statusTone(); @endphp
                            <tr>
                                <td data-label="Date & time"><strong style="display:block; color:#0f172a;">{{ $activity->created_at->format('M j, Y') }}</strong><span class="la-sub">{{ $activity->created_at->format('g:i A') }} · {{ $activity->created_at->diffForHumans() }}</span></td>
                                <td data-label="User" class="la-user"><strong>{{ $activity->user_name ?: 'Unknown account' }}</strong><small>{{ $activity->user_email ?: 'No matching account' }}</small></td>
                                <td data-label="Role">@if($activity->user_role)<span class="la-role">{{ role_label($activity->user_role) }}</span>@else<span class="la-sub">Unknown</span>@endif</td>
                                <td data-label="Location"><span>{{ $activity->location_label ?: 'Unavailable' }}</span><span class="la-sub">IP {{ $activity->maskedIp() }}</span></td>
                                <td data-label="Device"><span>{{ $activity->deviceLabel() }}</span>@if($activity->mfa_used)<span class="la-sub" style="color:#2563eb;">MFA verified</span>@endif</td>
                                <td data-label="Status"><span class="la-status" style="background:{{ $statusBg }}; color:{{ $statusFg }}"><span class="la-status-dot"></span>{{ $activity->statusLabel() }}</span>@if($activity->is_suspicious)<span class="la-flag">Review recommended</span>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($activities->hasPages())<div class="la-pagination">{{ $activities->links('partials.pagination') }}</div>@endif
        @endif
    </section>
@endsection
