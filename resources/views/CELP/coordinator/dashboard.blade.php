@extends('layouts.admin')

@section('title', 'Coordinator Dashboard')
@section('subtitle', 'Second-level review, monitoring & reports')

@section('content')
    @php
        $greeting = now()->hour < 12 ? 'Good morning' : (now()->hour < 18 ? 'Good afternoon' : 'Good evening');
        $statusMeta = $workflowStages;
        $statusSum = $workflowTotal;
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
                $path .= sprintf(
                    ' C %.2f,%.2f %.2f,%.2f %.2f,%.2f',
                    $start['x'] + (($end['x'] - $previous['x']) / 6),
                    $start['y'] + (($end['y'] - $previous['y']) / 6),
                    $end['x'] - (($next['x'] - $start['x']) / 6),
                    $end['y'] - (($next['y'] - $start['y']) / 6),
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
        .cd-dashboard { display:grid; gap:16px; }
        .cd-welcome {
            position:relative; display:flex; align-items:center; min-height:104px; overflow:hidden;
            padding:20px 24px; border:1px solid #d8e8f3; border-radius:16px;
            background:linear-gradient(105deg,#f1f7ff 0%,#f7faff 55%,#e7f5f1 100%);
        }
        .cd-welcome-copy { position:relative; z-index:1; max-width:650px; }
        .cd-welcome h3 { margin:0; color:#12304f; font-size:1.35rem; line-height:1.35; }
        .cd-welcome p { margin:5px 0 0; color:#58718d; font-size:0.88rem; line-height:1.5; }
        .cd-welcome-meta { position:relative; z-index:1; margin-left:auto; padding-left:20px; color:#365c72; font-size:0.8rem; line-height:1.6; text-align:right; }
        .cd-welcome-meta strong { display:block; color:#214b43; }
        .cd-welcome-art { position:absolute; right:180px; top:50%; width:168px; height:100px; transform:translateY(-50%); color:#14815b; opacity:0.12; }
        .cd-stats { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; }
        .cd-stat { position:relative; display:flex; align-items:center; gap:11px; min-width:0; padding:14px; border:1px solid; border-radius:14px; text-decoration:none; transition:transform 150ms ease,box-shadow 150ms ease; }
        .cd-stat:hover { transform:translateY(-2px); box-shadow:0 9px 20px rgba(15,23,42,0.08); }
        .cd-stat-pending { border-color:#f5dfaa; background:linear-gradient(145deg,#fffaf0,#fff4d8); }
        .cd-stat-active { border-color:#ccebd9; background:linear-gradient(145deg,#f1fbf6,#e7f8f0); }
        .cd-stat-evaluations { border-color:#d2e2fb; background:linear-gradient(145deg,#f2f7ff,#e4f1ff); }
        .cd-stat-reports { border-color:#e0d5fb; background:linear-gradient(145deg,#faf7ff,#f1eaff); }
        .cd-stat-icon { display:flex; align-items:center; justify-content:center; width:45px; height:45px; flex:0 0 45px; border-radius:14px; color:#fff; }
        .cd-stat-pending .cd-stat-icon { background:#f59e0b; }
        .cd-stat-active .cd-stat-icon { background:#10b981; }
        .cd-stat-evaluations .cd-stat-icon { background:#3b82f6; }
        .cd-stat-reports .cd-stat-icon { background:#8b5cf6; }
        .cd-stat-icon svg { width:22px; height:22px; }
        .cd-stat-copy { min-width:0; }
        .cd-stat-label { color:#475569; font-size:0.77rem; font-weight:750; line-height:1.35; }
        .cd-stat-value { margin-top:3px; color:#102e2a; font-size:1.45rem; font-weight:800; line-height:1.1; }
        .cd-stat-pending .cd-stat-label { color:#92400e; }
        .cd-stat-active .cd-stat-label { color:#065f46; }
        .cd-stat-evaluations .cd-stat-label { color:#1e3a8a; }
        .cd-stat-reports .cd-stat-label { color:#5b21b6; }
        .cd-stat-link { display:inline-block; margin-top:5px; color:#36516f; font-size:0.76rem; font-weight:700; text-decoration:none; }
        .cd-stat-link:hover, .cd-panel-link:hover { text-decoration:underline; }
        .cd-stat-arrow { display:flex; align-items:center; justify-content:center; width:26px; height:26px; flex:0 0 26px; margin-left:auto; border-radius:50%; background:rgba(255,255,255,0.6); color:#315b79; font-size:1.15rem; }
        .cd-content-grid { display:grid; grid-template-columns:minmax(0,1.65fr) minmax(260px,0.95fr); gap:14px; align-items:stretch; }
        .cd-panel { min-width:0; padding:18px; border:1px solid #e5ebf0; border-radius:15px; background:#fff; box-shadow:0 5px 16px rgba(11,42,42,0.04); }
        .cd-panel-head { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:6px; }
        .cd-panel-title { display:flex; align-items:center; gap:9px; min-width:0; margin:0; color:#123d55; font-size:0.98rem; }
        .cd-panel-icon { display:flex; align-items:center; justify-content:center; width:34px; height:34px; flex:0 0 34px; border-radius:11px; background:#edf5ff; color:#2563eb; }
        .cd-panel-link { color:#2563eb; font-size:0.79rem; font-weight:700; text-decoration:none; white-space:nowrap; }
        .cd-panel-description { margin:0 0 12px; color:#718096; font-size:0.82rem; line-height:1.5; }
        .cd-queue { display:flex; flex-direction:column; gap:8px; margin:0; padding:0; list-style:none; }
        .cd-queue-item { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:11px 12px; border:1px solid #e3ebf4; border-radius:11px; background:#f8fbff; }
        .cd-queue-item.is-oldest { border-color:#f1dcae; background:#fffdf8; }
        .cd-queue-main { min-width:0; }
        .cd-queue-titleline { display:flex; align-items:center; gap:7px; flex-wrap:wrap; }
        .cd-queue-title { color:#172f3e; font-size:0.88rem; font-weight:750; line-height:1.4; overflow-wrap:anywhere; }
        .cd-priority { padding:3px 7px; border-radius:999px; background:#fff2d5; color:#925d00; font-size:0.65rem; font-weight:800; white-space:nowrap; }
        .cd-queue-meta { display:flex; align-items:center; gap:7px; flex-wrap:wrap; margin-top:5px; color:#718096; font-size:0.76rem; line-height:1.4; }
        .cd-review-actions { display:flex; align-items:center; gap:10px; flex:0 0 auto; }
        .cd-waiting { padding:4px 8px; border-radius:999px; background:#fff4d8; color:#925d00; font-size:0.7rem; font-weight:750; white-space:nowrap; }
        .cd-waiting.is-late { background:#fff1f2; color:#b91c1c; }
        .cd-review-link { padding:7px 11px; border-radius:8px; background:#0b7656; color:#fff; font-size:0.78rem; font-weight:700; text-decoration:none; white-space:nowrap; }
        .cd-review-link:hover { background:#086247; }
        .cd-attention-box { display:flex; align-items:flex-start; gap:10px; padding:13px; border:1px solid #c9eee5; border-radius:11px; background:#effcf8; }
        .cd-attention-icon { display:flex; align-items:center; justify-content:center; width:30px; height:30px; flex:0 0 30px; border-radius:50%; background:#10b981; color:#fff; }
        .cd-attention-box strong { display:block; color:#176047; font-size:0.83rem; }
        .cd-attention-box p { margin:3px 0 0; color:#58746d; font-size:0.78rem; line-height:1.45; }
        .cd-oldest-card { margin-top:10px; padding:12px; border:1px solid #f1dcae; border-radius:11px; background:#fffdf8; }
        .cd-oldest-label { margin-bottom:5px; color:#925d00; font-size:0.67rem; font-weight:800; letter-spacing:0.06em; text-transform:uppercase; }
        .cd-oldest-title { color:#172f3e; font-size:0.88rem; font-weight:750; line-height:1.4; }
        .cd-oldest-meta { margin-top:5px; color:#718096; font-size:0.75rem; }
        .cd-chart-layout { display:grid; grid-template-columns:minmax(0,1.55fr) minmax(250px,0.9fr); gap:14px; align-items:stretch; }
        .cd-status-overview { display:grid; grid-template-columns:150px minmax(0,1fr); align-items:center; gap:16px; margin-top:12px; padding:12px; border:1px solid #edf2f2; border-radius:14px; background:linear-gradient(140deg,#fff,#fbfdfc); }
        .cd-donut { display:block; width:150px; height:150px; }
        .cd-donut-track { fill:none; stroke:#e9eff2; stroke-width:16; }
        .cd-donut-segment { fill:none; stroke-width:16; transform:rotate(-90deg); transform-origin:80px 80px; }
        .cd-donut-total { fill:#183247; font-size:25px; font-weight:800; text-anchor:middle; }
        .cd-donut-label { fill:#718394; font-size:10px; font-weight:650; text-anchor:middle; }
        .cd-status-legend { display:grid; gap:8px; }
        .cd-status-legend-row { display:grid; grid-template-columns:minmax(0,1fr) auto auto; align-items:center; gap:10px; padding:9px 10px; border:1px solid #edf1f3; border-radius:10px; background:#fff; }
        .cd-status-legend-name { display:flex; align-items:center; gap:8px; color:#42596a; font-size:0.76rem; font-weight:700; }
        .cd-status-dot { width:9px; height:9px; flex:0 0 9px; border-radius:50%; }
        .cd-status-count { color:#183247; font-size:0.88rem; font-weight:800; font-variant-numeric:tabular-nums; }
        .cd-status-percent { min-width:36px; color:#7b8da4; font-size:0.7rem; text-align:right; }
        .cd-trend-chart { display:block; width:100%; height:auto; margin-top:8px; overflow:visible; }
        .cd-trend-plot { fill:#f3f6f8; }
        .cd-trend-gridline { stroke:#dce5eb; stroke-width:1; }
        .cd-trend-axis { fill:#718394; font-size:10px; }
        .cd-trend-y-axis { fill:#718394; font-size:9px; font-variant-numeric:tabular-nums; }
        .cd-trend-line { fill:none; stroke-width:3.2; stroke-linecap:round; stroke-linejoin:round; }
        .cd-trend-area { stroke:none; }
        .cd-trend-point { stroke:#f3f6f8; stroke-width:2.5; }
        .cd-chart-legend { display:flex; flex-wrap:wrap; gap:14px; margin-top:5px; }
        .cd-chart-legend-item { display:flex; align-items:center; gap:6px; color:#627384; font-size:0.72rem; font-weight:700; }
        .cd-announcements { display:flex; flex-direction:column; gap:10px; margin:10px 0 0; padding:0; list-style:none; }
        .cd-announcement { padding:10px 11px; border:1px solid #e8e4fb; border-radius:10px; background:#fbfaff; }
        .cd-announcement-title { color:#253b54; font-size:0.82rem; font-weight:750; line-height:1.4; overflow-wrap:anywhere; }
        .cd-announcement-date { margin-top:4px; color:#718096; font-size:0.74rem; }
        .cd-empty { display:flex; align-items:center; gap:10px; padding:13px; border:1px dashed #dbe7f0; border-radius:11px; background:#f8fbff; color:#65768a; font-size:0.8rem; line-height:1.45; }
        .cd-empty-icon { display:flex; align-items:center; justify-content:center; width:30px; height:30px; flex:0 0 30px; border-radius:10px; background:#eef4ff; color:#4779d1; }
        .cd-focus:focus-visible { outline:3px solid rgba(37,99,235,0.32); outline-offset:3px; }
        @media(max-width:1050px) {
            .cd-stats { grid-template-columns:repeat(2,minmax(0,1fr)); }
            .cd-content-grid { grid-template-columns:minmax(0,1fr); }
            .cd-chart-layout { grid-template-columns:minmax(0,1fr); }
        }
        @media(max-width:640px) {
            .cd-welcome { align-items:flex-start; min-height:120px; padding:18px; }
            .cd-welcome-meta { display:none; }
            .cd-welcome-art { right:-24px; top:0; transform:none; }
            .cd-stats { grid-template-columns:minmax(0,1fr); }
            .cd-panel { padding:15px; }
            .cd-status-overview { grid-template-columns:120px minmax(0,1fr); gap:10px; }
            .cd-donut { width:120px; height:120px; }
            .cd-queue-item { align-items:flex-start; flex-direction:column; }
            .cd-review-actions { width:100%; justify-content:space-between; }
        }
        @media(prefers-reduced-motion:reduce) {
            .cd-stat { transition:none; }
        }
    </style>

    <div class="cd-dashboard">
        @if(session('status'))
            <div class="card" style="border-left:4px solid #16a34a; background:#ecfdf5; color:#134e4a; padding:16px 20px">
                {{ session('status') }}
            </div>
        @endif

        <section class="cd-welcome" aria-label="Welcome">
            <div class="cd-welcome-copy">
                <h3>{{ $greeting }}, {{ $user->first_name ?: $user->name }}!</h3>
                <p>Here’s what’s happening with your coordinator dashboard.</p>
            </div>
            <svg class="cd-welcome-art" viewBox="0 0 180 110" fill="none" aria-hidden="true">
                <path d="M20 83h142M32 83V42l24-18 24 18v41m-40 0V55h32v28m17 0V31h41v52m-31-39h8m-8 14h8m-8 14h8" stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M114 31V15h23v16" stroke="currentColor" stroke-width="5" stroke-linejoin="round"/>
            </svg>
            <div class="cd-welcome-meta">
                <strong>{{ now()->format('D, M j, Y') }}</strong>
                Holy Name University
            </div>
        </section>

        <section class="cd-stats" aria-label="Coordinator dashboard summary">
            <a class="cd-stat cd-stat-pending cd-focus" href="{{ route('projects.coordinator_review') }}">
                <span class="cd-stat-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M6 3h9l4 4v6M14 3v5h5M6 3v18h7" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="17" cy="17" r="4.5" fill="currentColor"/><path d="M17 14.5V17l1.6 1" stroke="#fff" stroke-width="1.4" stroke-linecap="round"/></svg>
                </span>
                <span class="cd-stat-copy">
                    <span class="cd-stat-label">Pending second-review</span>
                    <span class="cd-stat-value" style="display:block">{{ $pendingSecondReview }}</span>
                    <span class="cd-stat-link">Open queue</span>
                </span>
                <span class="cd-stat-arrow" aria-hidden="true">›</span>
            </a>
            <a class="cd-stat cd-stat-active cd-focus" href="{{ route('activities.monitor') }}">
                <span class="cd-stat-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><circle cx="9" cy="8" r="3.2" stroke="currentColor" stroke-width="1.8"/><path d="M3.5 20v-1.2a4.8 4.8 0 0 1 4.8-4.8h1.4a4.8 4.8 0 0 1 4.8 4.8V20M16 5.2a3.2 3.2 0 0 1 0 6.2m1.2 2.8a4.7 4.7 0 0 1 3.3 4.5V20" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </span>
                <span class="cd-stat-copy">
                    <span class="cd-stat-label">Active activities</span>
                    <span class="cd-stat-value" style="display:block">{{ $activeActivities }}</span>
                    <span class="cd-stat-link">Monitor activities</span>
                </span>
                <span class="cd-stat-arrow" aria-hidden="true">›</span>
            </a>
            <a class="cd-stat cd-stat-evaluations cd-focus" href="{{ route('evaluations.view') }}">
                <span class="cd-stat-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M7 3h7l4 4v14H7zM14 3v5h5M10 12h5m-5 4h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <span class="cd-stat-copy">
                    <span class="cd-stat-label">Evaluation reports</span>
                    <span class="cd-stat-value" style="display:block">{{ $evaluationSummaries }}</span>
                    <span class="cd-stat-link">View summaries</span>
                </span>
                <span class="cd-stat-arrow" aria-hidden="true">›</span>
            </a>
            <a class="cd-stat cd-stat-reports cd-focus" href="{{ route('reports.coordinator') }}">
                <span class="cd-stat-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M6 3h12v18l-6-3.5L6 21z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9 8h6m-6 4h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </span>
                <span class="cd-stat-copy">
                    <span class="cd-stat-label">Generate reports</span>
                    <span class="cd-stat-value" style="display:block">—</span>
                    <span class="cd-stat-link">Run reports</span>
                </span>
                <span class="cd-stat-arrow" aria-hidden="true">›</span>
            </a>
        </section>

        <div class="cd-content-grid">
            <section class="cd-panel">
                <div class="cd-panel-head">
                    <h3 class="cd-panel-title">
                        <span class="cd-panel-icon" aria-hidden="true">
                            <svg width="19" height="19" viewBox="0 0 24 24" fill="none"><path d="M4 5h16M4 12h16M4 19h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="18" cy="19" r="2" fill="currentColor"/></svg>
                        </span>
                        Review queue
                    </h3>
                    <a class="cd-panel-link cd-focus" href="{{ route('projects.coordinator_review') }}">View all</a>
                </div>
                <p class="cd-panel-description">Second-check proposals already cleared by the CELP Secretary.</p>

                @if($queue->isEmpty())
                    <div class="cd-empty">
                        <span class="cd-empty-icon" aria-hidden="true">✓</span>
                        <span>Nothing is waiting on your second review right now.</span>
                    </div>
                @else
                    <ul class="cd-queue">
                        @foreach($queue as $project)
                            @php $daysPending = $project->created_at?->diffInDays(now()) ?? 0; @endphp
                            <li class="cd-queue-item {{ $loop->first ? 'is-oldest' : '' }}">
                                <div class="cd-queue-main">
                                    <div class="cd-queue-titleline">
                                        <span class="cd-queue-title">{{ $project->title }}</span>
                                        @if($loop->first)
                                            <span class="cd-priority">Waiting longest</span>
                                        @endif
                                    </div>
                                    <div class="cd-queue-meta">
                                        <x-organization-avatar :organization="$project->creator" :size="28" />
                                        <span>{{ $project->creator->organization_name ?? $project->creator->name ?? 'Unknown organization' }}</span>
                                        <span>· Submitted {{ $project->created_at?->diffForHumans() ?? 'Date unavailable' }}</span>
                                        <span>· {{ $project->attachments_count }} {{ \Illuminate\Support\Str::plural('attachment', $project->attachments_count) }}</span>
                                    </div>
                                </div>
                                <div class="cd-review-actions">
                                    <span class="cd-waiting {{ $daysPending >= 5 ? 'is-late' : '' }}">{{ $daysPending }} {{ \Illuminate\Support\Str::plural('day', $daysPending) }} pending</span>
                                    <a class="cd-review-link cd-focus" href="{{ route('projects.show_review', $project) }}">Review</a>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="cd-panel">
                <div class="cd-panel-head">
                    <h3 class="cd-panel-title">
                        <span class="cd-panel-icon" style="background:#eaf9f4; color:#07815a" aria-hidden="true">
                            <svg width="19" height="19" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M12 7v5l3 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                        </span>
                        Needs your attention
                    </h3>
                </div>
                @if($oldestPending)
                    <div class="cd-oldest-card">
                        <div class="cd-oldest-label">Longest-waiting review</div>
                        <div class="cd-oldest-title">{{ $oldestPending->title }}</div>
                        <div class="cd-oldest-meta">
                            {{ $oldestPending->creator->organization_name ?? $oldestPending->creator->name ?? 'Unknown organization' }}
                            · Waiting {{ $oldestPending->created_at?->diffForHumans() ?? 'Date unavailable' }}
                        </div>
                        <a class="cd-review-link cd-focus" style="display:inline-flex; margin-top:10px" href="{{ route('projects.show_review', $oldestPending) }}">Review now</a>
                    </div>
                @else
                    <div class="cd-attention-box">
                        <span class="cd-attention-icon" aria-hidden="true">✓</span>
                        <div>
                            <strong>Your review queue is clear.</strong>
                            <p>No items require your attention at the moment.</p>
                        </div>
                    </div>
                @endif
            </section>
        </div>

        <div class="cd-chart-layout">
            <section class="cd-panel">
                <div class="cd-panel-head">
                    <h3 class="cd-panel-title">
                        <span class="cd-panel-icon" aria-hidden="true">
                            <svg width="19" height="19" viewBox="0 0 24 24" fill="none"><path d="M4 19V5m0 14h16M7 15l4-4 3 2 6-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        Coordinator review decisions
                    </h3>
                    <span style="color:#718096; font-size:0.72rem">Recorded decisions · last 6 months</span>
                </div>
                @php
                    $trendMax = max((int) ceil((collect($reviewTrend)->flatMap(fn ($month) => [$month['advanced'], $month['returned']])->max() ?? 0) / 2) * 2, 2);
                    $trendAxisTicks = [['y' => 36, 'label' => $trendMax]];
                    $trendAxisTicks[] = ['y' => 92, 'label' => (int) ($trendMax / 2)];
                    $trendAxisTicks[] = ['y' => 148, 'label' => 0];
                    $trendSeries = [
                        ['key' => 'advanced', 'label' => 'Endorsed to CELP Head', 'event' => 'endorsed', 'color' => '#078d70'],
                        ['key' => 'returned', 'label' => 'Returned to applicants', 'event' => 'returned', 'color' => '#d94e74'],
                    ];
                    $trendPoints = [];
                    foreach ($trendSeries as $series) {
                        $points = [];
                        foreach ($reviewTrend as $index => $month) {
                            $x = 50 + ($index * 94);
                            $y = 148 - (($month[$series['key']] / $trendMax) * 112);
                            $points[] = ['x' => $x, 'y' => $y, 'count' => $month[$series['key']], 'label' => $month['label']];
                        }
                        $trendPoints[$series['key']] = $points;
                    }
                @endphp
                <svg class="cd-trend-chart" viewBox="0 0 560 188" role="img" aria-label="CELP Coordinator decisions recorded each month over the last six months.">
                    <rect class="cd-trend-plot" x="22" y="16" width="534" height="148" rx="12"/>
                    @foreach($trendAxisTicks as $tick)
                        <line class="cd-trend-gridline" x1="50" y1="{{ $tick['y'] }}" x2="542" y2="{{ $tick['y'] }}"/>
                        <text class="cd-trend-y-axis" x="42" y="{{ $tick['y'] + 3 }}" text-anchor="end">{{ $tick['label'] }}</text>
                    @endforeach
                    @foreach($trendSeries as $series)
                        @php $points = $trendPoints[$series['key']]; @endphp
                        <path class="cd-trend-area" fill="{{ $series['color'] }}" fill-opacity="0.06" d="{{ $smoothTrendPath($points, 148) }}"/>
                        <path class="cd-trend-line" stroke="{{ $series['color'] }}" d="{{ $smoothTrendPath($points) }}"/>
                        @foreach($points as $point)
                            <circle class="cd-trend-point" cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="3" fill="{{ $series['color'] }}">
                                <title>{{ $point['label'] }}: {{ $point['count'] }} proposals {{ $series['event'] }}</title>
                            </circle>
                        @endforeach
                    @endforeach
                    @foreach($reviewTrend as $index => $month)
                        <text class="cd-trend-axis" x="{{ 50 + ($index * 94) }}" y="181" text-anchor="middle">{{ $month['label'] }}</text>
                    @endforeach
                </svg>
                <div class="cd-chart-legend" aria-label="Trend chart legend">
                    @foreach($trendSeries as $series)
                        <span class="cd-chart-legend-item"><span class="cd-status-dot" style="background:{{ $series['color'] }}"></span>{{ $series['label'] }}</span>
                    @endforeach
                </div>
            </section>

            <section class="cd-panel">
                <div class="cd-panel-head">
                    <h3 class="cd-panel-title">
                        <span class="cd-panel-icon" aria-hidden="true">
                            <svg width="19" height="19" viewBox="0 0 24 24" fill="none"><path d="M4 20V10m5 10V4m5 16v-7m5 7V7" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
                        </span>
                        Coordinator pipeline
                    </h3>
                </div>
                @php $donutOffset = 0; @endphp
                <div class="cd-status-overview">
                    <svg class="cd-donut" viewBox="0 0 160 160" role="img" aria-label="Current Coordinator workflow: {{ collect($statusMeta)->map(fn ($status) => $status['label'].' '.$status['count'])->implode(', ') }}">
                        <circle class="cd-donut-track" cx="80" cy="80" r="46"/>
                        @foreach($statusMeta as $status)
                            @if($status['count'] > 0)
                                @php
                                    $segmentLength = $status['count'] / max($statusSum, 1) * 289.03;
                                    $segmentOffset = $donutOffset;
                                    $donutOffset += $segmentLength;
                                @endphp
                                <circle class="cd-donut-segment" cx="80" cy="80" r="46" stroke="{{ $status['color'] }}"
                                        stroke-dasharray="{{ $segmentLength }} 289.03" stroke-dashoffset="{{ -$segmentOffset }}"/>
                            @endif
                        @endforeach
                        <text class="cd-donut-total" x="80" y="78">{{ $statusSum }}</text>
                        <text class="cd-donut-label" x="80" y="96">In workflow</text>
                    </svg>
                    <div class="cd-status-legend">
                        @foreach($statusMeta as $status)
                            <div class="cd-status-legend-row">
                                <span class="cd-status-legend-name"><span class="cd-status-dot" style="background:{{ $status['color'] }}"></span>{{ $status['label'] }}</span>
                                <span class="cd-status-count">{{ $status['count'] }}</span>
                                <span class="cd-status-percent">{{ $status['percent'] }}%</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        </div>

        <div class="cd-content-grid">
            <section class="cd-panel" style="grid-column:1/-1">
                <div class="cd-panel-head">
                    <h3 class="cd-panel-title">
                        <span class="cd-panel-icon" style="background:#f2edff; color:#7c3aed" aria-hidden="true">
                            <svg width="19" height="19" viewBox="0 0 24 24" fill="none"><path d="M4 5h16v14H4z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="m4 7 8 6 8-6" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
                        </span>
                        Latest announcements
                    </h3>
                </div>
                @if($announcements->isEmpty())
                    <div class="cd-empty">
                        <span class="cd-empty-icon" style="background:#f2edff; color:#7c3aed" aria-hidden="true">✦</span>
                        <span>No announcements from the CELP Secretary yet.</span>
                    </div>
                @else
                    <ul class="cd-announcements">
                        @foreach($announcements as $announcement)
                            <li class="cd-announcement">
                                <div class="cd-announcement-title">{{ $announcement->title }}</div>
                                <div class="cd-announcement-date">{{ $announcement->published_at?->diffForHumans() ?? $announcement->created_at?->diffForHumans() ?? 'Date unavailable' }}</div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>
@endsection
