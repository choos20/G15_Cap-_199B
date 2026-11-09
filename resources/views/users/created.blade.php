@extends('layouts.admin')

@section('title', 'Account created')
@section('subtitle', 'The new account is ready to use')

@section('content')
    @php
        $roleTones = [
            'celp_secretary' => ['#fff7ed', '#9a3412'],
            'coordinator' => ['#f0fdf4', '#166534'],
            'csa_director' => ['#fdf2f8', '#9d174d'],
            'student_org' => ['#eff6ff', '#1d4ed8'],
            'college_department' => ['#eef2ff', '#4338ca'],
        ];
        [$roleBg, $roleFg] = $roleTones[$created->role] ?? ['#f1f5f9', '#334155'];
        $initials = collect(explode(' ', $created->name))->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode('');
    @endphp

    <style>
        .ac-wrap { max-width:660px; }
        .ac-card { background:#fff; border-radius:20px; padding:34px; box-shadow:0 10px 30px rgba(11,42,42,0.08); }
        .ac-tick { width:60px; height:60px; border-radius:50%; background:#ecfdf5; color:#047857;
                   display:flex; align-items:center; justify-content:center; margin-bottom:20px; }
        .ac-title { font-size:1.45rem; font-weight:800; color:#0f172a; margin:0; }
        .ac-lede { color:#475569; margin-top:8px; line-height:1.65; }

        .ac-summary { display:flex; align-items:center; gap:16px; margin-top:26px; padding:18px;
                      background:#f8fafc; border:1px solid #e2e8f0; border-radius:16px; }
        .ac-avatar { width:48px; height:48px; border-radius:50%; background:#0b6a4a; color:#fff; font-weight:700;
                     display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .ac-name { font-weight:700; color:#0f172a; }
        .ac-meta { color:#64748b; font-size:0.88rem; margin-top:3px; word-break:break-all; }
        .ac-pill { display:inline-flex; padding:5px 12px; border-radius:999px; font-size:0.78rem; font-weight:700; white-space:nowrap; }

        .ac-note { display:flex; gap:11px; margin-top:18px; padding:14px 16px; border-radius:12px; font-size:0.88rem; line-height:1.6; }
        .ac-note-ok { background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; }
        .ac-note-warn { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }

        .ac-actions { display:flex; gap:12px; flex-wrap:wrap; margin-top:28px; }
        .ac-btn { display:inline-flex; align-items:center; gap:9px; padding:13px 22px; border-radius:12px;
                  font-weight:700; font-size:0.92rem; text-decoration:none; border:1px solid transparent; }
        .ac-primary { background:#0b6a4a; color:#fff; }
        .ac-primary:hover { background:#0f8a61; }
        .ac-secondary { background:#fff; color:#0f172a; border-color:#d1d5db; }
        .ac-secondary:hover { background:#f8fafc; border-color:#cbd5e1; }
    </style>

    <div class="ac-wrap">
        <div class="ac-card">
            <div class="ac-tick">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>

            <h2 class="ac-title">Account created</h2>
            <p class="ac-lede">
                {{ $created->first_name ?: $created->name }} can sign in right away with the password sent to their email.
                No verification code is needed — you vouched for this account by creating it.
            </p>

            <div class="ac-summary">
                <div class="ac-avatar">{{ strtoupper($initials) }}</div>
                <div style="flex:1; min-width:0">
                    <div class="ac-name">{{ $created->name }}</div>
                    <div class="ac-meta">{{ $created->email }} &middot; {{ $created->username }}</div>
                </div>
                <span class="ac-pill" style="background:{{ $roleBg }}; color:{{ $roleFg }}">{{ role_label($created->role) }}</span>
            </div>

            @if($mailed)
                <div class="ac-note ac-note-ok">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" style="flex-shrink:0; margin-top:1px"><path d="M3 6h18v12H3V6zm0 0l9 7 9-7" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                    <span>Their sign-in details were emailed to <strong>{{ $created->email }}</strong>.</span>
                </div>
            @else
                <div class="ac-note ac-note-warn">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" style="flex-shrink:0; margin-top:1px"><path d="M12 9v5m0 3.5v.01M10.3 3.9L2.4 18a2 2 0 0 0 1.7 3h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span>
                        The account exists, but the email to <strong>{{ $created->email }}</strong> could not be sent,
                        so they don't have their password yet. Check the address is correct, then use
                        <strong>Edit</strong> on the account list to resend or set a new password.
                        @if($mailError)
                            <br><span style="font-size:0.82rem; opacity:0.85">{{ \Illuminate\Support\Str::limit($mailError, 140) }}</span>
                        @endif
                    </span>
                </div>
            @endif

            <div class="ac-actions">
                <a href="{{ route(dashboard_route_for(auth()->user())) }}" class="ac-btn ac-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M3 11l9-8 9 8M5 10v10h14V10" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                    Back to dashboard
                </a>
                <a href="{{ route('users.create') }}" class="ac-btn ac-secondary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    Create another account
                </a>
                <a href="{{ route('users.index') }}" class="ac-btn ac-secondary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M6 7a3 3 0 1 1 6 0 3 3 0 0 1-6 0zm0 7a5 5 0 0 1 10 0v2H6v-2z" fill="currentColor"/></svg>
                    Manage accounts
                </a>
            </div>
        </div>
    </div>
@endsection
