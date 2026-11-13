@extends('layouts.admin')

@section('title', 'CELP Secretary Dashboard')
@section('subtitle', 'Proposal review, evaluations & records')

@section('content')
    @php
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
        .csd-dashboard { display:grid; gap:18px; }
        .csd-welcome {
            position:relative; display:flex; align-items:center; min-height:130px; overflow:hidden;
            padding:24px 28px; border:1px solid #d8eee5; border-radius:17px;
            background:linear-gradient(105deg,#effaf4 0%,#f5fbf7 58%,#e3f4eb 100%);
        }
        .csd-welcome-copy { position:relative; z-index:1; max-width:650px; }
        .csd-eyebrow { margin:0 0 7px; color:#08734e; font-size:0.75rem; font-weight:800; letter-spacing:0.1em; text-transform:uppercase; }
        .csd-welcome h3 { margin:0; color:#103b32; font-size:1.45rem; line-height:1.3; }
        .csd-welcome p { margin:7px 0 0; color:#54746a; font-size:0.91rem; line-height:1.55; }
        .csd-welcome-art { position:absolute; top:50%; right:35px; width:170px; height:120px; transform:translateY(-50%); color:#14815b; opacity:0.14; }
        .csd-stats { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; }
        .csd-stat { display:flex; align-items:center; gap:12px; min-width:0; padding:16px; border:1px solid #e5eaf0; border-radius:14px; background:#fff; }
        .csd-stat-icon { display:flex; align-items:center; justify-content:center; width:44px; height:44px; flex:0 0 44px; border:1px solid #e2e8f0; border-radius:13px; background:#f8fafc; color:#475569; }
        .csd-stat-icon svg { width:22px; height:22px; }
        .csd-stat-icon.is-pending { border-color:#f4dfb5; background:#fff7e7; color:#b45309; }
        .csd-stat-icon.is-returned { border-color:#fecaca; background:#fff1f2; color:#b91c1c; }
        .csd-stat-icon.is-templates { border-color:#d2eadc; background:#effaf3; color:#08734e; }
        .csd-stat-icon.is-records { border-color:#d2e2fb; background:#eff6ff; color:#2563eb; }
        .csd-stat-label { color:#64748b; font-size:0.8rem; font-weight:700; line-height:1.35; }
        .csd-stat-value { margin-top:3px; color:#102e2a; font-size:1.55rem; font-weight:800; line-height:1.1; }
        .csd-stat-link { display:inline-block; margin-top:5px; color:#08734e; font-size:0.78rem; font-weight:700; text-decoration:none; }
        .csd-stat-link:hover, .csd-panel-link:hover, .csd-template-link:hover { text-decoration:underline; }
        .csd-main-grid { display:grid; grid-template-columns:minmax(0,1.8fr) minmax(270px,0.9fr); gap:18px; align-items:stretch; }
        .csd-panel { min-width:0; padding:21px; border:1px solid #e8edf0; border-radius:15px; background:#fff; box-shadow:0 5px 16px rgba(11,42,42,0.04); }
        .csd-panel-head { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:8px; }
        .csd-panel-head h3 { margin:0; color:#123d33; font-size:1.02rem; }
        .csd-panel-link { color:#08734e; font-size:0.82rem; font-weight:700; text-decoration:none; white-space:nowrap; }
        .csd-panel-description { margin:0 0 15px; color:#71817c; font-size:0.85rem; line-height:1.5; }
        .csd-queue { display:flex; flex-direction:column; gap:9px; margin:0; padding:0; list-style:none; }
        .csd-queue-item { display:flex; align-items:center; justify-content:space-between; gap:14px; padding:13px 14px; border:1px solid #e6edf0; border-radius:12px; background:#fbfdfc; }
        .csd-queue-item.is-oldest { border-color:#f1dcae; background:#fffdf8; }
        .csd-queue-main { min-width:0; }
        .csd-queue-titleline { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .csd-queue-title { color:#172f2a; font-size:0.92rem; font-weight:750; line-height:1.4; overflow-wrap:anywhere; }
        .csd-priority { padding:3px 8px; border-radius:999px; background:#fff2d5; color:#925d00; font-size:0.68rem; font-weight:800; white-space:nowrap; }
        .csd-queue-meta { display:flex; align-items:center; gap:7px; flex-wrap:wrap; margin-top:6px; color:#71817c; font-size:0.79rem; line-height:1.4; }
        .csd-review-link { flex:0 0 auto; padding:8px 12px; border-radius:9px; background:#0b7656; color:#fff; font-size:0.81rem; font-weight:700; text-decoration:none; }
        .csd-review-link:hover { background:#086247; }
        .csd-chart-grid { display:grid; grid-template-columns:minmax(0,1.65fr) minmax(260px,0.95fr); gap:14px; align-items:stretch; }
        .csd-chart-panel { min-width:0; padding:19px; border:1px solid #e4ebf0; border-radius:16px; background:#fff; box-shadow:0 5px 17px rgba(24,50,71,0.045); }
        .csd-chart-head { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:8px; }
        .csd-chart-title { display:flex; align-items:center; gap:9px; margin:0; color:#203b50; font-size:0.98rem; letter-spacing:-.01em; }
        .csd-chart-icon { display:flex; align-items:center; justify-content:center; width:34px; height:34px; flex:0 0 34px; border-radius:11px; background:#edf6f3; color:#087f67; }
        .csd-chart-period { color:#718096; font-size:0.72rem; white-space:nowrap; }
        .csd-trend-chart { display:block; width:100%; height:auto; margin-top:8px; overflow:visible; }
        .csd-trend-plot { fill:#f3f6f8; }
        .csd-trend-gridline { stroke:#dce5eb; stroke-width:1; }
        .csd-trend-axis { fill:#718394; font-size:10px; }
        .csd-trend-y-axis { fill:#718394; font-size:9px; font-variant-numeric:tabular-nums; }
        .csd-trend-line { fill:none; stroke-width:3.2; stroke-linecap:round; stroke-linejoin:round; }
        .csd-trend-area { stroke:none; }
        .csd-trend-point { stroke:#f3f6f8; stroke-width:2.5; }
        .csd-chart-legend { display:flex; flex-wrap:wrap; gap:14px; margin-top:5px; }
        .csd-chart-legend-item { display:flex; align-items:center; gap:6px; color:#627384; font-size:0.72rem; font-weight:700; }
        .csd-status-overview { display:grid; grid-template-columns:145px minmax(0,1fr); align-items:center; gap:12px; margin-top:12px; padding:10px; border:1px solid #edf2f2; border-radius:14px; background:linear-gradient(140deg,#fff,#fbfdfc); }
        .csd-donut { display:block; width:145px; height:145px; }
        .csd-donut-track { fill:none; stroke:#e9eff2; stroke-width:16; }
        .csd-donut-segment { fill:none; stroke-width:16; transform:rotate(-90deg); transform-origin:80px 80px; }
        .csd-donut-total { fill:#183247; font-size:25px; font-weight:800; text-anchor:middle; }
        .csd-donut-label { fill:#718394; font-size:10px; font-weight:650; text-anchor:middle; }
        .csd-status-legend { display:grid; gap:7px; }
        .csd-status-legend-row { display:grid; grid-template-columns:minmax(0,1fr) auto auto; align-items:center; gap:8px; padding:8px; border:1px solid #edf1f3; border-radius:9px; background:#fff; }
        .csd-status-legend-name { display:flex; align-items:center; gap:7px; color:#42596a; font-size:0.72rem; font-weight:700; }
        .csd-status-dot { width:9px; height:9px; flex:0 0 9px; border-radius:50%; }
        .csd-status-count { color:#183247; font-size:0.84rem; font-weight:800; font-variant-numeric:tabular-nums; }
        .csd-status-percent { min-width:32px; color:#7b8da4; font-size:0.68rem; text-align:right; }
        .csd-aside { display:flex; flex-direction:column; gap:14px; }
        .csd-template-list { display:flex; flex-direction:column; gap:10px; margin:0; padding:0; list-style:none; }
        .csd-template-link { color:#08734e; font-size:0.83rem; font-weight:700; line-height:1.4; text-decoration:none; overflow-wrap:anywhere; }
        .csd-template-date { margin-top:3px; color:#8a9893; font-size:0.75rem; }
        .csd-checklist { display:grid; gap:8px; margin:12px 0 0; padding:0; list-style:none; }
        .csd-checklist li { display:flex; align-items:flex-start; gap:8px; color:#52645e; font-size:0.81rem; line-height:1.45; }
        .csd-check { color:#0b8a5f; font-weight:800; }
        .csd-quick-actions { display:grid; gap:8px; margin-top:14px; padding-top:13px; border-top:1px solid #f0f3f2; }
        .csd-quick-link { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:10px 12px; border:1px solid #e5eee9; border-radius:10px; background:#fbfdfc; color:#176047; font-size:0.82rem; font-weight:700; text-decoration:none; transition:background-color 150ms ease,border-color 150ms ease; }
        .csd-quick-link:hover { border-color:#cde5d7; background:#f1faf4; }
        .csd-attention { border-color:#f1e3bd; }
        .csd-attention-head { display:flex; align-items:center; justify-content:space-between; gap:14px; margin-bottom:15px; }
        .csd-attention-title { margin:3px 0 0; color:#123d33; font-size:1.22rem; line-height:1.35; }
        .csd-attention-icon { display:flex; align-items:center; justify-content:center; width:46px; height:46px; flex:0 0 46px; border-radius:14px; background:#fff4d8; color:#a16207; }
        .csd-oldest { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:17px; border:1px solid #eadfca; border-radius:12px; background:#fffdf8; }
        .csd-oldest-main { min-width:0; }
        .csd-oldest-title { color:#172f2a; font-size:1.05rem; font-weight:800; line-height:1.4; overflow-wrap:anywhere; }
        .csd-oldest-meta { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-top:7px; color:#71817c; font-size:0.81rem; line-height:1.45; }
        .csd-empty { padding:17px; border:1px dashed #dbe7e1; border-radius:11px; background:#f8fbf9; color:#64756e; font-size:0.87rem; line-height:1.5; }
        .csd-focus:focus-visible { outline:3px solid rgba(8,115,78,0.32); outline-offset:3px; }
        @media(max-width:1050px) {
            .csd-stats { grid-template-columns:repeat(2,minmax(0,1fr)); }
            .csd-main-grid { grid-template-columns:minmax(0,1fr); }
            .csd-chart-grid { grid-template-columns:minmax(0,1fr); }
            .csd-aside { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); align-items:start; }
        }
        @media(max-width:640px) {
            .csd-welcome { min-height:120px; padding:21px; }
            .csd-welcome-art { right:-25px; top:6px; transform:none; }
            .csd-stats, .csd-aside { grid-template-columns:minmax(0,1fr); }
            .csd-panel { padding:16px; }
            .csd-chart-panel { padding:15px; }
            .csd-status-overview { grid-template-columns:115px minmax(0,1fr); gap:8px; padding:8px; }
            .csd-donut { width:115px; height:115px; }
            .csd-queue-item, .csd-oldest { align-items:flex-start; flex-direction:column; }
            .csd-review-link { align-self:flex-start; }
        }
        @media(prefers-reduced-motion:reduce) {
            .csd-quick-link { transition:none; }
        }
    </style>

    <div class="csd-dashboard">
        @if(session('status'))
            <div class="card" style="border-left:4px solid #16a34a; background:#ecfdf5; color:#134e4a; padding:16px 20px">
                {{ session('status') }}
            </div>
        @endif

        <section class="csd-welcome" aria-label="Welcome">
            <div class="csd-welcome-copy">
                <p class="csd-eyebrow">CELP Secretary workspace</p>
                <h3>Welcome back, {{ $user->first_name ?: $user->name }}.</h3>
                <p>Review incoming proposals, check baseline requirements, and keep evaluation resources organized.</p>
            </div>
            <svg class="csd-welcome-art" viewBox="0 0 180 130" fill="none" aria-hidden="true">
                <path d="M42 28h76v83H42z" stroke="currentColor" stroke-width="6" stroke-linejoin="round"/>
                <path d="M59 51h42M59 68h42M59 85h25" stroke="currentColor" stroke-width="6" stroke-linecap="round"/>
                <path d="m113 87 13 13 27-31" stroke="currentColor" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </section>

        <section class="csd-stats" aria-label="Secretary dashboard summary">
            <div class="csd-stat">
                <span class="csd-stat-icon is-pending" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M4 4h7v7H4zM13 13h7v7h-7zM13 4h7v7h-7zM4 13h7v7H4z" fill="currentColor"/></svg>
                </span>
                <div>
                    <div class="csd-stat-label">Pending proposals</div>
                    <div class="csd-stat-value">{{ $pendingProposals }}</div>
                    <a class="csd-stat-link csd-focus" href="{{ route('projects.secretary_review') }}">Open review queue</a>
                </div>
            </div>
            <div class="csd-stat">
                <span class="csd-stat-icon is-returned" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12l7 7 7-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <div>
                    <div class="csd-stat-label">Returned to applicants</div>
                    <div class="csd-stat-value">{{ $returnedCount }}</div>
                </div>
            </div>
            <div class="csd-stat">
                <span class="csd-stat-icon is-templates" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M5 6h14M5 12h14M5 18h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                </span>
                <div>
                    <div class="csd-stat-label">Evaluation templates</div>
                    <div class="csd-stat-value">{{ $templatesCount }}</div>
                    <a class="csd-stat-link csd-focus" href="{{ route('templates.manage') }}">Manage templates</a>
                </div>
            </div>
            <div class="csd-stat">
                <span class="csd-stat-icon is-records" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M4 4h16v4H4zM4 10h16v10H4z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M8 14h8m-8 3h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </span>
                <div>
                    <div class="csd-stat-label">Records &amp; archives</div>
                    <div class="csd-stat-value">{{ $recordsCount }}</div>
                    <a class="csd-stat-link csd-focus" href="{{ route('records.index') }}">View records</a>
                </div>
            </div>
        </section>

        <div class="csd-chart-grid">
            <section class="csd-chart-panel">
                <div class="csd-chart-head">
                    <h3 class="csd-chart-title">
                        <span class="csd-chart-icon" aria-hidden="true">
                            <svg width="19" height="19" viewBox="0 0 24 24" fill="none"><path d="M4 19V5m0 14h16M7 15l4-4 3 2 6-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        Secretary review decisions
                    </h3>
                    <span class="csd-chart-period">Recorded decisions · last 6 months</span>
                </div>
                @php
                    $trendMax = max((int) ceil((collect($reviewTrend)->flatMap(fn ($month) => [$month['advanced'], $month['returned']])->max() ?? 0) / 2) * 2, 2);
                    $trendAxisTicks = [['y' => 36, 'label' => $trendMax]];
                    $trendAxisTicks[] = ['y' => 92, 'label' => (int) ($trendMax / 2)];
                    $trendAxisTicks[] = ['y' => 148, 'label' => 0];
                    $trendSeries = [
                        ['key' => 'advanced', 'label' => 'Endorsed to Coordinator', 'event' => 'endorsed', 'color' => '#078d70'],
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
                <svg class="csd-trend-chart" viewBox="0 0 560 188" role="img" aria-label="CELP Secretary decisions recorded each month over the last six months.">
                    <rect class="csd-trend-plot" x="22" y="16" width="534" height="148" rx="12"/>
                    @foreach($trendAxisTicks as $tick)
                        <line class="csd-trend-gridline" x1="50" y1="{{ $tick['y'] }}" x2="542" y2="{{ $tick['y'] }}"/>
                        <text class="csd-trend-y-axis" x="42" y="{{ $tick['y'] + 3 }}" text-anchor="end">{{ $tick['label'] }}</text>
                    @endforeach
                    @foreach($trendSeries as $series)
                        @php $points = $trendPoints[$series['key']]; @endphp
                        <path class="csd-trend-area" fill="{{ $series['color'] }}" fill-opacity="0.06" d="{{ $smoothTrendPath($points, 148) }}"/>
                        <path class="csd-trend-line" stroke="{{ $series['color'] }}" d="{{ $smoothTrendPath($points) }}"/>
                        @foreach($points as $point)
                            <circle class="csd-trend-point" cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="3" fill="{{ $series['color'] }}">
                                <title>{{ $point['label'] }}: {{ $point['count'] }} proposals {{ $series['event'] }}</title>
                            </circle>
                        @endforeach
                    @endforeach
                    @foreach($reviewTrend as $index => $month)
                        <text class="csd-trend-axis" x="{{ 50 + ($index * 94) }}" y="181" text-anchor="middle">{{ $month['label'] }}</text>
                    @endforeach
                </svg>
                <div class="csd-chart-legend" aria-label="Trend chart legend">
                    @foreach($trendSeries as $series)
                        <span class="csd-chart-legend-item"><span class="csd-status-dot" style="background:{{ $series['color'] }}"></span>{{ $series['label'] }}</span>
                    @endforeach
                </div>
            </section>

            <section class="csd-chart-panel">
                <div class="csd-chart-head">
                    <h3 class="csd-chart-title">
                        <span class="csd-chart-icon" aria-hidden="true">
                            <svg width="19" height="19" viewBox="0 0 24 24" fill="none"><path d="M4 20V10m5 10V4m5 16v-7m5 7V7" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
                        </span>
                        Secretary pipeline
                    </h3>
                </div>
                @php $donutOffset = 0; @endphp
                <div class="csd-status-overview">
                    <svg class="csd-donut" viewBox="0 0 160 160" role="img" aria-label="Current Secretary workflow: {{ collect($statusMeta)->map(fn ($status) => $status['label'].' '.$status['count'])->implode(', ') }}">
                        <circle class="csd-donut-track" cx="80" cy="80" r="46"/>
                        @foreach($statusMeta as $status)
                            @if($status['count'] > 0)
                                @php
                                    $segmentLength = $status['count'] / max($statusSum, 1) * 289.03;
                                    $segmentOffset = $donutOffset;
                                    $donutOffset += $segmentLength;
                                @endphp
                                <circle class="csd-donut-segment" cx="80" cy="80" r="46" stroke="{{ $status['color'] }}"
                                        stroke-dasharray="{{ $segmentLength }} 289.03" stroke-dashoffset="{{ -$segmentOffset }}"/>
                            @endif
                        @endforeach
                        <text class="csd-donut-total" x="80" y="78">{{ $statusSum }}</text>
                        <text class="csd-donut-label" x="80" y="96">In workflow</text>
                    </svg>
                    <div class="csd-status-legend">
                        @foreach($statusMeta as $status)
                            <div class="csd-status-legend-row">
                                <span class="csd-status-legend-name"><span class="csd-status-dot" style="background:{{ $status['color'] }}"></span>{{ $status['label'] }}</span>
                                <span class="csd-status-count">{{ $status['count'] }}</span>
                                <span class="csd-status-percent">{{ $status['percent'] }}%</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        </div>

        <div class="csd-main-grid">
            <section class="csd-panel">
                <div class="csd-panel-head">
                    <h3>Proposal review queue</h3>
                    <a class="csd-panel-link csd-focus" href="{{ route('projects.secretary_review') }}">Open full queue</a>
                </div>
                <p class="csd-panel-description">Check baseline requirements and return deficient proposals to applicants.</p>

                @if($queue->isEmpty())
                    <div class="csd-empty">No proposals are waiting on your initial review right now.</div>
                @else
                    <ul class="csd-queue">
                        @foreach($queue as $project)
                            <li class="csd-queue-item {{ $loop->first ? 'is-oldest' : '' }}">
                                <div class="csd-queue-main">
                                    <div class="csd-queue-titleline">
                                        <span class="csd-queue-title">{{ $project->title }}</span>
                                        @if($loop->first)
                                            <span class="csd-priority">Waiting longest</span>
                                        @endif
                                    </div>
                                    <div class="csd-queue-meta">
                                        <x-organization-avatar :organization="$project->creator" :size="28" />
                                        <span>{{ $project->creator->organization_name ?? $project->creator->name ?? 'Unknown organization' }}</span>
                                        <span>· Submitted {{ $project->created_at?->diffForHumans() ?? 'Date unavailable' }}</span>
                                        <span>· {{ $project->attachments_count }} {{ \Illuminate\Support\Str::plural('attachment', $project->attachments_count) }}</span>
                                    </div>
                                </div>
                                <a class="csd-review-link csd-focus" href="{{ route('projects.show_review', $project) }}">Review</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <aside class="csd-aside">
                <section class="csd-panel">
                    <div class="csd-panel-head">
                        <h3>Recent templates</h3>
                        <a class="csd-panel-link csd-focus" href="{{ route('templates.manage') }}">Manage</a>
                    </div>
                    @if($recentTemplates->isEmpty())
                        <div class="csd-empty">No templates are currently available.</div>
                    @else
                        <ul class="csd-template-list">
                            @foreach($recentTemplates as $template)
                                <li>
                                    <a class="csd-template-link csd-focus" href="{{ route('templates.download', $template->id) }}">{{ $template->original_name }}</a>
                                    <div class="csd-template-date">{{ $template->created_at?->format('M j, Y') ?? 'Date unavailable' }}</div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                <section class="csd-panel">
                    <div class="csd-panel-head">
                        <h3>Review checklist</h3>
                    </div>
                    <ul class="csd-checklist">
                        <li><span class="csd-check" aria-hidden="true">✓</span><span>Proposal form is complete and signed</span></li>
                        <li><span class="csd-check" aria-hidden="true">✓</span><span>Required attachments are present</span></li>
                        <li><span class="csd-check" aria-hidden="true">✓</span><span>Organization is in good standing</span></li>
                        <li><span class="csd-check" aria-hidden="true">✓</span><span>Activity dates do not conflict with holds</span></li>
                    </ul>
                    <nav class="csd-quick-actions" aria-label="Quick actions">
                        <a class="csd-quick-link csd-focus" href="{{ route('celp.secretary.announcements') }}">Post an announcement</a>
                        <a class="csd-quick-link csd-focus" href="{{ route('evaluation.encode') }}">Encode beneficiary evaluation</a>
                        <a class="csd-quick-link csd-focus" href="{{ route('attendance.index') }}">View attendance records</a>
                        <a class="csd-quick-link csd-focus" href="{{ route('reports.secretary') }}">Generate reports</a>
                    </nav>
                </section>
            </aside>
        </div>

        <section class="csd-panel csd-attention" aria-labelledby="csd-attention-title">
            <div class="csd-attention-head">
                <div>
                    <p class="csd-eyebrow">Needs your attention</p>
                    <h3 class="csd-attention-title" id="csd-attention-title">Longest-waiting proposal</h3>
                </div>
                <span class="csd-attention-icon" aria-hidden="true">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="M12 7v5l3 2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
            </div>

            @if($oldestPending)
                <div class="csd-oldest">
                    <div class="csd-oldest-main">
                        <div class="csd-oldest-title">{{ $oldestPending->title }}</div>
                        <div class="csd-oldest-meta">
                            <x-organization-avatar :organization="$oldestPending->creator" :size="28" />
                            <span>{{ $oldestPending->creator->organization_name ?? $oldestPending->creator->name ?? 'Unknown organization' }}</span>
                            <span>· Waiting {{ $oldestPending->created_at?->diffForHumans() ?? 'Date unavailable' }}</span>
                        </div>
                    </div>
                    <a class="csd-review-link csd-focus" href="{{ route('projects.show_review', $oldestPending) }}">Review now</a>
                </div>
            @else
                <div class="csd-empty">Nothing is waiting on your initial review. New submissions will appear here.</div>
            @endif
        </section>
    </div>
@endsection
