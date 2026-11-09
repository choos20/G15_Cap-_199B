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
        .pd-stats { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:16px; margin-bottom:20px; }
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
        .pd-panels { display:grid; grid-template-columns:minmax(0,1.8fr) minmax(280px,0.9fr); gap:18px; align-items:stretch; }
        .pd-panel { min-width:0; padding:22px; border:1px solid #e8edf0; border-radius:16px; background:#fff; box-shadow:0 6px 18px rgba(11,42,42,0.045); }
        .pd-panel-head { display:flex; align-items:center; justify-content:space-between; gap:14px; margin-bottom:16px; }
        .pd-panel-title { display:flex; align-items:center; gap:10px; margin:0; color:#123d33; font-size:1.02rem; }
        .pd-panel-icon { display:flex; align-items:center; justify-content:center; width:36px; height:36px; flex:0 0 36px; border-radius:12px; background:#e9f7f0; color:#08734e; }
        .pd-panel-link { color:#08734e; font-size:0.84rem; font-weight:700; text-decoration:none; white-space:nowrap; }
        .pd-panel-link:hover { text-decoration:underline; }
        .pd-opportunities { display:flex; flex-direction:column; gap:10px; }
        .pd-opportunity { display:flex; align-items:center; justify-content:space-between; gap:14px; padding:14px 15px; border:1px solid #e6edf0; border-radius:12px; background:#fbfdfc; }
        .pd-opportunity-org { display:flex; align-items:center; gap:8px; min-width:0; }
        .pd-opportunity-title { color:#172f2a; font-size:0.92rem; font-weight:700; line-height:1.4; }
        .pd-opportunity-meta { margin-top:4px; color:#71817c; font-size:0.82rem; line-height:1.45; }
        .pd-view-link { flex-shrink:0; color:#08734e; font-size:0.84rem; font-weight:700; text-decoration:none; }
        .pd-view-link:hover { text-decoration:underline; }
        .pd-empty { display:flex; align-items:center; gap:20px; min-height:188px; padding:24px; border-radius:12px; background:linear-gradient(120deg,#f8fcf9,#f2f8f5); }
        .pd-empty-art { display:flex; align-items:center; justify-content:center; width:112px; height:112px; flex:0 0 112px; border-radius:50%; background:#e1f2e9; color:#16815a; }
        .pd-empty-copy { max-width:320px; }
        .pd-empty-copy h4 { margin:0; color:#173e34; font-size:0.98rem; }
        .pd-empty-copy p { margin:6px 0 12px; color:#71817c; font-size:0.86rem; line-height:1.55; }
        .pd-empty-action { color:#08734e; font-size:0.86rem; font-weight:700; text-decoration:none; }
        .pd-empty-action:hover { text-decoration:underline; }
        .pd-next-card { display:flex; flex-direction:column; min-height:188px; padding:18px; border:1px solid #e5eeea; border-radius:12px; background:#f8fbf9; }
        .pd-next-date { color:#08734e; font-size:0.8rem; font-weight:800; letter-spacing:0.04em; text-transform:uppercase; }
        .pd-next-title { margin:7px 0 0; color:#172f2a; font-size:1rem; line-height:1.45; }
        .pd-next-meta { margin-top:8px; color:#71817c; font-size:0.85rem; line-height:1.5; }
        .pd-next-link { align-self:flex-start; margin-top:auto; padding-top:16px; color:#08734e; font-size:0.85rem; font-weight:700; text-decoration:none; }
        .pd-next-link:hover { text-decoration:underline; }
        .pd-next-empty { position:relative; display:flex; flex:1; flex-direction:column; align-items:center; justify-content:center; gap:12px; min-height:188px; overflow:hidden; padding:22px; border:1px solid #e1eee7; border-radius:12px; background:linear-gradient(145deg,#f7fbf8 0%,#edf7f1 100%); text-align:center; }
        .pd-next-empty::before, .pd-next-empty::after { position:absolute; content:''; width:112px; height:112px; border:1px solid rgba(8,115,78,0.09); border-radius:50%; pointer-events:none; }
        .pd-next-empty::before { top:-68px; right:-38px; }
        .pd-next-empty::after { bottom:-78px; left:-48px; }
        .pd-next-empty-icon-wrap { display:flex; align-items:center; justify-content:center; width:58px; height:58px; border:1px solid #d4e9dc; border-radius:18px; background:rgba(255,255,255,0.8); color:#23815e; box-shadow:0 5px 14px rgba(15,70,49,0.05); }
        .pd-next-empty-title { margin:0; color:#173e34; font-size:0.98rem; font-weight:750; }
        .pd-next-empty p { max-width:245px; margin:0; color:#71817c; font-size:0.85rem; line-height:1.55; }
        .pd-next-empty .pd-empty-action { position:relative; z-index:1; display:inline-flex; align-items:center; gap:7px; padding:9px 13px; border:1px solid #cde5d7; border-radius:9px; background:#fff; }
        .pd-next-empty .pd-empty-action:hover { background:#f4fbf6; text-decoration:none; }
        .pd-chart-panel { margin-top:18px; }
        .pd-chart-summary { margin:0 0 6px; color:#71817c; font-size:0.84rem; }
        .pd-participation-chart { display:block; width:100%; max-width:560px; height:auto; margin:8px auto 0; overflow:visible; }
        .pd-chart-gridline { stroke:#e9f0ed; stroke-width:1; }
        .pd-chart-bar { fill:#12a878; }
        .pd-chart-value { fill:#36564b; font-size:12px; font-weight:700; text-anchor:middle; }
        .pd-chart-month { fill:#71817c; font-size:12px; font-weight:700; text-anchor:middle; }
        .pd-chart-empty { display:flex; align-items:center; gap:15px; padding:20px; border-radius:12px; background:#f8fbf9; color:#71817c; }
        .pd-chart-empty-icon { display:flex; align-items:center; justify-content:center; width:46px; height:46px; flex:0 0 46px; border-radius:14px; background:#e9f7f0; color:#08734e; }
        .pd-chart-empty strong { display:block; margin-bottom:4px; color:#173e34; }
        @media (max-width:980px) {
            .pd-panels { grid-template-columns:minmax(0,1fr); }
        }
        @media (max-width:700px) {
            .pd-stats { grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
            .pd-stat { padding:15px 13px; gap:10px; }
            .pd-stat-icon { width:42px; height:42px; flex-basis:42px; }
            .pd-stat-label { font-size:0.78rem; }
            .pd-stat-value { font-size:1.55rem; }
            .pd-welcome { align-items:flex-start; gap:16px; padding:22px; }
            .pd-welcome-art { right:-20px; top:8px; transform:none; }
        }
        @media (max-width:480px) {
            .pd-stats { grid-template-columns:minmax(0,1fr); }
            .pd-panel { padding:17px; }
            .pd-empty { flex-direction:column; align-items:flex-start; gap:14px; padding:18px; }
            .pd-empty-art { width:76px; height:76px; flex-basis:76px; }
            .pd-empty-art svg { width:40px; height:40px; }
            .pd-opportunity { align-items:flex-start; }
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
    </section>

    <section class="pd-panels">
        <div class="pd-panel">
            <div class="pd-panel-head">
                <h3 class="pd-panel-title">
                    <span class="pd-panel-icon" aria-hidden="true">
                        <svg width="19" height="19" viewBox="0 0 24 24" fill="none"><path d="M12 21V11m0 5c-4 0-6-2-6-6 4 0 6 2 6 6Zm0-3c4 0 6-2 6-6-4 0-6 2-6 6Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                    Open volunteer opportunities
                </h3>
                <a class="pd-panel-link" href="{{ route('participant.activities') }}">Browse all</a>
            </div>

            @if($feedPreview->isEmpty())
                <div class="pd-empty">
                    <div class="pd-empty-art" aria-hidden="true">
                        <svg width="54" height="54" viewBox="0 0 54 54" fill="none">
                            <path d="M27 45V23m0 14c-9 0-14-5-15-14 9 0 14 5 15 14Zm0-9c9 0 14-5 15-14-9 0-14 5-15 14Z" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M13 45h28" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div class="pd-empty-copy">
                        <h4>No activities open right now</h4>
                        <p>New opportunities are added throughout the year. Check the activity list to see what’s available.</p>
                        <a class="pd-empty-action" href="{{ route('participant.activities') }}">Explore activities</a>
                    </div>
                </div>
            @else
                <ul class="pd-opportunities" style="list-style:none; margin:0; padding:0">
                    @foreach($feedPreview as $opportunity)
                        <li class="pd-opportunity">
                            <div style="min-width:0">
                                <div class="pd-opportunity-title">{{ $opportunity->title }}</div>
                                <div class="pd-opportunity-meta pd-opportunity-org">
                                    <x-organization-avatar :organization="$opportunity->creator" :size="28" />
                                    <span>{{ $opportunity->creator->organization_name ?? $opportunity->creator->name ?? 'Unknown organization' }}</span>
                                    @if($opportunity->event_date)
                                        <span>· {{ $opportunity->event_date->format('M j, Y') }}</span>
                                    @endif
                                </div>
                            </div>
                            <a class="pd-view-link" href="{{ route('participant.activities.show', $opportunity) }}">View details</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="pd-panel">
            <div class="pd-panel-head">
                <h3 class="pd-panel-title">
                    <span class="pd-panel-icon" aria-hidden="true">
                        <svg width="19" height="19" viewBox="0 0 24 24" fill="none"><rect x="4" y="5" width="16" height="16" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M8 3v4m8-4v4M4 10h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    </span>
                    Next activity
                </h3>
            </div>

            @if($nextEnrolled)
                <div class="pd-next-card">
                    <div class="pd-next-date">{{ $nextEnrolled->announcement->event_date->format('D, M j · g:i A') }}</div>
                    <h4 class="pd-next-title">{{ $nextEnrolled->announcement->title }}</h4>
                    @if($nextEnrolled->announcement->event_location)
                        <div class="pd-next-meta">{{ $nextEnrolled->announcement->event_location }}</div>
                    @endif
                    @if($nextEnrolled->announcement->creator)
                        <div class="pd-next-meta pd-opportunity-org">
                            <x-organization-avatar :organization="$nextEnrolled->announcement->creator" :size="28" />
                            <span>{{ $nextEnrolled->announcement->creator->organization_name ?? $nextEnrolled->announcement->creator->name ?? 'Organization' }}</span>
                        </div>
                    @endif
                    <a class="pd-next-link" href="{{ route('participant.activities.show', $nextEnrolled->announcement) }}">View activity details</a>
                </div>
            @else
                <div class="pd-next-empty">
                    <span class="pd-next-empty-icon-wrap" aria-hidden="true">
                        <svg width="32" height="32" viewBox="0 0 48 48" fill="none">
                            <rect x="7" y="10" width="28" height="29" rx="4" stroke="currentColor" stroke-width="2"/>
                            <path d="M14 6v8m14-8v8M7 19h28" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            <circle cx="34" cy="33" r="9" fill="#fff" stroke="currentColor" stroke-width="2"/>
                            <path d="M34 28v5l3 2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </span>
                    <h4 class="pd-next-empty-title">Your next activity starts here</h4>
                    <p>You’re not enrolled in an upcoming activity yet. Find a community opportunity that fits your interests.</p>
                    <a class="pd-empty-action" href="{{ route('participant.activities') }}">Find an activity</a>
                </div>
            @endif
        </div>
    </section>

    @if($participantAnnouncements->isNotEmpty())
        <section class="pd-panel" style="margin-top:18px" aria-labelledby="pd-announcements-title">
            <div class="pd-panel-head">
                <h3 class="pd-panel-title" id="pd-announcements-title">
                    <span class="pd-panel-icon" aria-hidden="true">
                        <svg width="19" height="19" viewBox="0 0 24 24" fill="none"><path d="M4 5h16v14H4zM8 9h8m-8 4h8m-8 3h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                    Community announcements
                </h3>
                <a class="pd-panel-link" href="{{ route('participant.activities') }}">Browse all</a>
            </div>
            <ul class="pd-opportunities" style="list-style:none; margin:0; padding:0">
                @foreach($participantAnnouncements as $participantAnnouncement)
                    <li class="pd-opportunity">
                        <div style="min-width:0">
                            <div class="pd-opportunity-title">{{ $participantAnnouncement->title }}</div>
                            <div class="pd-opportunity-meta pd-opportunity-org">
                                <x-organization-avatar :organization="$participantAnnouncement->creator" :size="28" />
                                <span>{{ $participantAnnouncement->creator->organization_name ?? $participantAnnouncement->creator->name ?? 'Unknown organization' }}</span>
                                <span>· {{ $participantAnnouncement->category }}</span>
                            </div>
                            @if($participantAnnouncement->project)
                                <div class="pd-opportunity-meta">Activity: {{ $participantAnnouncement->project->title }}</div>
                            @endif
                        </div>
                        <a class="pd-view-link" href="{{ route('participant.activities.show', $participantAnnouncement) }}">View announcement</a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="pd-panel pd-chart-panel" aria-labelledby="pd-participation-title">
        <div class="pd-panel-head">
            <div>
                <h3 class="pd-panel-title" id="pd-participation-title">
                    <span class="pd-panel-icon" aria-hidden="true">
                        <svg width="19" height="19" viewBox="0 0 24 24" fill="none"><path d="M4 19V5m0 14h17M8 15l4-4 3 2 5-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                    My participation
                </h3>
                <p class="pd-chart-summary">Activities attended per month · last 6 months</p>
            </div>
            <a class="pd-panel-link" href="{{ route('participant.attendance') }}">View attendance</a>
        </div>

        @if($totalAttendedActivities > 0)
            <svg class="pd-participation-chart" viewBox="0 0 560 205" role="img" aria-label="You attended {{ $totalAttendedActivities }} {{ $totalAttendedActivities === 1 ? 'activity' : 'activities' }} over the last six months.">
                <line class="pd-chart-gridline" x1="20" y1="36" x2="540" y2="36"/>
                <line class="pd-chart-gridline" x1="20" y1="76" x2="540" y2="76"/>
                <line class="pd-chart-gridline" x1="20" y1="116" x2="540" y2="116"/>
                <line class="pd-chart-gridline" x1="20" y1="156" x2="540" y2="156"/>
                @foreach($participationMonths as $month)
                    @php
                        $barTop = 156 - $month['bar_height'];
                        $valueY = max(26, $barTop - 8);
                    @endphp
                    <rect class="pd-chart-bar" x="{{ $month['bar_x'] }}" y="{{ $barTop }}" width="48" height="{{ $month['bar_height'] }}" rx="7"/>
                    <text class="pd-chart-value" x="{{ $month['bar_x'] + 24 }}" y="{{ $valueY }}">{{ $month['count'] }}</text>
                    <text class="pd-chart-month" x="{{ $month['bar_x'] + 24 }}" y="184">{{ $month['label'] }}</text>
                @endforeach
            </svg>
        @else
            <div class="pd-chart-empty">
                <span class="pd-chart-empty-icon" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M4 19V5m0 14h17M8 15l4-4 3 2 5-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <div>
                    <strong>No attendance history yet</strong>
                    Once you attend an activity, your participation will appear here.
                </div>
            </div>
        @endif
    </section>
@endsection
