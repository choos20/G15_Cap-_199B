@extends('layouts.admin')

@section('title', 'Reports')
@section('subtitle', 'Accomplishment reports and records')

@section('content')
    @php
        $reports = [
            [
                'route' => 'reports.project_status',
                'name' => 'Project status summary',
                'blurb' => 'The review pipeline for a period — approved, pending and returned, with counts, shares, days in queue, and a comparison against the previous academic year.',
                'meta' => 'On-screen chart · CSV · Print to PDF',
                'bg' => '#eff6ff', 'fg' => '#1d4ed8',
                'icon' => '<path d="M4 19h16M7 16V9m5 7V5m5 11v-5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>',
            ],
            [
                'route' => 'reports.activities',
                'name' => 'Activities by initiator',
                'blurb' => 'What each organization, college or department actually carried out — activity, date conducted, venue, volunteers and recorded attendance.',
                'meta' => 'CSV · Print to PDF',
                'bg' => '#ecfdf5', 'fg' => '#047857',
                'icon' => '<path d="M4 5h16v15H4V5zm0 5h16M9 3v4m6-4v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
            ],
            [
                'route' => 'reports.attendance',
                'name' => 'Participants per activity',
                'blurb' => 'Attendance roster for one activity or across a date range. The printed version carries a signature column for on-site use.',
                'meta' => 'CSV · Printable roster with signature column',
                'bg' => '#fff7ed', 'fg' => '#9a3412',
                'icon' => '<path d="M7 8a3 3 0 1 1 6 0 3 3 0 0 1-6 0zm-3 12a6 6 0 0 1 12 0M16 11h5m-5 4h5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
            ],
            [
                'route' => 'reports.volunteers',
                'name' => 'Activity participation record',
                'blurb' => 'Approved volunteers and assigned participants — consolidated across everyone, or one participant\'s record for clearance and scholarship requirements.',
                'meta' => 'CSV · Individual record with certification block',
                'bg' => '#fdf2f8', 'fg' => '#9d174d',
                'icon' => '<path d="M12 21s-7-4.5-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 11c0 5.5-7 10-7 10z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>',
            ],
            [
                'route' => 'reports.evaluations',
                'name' => 'Participant feedback',
                'blurb' => 'Review participant ratings and written feedback for activities, with averages by evaluation area.',
                'meta' => 'On-screen summary · CSV · Print to PDF',
                'bg' => '#f5f3ff', 'fg' => '#6d28d9',
                'icon' => '<path d="M12 3.5 14.6 9l6 .8-4.4 4.2 1.1 6-5.3-2.9-5.3 2.9 1.1-6-4.4-4.2 6-.8L12 3.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>',
            ],
        ];
    @endphp

    <style>
        .rh-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(360px,1fr)); gap:16px; }
        .rh-card { display:flex; gap:16px; padding:22px; background:#fff; border-radius:16px;
                   box-shadow:0 6px 18px rgba(11,42,42,0.06); text-decoration:none; color:inherit;
                   transition:box-shadow 180ms ease, transform 180ms ease; }
        .rh-card:hover { box-shadow:0 14px 30px rgba(11,42,42,0.12); transform:translateY(-2px); }
        .rh-icon { width:46px; height:46px; border-radius:14px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .rh-name { font-weight:700; font-size:1.05rem; color:#0f172a; }
        .rh-blurb { color:#475569; font-size:0.9rem; line-height:1.6; margin-top:7px; }
        .rh-meta { color:#94a3b8; font-size:0.8rem; margin-top:10px; font-weight:600; }
    </style>

    <div class="rh-grid">
        @foreach($reports as $r)
            <a href="{{ route($r['route']) }}" class="rh-card">
                <div class="rh-icon" style="background:{{ $r['bg'] }}; color:{{ $r['fg'] }}">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none">{!! $r['icon'] !!}</svg>
                </div>
                <div>
                    <div class="rh-name">{{ $r['name'] }}</div>
                    <div class="rh-blurb">{{ $r['blurb'] }}</div>
                    <div class="rh-meta">{{ $r['meta'] }}</div>
                </div>
            </a>
        @endforeach
    </div>

@endsection
