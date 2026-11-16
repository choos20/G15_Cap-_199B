@extends('layouts.admin')

@section('title', 'Dashboard')
@section('subtitle', 'Find and track community extension activities')

@section('content')
    <style>
        .pd-welcome {
            position:relative; display:flex; align-items:center; gap:24px;
            min-height:132px; overflow:hidden; margin-bottom:20px; padding:24px 30px;
            border:1px solid #d8eee5; border-radius:18px;
            background:linear-gradient(105deg,#effaf4 0%,#f5fbf7 58%,#e3f4eb 100%);
        }
        .pd-welcome-copy { position:relative; z-index:1; max-width:680px; }
        .pd-eyebrow { margin:0 0 8px; color:#08734e; font-size:0.76rem; font-weight:800; letter-spacing:0.1em; text-transform:uppercase; }
        .pd-welcome h3 { margin:0; color:#103b32; font-size:1.45rem; line-height:1.3; }
        .pd-welcome p { margin:7px 0 0; color:#54746a; font-size:0.92rem; line-height:1.55; }
        .pd-welcome-art { position:absolute; right:36px; top:50%; width:190px; height:130px; transform:translateY(-50%); color:#14815b; opacity:0.14; }
        .pd-stats { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; margin-bottom:18px; }
        .pd-stat { position:relative; display:flex; align-items:center; gap:14px; min-width:0; padding:19px; border:1px solid; border-radius:15px; text-decoration:none; transition:transform 160ms ease,box-shadow 160ms ease; }
        .pd-stat:hover { transform:translateY(-2px); box-shadow:0 12px 24px rgba(15,23,42,0.08); }
        .pd-stat-pending { background:#fffaf0; border-color:#f5dfaa; }
        .pd-stat-approved { background:#f1fbf6; border-color:#ccebd9; }
        .pd-stat-total { background:#f2f7ff; border-color:#d2e2fb; }
        .pd-stat-icon { display:flex; align-items:center; justify-content:center; width:48px; height:48px; flex:0 0 48px; border-radius:14px; color:#fff; }
        .pd-stat-pending .pd-stat-icon { background:#e99a16; }
        .pd-stat-approved .pd-stat-icon { background:#0da878; }
        .pd-stat-total .pd-stat-icon { background:#3979e8; }
        .pd-stat-label { color:#475569; font-size:0.85rem; font-weight:700; line-height:1.4; }
        .pd-stat-value { margin-top:3px; color:#102e2a; font-size:1.8rem; font-weight:800; line-height:1.1; }
        .pd-stat-arrow { margin-left:auto; color:#678179; font-size:1.2rem; }
        .pd-dashboard-grid { display:grid; grid-template-columns:minmax(0,1.35fr) minmax(340px,1fr); gap:16px; align-items:stretch; }
        .pd-overview-body { display:grid; grid-template-columns:minmax(205px,.85fr) minmax(0,1.4fr); gap:12px; align-items:center; }
        .pd-involvement { display:flex; align-items:center; justify-content:center; gap:15px; min-width:0; }
        .pd-ring { position:relative; display:grid; place-items:center; width:124px; height:124px; flex:0 0 124px; border-radius:50%; background:conic-gradient(#09965e 0 var(--pd-complete),#6bd39c var(--pd-complete) var(--pd-ongoing),#dce5e0 var(--pd-ongoing) 100%); }
        .pd-ring::before { position:absolute; inset:11px; border-radius:50%; background:#fff; content:''; }
        .pd-ring-center { position:relative; z-index:1; text-align:center; }
        .pd-ring-value { display:block; color:#102e2a; font-size:1.7rem; font-weight:850; line-height:1.1; }
        .pd-ring-label { color:#71817c; font-size:.76rem; }
        .pd-legend { display:grid; gap:10px; min-width:92px; }
        .pd-legend-row { display:grid; grid-template-columns:10px minmax(0,1fr) auto; gap:7px; align-items:center; color:#53635d; font-size:.78rem; }
        .pd-legend-dot { width:9px; height:9px; border-radius:3px; background:#09965e; }
        .pd-legend-dot.is-ongoing { background:#6bd39c; }
        .pd-legend-dot.is-upcoming { background:#d1d9d5; }
        .pd-mini-chart-title { margin-bottom:2px; color:#344b43; font-size:.8rem; font-weight:750; }
        .pd-mini-chart { display:block; width:100%; height:auto; overflow:visible; }
        .pd-mini-line { fill:none; stroke:#09965e; stroke-width:3; stroke-linecap:round; stroke-linejoin:round; }
        .pd-mini-area { fill:url(#pd-chart-gradient); }
        .pd-mini-dot { fill:#09965e; }
        .pd-event-card { display:grid; grid-template-columns:112px minmax(0,1fr); gap:14px; align-items:center; min-height:126px; padding:12px; border:1px solid #e4ece8; border-radius:14px; background:linear-gradient(140deg,#fff,#f8fbf9); }
        .pd-event-art { display:flex; align-items:center; justify-content:center; position:relative; height:104px; overflow:hidden; border-radius:11px; background:linear-gradient(145deg,#08734e,#0eaa74); color:#fff; }
        .pd-event-date { position:absolute; top:7px; left:7px; display:grid; place-items:center; width:42px; height:48px; border:1px solid rgba(255,255,255,.25); border-radius:8px; background:rgba(5,68,47,.88); color:#fff; font-size:.64rem; font-weight:800; line-height:1.05; text-align:center; text-transform:uppercase; }
        .pd-event-date strong { display:block; font-size:1.05rem; }
        .pd-event-copy { min-width:0; }
        .pd-event-title { margin:0 0 6px; color:#18372f; font-size:.95rem; line-height:1.35; }
        .pd-event-detail { display:flex; align-items:flex-start; gap:6px; margin-top:5px; color:#71817c; font-size:.76rem; line-height:1.35; }
        .pd-event-open { display:flex; align-items:center; justify-content:center; width:30px; height:30px; margin:8px 0 0 auto; border-radius:50%; background:#e9f7f0; color:#08734e; text-decoration:none; }
        .pd-event-empty { display:grid; place-items:center; min-height:126px; padding:18px; border:1px dashed #d7e7de; border-radius:14px; background:#f8fbf9; color:#71817c; text-align:center; }
        .pd-bottom-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:16px; margin-top:16px; }
        .pd-quick-actions { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:9px; }
        .pd-quick-action { display:flex; align-items:center; gap:9px; min-width:0; padding:11px; border:1px solid #edf2ef; border-radius:11px; background:#f8fbf9; color:#18372f; text-decoration:none; transition:background 140ms ease,transform 140ms ease; }
        .pd-quick-action:hover { transform:translateY(-1px); background:#eff8f2; }
        .pd-quick-icon { display:grid; place-items:center; width:34px; height:34px; flex:0 0 34px; border-radius:10px; background:#e2f4e9; color:#0b8a59; }
        .pd-quick-title { display:block; font-size:.76rem; font-weight:800; }
        .pd-quick-subtitle { display:block; margin-top:2px; color:#77857f; font-size:.66rem; line-height:1.35; }
        .pd-timeline { position:relative; display:grid; gap:0; padding-left:22px; }
        .pd-timeline::before { position:absolute; top:12px; bottom:12px; left:7px; width:2px; background:#e5eee9; content:''; }
        .pd-timeline-item { position:relative; display:block; padding:3px 0 15px 10px; color:inherit; text-decoration:none; }
        .pd-timeline-item:last-child { padding-bottom:3px; }
        .pd-timeline-dot { position:absolute; top:5px; left:-20px; width:16px; height:16px; border:2px solid #d7e1dc; border-radius:50%; background:#fff; }
        .pd-timeline-dot.is-complete { border-color:#0a9a60; background:#0a9a60; box-shadow:inset 0 0 0 3px #fff; }
        .pd-timeline-title { color:#263b34; font-size:.78rem; font-weight:800; }
        .pd-timeline-meta { display:block; margin-top:3px; color:#77857f; font-size:.7rem; line-height:1.4; }
        .pd-update-list { display:grid; gap:0; }
        .pd-update { display:flex; align-items:flex-start; gap:9px; padding:10px 0; border-bottom:1px solid #edf1ef; color:inherit; text-decoration:none; }
        .pd-update:first-child { padding-top:0; }
        .pd-update:last-child { padding-bottom:0; border-bottom:0; }
        .pd-update-icon { display:grid; place-items:center; width:32px; height:32px; flex:0 0 32px; border-radius:10px; background:#e7f7ed; color:#11965f; }
        .pd-update-title { color:#263b34; font-size:.75rem; font-weight:800; line-height:1.35; }
        .pd-update-meta { display:block; margin-top:3px; color:#77857f; font-size:.67rem; }
        .pd-panel { min-width:0; padding:22px; border:1px solid #e8edf0; border-radius:16px; background:#fff; box-shadow:0 6px 18px rgba(11,42,42,0.045); }
        .pd-panel-head { display:flex; align-items:center; justify-content:space-between; gap:14px; margin-bottom:16px; }
        .pd-panel-title { display:flex; align-items:center; gap:10px; margin:0; color:#123d33; font-size:1.02rem; }
        .pd-panel-icon { display:flex; align-items:center; justify-content:center; width:36px; height:36px; flex:0 0 36px; border-radius:12px; background:#e9f7f0; color:#08734e; }
        .pd-panel-link { color:#08734e; font-size:0.84rem; font-weight:700; text-decoration:none; white-space:nowrap; }
        .pd-panel-link:hover { text-decoration:underline; }
        .pd-chart-empty { display:flex; align-items:center; gap:15px; padding:20px; border-radius:12px; background:#f8fbf9; color:#71817c; }
        @media (max-width:980px) {
            .pd-stats { grid-template-columns:repeat(2,minmax(0,1fr)); }
            .pd-dashboard-grid { grid-template-columns:minmax(0,1fr); }
            .pd-bottom-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
        }
        @media (max-width:700px) {
            .pd-stats { grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
            .pd-bottom-grid { grid-template-columns:minmax(0,1fr); }
            .pd-overview-body { grid-template-columns:minmax(0,1fr); }
            .pd-involvement { justify-content:flex-start; }
            .pd-stat { padding:15px 13px; gap:10px; }
            .pd-stat-icon { width:42px; height:42px; flex-basis:42px; }
            .pd-stat-label { font-size:0.78rem; }
            .pd-stat-value { font-size:1.55rem; }
            .pd-welcome { align-items:flex-start; gap:16px; padding:22px; }
            .pd-welcome-art { right:-20px; top:8px; transform:none; }
        }
        @media (max-width:480px) {
            .pd-stats { grid-template-columns:minmax(0,1fr); }
            .pd-opportunities { grid-template-columns:minmax(0,1fr); }
            .pd-panel { padding:17px; }
            .pd-empty { flex-direction:column; align-items:flex-start; gap:14px; padding:18px; }
            .pd-empty-art { width:76px; height:76px; flex-basis:76px; }
            .pd-empty-art svg { width:40px; height:40px; }
        }
    </style>

    @if(session('status'))
        <div class="card" style="border-left:4px solid #16a34a; background:#ecfdf5; color:#134e4a; padding:16px 20px; margin-bottom:20px">
            {{ session('status') }}
        </div>
    @endif

    <section class="pd-welcome" aria-label="Welcome">
        <div class="pd-welcome-copy">
            <div class="pd-eyebrow">ProjectLink community</div>
            <h3>Welcome back, {{ auth()->user()->first_name ?: auth()->user()->name }}.</h3>
            <p>Discover meaningful ways to serve your community and keep track of your applications.</p>
        </div>
        <svg class="pd-welcome-art" viewBox="0 0 180 130" fill="none" aria-hidden="true">
            <path d="M88 116V65m0 29C68 92 57 80 55 61c19 0 31 10 33 33Zm0-16c19-3 29-15 29-34-18 3-28 14-29 34Zm0-17C71 53 65 40 68 23c15 8 22 20 20 35Z" fill="currentColor"/>
            <path d="M48 116h83M34 116c0-8 6-14 14-14m99 14c0-8-6-14-14-14" stroke="currentColor" stroke-width="5" stroke-linecap="round"/>
            <circle cx="139" cy="33" r="11" fill="currentColor"/>
        </svg>
    </section>

    <section class="pd-stats" aria-label="Application summary">
        <a class="pd-stat pd-stat-pending" href="{{ route('participant.applications') }}">
            <span class="pd-stat-icon" aria-hidden="true">
                <svg width="23" height="23" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="M12 7v5l3 2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </span>
            <span><span class="pd-stat-label">Pending applications</span><span class="pd-stat-value" style="display:block">{{ $pendingCount }}</span></span>
            <span class="pd-stat-arrow" aria-hidden="true">›</span>
        </a>
        <a class="pd-stat pd-stat-approved" href="{{ route('participant.applications') }}">
            <span class="pd-stat-icon" aria-hidden="true">
                <svg width="23" height="23" viewBox="0 0 24 24" fill="none"><path d="m5 12 4 4L19 6" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            <span><span class="pd-stat-label">Approved / enrolled</span><span class="pd-stat-value" style="display:block">{{ $approvedCount }}</span></span>
            <span class="pd-stat-arrow" aria-hidden="true">›</span>
        </a>
        <a class="pd-stat pd-stat-total" href="{{ route('participant.applications') }}">
            <span class="pd-stat-icon" aria-hidden="true">
                <svg width="23" height="23" viewBox="0 0 24 24" fill="none"><path d="M6 3h9l4 4v14H6V3Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M14 3v5h5M9 13h7m-7 4h7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </span>
            <span><span class="pd-stat-label">Total applications</span><span class="pd-stat-value" style="display:block">{{ $totalApplications }}</span></span>
            <span class="pd-stat-arrow" aria-hidden="true">›</span>
        </a>
        <a class="pd-stat" style="background:#f8f2ff; border-color:#e5d5fb" href="{{ route('participant.applications') }}">
            <span class="pd-stat-icon" style="background:#9453e8" aria-hidden="true">
                <svg width="23" height="23" viewBox="0 0 24 24" fill="none"><rect x="4" y="5" width="16" height="16" rx="2" stroke="currentColor" stroke-width="2"/><path d="M8 3v4m8-4v4M4 10h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </span>
            <span><span class="pd-stat-label">Upcoming activities</span><span class="pd-stat-value" style="display:block">{{ $upcomingCount }}</span></span>
            <span class="pd-stat-arrow" aria-hidden="true">›</span>
        </a>
    </section>

    <section class="pd-dashboard-grid">
        <article class="pd-panel">
            <div class="pd-panel-head">
                <h3 class="pd-panel-title">
                    <span class="pd-panel-icon" aria-hidden="true"><svg width="19" height="19" viewBox="0 0 24 24" fill="none"><path d="M12 21V11m0 5c-4 0-6-2-6-6 4 0 6 2 6 6Zm0-3c4 0 6-2 6-6-4 0-6 2-6 6Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                    My Involvement Overview
                </h3>
                <span class="pd-panel-link" style="padding:7px 10px; border:1px solid #e5ece8; border-radius:9px">Last 6 months</span>
            </div>
            @php
                $completedPercent = $involvementTotal > 0 ? (int) round($completedActivityCount / $involvementTotal * 100) : 0;
                $ongoingPercent = $involvementTotal > 0 ? (int) round(($completedActivityCount + $ongoingActivityCount) / $involvementTotal * 100) : 0;
                $chartMax = max(1, (int) $participationMonths->max('count'));
                $chartPoints = $participationMonths->map(fn ($month, $index) => (40 + $index * 88).','. (132 - (int) round($month['count'] / $chartMax * 76)))->implode(' ');
            @endphp
            <div class="pd-overview-body">
                <div class="pd-involvement">
                    <div class="pd-ring" style="--pd-complete:{{ $completedPercent }}%; --pd-ongoing:{{ $ongoingPercent }}%" role="img" aria-label="{{ $completedActivityCount }} activities attended, {{ $ongoingActivityCount }} ongoing, {{ $upcomingCount }} upcoming">
                        <div class="pd-ring-center"><span class="pd-ring-value">{{ $completedActivityCount }}</span><span class="pd-ring-label">Activities</span></div>
                    </div>
                    <div class="pd-legend" aria-label="Activity breakdown">
                        <div class="pd-legend-row"><span class="pd-legend-dot"></span><span>Completed</span><strong>{{ $completedActivityCount }}</strong></div>
                        <div class="pd-legend-row"><span class="pd-legend-dot is-ongoing"></span><span>Ongoing</span><strong>{{ $ongoingActivityCount }}</strong></div>
                        <div class="pd-legend-row"><span class="pd-legend-dot is-upcoming"></span><span>Upcoming</span><strong>{{ $upcomingCount }}</strong></div>
                    </div>
                </div>
                <div>
                    <div class="pd-mini-chart-title">Activities per month</div>
                    @if($totalAttendedActivities > 0)
                        <svg class="pd-mini-chart" viewBox="0 0 520 175" role="img" aria-label="Activities attended per month over the last six months">
                            <defs><linearGradient id="pd-chart-gradient" x1="0" x2="0" y1="0" y2="1"><stop offset="0%" stop-color="#19ad70" stop-opacity=".22"/><stop offset="100%" stop-color="#19ad70" stop-opacity="0"/></linearGradient></defs>
                            <line x1="22" y1="134" x2="510" y2="134" stroke="#e6eeea" stroke-width="1"/>
                            <polygon class="pd-mini-area" points="40,134 {{ $chartPoints }} 480,134"/>
                            <polyline class="pd-mini-line" points="{{ $chartPoints }}"/>
                            @foreach($participationMonths as $index => $month)
                                @php $chartX = 40 + ($index * 88); $chartY = 132 - (int) round($month['count'] / $chartMax * 76); @endphp
                                <circle class="pd-mini-dot" cx="{{ $chartX }}" cy="{{ $chartY }}" r="3.5"/>
                                <text x="{{ $chartX }}" y="158" text-anchor="middle" fill="#77857f" font-size="11" font-weight="700">{{ $month['label'] }}</text>
                            @endforeach
                        </svg>
                    @else
                        <div class="pd-chart-empty" style="margin-top:8px; padding:12px; font-size:.75rem">Your activity trend will appear after your first attendance.</div>
                    @endif
                </div>
            </div>
        </article>

        <article class="pd-panel">
            <div class="pd-panel-head">
                <h3 class="pd-panel-title"><span class="pd-panel-icon" aria-hidden="true"><svg width="19" height="19" viewBox="0 0 24 24" fill="none"><rect x="4" y="5" width="16" height="16" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M8 3v4m8-4v4M4 10h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></span>Upcoming Activity</h3>
                <a class="pd-panel-link" href="{{ route('participant.activities') }}">View all <span aria-hidden="true">→</span></a>
            </div>
            @if($nextEnrolled)
                @php $upcomingAnnouncement = $nextEnrolled->announcement; @endphp
                <div class="pd-event-card">
                    <div class="pd-event-art" aria-hidden="true">
                        @if($upcomingAnnouncement->creator?->orgLogoUrl())
                            <img src="{{ $upcomingAnnouncement->creator->orgLogoUrl() }}" alt="" style="width:100%;height:100%;object-fit:cover" onerror="this.hidden=true; this.nextElementSibling.hidden=false">
                        @endif
                        <svg @if($upcomingAnnouncement->creator?->orgLogoUrl()) hidden @endif width="54" height="54" viewBox="0 0 54 54" fill="none"><path d="M27 45V23m0 14c-9 0-14-5-15-14 9 0 14 5 15 14Zm0-9c9 0 14-5 15-14-9 0-14 5-15 14Z" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M13 45h28" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>
                        <span class="pd-event-date">{{ $upcomingAnnouncement->event_date->format('M') }}<strong>{{ $upcomingAnnouncement->event_date->format('d') }}</strong></span>
                    </div>
                    <div class="pd-event-copy">
                        <h4 class="pd-event-title">{{ $upcomingAnnouncement->title }}</h4>
                        @if($upcomingAnnouncement->event_location)<div class="pd-event-detail"><span aria-hidden="true">⌖</span>{{ $upcomingAnnouncement->event_location }}</div>@endif
                        <div class="pd-event-detail"><span aria-hidden="true">◷</span>{{ $upcomingAnnouncement->event_date->format('g:i A') }}</div>
                        <a class="pd-event-open" href="{{ route('participant.activities.show', $upcomingAnnouncement) }}" aria-label="View {{ $upcomingAnnouncement->title }} details">→</a>
                    </div>
                </div>
            @else
                <div class="pd-event-empty">No upcoming activity yet. Browse opportunities and find your next way to help.</div>
            @endif
        </article>
    </section>

    <section class="pd-bottom-grid">
        <article class="pd-panel">
            <div class="pd-panel-head"><h3 class="pd-panel-title"><span class="pd-panel-icon" aria-hidden="true">⚡</span>Quick Actions</h3></div>
            <div class="pd-quick-actions">
                <a class="pd-quick-action" href="{{ route('participant.activities') }}"><span class="pd-quick-icon">＋</span><span><span class="pd-quick-title">Find Activities</span><span class="pd-quick-subtitle">Browse and join programs</span></span></a>
                <a class="pd-quick-action" href="{{ route('participant.attendance') }}"><span class="pd-quick-icon">▣</span><span><span class="pd-quick-title">Track Attendance</span><span class="pd-quick-subtitle">View your participation</span></span></a>
                <a class="pd-quick-action" href="{{ route('participant.applications') }}"><span class="pd-quick-icon">▤</span><span><span class="pd-quick-title">My Applications</span><span class="pd-quick-subtitle">Check your application status</span></span></a>
                <a class="pd-quick-action" href="{{ route('participant.scanner') }}"><span class="pd-quick-icon">⌗</span><span><span class="pd-quick-title">Scan QR</span><span class="pd-quick-subtitle">Check in to activities</span></span></a>
            </div>
        </article>

        <article class="pd-panel">
            <div class="pd-panel-head"><h3 class="pd-panel-title"><span class="pd-panel-icon" aria-hidden="true">♧</span>Activity Timeline</h3><a class="pd-panel-link" href="{{ route('participant.attendance') }}">View all <span aria-hidden="true">→</span></a></div>
            <div class="pd-timeline">
                @if($nextEnrolled)
                    <a class="pd-timeline-item" href="{{ route('participant.activities.show', $nextEnrolled->announcement) }}"><span class="pd-timeline-dot"></span><span class="pd-timeline-title">{{ $nextEnrolled->announcement->title }}</span><span class="pd-timeline-meta">Next activity · {{ $nextEnrolled->announcement->event_date->format('M j, Y') }}</span></a>
                @endif
                @forelse($timelineActivities as $timelineActivity)
                    <a class="pd-timeline-item" href="{{ route('participant.attendance') }}"><span class="pd-timeline-dot is-complete"></span><span class="pd-timeline-title">{{ $timelineActivity->project_title ?: 'Community activity' }}</span><span class="pd-timeline-meta">Attended · {{ \Illuminate\Support\Carbon::parse($timelineActivity->opens_at)->format('M j, Y') }}</span></a>
                @empty
                    @unless($nextEnrolled)<div class="pd-chart-empty" style="font-size:.76rem">Your activity history will appear here.</div>@endunless
                @endforelse
            </div>
        </article>

        <article class="pd-panel">
            <div class="pd-panel-head"><h3 class="pd-panel-title"><span class="pd-panel-icon" aria-hidden="true">♧</span>Recent Updates</h3><a class="pd-panel-link" href="{{ route('notifications.index') }}">View all <span aria-hidden="true">→</span></a></div>
            <div class="pd-update-list">
                @forelse($navRecentNotifications->take(3) as $notification)
                    <form action="{{ route('notifications.read', $notification->id) }}" method="POST" class="pd-update">
                        @csrf
                        <button type="submit" style="display:flex;align-items:flex-start;gap:9px;width:100%;padding:0;border:0;background:transparent;text-align:left;cursor:pointer">
                            <span class="pd-update-icon" aria-hidden="true">{{ str_contains(strtolower($notification->data['title'] ?? ''), 'approv') ? '✓' : '↗' }}</span>
                            <span><span class="pd-update-title">{{ $notification->data['title'] ?? 'ProjectLink update' }}</span><span class="pd-update-meta">{{ $notification->created_at->diffForHumans() }}</span></span>
                        </button>
                    </form>
                @empty
                    <div class="pd-chart-empty" style="font-size:.76rem">You’re all caught up. New updates will appear here.</div>
                @endforelse
            </div>
        </article>
    </section>


@endsection
