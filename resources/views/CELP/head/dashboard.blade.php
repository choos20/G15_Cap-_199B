@extends('layouts.admin')

@section('title', 'Dashboard')
@section('subtitle', 'CELP oversight and system administration')

@section('content')
    @php
        $greeting = now()->hour < 12 ? 'Good morning' : (now()->hour < 18 ? 'Good afternoon' : 'Good evening');
        $statusMeta = [
            ['label' => 'Approved', 'count' => $approvedCount, 'fill' => '#16866c', 'icon' => '✓'],
            ['label' => 'Pending', 'count' => $pendingCount, 'fill' => '#d18a1c', 'icon' => '◷'],
            ['label' => 'Returned', 'count' => $returnedCount, 'fill' => '#c95f5f', 'icon' => '↶'],
        ];
        $statusSum = $approvedCount + $pendingCount + $returnedCount;
        foreach ($statusMeta as $index => $status) {
            $statusMeta[$index]['pct'] = $statusSum > 0 ? round($status['count'] / $statusSum * 100) : 0;
        }
        $sparklinePoints = function (array $values): string {
            $minimum = min($values);
            $range = max(max($values) - $minimum, 1);

            return collect($values)->values()->map(
                fn ($value, $index) => sprintf('%.1f,%.1f', $index * 16, 23 - (($value - $minimum) / $range * 18))
            )->implode(' ');
        };
        $smoothTrendPath = function (array $points, ?float $baseline = null): string {
            $first = $points[0];
            $path = $baseline === null
                ? sprintf('M %.2f,%.2f', $first['x'], $first['y'])
                : sprintf('M %.2f,%.2f L %.2f,%.2f', $first['x'], $baseline, $first['x'], $first['y']);
            $pointCount = count($points);

            for ($index = 0; $index < $pointCount - 1; $index++) {
                $previous = $points[max($index - 1, 0)];
                $start = $points[$index];
                $end = $points[$index + 1];
                $next = $points[min($index + 2, $pointCount - 1)];
                $controlOneX = $start['x'] + (($end['x'] - $previous['x']) / 6);
                $controlOneY = $start['y'] + (($end['y'] - $previous['y']) / 6);
                $controlTwoX = $end['x'] - (($next['x'] - $start['x']) / 6);
                $controlTwoY = $end['y'] - (($next['y'] - $start['y']) / 6);

                $path .= sprintf(
                    ' C %.2f,%.2f %.2f,%.2f %.2f,%.2f',
                    $controlOneX,
                    $controlOneY,
                    $controlTwoX,
                    $controlTwoY,
                    $end['x'],
                    $end['y']
                );
            }

            return $baseline === null
                ? $path
                : $path.sprintf(' L %.2f,%.2f Z', $points[$pointCount - 1]['x'], $baseline);
        };
    @endphp

    <style>
        .chd-dashboard {
            --chd-ink:#183247; --chd-muted:#687b8d; --chd-line:#e4ebf0;
            --chd-brand:#087f67; --chd-brand-soft:#eaf6f2;
            display:grid; gap:17px; color:var(--chd-ink);
        }
        .chd-welcome {
            position:relative; display:flex; align-items:center; min-height:104px; overflow:hidden;
            padding:22px 26px; border:1px solid #d8e9e3; border-radius:17px;
            background:linear-gradient(110deg,#f3faf7 0%,#eef7f4 58%,#e5f1ed 100%);
            box-shadow:0 6px 18px rgba(24,50,71,0.035);
        }
        .chd-welcome-copy { position:relative; z-index:1; max-width:650px; }
        .chd-welcome h3 { margin:0; color:#173b3b; font-size:1.4rem; letter-spacing:-.025em; line-height:1.35; }
        .chd-welcome p { margin:5px 0 0; color:#607b78; font-size:0.88rem; line-height:1.5; }
        .chd-welcome-meta { position:relative; z-index:1; margin-left:auto; padding-left:20px; color:#637b8a; font-size:0.8rem; line-height:1.6; text-align:right; }
        .chd-welcome-meta strong { display:block; color:#284c52; }
        .chd-welcome-art { position:absolute; right:175px; top:50%; width:180px; height:110px; transform:translateY(-50%); color:#0d806b; opacity:0.1; }
        .chd-stats { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; }
        .chd-stat { position:relative; display:flex; align-items:center; gap:12px; min-width:0; padding:15px; border:1px solid var(--chd-line); border-radius:15px; background:#fff; box-shadow:0 4px 13px rgba(24,50,71,0.035); text-decoration:none; transition:transform 170ms ease,border-color 170ms ease,box-shadow 170ms ease; }
        .chd-stat:hover { transform:translateY(-2px); border-color:#cbd9df; box-shadow:0 11px 24px rgba(24,50,71,0.09); }
        .chd-stat-review { background:linear-gradient(145deg,#fff 0%,#fffbf3 100%); }
        .chd-stat-projects { background:linear-gradient(145deg,#fff 0%,#f3faf7 100%); }
        .chd-stat-accounts { background:linear-gradient(145deg,#fff 0%,#f4f8fd 100%); }
        .chd-stat-volunteers { background:linear-gradient(145deg,#fff 0%,#f8f6fc 100%); }
        .chd-stat-icon { display:flex; align-items:center; justify-content:center; width:44px; height:44px; flex:0 0 44px; border-radius:13px; color:#fff; box-shadow:0 4px 9px rgba(24,50,71,0.12); }
        .chd-stat-review .chd-stat-icon { background:#d99013; }
        .chd-stat-projects .chd-stat-icon { background:#0b8b6c; }
        .chd-stat-accounts .chd-stat-icon { background:#3977c6; }
        .chd-stat-volunteers .chd-stat-icon { background:#7660b5; }
        .chd-stat-icon svg { width:22px; height:22px; }
        .chd-stat-copy { min-width:0; }
        .chd-stat-label { color:#596d7e; font-size:0.76rem; font-weight:700; line-height:1.35; }
        .chd-stat-value { margin-top:4px; color:#183247; font-size:1.55rem; font-weight:800; letter-spacing:-.035em; line-height:1.1; }
        .chd-stat-review .chd-stat-label { color:#80602b; }
        .chd-stat-projects .chd-stat-label { color:#286c59; }
        .chd-stat-accounts .chd-stat-label { color:#3e5e84; }
        .chd-stat-volunteers .chd-stat-label { color:#655783; }
        .chd-stat-link { display:inline-block; margin-top:5px; color:#65798b; font-size:0.73rem; font-weight:650; text-decoration:none; }
        .chd-stat-link:hover, .chd-panel-link:hover, .chd-project-name:hover { text-decoration:underline; }
        .chd-stat-trend { display:flex; align-items:center; gap:5px; min-width:0; margin-top:5px; }
        .chd-sparkline { display:block; width:54px; height:23px; flex:0 0 54px; overflow:visible; }
        .chd-sparkline polyline { fill:none; stroke:currentColor; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; }
        .chd-stat-projects .chd-sparkline { color:#16866c; }
        .chd-stat-accounts .chd-sparkline { color:#3977c6; }
        .chd-trend-badge { overflow:hidden; padding:4px 6px; border-radius:999px; font-size:0.56rem; font-weight:750; line-height:1.2; text-overflow:ellipsis; white-space:nowrap; }
        .chd-trend-badge.is-up { background:#e4f4ec; color:#14704f; }
        .chd-trend-badge.is-down { background:#faeaea; color:#a14343; }
        .chd-trend-badge.is-flat { background:#f1f5f9; color:#64748b; }
        .chd-stat-arrow { display:flex; align-items:center; justify-content:center; width:30px; height:30px; flex:0 0 30px; margin-left:auto; border:1px solid #e3ebe9; border-radius:50%; background:#fff; color:#54716f; box-shadow:0 2px 6px rgba(24,50,71,0.06); transition:transform 170ms ease,background-color 170ms ease,color 170ms ease,border-color 170ms ease; }
        .chd-stat-arrow svg { width:15px; height:15px; transition:transform 170ms ease; }
        .chd-stat:hover .chd-stat-arrow { transform:translateX(2px); border-color:#0b8b6c; background:#0b8b6c; color:#fff; }
        .chd-stat:hover .chd-stat-arrow svg { transform:translateX(1px); }
        .chd-stat:focus-visible { outline:3px solid rgba(8,127,103,0.28); outline-offset:3px; }
        .chd-stat:focus-visible .chd-stat-arrow { border-color:#0b8b6c; color:#0b8b6c; }
        .chd-content-grid { display:grid; grid-template-columns:minmax(0,1.65fr) minmax(260px,0.95fr); gap:14px; align-items:stretch; }
        .chd-panel { min-width:0; padding:19px; border:1px solid var(--chd-line); border-radius:16px; background:#fff; box-shadow:0 5px 17px rgba(24,50,71,0.045); }
        .chd-panel-head { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:8px; }
        .chd-panel-title { display:flex; align-items:center; gap:9px; min-width:0; margin:0; color:#203b50; font-size:0.98rem; letter-spacing:-.01em; }
        .chd-panel-icon { display:flex; align-items:center; justify-content:center; width:34px; height:34px; flex:0 0 34px; border-radius:11px; background:#edf6f3; color:#087f67; }
        .chd-panel-link { color:#087f67; font-size:0.77rem; font-weight:700; text-decoration:none; white-space:nowrap; }
        .chd-overview-chart { display:grid; grid-template-columns:150px minmax(0,1fr); align-items:center; gap:17px; margin-top:13px; padding:12px; border:1px solid #edf2f2; border-radius:14px; background:linear-gradient(140deg,#fff,#fbfdfc); }
        .chd-donut { display:block; width:150px; height:150px; overflow:visible; }
        .chd-donut-track { fill:none; stroke:#edf2f4; stroke-width:16; }
        .chd-donut-segment { fill:none; stroke-width:16; transform:rotate(-90deg); transform-origin:80px 80px; }
        .chd-donut-total { fill:#12304f; font-size:25px; font-weight:800; text-anchor:middle; }
        .chd-donut-label { fill:#8290a0; font-size:10px; font-weight:650; text-anchor:middle; }
        .chd-legend { display:grid; gap:8px; }
        .chd-legend-row { display:grid; grid-template-columns:minmax(0,1fr) auto auto; align-items:center; gap:12px; padding:9px 10px; border:1px solid #edf1f3; border-radius:10px; background:#fbfdfc; }
        .chd-legend-name { display:flex; align-items:center; gap:8px; min-width:0; color:#42596a; font-size:0.77rem; font-weight:700; }
        .chd-status-dot { width:9px; height:9px; flex:0 0 9px; border-radius:50%; }
        .chd-legend-count { color:#12304f; font-size:0.9rem; font-weight:800; font-variant-numeric:tabular-nums; }
        .chd-legend-pct { min-width:38px; color:#8290a0; font-size:0.72rem; text-align:right; }
        .chd-chart-grid { display:grid; grid-template-columns:minmax(0,1.65fr) minmax(260px,0.95fr); gap:14px; align-items:stretch; }
        .chd-trend-chart { display:block; width:100%; height:auto; margin-top:8px; overflow:visible; }
        .chd-trend-plot { fill:#f3f6f8; }
        .chd-trend-gridline { stroke:#dce5eb; stroke-width:1; }
        .chd-trend-axis { fill:#718394; font-size:10px; }
        .chd-trend-y-axis { fill:#718394; font-size:9px; font-variant-numeric:tabular-nums; }
        .chd-trend-line { fill:none; stroke-width:3.2; stroke-linecap:round; stroke-linejoin:round; }
        .chd-trend-area { stroke:none; }
        .chd-trend-point { stroke:#f3f6f8; stroke-width:2.5; }
        .chd-chart-legend { display:flex; flex-wrap:wrap; gap:14px; margin-top:4px; }
        .chd-chart-legend-item { display:flex; align-items:center; gap:6px; color:#627384; font-size:0.72rem; font-weight:700; }
        .chd-status-breakdown { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:9px; margin-top:15px; }
        .chd-status-card { min-width:0; padding:12px 10px; border:1px solid #e8edf0; border-radius:12px; background:#fbfdfc; }
        .chd-status-card-head { display:flex; align-items:center; gap:7px; color:#5f7180; font-size:0.72rem; font-weight:700; }
        .chd-status-pct { margin-top:10px; color:#12304f; font-size:1.2rem; font-weight:800; line-height:1.1; }
        .chd-status-count { margin-top:4px; color:#8290a0; font-size:0.7rem; }
        .chd-toggle { margin-top:13px; padding:0; border:0; background:none; color:#08734e; font:inherit; font-size:0.78rem; font-weight:700; cursor:pointer; }
        .chd-table { width:100%; margin-top:10px; border-collapse:collapse; font-size:0.8rem; }
        .chd-table th, .chd-table td { padding:8px; border-bottom:1px solid #edf1f3; text-align:left; }
        .chd-table th { color:#8290a0; font-size:0.68rem; text-transform:uppercase; letter-spacing:.04em; }
        .chd-table[hidden] { display:none; }
        .chd-project-list { display:flex; flex-direction:column; gap:8px; margin:10px 0 0; padding:0; list-style:none; }
        .chd-project { display:flex; align-items:center; gap:9px; min-width:0; padding:8px; border:1px solid #edf1f3; border-radius:10px; background:#fff; }
        .chd-project-main { min-width:0; flex:1; }
        .chd-project-name { display:block; overflow:hidden; color:#253b4b; font-size:0.78rem; font-weight:750; line-height:1.35; text-decoration:none; text-overflow:ellipsis; white-space:nowrap; }
        .chd-project-org { margin-top:3px; overflow:hidden; color:#8290a0; font-size:0.68rem; text-overflow:ellipsis; white-space:nowrap; }
        .chd-project-date { margin-top:2px; color:#99a3af; font-size:0.66rem; }
        .chd-project-status { flex:0 0 auto; padding:4px 7px; border-radius:999px; font-size:0.64rem; font-weight:800; white-space:nowrap; }
        .chd-project-status.is-approved { background:#e8f8ef; color:#08734e; }
        .chd-project-status.is-pending { background:#fff5db; color:#96620a; }
        .chd-project-status.is-returned { background:#fff0f0; color:#b42323; }
        .chd-project-status.is-other { background:#edf4ff; color:#315b9a; }
        .chd-empty { display:flex; align-items:center; gap:10px; padding:13px; border:1px dashed #dbe7e1; border-radius:11px; background:#f8fbf9; color:#64756e; font-size:0.8rem; line-height:1.45; }
        .chd-attention { display:grid; gap:12px; }
        .chd-attention-box { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:12px; border:1px solid #f3e2b8; border-radius:11px; background:#fffaf0; }
        .chd-attention-copy { min-width:0; }
        .chd-attention-copy strong { display:block; color:#78520d; font-size:0.81rem; }
        .chd-attention-copy span { display:block; margin-top:3px; color:#8a7959; font-size:0.72rem; line-height:1.4; }
        .chd-review-link { flex:0 0 auto; padding:7px 10px; border-radius:8px; background:#0b7656; color:#fff; font-size:0.75rem; font-weight:700; text-decoration:none; }
        .chd-review-link:hover { background:#086247; }
        .chd-oldest { padding:12px; border:1px solid #e5ebf0; border-radius:11px; background:#f9fbfc; }
        .chd-oldest-head { display:flex; align-items:center; justify-content:space-between; gap:8px; color:#4b6473; font-size:0.76rem; font-weight:750; }
        .chd-oldest-project { margin-top:8px; color:#1e3947; font-size:0.85rem; font-weight:800; line-height:1.4; }
        .chd-oldest-meta { display:flex; align-items:center; gap:7px; flex-wrap:wrap; margin-top:6px; color:#718096; font-size:0.72rem; }
        .chd-quick-links { display:grid; gap:8px; }
        .chd-quick-link { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:10px 11px; border:1px solid #e3ebef; border-radius:10px; background:#fff; color:#315d57; font-size:0.78rem; font-weight:700; text-decoration:none; transition:background-color 150ms ease,border-color 150ms ease; }
        .chd-quick-link:hover { border-color:#cbdcd5; background:#f7faf9; }
        .chd-community { display:flex; align-items:center; gap:10px; margin-top:10px; padding:11px; border-radius:11px; background:linear-gradient(100deg,#effaf4,#e4f4eb); color:#176047; font-size:0.74rem; font-weight:700; line-height:1.4; }
        .chd-community-icon { font-size:1.2rem; }
        .chd-focus:focus-visible { outline:3px solid rgba(8,115,78,0.32); outline-offset:3px; }
        .chd-table-toggle[hidden] { display:none; }
        @media(max-width:1050px) {
            .chd-stats { grid-template-columns:repeat(2,minmax(0,1fr)); }
            .chd-content-grid { grid-template-columns:minmax(0,1fr); }
        }
        @media(max-width:640px) {
            .chd-welcome { align-items:flex-start; min-height:120px; padding:18px; }
            .chd-welcome-meta { display:none; }
            .chd-welcome-art { right:-25px; top:0; transform:none; }
            .chd-stats { grid-template-columns:minmax(0,1fr); }
            .chd-panel { padding:15px; }
            .chd-chart-grid { grid-template-columns:minmax(0,1fr); }
            .chd-overview-chart { grid-template-columns:120px minmax(0,1fr); gap:10px; }
            .chd-donut { width:120px; height:120px; }
            .chd-status-breakdown { grid-template-columns:minmax(0,1fr); }
            .chd-attention-box { align-items:flex-start; flex-direction:column; }
        }
        @media(prefers-reduced-motion:reduce) {
            .chd-stat, .chd-stat-arrow, .chd-stat-arrow svg, .chd-quick-link { transition:none; }
        }
    </style>

    <div class="chd-dashboard">
        @if(session('status'))
            <div class="card" style="border-left:4px solid #16a34a; background:#ecfdf5; color:#134e4a; padding:16px 20px">
                {{ session('status') }}
            </div>
        @endif

        <section class="chd-welcome" aria-label="Welcome">
            <div class="chd-welcome-copy">
                <h3>{{ $greeting }}, {{ $user->first_name ?: $user->name }}!</h3>
                <p>Here’s what’s happening with your CELP dashboard.</p>
            </div>
            <svg class="chd-welcome-art" viewBox="0 0 180 110" fill="none" aria-hidden="true">
                <path d="M20 83h142M32 83V42l24-18 24 18v41m-40 0V55h32v28m17 0V31h41v52m-31-39h8m-8 14h8m-8 14h8" stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M114 31V15h23v16" stroke="currentColor" stroke-width="5" stroke-linejoin="round"/>
            </svg>
            <div class="chd-welcome-meta">
                <strong>{{ now()->format('D, M j, Y') }}</strong>
                Holy Name University
            </div>
        </section>

        <section class="chd-stats" aria-label="CELP Head dashboard summary">
            <a class="chd-stat chd-stat-review chd-focus" href="{{ route('projects.celp_head_review') }}">
                <span class="chd-stat-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M6 3h9l4 4v14H6zM14 3v5h5M9 13h6m-6 4h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <span class="chd-stat-copy">
                    <span class="chd-stat-label">Pending CELP Head review</span>
                    <span class="chd-stat-value" style="display:block">{{ $pendingProposals }}</span>
                    <span class="chd-stat-link">Open review queue</span>
                </span>
                <span class="chd-stat-arrow" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M5 12h14m-6-6 6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
            </a>
            <a class="chd-stat chd-stat-projects chd-focus" href="{{ route('projects.all') }}">
                <span class="chd-stat-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z" fill="currentColor"/></svg>
                </span>
                <span class="chd-stat-copy">
                    <span class="chd-stat-label">Total projects</span>
                    <span class="chd-stat-value" style="display:block">{{ $totalProjects }}</span>
                    <span class="chd-stat-link">View org-initiated projects</span>
                    <span class="chd-stat-trend">
                        <svg class="chd-sparkline" viewBox="0 0 80 28" aria-hidden="true">
                            <polyline points="{{ $sparklinePoints($projectSubmissionTrend['values']) }}"/>
                        </svg>
                        <span class="chd-trend-badge is-{{ $projectSubmissionTrend['direction'] }}" title="{{ $projectSubmissionTrend['label'] }}">
                            {{ $projectSubmissionTrend['label'] }}
                        </span>
                    </span>
                </span>
                <span class="chd-stat-arrow" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M5 12h14m-6-6 6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
            </a>
            <a class="chd-stat chd-stat-accounts chd-focus" href="{{ route('users.index') }}">
                <span class="chd-stat-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="1.8"/><path d="M4 21v-2a8 8 0 0 1 16 0v2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </span>
                <span class="chd-stat-copy">
                    <span class="chd-stat-label">System accounts</span>
                    <span class="chd-stat-value" style="display:block">{{ $totalUsers }}</span>
                    <span class="chd-stat-link">Manage accounts</span>
                    <span class="chd-stat-trend">
                        <svg class="chd-sparkline" viewBox="0 0 80 28" aria-hidden="true">
                            <polyline points="{{ $sparklinePoints($accountCreationTrend['values']) }}"/>
                        </svg>
                        <span class="chd-trend-badge is-{{ $accountCreationTrend['direction'] }}" title="{{ $accountCreationTrend['label'] }}">
                            {{ $accountCreationTrend['label'] }}
                        </span>
                    </span>
                </span>
                <span class="chd-stat-arrow" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M5 12h14m-6-6 6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
            </a>
            <div class="chd-stat chd-stat-volunteers">
                <span class="chd-stat-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><circle cx="9" cy="8" r="3.2" stroke="currentColor" stroke-width="1.8"/><path d="M3.5 20v-1.2a4.8 4.8 0 0 1 4.8-4.8h1.4a4.8 4.8 0 0 1 4.8 4.8V20M16 5.2a3.2 3.2 0 0 1 0 6.2m1.2 2.8a4.7 4.7 0 0 1 3.3 4.5V20" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </span>
                <span class="chd-stat-copy">
                    <span class="chd-stat-label">Total volunteers</span>
                    <span class="chd-stat-value" style="display:block">{{ $totalVolunteers }}</span>
                    <span class="chd-stat-link">Across org-initiated projects</span>
                </span>
            </div>
        </section>

        <div class="chd-content-grid">
            <section class="chd-panel">
                <div class="chd-panel-head">
                    <h3 class="chd-panel-title">
                        <span class="chd-panel-icon" aria-hidden="true">
                            <svg width="19" height="19" viewBox="0 0 24 24" fill="none"><path d="M4 20V10m5 10V4m5 16v-7m5 7V7" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
                        </span>
                        Projects overview
                    </h3>
                    <a class="chd-panel-link chd-focus" href="{{ route('projects.all') }}">View all projects</a>
                </div>
                @php $donutOffset = 0; @endphp
                <div class="chd-overview-chart">
                    <svg class="chd-donut" viewBox="0 0 160 160" role="img" aria-label="Projects by status: {{ collect($statusMeta)->map(fn($status) => "{$status['label']} {$status['count']}")->implode(', ') }}.">
                        <circle class="chd-donut-track" cx="80" cy="80" r="46"/>
                        @foreach($statusMeta as $status)
                            @if($status['count'] > 0)
                                @php
                                    $segmentLength = $status['count'] / max($statusSum, 1) * 289.03;
                                    $segmentOffset = $donutOffset;
                                    $donutOffset += $segmentLength;
                                @endphp
                                <circle class="chd-donut-segment" cx="80" cy="80" r="46" stroke="{{ $status['fill'] }}"
                                        stroke-dasharray="{{ $segmentLength }} 289.03" stroke-dashoffset="{{ -$segmentOffset }}"/>
                            @endif
                        @endforeach
                        <text class="chd-donut-total" x="80" y="78">{{ $statusSum }}</text>
                        <text class="chd-donut-label" x="80" y="96">Total projects</text>
                    </svg>
                    <div class="chd-legend" aria-label="Project status details">
                        @foreach($statusMeta as $status)
                            <div class="chd-legend-row">
                                <span class="chd-legend-name"><span class="chd-status-dot" style="background:{{ $status['fill'] }}"></span>{{ $status['label'] }}</span>
                                <span class="chd-legend-count">{{ $status['count'] }}</span>
                                <span class="chd-legend-pct">{{ $status['pct'] }}%</span>
                            </div>
                        @endforeach
                    </div>
                </div>
                <button type="button" class="chd-toggle chd-focus" id="chd-projects-toggle" aria-expanded="false" aria-controls="chd-projects-table">Show project list</button>
                <table class="chd-table" id="chd-projects-table" hidden>
                    <caption style="position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap">Project counts by status</caption>
                    <thead><tr><th scope="col">Status</th><th scope="col">Projects</th><th scope="col">Share</th></tr></thead>
                    <tbody>
                        @foreach($statusMeta as $status)
                            <tr>
                                <td>{{ $status['label'] }}</td>
                                <td>{{ $status['count'] }}</td>
                                <td>{{ $status['pct'] }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>

            <section class="chd-panel">
                <div class="chd-panel-head">
                    <h3 class="chd-panel-title">
                        <span class="chd-panel-icon" aria-hidden="true">
                            <svg width="19" height="19" viewBox="0 0 24 24" fill="none"><path d="M4 5h16M4 12h16M4 19h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="18" cy="19" r="2" fill="currentColor"/></svg>
                        </span>
                        Recent projects
                    </h3>
                    <a class="chd-panel-link chd-focus" href="{{ route('projects.all') }}">View all</a>
                </div>
                @if($recentProjects->isEmpty())
                    <div class="chd-empty">No organization projects have been submitted yet.</div>
                @else
                    <ul class="chd-project-list">
                        @foreach($recentProjects as $project)
                            @php
                                $statusClass = str_starts_with($project->status, 'returned_by_')
                                    ? 'is-returned'
                                    : (in_array($project->status, ['approved', 'csa_cleared'], true)
                                        ? 'is-approved'
                                        : (in_array($project->status, ['submitted', 'endorsed_by_secretary', 'endorsed_by_coordinator'], true) ? 'is-pending' : 'is-other'));
                                $statusLabel = str_starts_with($project->status, 'returned_by_')
                                    ? 'Returned'
                                    : (in_array($project->status, ['approved', 'csa_cleared'], true)
                                        ? 'Approved'
                                        : (in_array($project->status, ['submitted', 'endorsed_by_secretary', 'endorsed_by_coordinator'], true) ? 'Pending' : \App\Models\Project::statusLabel($project->status)));
                            @endphp
                            <li class="chd-project">
                                <x-organization-avatar :organization="$project->creator" :size="34" />
                                <div class="chd-project-main">
                                    <a class="chd-project-name chd-focus" href="{{ route('projects.all') }}">{{ $project->title }}</a>
                                    <div class="chd-project-org">{{ $project->creator->organization_name ?? $project->creator->name ?? 'Organization' }}</div>
                                    <div class="chd-project-date">{{ $project->created_at?->diffForHumans() ?? 'Date unavailable' }}</div>
                                </div>
                                <span class="chd-project-status {{ $statusClass }}">{{ $statusLabel }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        <div class="chd-chart-grid">
            <section class="chd-panel">
                <div class="chd-panel-head">
                    <h3 class="chd-panel-title">
                        <span class="chd-panel-icon" aria-hidden="true">
                            <svg width="19" height="19" viewBox="0 0 24 24" fill="none"><path d="M4 19V5m0 14h16M7 15l4-4 3 2 6-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        Monthly project submissions
                    </h3>
                    <span class="chd-overview-total">Last 6 months</span>
                </div>
                @php
                    $trendMax = max(collect($projectTrend)->flatMap(fn ($month) => [$month['approved'], $month['pending'], $month['returned']])->max() ?? 0, 1);
                    $trendAxisTicks = [['y' => 36, 'label' => $trendMax]];
                    if ($trendMax >= 2) {
                        $trendAxisTicks[] = ['y' => 92, 'label' => (int) round($trendMax / 2)];
                    }
                    $trendAxisTicks[] = ['y' => 148, 'label' => 0];
                    $trendSeries = [
                            ['key' => 'approved', 'label' => 'Approved', 'color' => '#078d70'],
                            ['key' => 'pending', 'label' => 'Pending', 'color' => '#c98208'],
                            ['key' => 'returned', 'label' => 'Returned', 'color' => '#d94e74'],
                    ];
                    $trendPoints = [];
                    foreach ($trendSeries as $series) {
                        $points = [];
                        foreach ($projectTrend as $index => $month) {
                            $x = 50 + ($index * 94);
                            $y = 148 - (($month[$series['key']] / $trendMax) * 112);
                            $points[] = ['x' => $x, 'y' => $y, 'count' => $month[$series['key']], 'label' => $month['label']];
                        }
                        $trendPoints[$series['key']] = $points;
                    }
                @endphp
                <svg class="chd-trend-chart" viewBox="0 0 560 188" role="img" aria-label="Projects created each month over the last six months, grouped by current status. Vertical axis shows project counts.">
                    <rect class="chd-trend-plot" x="22" y="16" width="534" height="148" rx="12"/>
                    @foreach($trendAxisTicks as $tick)
                        <line class="chd-trend-gridline" x1="50" y1="{{ $tick['y'] }}" x2="542" y2="{{ $tick['y'] }}"/>
                        <text class="chd-trend-y-axis" x="42" y="{{ $tick['y'] + 3 }}" text-anchor="end">{{ $tick['label'] }}</text>
                    @endforeach
                    @foreach($trendSeries as $series)
                        @php $points = $trendPoints[$series['key']]; @endphp
                        <path class="chd-trend-area" fill="{{ $series['color'] }}" fill-opacity="0.06" d="{{ $smoothTrendPath($points, 148) }}"/>
                        <path class="chd-trend-line" stroke="{{ $series['color'] }}" d="{{ $smoothTrendPath($points) }}"/>
                        @foreach($points as $point)
                            <circle class="chd-trend-point" cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="3" fill="{{ $series['color'] }}">
                                <title>{{ $point['label'] }}: {{ $point['count'] }} {{ \Illuminate\Support\Str::plural('project', $point['count']) }} {{ strtolower($series['label']) }}</title>
                            </circle>
                        @endforeach
                    @endforeach
                    @foreach($projectTrend as $index => $month)
                        <text class="chd-trend-axis" x="{{ 50 + ($index * 94) }}" y="181" text-anchor="middle">{{ $month['label'] }}</text>
                    @endforeach
                </svg>
                <div class="chd-chart-legend" aria-label="Trend chart legend">
                    @foreach($trendSeries as $series)
                        <span class="chd-chart-legend-item"><span class="chd-status-dot" style="background:{{ $series['color'] }}"></span>{{ $series['label'] }}</span>
                    @endforeach
                </div>
            </section>

            <section class="chd-panel">
                <div class="chd-panel-head">
                    <h3 class="chd-panel-title">
                        <span class="chd-panel-icon" aria-hidden="true">
                            <svg width="19" height="19" viewBox="0 0 24 24" fill="none"><path d="M4 20V10m5 10V4m5 16v-7m5 7V7" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
                        </span>
                        Status breakdown
                    </h3>
                </div>
                <div class="chd-status-breakdown">
                    @foreach($statusMeta as $status)
                        <div class="chd-status-card">
                            <div class="chd-status-card-head"><span class="chd-status-dot" style="background:{{ $status['fill'] }}"></span>{{ $status['label'] }}</div>
                            <div class="chd-status-pct">{{ $status['pct'] }}%</div>
                            <div class="chd-status-count">{{ $status['count'] }} {{ \Illuminate\Support\Str::plural('project', $status['count']) }}</div>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        <div class="chd-content-grid">
            <section class="chd-panel">
                <div class="chd-panel-head">
                    <h3 class="chd-panel-title">
                        <span class="chd-panel-icon" style="background:#eaf8f2; color:#07815a" aria-hidden="true">
                            <svg width="19" height="19" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M12 7v5l3 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                        </span>
                        Needs your attention
                        @if($pendingProposals > 0)
                            <span class="chd-project-status is-pending">{{ $pendingProposals }} {{ \Illuminate\Support\Str::plural('project', $pendingProposals) }}</span>
                        @endif
                    </h3>
                    <a class="chd-panel-link chd-focus" href="{{ route('projects.celp_head_review') }}">View all</a>
                </div>
                @if($pendingProposals > 0)
                    <div class="chd-attention-box">
                        <div class="chd-attention-copy">
                            <strong>{{ $pendingProposals }} {{ \Illuminate\Support\Str::plural('project', $pendingProposals) }} waiting for review</strong>
                            <span>Coordinator-endorsed proposals are waiting for your final decision.</span>
                        </div>
                        <a class="chd-review-link chd-focus" href="{{ route('projects.celp_head_review') }}">Review now</a>
                    </div>
                @else
                    <div class="chd-empty">Your final-review queue is clear. New endorsed proposals will appear here.</div>
                @endif

                <div class="chd-oldest">
                    <div class="chd-oldest-head">
                        <span>Longest-waiting final review</span>
                        @if($oldestPending)
                            <span class="chd-project-status is-pending">Waiting</span>
                        @endif
                    </div>
                    @if($oldestPending)
                        <div class="chd-oldest-project">{{ $oldestPending->title }}</div>
                        <div class="chd-oldest-meta">
                            <x-organization-avatar :organization="$oldestPending->creator" :size="26" />
                            <span>{{ $oldestPending->creator->organization_name ?? $oldestPending->creator->name ?? 'Unknown organization' }}</span>
                            <span>· Waiting {{ $oldestPending->created_at?->diffForHumans() ?? 'Date unavailable' }}</span>
                            <a class="chd-panel-link chd-focus" href="{{ route('projects.show_review', $oldestPending) }}">Review now</a>
                        </div>
                    @else
                        <div class="chd-empty" style="margin-top:8px">Nothing is waiting on your final review right now.</div>
                    @endif
                </div>
            </section>

            <aside class="chd-panel">
                <div class="chd-panel-head">
                    <h3 class="chd-panel-title">
                        <span class="chd-panel-icon" aria-hidden="true">⚡</span>
                        Quick actions
                    </h3>
                </div>
                <nav class="chd-quick-links" aria-label="Quick actions">
                    <a class="chd-quick-link chd-focus" href="{{ route('users.create') }}">Create an account</a>
                    <a class="chd-quick-link chd-focus" href="{{ route('users.index') }}">Manage accounts</a>
                    <a class="chd-quick-link chd-focus" href="{{ route('reports.operational') }}">Generate reports</a>
                    <a class="chd-quick-link chd-focus" href="{{ route('projects.all') }}">Browse all projects</a>
                </nav>
                <div class="chd-community">
                    <span class="chd-community-icon" aria-hidden="true">🌱</span>
                    <span>Together we make a difference through community extension.</span>
                </div>
            </aside>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggle = document.getElementById('chd-projects-toggle');
            const table = document.getElementById('chd-projects-table');

            if (!toggle || !table) {
                return;
            }

            toggle.addEventListener('click', function () {
                const isOpening = table.hasAttribute('hidden');
                table.toggleAttribute('hidden', !isOpening);
                toggle.setAttribute('aria-expanded', String(isOpening));
                toggle.textContent = isOpening ? 'Hide project list' : 'Show project list';
            });
        });
    </script>
@endsection
