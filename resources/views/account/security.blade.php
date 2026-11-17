@extends('layouts.admin')

@section('title', 'Security settings')
@section('subtitle', 'Protect your account and review sign-ins')

@section('content')
    @php
        $displayTimezone = config('app.display_timezone', 'Asia/Manila');
        $displayTimezoneLabel = config('app.display_timezone_label', 'PHT');
    @endphp

    <style>
        .sec-hero { display:flex; align-items:flex-start; justify-content:space-between; gap:18px; padding:22px; margin-bottom:16px;
                    border:1px solid #d8eee5; border-radius:18px; background:linear-gradient(135deg,#ecfdf5 0%,#fff 68%); }
        .sec-hero h3 { margin:0; color:#064e3b; font-size:1.1rem; }
        .sec-hero p { margin:7px 0 0; color:#52716a; line-height:1.55; max-width:680px; }
        .sec-hero-mark { display:flex; align-items:center; justify-content:center; flex:0 0 48px; width:48px; height:48px; border-radius:15px; background:#d1fae5; color:#047857; }
        .sec-hero-mark svg { width:25px; height:25px; }
        .sec-actions { display:flex; gap:9px; flex-wrap:wrap; justify-content:flex-end; }
        .sec-btn { display:inline-flex; align-items:center; justify-content:center; gap:7px; min-height:40px; padding:9px 14px; border-radius:10px; border:1px solid #cfe5dc; background:#fff; color:#065f46; text-decoration:none; font-size:.84rem; font-weight:700; }
        .sec-btn:hover { background:#f0fdf4; border-color:#86efac; }
        .sec-btn-primary { border-color:#087c5b; background:#087c5b; color:#fff; }
        .sec-btn-primary:hover { background:#066348; color:#fff; }
        .sec-tabs { display:flex; gap:6px; padding:5px; margin-bottom:18px; width:max-content; max-width:100%; overflow:auto; border:1px solid #e2e8f0; border-radius:12px; background:#fff; }
        .sec-tab { padding:9px 13px; border-radius:8px; color:#64748b; text-decoration:none; font-size:.84rem; font-weight:700; white-space:nowrap; }
        .sec-tab:hover { background:#f8fafc; color:#334155; }
        .sec-tab.is-active { background:#ecfdf5; color:#047857; }
        .sec-stats { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; margin-bottom:18px; }
        .sec-stat { min-width:0; padding:17px 18px; border:1px solid #e5e7eb; border-radius:15px; background:#fff; box-shadow:0 6px 18px rgba(15,23,42,.04); }
        .sec-stat-label { color:#64748b; font-size:.78rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
        .sec-stat-value { margin-top:9px; color:#0f172a; font-size:1.35rem; font-weight:800; }
        .sec-stat-note { margin-top:4px; color:#94a3b8; font-size:.78rem; }
        .sec-panel { overflow:hidden; padding:0; border:1px solid #e5e7eb; border-radius:16px; background:#fff; box-shadow:0 6px 18px rgba(15,23,42,.04); }
        .sec-panel-head { display:flex; align-items:flex-start; justify-content:space-between; gap:14px; padding:18px 20px; border-bottom:1px solid #eef2f7; }
        .sec-panel-head h3 { margin:0; color:#0f172a; font-size:1rem; }
        .sec-panel-head p { margin:5px 0 0; color:#64748b; font-size:.83rem; line-height:1.45; }
        .sec-table-wrap { overflow-x:auto; }
        .sec-table { width:100%; border-collapse:collapse; }
        .sec-table th { padding:12px 20px; color:#64748b; background:#f8fafc; border-bottom:1px solid #eef2f7; text-align:left; font-size:.72rem; text-transform:uppercase; letter-spacing:.045em; }
        .sec-table td { padding:15px 20px; color:#334155; border-bottom:1px solid #f1f5f9; vertical-align:top; font-size:.84rem; }
        .sec-table tbody tr:last-child td { border-bottom:0; }
        .sec-table tbody tr:hover { background:#fbfefc; }
        .sec-when strong { display:block; color:#0f172a; font-size:.84rem; }
        .sec-when small,.sec-muted { display:block; margin-top:4px; color:#94a3b8; font-size:.76rem; }
        .sec-device { color:#334155; line-height:1.45; }
        .sec-device small { display:block; margin-top:3px; color:#94a3b8; }
        .sec-pill { display:inline-flex; align-items:center; gap:5px; padding:5px 9px; border-radius:999px; font-size:.72rem; font-weight:800; white-space:nowrap; }
        .sec-dot { width:6px; height:6px; border-radius:50%; background:currentColor; }
        .sec-mfa { display:inline-flex; margin-top:6px; padding:3px 7px; border-radius:6px; background:#eff6ff; color:#2563eb; font-size:.68rem; font-weight:700; }
        .sec-empty { padding:46px 22px; color:#64748b; text-align:center; }
        .sec-empty strong { display:block; margin-bottom:5px; color:#334155; }
        .sec-pagination { padding:16px 20px; border-top:1px solid #eef2f7; }
        @media (max-width:760px) {
            .sec-hero { flex-direction:column; }
            .sec-actions { justify-content:flex-start; }
            .sec-stats { grid-template-columns:1fr; }
            .sec-table thead { display:none; }
            .sec-table,.sec-table tbody,.sec-table tr,.sec-table td { display:block; width:100%; }
            .sec-table tr { padding:14px 16px; border-bottom:1px solid #eef2f7; }
            .sec-table tbody tr:last-child { border-bottom:0; }
            .sec-table td { display:flex; align-items:flex-start; justify-content:space-between; gap:14px; padding:7px 0; border:0; text-align:right; }
            .sec-table td::before { flex:0 0 38%; color:#94a3b8; content:attr(data-label); font-size:.71rem; font-weight:800; letter-spacing:.04em; text-align:left; text-transform:uppercase; }
            .sec-table td > * { max-width:62%; }
            .sec-table .sec-when small,.sec-device small { margin-top:3px; }
        }
    </style>

    @if(session('status'))
        <div style="margin-bottom:14px; padding:12px 15px; border:1px solid #bbf7d0; border-radius:12px; background:#f0fdf4; color:#166534; font-size:.88rem;">
            {{ session('status') }}
        </div>
    @endif

    <section class="sec-hero">
        <div style="display:flex; align-items:flex-start; gap:13px; min-width:0;">
            <div class="sec-hero-mark" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none"><path d="M12 3 5 6v5c0 4.4 2.9 8.4 7 10 4.1-1.6 7-5.6 7-10V6l-7-3Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="m9 12 2 2 4-4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <div>
                <h3>Your account security</h3>
                <p>Review when this account was used and confirm that your sign-ins look familiar. Login activity is private to you.</p>
            </div>
        </div>
        <div class="sec-actions">
            <a class="sec-btn" href="{{ route('account.password') }}">Change password</a>
            @if($canViewAudit)
                <a class="sec-btn sec-btn-primary" href="{{ route('login-audit.index') }}">Open audit logs</a>
            @endif
        </div>
    </section>

    <nav class="sec-tabs" aria-label="Security settings">
        <a href="{{ route('account.security') }}" class="sec-tab {{ request()->routeIs('account.security') ? 'is-active' : '' }}">Overview</a>
        <a href="{{ route('account.login_activity') }}" class="sec-tab {{ request()->routeIs('account.login_activity') ? 'is-active' : '' }}">Login activity</a>
        <a href="{{ route('account.password') }}" class="sec-tab {{ request()->routeIs('account.password') ? 'is-active' : '' }}">Password</a>
    </nav>

    <div class="sec-stats">
        <div class="sec-stat">
            <div class="sec-stat-label">MFA protection</div>
            <div class="sec-stat-value" style="color:{{ auth()->user()->mfa_enabled_at ? '#047857' : '#64748b' }}; font-size:1.1rem;">{{ auth()->user()->mfa_enabled_at ? 'Enabled' : 'Not required' }}</div>
            <div class="sec-stat-note">{{ in_array(auth()->user()->role, ['celp_head','csa_director'], true) ? 'Required for this role' : 'Password sign-in is active' }}</div>
        </div>
        <div class="sec-stat">
            <div class="sec-stat-label">Successful sign-ins</div>
            <div class="sec-stat-value">{{ $successfulCount }}</div>
            <div class="sec-stat-note">Last 30 days</div>
        </div>
        <div class="sec-stat">
            <div class="sec-stat-label">Review needed</div>
            <div class="sec-stat-value" style="color:{{ $suspiciousCount > 0 ? '#b91c1c' : '#047857' }};">{{ $suspiciousCount }}</div>
            <div class="sec-stat-note">{{ $failedCount }} failed sign-in{{ $failedCount === 1 ? '' : 's' }} in the last 30 days</div>
        </div>
    </div>

    <section class="sec-panel">
        <div class="sec-panel-head">
            <div>
                <h3>Recent login activity</h3>
                <p>Successful and blocked sign-ins for {{ auth()->user()->name }}. Times are shown in {{ $displayTimezoneLabel }}.</p>
            </div>
            @if($lastSuccessful)
                <div style="text-align:right; color:#64748b; font-size:.77rem;">
                    Last successful login
                    <strong style="display:block; margin-top:3px; color:#334155;">{{ $lastSuccessful->created_at->copy()->timezone($displayTimezone)->format('M j, Y · g:i A') }} {{ $displayTimezoneLabel }}</strong>
                </div>
            @endif
        </div>

        @if($activities->isEmpty())
            <div class="sec-empty">
                <strong>No login activity recorded yet</strong>
                Your future sign-ins will appear here with a device and security status.
            </div>
        @else
            <div class="sec-table-wrap">
                <table class="sec-table">
                    <thead>
                        <tr><th>Date &amp; time</th><th>Approx. location</th><th>Device</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @foreach($activities as $activity)
                            @php [$statusBg, $statusFg] = $activity->statusTone(); @endphp
                            <tr>
                                <td data-label="Date & time" class="sec-when">
                                    <strong>{{ $activity->created_at->copy()->timezone($displayTimezone)->format('M j, Y') }}</strong>
                                    <small>{{ $activity->created_at->copy()->timezone($displayTimezone)->format('g:i A') }} {{ $displayTimezoneLabel }} · {{ $activity->created_at->diffForHumans() }}</small>
                                </td>
                                <td data-label="Approx. location">
                                    <span>{{ $activity->location_label ?: 'Unavailable' }}</span>
                                    <span class="sec-muted">IP-based estimate; not GPS.</span>
                                </td>
                                <td data-label="Device" class="sec-device">
                                    {{ $activity->deviceLabel() }}
                                    @if($activity->mfa_used)<span class="sec-mfa">MFA verified</span>@endif
                                </td>
                                <td data-label="Status">
                                    <span class="sec-pill" style="background:{{ $statusBg }}; color:{{ $statusFg }}"><span class="sec-dot"></span>{{ $activity->statusLabel() }}</span>
                                    @if($activity->is_suspicious)<span class="sec-muted" style="color:#b91c1c;">Review recommended</span>@endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($activities->hasPages())
                <div class="sec-pagination">{{ $activities->links('partials.pagination') }}</div>
            @endif
        @endif
    </section>
@endsection
