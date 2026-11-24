@extends('layouts.admin')

@section('title', 'Security')
@section('subtitle', 'Manage sign-in protection, password, and login activity')

@section('content')
    @php
        $displayTimezone = config('app.display_timezone', 'Asia/Manila');
        $displayTimezoneLabel = config('app.display_timezone_label', 'PHT');
        $canDisableMfa = $mfaEnabled && ! $mfaRequired;
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
        .sec-stats { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; margin-bottom:18px; }
        .sec-stat { min-width:0; padding:17px 18px; border:1px solid #e5e7eb; border-radius:15px; background:#fff; box-shadow:0 6px 18px rgba(15,23,42,.04); }
        .sec-stat-label { color:#000; font-size:.78rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
        .sec-stat-value { margin-top:9px; color:#0f172a; font-size:1.35rem; font-weight:800; }
        .sec-stat-note { margin-top:4px; color:#5b7067; font-size:.78rem; }
        .sec-settings-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; margin-bottom:18px; }
        .sec-panel { overflow:hidden; padding:0; border:1px solid #e5e7eb; border-radius:16px; background:#fff; box-shadow:0 6px 18px rgba(15,23,42,.04); }
        .sec-panel-head { display:flex; align-items:flex-start; justify-content:space-between; gap:14px; padding:18px 20px; border-bottom:1px solid #eef2f7; }
        .sec-panel-head h3 { margin:0; color:#0f172a; font-size:1rem; }
        .sec-panel-head p { margin:5px 0 0; color:#52665d; font-size:.83rem; line-height:1.45; }
        .sec-panel-body { padding:20px; }
        .sec-setting-summary { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; }
        .sec-setting-summary p { margin:8px 0 0; color:#52665d; font-size:.82rem; line-height:1.55; }
        .sec-status { display:inline-flex; align-items:center; gap:6px; padding:5px 9px; border-radius:999px; font-size:.72rem; font-weight:800; }
        .sec-status.is-on { background:#ecfdf5; color:#047857; }
        .sec-status.is-required { background:#eff6ff; color:#1d4ed8; }
        .sec-status.is-off { background:#f1f5f9; color:#52665d; }
        .sec-status-dot { width:6px; height:6px; border-radius:50%; background:currentColor; }
        .sec-setting-meta { margin-top:16px; padding-top:14px; border-top:1px solid #eef2f7; color:#52665d; font-size:.77rem; line-height:1.5; }
        .sec-inline-form { margin-top:16px; padding-top:16px; border-top:1px solid #eef2f7; }
        .sec-form-field { margin-bottom:12px; }
        .sec-form-field label { display:block; margin-bottom:6px; color:#334155; font-size:.78rem; font-weight:700; }
        .sec-form-field input { width:100%; padding:10px 11px; border:1px solid #dbe4e9; border-radius:9px; background:#fff; color:#0f172a; font:inherit; font-size:.86rem; }
        .sec-form-field input:focus { border-color:#12b886; outline:0; box-shadow:0 0 0 4px rgba(18,184,134,.12); }
        .sec-password-wrap { position:relative; }
        .sec-form-field .sec-password-wrap input { padding-right:48px; }
        .sec-password-toggle { position:absolute; top:50%; right:4px; transform:translateY(-50%); display:flex; align-items:center; justify-content:center; width:40px; height:40px; padding:0; border:0; border-radius:7px; background:transparent; color:#52665d; cursor:pointer; }
        .sec-password-toggle[hidden] { display:none; }
        .sec-password-toggle:hover { color:#047857; background:#f0fdf4; }
        .sec-password-toggle:focus-visible { outline:2px solid #047857; outline-offset:-2px; }
        .sec-password-toggle svg { width:20px; height:20px; }
        .sec-form-error { margin-top:5px; color:#b91c1c; font-size:.75rem; line-height:1.4; }
        .sec-form-help { margin:0 0 14px; color:#52665d; font-size:.77rem; line-height:1.5; }
        .sec-danger-details { margin-top:16px; border-top:1px solid #fee2e2; padding-top:14px; }
        .sec-danger-details summary { color:#b91c1c; cursor:pointer; font-size:.78rem; font-weight:800; }
        .sec-danger-details[open] summary { margin-bottom:12px; }
        .sec-danger-details .sec-inline-form { margin-top:0; padding-top:0; border-top:0; }
        .sec-activity-cell { display:flex; align-items:flex-start; gap:10px; min-width:0; }
        .sec-activity-icon { display:inline-flex; align-items:center; justify-content:center; flex:0 0 34px; width:34px; height:34px; border:1px solid #d1fae5; border-radius:10px; background:#ecfdf5; color:#087c5b; }
        .sec-activity-icon svg { width:17px; height:17px; }
        .sec-activity-copy { flex:1 1 auto; min-width:0; }
        .sec-table-wrap { overflow-x:auto; }
        .sec-table { width:100%; border-collapse:collapse; }
        .sec-table th { padding:12px 20px; color:#000; background:#f8fafc; border-bottom:1px solid #eef2f7; text-align:left; font-size:.72rem; text-transform:uppercase; letter-spacing:.045em; }
        .sec-table td { padding:15px 20px; color:#334155; border-bottom:1px solid #f1f5f9; vertical-align:top; font-size:.84rem; }
        .sec-table tbody tr:last-child td { border-bottom:0; }
        .sec-table tbody tr:hover { background:#fbfefc; }
        .sec-when strong { display:block; color:#0f172a; font-size:.84rem; }
        .sec-when small,.sec-muted { display:block; margin-top:4px; color:#5b7067; font-size:.76rem; }
        .sec-device { color:#334155; line-height:1.45; }
        .sec-device small { display:block; margin-top:3px; color:#5b7067; }
        .sec-pill { display:inline-flex; align-items:center; gap:5px; padding:5px 9px; border-radius:999px; font-size:.72rem; font-weight:800; white-space:nowrap; }
        .sec-dot { width:6px; height:6px; border-radius:50%; background:currentColor; }
        .sec-mfa { display:inline-flex; margin-top:6px; padding:3px 7px; border-radius:6px; background:#eff6ff; color:#2563eb; font-size:.68rem; font-weight:700; }
        .sec-empty { padding:46px 22px; color:#52665d; text-align:center; }
        .sec-empty strong { display:block; margin-bottom:5px; color:#334155; }
        .sec-pagination { padding:16px 20px; border-top:1px solid #eef2f7; }
        @media (max-width:760px) {
            .sec-hero { flex-direction:column; }
            .sec-actions { justify-content:flex-start; }
            .sec-stats { grid-template-columns:1fr; }
            .sec-settings-grid { grid-template-columns:1fr; }
            .sec-setting-summary { flex-direction:column; }
            .sec-table thead { display:none; }
            .sec-table,.sec-table tbody,.sec-table tr,.sec-table td { display:block; width:100%; }
            .sec-table tr { padding:14px 16px; border-bottom:1px solid #eef2f7; }
            .sec-table tbody tr:last-child { border-bottom:0; }
            .sec-table td { display:flex; align-items:flex-start; justify-content:space-between; gap:14px; padding:7px 0; border:0; text-align:right; }
            .sec-table td::before { flex:0 0 38%; color:#000; content:attr(data-label); font-size:.71rem; font-weight:800; letter-spacing:.04em; text-align:left; text-transform:uppercase; }
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
                <x-ui-icon name="user-lock" :size="25" />
            </div>
            <div>
                <h3>Your account security</h3>
                <p>Review when this account was used and confirm that your sign-ins look familiar. Login activity is private to you.</p>
            </div>
        </div>
        <div class="sec-actions">
            <a class="sec-btn" href="#password">Change password</a>
            <a class="sec-btn" href="#login-activity">Review activity</a>
            @if($canViewAudit)
                <a class="sec-btn sec-btn-primary" href="{{ route('login-audit.index') }}">Open audit logs</a>
            @endif
        </div>
    </section>

    <div class="sec-stats">
        <div class="sec-stat">
            <div class="sec-stat-label">MFA protection</div>
            <div class="sec-stat-value" style="color:{{ $mfaEnabled ? '#047857' : '#52665d' }}; font-size:1.1rem;">{{ $mfaEnabled ? 'Enabled' : ($mfaRequired ? 'Setup required' : 'Not enabled') }}</div>
            <div class="sec-stat-note">{{ $mfaRequired ? 'Required for this role' : 'Optional for your account' }}</div>
        </div>
        <div class="sec-stat">
            <div class="sec-stat-label">Successful sign-ins</div>
            <div class="sec-stat-value">{{ $successfulCount }}</div>
            <div class="sec-stat-note">Last 30 days</div>
        </div>
        <div class="sec-stat">
            <div class="sec-stat-label">Review needed</div>
            <div class="sec-stat-value" style="color:{{ $suspiciousCount > 0 ? '#b91c1c' : '#000101' }};">{{ $suspiciousCount }}</div>
            <div class="sec-stat-note">{{ $failedCount }} failed sign-in{{ $failedCount === 1 ? '' : 's' }} in the last 30 days</div>
        </div>
    </div>

    <div class="sec-settings-grid">
        <section class="sec-panel" id="mfa">
            <div class="sec-panel-head">
                <div>
                    <h3>Sign-in protection</h3>
                    <p>Use SafeAuth or another standard authenticator app for a six-digit verification code.</p>
                </div>
            </div>
            <div class="sec-panel-body">
                <div class="sec-setting-summary">
                    <div>
                        @if($mfaEnabled)
                            <span class="sec-status {{ $mfaRequired ? 'is-required' : 'is-on' }}"><span class="sec-status-dot"></span>{{ $mfaRequired ? 'MFA required' : 'MFA enabled' }}</span>
                            <p>{{ $mfaRequired ? 'This role must complete MFA before accessing the dashboard.' : 'Your account will ask for a code after your password when you sign in.' }}</p>
                        @else
                            <span class="sec-status {{ $mfaRequired ? 'is-required' : 'is-off' }}"><span class="sec-status-dot"></span>{{ $mfaRequired ? 'Setup required' : 'MFA not enabled' }}</span>
                            <p>{{ $mfaRequired ? 'Complete setup now to protect this high-privilege account.' : 'Add an authenticator code as an extra layer of protection for your account.' }}</p>
                        @endif
                    </div>
                    @if(! $mfaEnabled)
                        <a class="sec-btn sec-btn-primary" href="{{ route('mfa.enroll') }}">{{ $mfaRequired ? 'Set up MFA' : 'Enable MFA' }}</a>
                    @endif
                </div>

                @if($mfaEnabled)
                    <div class="sec-setting-meta">
                        @if($mfaRecoveryCodesRemaining > 0)
                            {{ $mfaRecoveryCodesRemaining }} recovery code{{ $mfaRecoveryCodesRemaining === 1 ? '' : 's' }} remaining. Keep them somewhere secure in case you lose access to your authenticator app.
                        @else
                            Recovery codes are unavailable. Contact an administrator if you need to recover access.
                        @endif
                    </div>

                    @if($canDisableMfa)
                        <details class="sec-danger-details">
                            <summary>Disable MFA for this account</summary>
                            <form class="sec-inline-form" method="POST" action="{{ route('account.mfa.disable') }}">
                                @csrf
                                <p class="sec-form-help">For your protection, enter your current password and either the current six-digit authenticator code or an unused recovery code.</p>
                                <div class="sec-form-field">
                                    <label for="mfa-disable-password">Current password</label>
                                    <input id="mfa-disable-password" type="password" name="mfa_current_password" autocomplete="current-password" required>
                                    @error('mfa_current_password')<div class="sec-form-error">{{ $message }}</div>@enderror
                                </div>
                                <div class="sec-form-field">
                                    <label for="mfa-disable-code">Authenticator or recovery code</label>
                                    <input id="mfa-disable-code" type="text" name="mfa_code" inputmode="numeric" autocomplete="one-time-code" maxlength="32" required>
                                    @error('mfa_code')<div class="sec-form-error">{{ $message }}</div>@enderror
                                </div>
                                <button class="sec-btn" type="submit" style="border-color:#fecaca; color:#b91c1c;">Disable MFA</button>
                            </form>
                        </details>
                    @else
                        <div class="sec-setting-meta">MFA is required for this role and cannot be disabled from this page.</div>
                    @endif
                @endif
            </div>
        </section>

        <section class="sec-panel" id="password">
            <div class="sec-panel-head">
                <div>
                    <h3>Change password</h3>
                    <p>Use a strong password that you do not reuse on another service.</p>
                </div>
            </div>
            <div class="sec-panel-body">
                @if(auth()->user()->must_change_password)
                    <div style="margin-bottom:14px; padding:12px 13px; border:1px solid #fde68a; border-radius:10px; background:#fffbeb; color:#92400e; font-size:.78rem; line-height:1.5;">
                        <strong>Set your own password to continue.</strong><br>
                        Your temporary password must be replaced before you can continue using the rest of the system.
                    </div>
                @endif
                <form method="POST" action="{{ route('account.password.update') }}">
                    @csrf
                    <div class="sec-form-field">
                        <label for="security-current-password">Current password</label>
                        <input id="security-current-password" type="password" name="current_password" autocomplete="current-password" required>
                        @error('current_password')<div class="sec-form-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="sec-form-field">
                        <label for="security-new-password">New password</label>
                        <input id="security-new-password" type="password" name="password" autocomplete="new-password" required>
                        @error('password')<div class="sec-form-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="sec-form-field">
                        <label for="security-password-confirmation">Confirm new password</label>
                        <input id="security-password-confirmation" type="password" name="password_confirmation" autocomplete="new-password" required>
                    </div>
                    <button class="sec-btn sec-btn-primary" type="submit">Update password</button>
                </form>
            </div>
        </section>
    </div>

    <section class="sec-panel" id="login-activity">
        <div class="sec-panel-head">
            <div>
                <h3>Recent login activity</h3>
                <p>Successful and blocked sign-ins for {{ auth()->user()->name }}. Times are shown in {{ $displayTimezoneLabel }}.</p>
            </div>
            @if($lastSuccessful)
                <div style="text-align:right; color:#52665d; font-size:.77rem;">
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
                                    <div class="sec-activity-cell">
                                        <span class="sec-activity-icon" aria-hidden="true"><x-ui-icon name="clock" :size="17" /></span>
                                        <span class="sec-activity-copy">
                                    <strong>{{ $activity->created_at->copy()->timezone($displayTimezone)->format('M j, Y') }}</strong>
                                    <small>{{ $activity->created_at->copy()->timezone($displayTimezone)->format('g:i A') }} {{ $displayTimezoneLabel }} · {{ $activity->created_at->diffForHumans() }}</small>
                                        </span>
                                    </div>
                                </td>
                                <td data-label="Approx. location">
                                    <div class="sec-activity-cell">
                                        <span class="sec-activity-icon" aria-hidden="true"><x-ui-icon name="location" :size="17" /></span>
                                        <span class="sec-activity-copy">
                                            <span>{{ $activity->location_label ?: 'Unavailable' }}</span>
                                            <span class="sec-muted">IP-based estimate; not GPS.</span>
                                        </span>
                                    </div>
                                </td>
                                <td data-label="Device" class="sec-device">
                                    <div class="sec-activity-cell">
                                        <span class="sec-activity-icon" aria-hidden="true"><x-ui-icon name="desktop" :size="17" /></span>
                                        <span class="sec-activity-copy">
                                            {{ $activity->deviceLabel() }}
                                            @if($activity->mfa_used)<span class="sec-mfa">MFA verified</span>@endif
                                        </span>
                                    </div>
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
    <script>
        document.querySelectorAll('#mfa input[type="password"], #password input[type="password"]').forEach((input) => {
            const wrapper = document.createElement('div');
            wrapper.className = 'sec-password-wrap';
            input.before(wrapper);
            wrapper.appendChild(input);

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'sec-password-toggle';
            button.setAttribute('aria-controls', input.id);
            const label = document.querySelector(`label[for="${input.id}"]`).textContent.trim().toLowerCase();
            const update = (visible) => {
                input.type = visible ? 'text' : 'password';
                button.setAttribute('aria-label', `${visible ? 'Hide' : 'Show'} ${label}`);
                button.setAttribute('aria-pressed', String(visible));
                button.innerHTML = visible
                    ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3l18 18M10.6 10.6a2 2 0 0 0 2.8 2.8"/><path d="M9.9 5.2A11.7 11.7 0 0 1 12 5c5.5 0 9 5 10 7a13 13 0 0 1-3 3.7M6.3 6.3A14.1 14.1 0 0 0 2 12c1 2 4.5 7 10 7a10 10 0 0 0 4-.8"/></svg>'
                    : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>';
            };
            update(false);
            const syncVisibility = () => {
                button.hidden = input.value.length === 0;
                if (button.hidden) update(false);
            };
            input.addEventListener('input', syncVisibility);
            input.addEventListener('change', syncVisibility);
            button.addEventListener('click', () => update(input.type === 'password'));
            wrapper.appendChild(button);
            syncVisibility();
        });
    </script>
@endsection
