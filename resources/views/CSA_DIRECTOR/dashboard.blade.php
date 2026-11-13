@extends('layouts.admin')

@section('title','CSA Director Dashboard')
@section('subtitle','Administrative compliance & monitoring')

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
        .csa-dashboard { display:grid; gap:18px; }
        .csa-stats { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; }
        .csa-stat { display:flex; align-items:center; gap:13px; min-width:0; padding:17px; border:1px solid #e5eaf0; border-radius:14px; background:#fff; }
        .csa-stat-icon { display:flex; align-items:center; justify-content:center; width:46px; height:46px; flex:0 0 46px; border:1px solid #e2e8f0; border-radius:14px; background:linear-gradient(145deg,#f8fafc,#eef2f6); color:#475569; }
        .csa-stat-icon.is-pending { border-color:#f4dfb5; background:linear-gradient(145deg,#fffaf0,#fff2d9); color:#b45309; }
        .csa-stat-icon.is-approved { border-color:#d2eadc; background:linear-gradient(145deg,#f2fbf5,#e5f5eb); color:#08734e; }
        .csa-stat-icon.is-forms { border-color:#dce2fa; background:linear-gradient(145deg,#f5f6ff,#e9edff); color:#4338ca; }
        .csa-stat-icon svg { width:23px; height:23px; }
        .csa-stat-label { color:#64748b; font-size:0.82rem; font-weight:700; line-height:1.35; }
        .csa-stat-value { margin-top:4px; color:#102e2a; font-size:1.55rem; font-weight:800; line-height:1.1; }
        .csa-stat-link { display:inline-block; margin-top:5px; color:#08734e; font-size:0.8rem; font-weight:700; text-decoration:none; }
        .csa-stat-link:hover, .csa-panel-link:hover { text-decoration:underline; }
        .csa-chart-layout { display:grid; grid-template-columns:minmax(0,1.55fr) minmax(250px,0.9fr); gap:18px; align-items:stretch; }
        .csa-layout { display:grid; grid-template-columns:minmax(0,1.8fr) minmax(280px,0.9fr); gap:18px; align-items:start; }
        .csa-panel { min-width:0; padding:21px; border:1px solid #e8edf0; border-radius:15px; background:#fff; box-shadow:0 5px 16px rgba(11,42,42,0.04); }
        .csa-panel-head { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:8px; }
        .csa-panel-head h3 { margin:0; color:#123d33; font-size:1.02rem; }
        .csa-panel-link { color:#08734e; font-size:0.83rem; font-weight:700; text-decoration:none; white-space:nowrap; }
        .csa-chart-period { color:#71817c; font-size:0.72rem; white-space:nowrap; }
        .csa-chart-panel { min-width:0; padding:20px; border:1px solid #e8edf0; border-radius:15px; background:#fff; box-shadow:0 5px 16px rgba(11,42,42,0.04); }
        .csa-chart-title { display:flex; align-items:center; gap:9px; margin:0; color:#123d33; font-size:0.98rem; }
        .csa-chart-icon { display:flex; align-items:center; justify-content:center; width:34px; height:34px; flex:0 0 34px; border-radius:11px; background:#edf6f3; color:#087f67; }
        .csa-trend-chart { display:block; width:100%; height:auto; margin-top:8px; overflow:visible; }
        .csa-trend-plot { fill:#f3f6f8; }
        .csa-trend-gridline { stroke:#dce5eb; stroke-width:1; }
        .csa-trend-axis, .csa-trend-y-axis { fill:#718394; font-size:10px; }
        .csa-trend-y-axis { font-size:9px; font-variant-numeric:tabular-nums; }
        .csa-trend-line { fill:none; stroke-width:3.2; stroke-linecap:round; stroke-linejoin:round; }
        .csa-trend-area { stroke:none; }
        .csa-trend-point { stroke:#f3f6f8; stroke-width:2.5; }
        .csa-chart-legend { display:flex; flex-wrap:wrap; gap:14px; margin-top:5px; }
        .csa-chart-legend-item { display:flex; align-items:center; gap:6px; color:#627384; font-size:0.72rem; font-weight:700; }
        .csa-status-overview { display:grid; grid-template-columns:145px minmax(0,1fr); align-items:center; gap:14px; margin-top:12px; padding:12px; border:1px solid #edf2f2; border-radius:14px; background:linear-gradient(140deg,#fff,#fbfdfc); }
        .csa-donut { display:block; width:145px; height:145px; }
        .csa-donut-track { fill:none; stroke:#e9eff2; stroke-width:16; }
        .csa-donut-segment { fill:none; stroke-width:16; transform:rotate(-90deg); transform-origin:80px 80px; }
        .csa-donut-total { fill:#183b32; font-size:25px; font-weight:800; text-anchor:middle; }
        .csa-donut-label { fill:#71817c; font-size:10px; font-weight:650; text-anchor:middle; }
        .csa-status-legend { display:grid; gap:8px; }
        .csa-status-legend-row { display:grid; grid-template-columns:minmax(0,1fr) auto auto; align-items:center; gap:8px; padding:8px; border:1px solid #edf1f3; border-radius:10px; background:#fff; }
        .csa-status-legend-name { display:flex; align-items:center; gap:7px; color:#52645e; font-size:0.72rem; font-weight:700; }
        .csa-status-dot { width:9px; height:9px; flex:0 0 9px; border-radius:50%; }
        .csa-status-count { color:#183b32; font-size:0.84rem; font-weight:800; font-variant-numeric:tabular-nums; }
        .csa-status-percent { min-width:32px; color:#7b8d84; font-size:0.68rem; text-align:right; }
        .csa-panel-description { margin:0 0 15px; color:#71817c; font-size:0.86rem; line-height:1.5; }
        .csa-queue { display:flex; flex-direction:column; gap:9px; margin:0; padding:0; list-style:none; }
        .csa-queue-item { display:flex; align-items:center; justify-content:space-between; gap:14px; padding:13px 14px; border:1px solid #e6edf0; border-radius:12px; background:#fbfdfc; }
        .csa-queue-item.is-oldest { border-color:#f1dcae; background:#fffdf8; }
        .csa-queue-main { min-width:0; }
        .csa-queue-titleline { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .csa-queue-title { color:#172f2a; font-size:0.93rem; font-weight:750; line-height:1.4; overflow-wrap:anywhere; }
        .csa-priority { padding:3px 8px; border-radius:999px; background:#fff2d5; color:#925d00; font-size:0.68rem; font-weight:800; letter-spacing:0.02em; white-space:nowrap; }
        .csa-queue-meta { display:flex; align-items:center; gap:7px; flex-wrap:wrap; margin-top:6px; color:#71817c; font-size:0.8rem; line-height:1.4; }
        .csa-review-link { flex:0 0 auto; padding:8px 12px; border-radius:9px; background:#0b7656; color:#fff; font-size:0.82rem; font-weight:700; text-decoration:none; }
        .csa-review-link:hover { background:#086247; }
        .csa-aside { display:flex; flex-direction:column; gap:14px; }
        .csa-org-list { display:flex; flex-direction:column; gap:10px; margin:0; padding:0; list-style:none; }
        .csa-org-link { display:flex; align-items:center; gap:9px; min-width:0; color:inherit; text-decoration:none; }
        .csa-org-link:hover .csa-org-name { color:#08734e; }
        .csa-org-name { min-width:0; overflow:hidden; color:#243b36; font-size:0.84rem; font-weight:700; text-overflow:ellipsis; white-space:nowrap; }
        .csa-org-meta { margin:3px 0 0 43px; color:#8a9893; font-size:0.75rem; }
        .csa-panel-actions { display:flex; flex-wrap:wrap; gap:8px 14px; margin-top:13px; padding-top:12px; border-top:1px solid #f0f3f2; }
        .csa-checklist { display:grid; gap:8px; margin:12px 0 0; padding:0; list-style:none; }
        .csa-checklist li { display:flex; align-items:flex-start; gap:8px; color:#52645e; font-size:0.81rem; line-height:1.45; }
        .csa-check { color:#0b8a5f; font-weight:800; }
        .csa-empty { padding:18px; border:1px dashed #dbe7e1; border-radius:11px; background:#f8fbf9; color:#64756e; font-size:0.88rem; }
        @media(max-width:1000px) {
            .csa-stats { grid-template-columns:repeat(2,minmax(0,1fr)); }
            .csa-chart-layout { grid-template-columns:minmax(0,1fr); }
            .csa-layout { grid-template-columns:minmax(0,1fr); }
            .csa-aside { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); align-items:start; }
        }
        @media(max-width:600px) {
            .csa-stats, .csa-aside { grid-template-columns:minmax(0,1fr); }
            .csa-panel, .csa-chart-panel { padding:16px; }
            .csa-status-overview { grid-template-columns:110px minmax(0,1fr); gap:8px; }
            .csa-donut { width:110px; height:110px; }
            .csa-status-legend-row { grid-template-columns:minmax(0,1fr) auto; }
            .csa-status-percent { display:none; }
            .csa-queue-item { align-items:flex-start; }
            .csa-review-link { align-self:center; }
        }
    </style>

    <div class="csa-dashboard">
    @if(session('status'))
        <div class="card" style="border-left:4px solid #16a34a; background:#ecfdf5; color:#134e4a; padding:18px 22px; margin-bottom:20px">
            {{ session('status') }}
        </div>
    @endif

    <div class="csa-stats">
        <div class="csa-stat">
            <div class="csa-stat-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M16 20v-1.5a3.5 3.5 0 0 0-3.5-3.5h-5A3.5 3.5 0 0 0 4 18.5V20" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="10" cy="7.5" r="3.5" stroke="currentColor" stroke-width="1.8"/>
                    <path d="M16 4.5a3.5 3.5 0 0 1 0 6.8M17 15h.5a3.5 3.5 0 0 1 3.5 3.5V20" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div>
                <div class="csa-stat-label">Managed org accounts</div>
                <div class="csa-stat-value">{{ $orgCount }}</div>
                <a class="csa-stat-link" href="{{ route('users.index') }}">Manage accounts</a>
            </div>
        </div>
        <div class="csa-stat">
            <div class="csa-stat-icon is-pending" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M13 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2v-7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M8 8h5M8 12h3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    <circle cx="17.5" cy="7.5" r="4.5" fill="currentColor"/>
                    <path d="M17.5 5v2.7l1.7 1" stroke="#fff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div>
                <div class="csa-stat-label">Pending CSA compliance</div>
                <div class="csa-stat-value">{{ $pendingCompliance }}</div>
                <a class="csa-stat-link" href="{{ route('projects.csa_review') }}">Open review queue</a>
            </div>
        </div>
        <div class="csa-stat">
            <div class="csa-stat-icon is-approved" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/>
                    <path d="m8 12.2 2.6 2.6 5.6-5.6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div>
                <div class="csa-stat-label">Approved activities</div>
                <div class="csa-stat-value">{{ $approvedActivities }}</div>
                <a class="csa-stat-link" href="{{ route('csa.reports.index') }}">View reports</a>
            </div>
        </div>
        <div class="csa-stat">
            <div class="csa-stat-icon is-forms" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M7 3.75h7l4.25 4.3V20a1.25 1.25 0 0 1-1.25 1.25H7A1.25 1.25 0 0 1 5.75 20V5A1.25 1.25 0 0 1 7 3.75Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                    <path d="M14 4v4h4M9 12h6M9 15.5h6M9 19h3.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div>
                <div class="csa-stat-label">Compliance forms</div>
                <div class="csa-stat-value">{{ $templatesCount }}</div>
                <a class="csa-stat-link" href="{{ route('templates.manage') }}">Manage forms</a>
            </div>
        </div>
    </div>

    <div class="csa-chart-layout">
        <section class="csa-chart-panel">
            <div class="csa-panel-head">
                <h3 class="csa-chart-title">
                    <span class="csa-chart-icon" aria-hidden="true">
                        <svg width="19" height="19" viewBox="0 0 24 24" fill="none"><path d="M4 19V5m0 14h16M7 15l4-4 3 2 6-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                    CSA compliance decisions
                </h3>
                <span class="csa-chart-period">Recorded decisions · last 6 months</span>
            </div>
            @php
                $trendMax = max((int) ceil((collect($reviewTrend)->flatMap(fn ($month) => [$month['cleared'], $month['returned']])->max() ?? 0) / 2) * 2, 2);
                $trendAxisTicks = [
                    ['y' => 36, 'label' => $trendMax],
                    ['y' => 92, 'label' => (int) ($trendMax / 2)],
                    ['y' => 148, 'label' => 0],
                ];
                $trendSeries = [
                    ['key' => 'cleared', 'label' => 'Cleared by CSA', 'event' => 'cleared', 'color' => '#078d70'],
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
            <svg class="csa-trend-chart" viewBox="0 0 560 188" role="img" aria-label="CSA Director decisions recorded each month over the last six months.">
                <rect class="csa-trend-plot" x="22" y="16" width="534" height="148" rx="12"/>
                @foreach($trendAxisTicks as $tick)
                    <line class="csa-trend-gridline" x1="50" y1="{{ $tick['y'] }}" x2="542" y2="{{ $tick['y'] }}"/>
                    <text class="csa-trend-y-axis" x="42" y="{{ $tick['y'] + 3 }}" text-anchor="end">{{ $tick['label'] }}</text>
                @endforeach
                @foreach($trendSeries as $series)
                    @php $points = $trendPoints[$series['key']]; @endphp
                    <path class="csa-trend-area" fill="{{ $series['color'] }}" fill-opacity="0.06" d="{{ $smoothTrendPath($points, 148) }}"/>
                    <path class="csa-trend-line" stroke="{{ $series['color'] }}" d="{{ $smoothTrendPath($points) }}"/>
                    @foreach($points as $point)
                        <circle class="csa-trend-point" cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="3" fill="{{ $series['color'] }}">
                            <title>{{ $point['label'] }}: {{ $point['count'] }} proposals {{ $series['event'] }}</title>
                        </circle>
                    @endforeach
                @endforeach
                @foreach($reviewTrend as $index => $month)
                    <text class="csa-trend-axis" x="{{ 50 + ($index * 94) }}" y="181" text-anchor="middle">{{ $month['label'] }}</text>
                @endforeach
            </svg>
            <div class="csa-chart-legend" aria-label="Trend chart legend">
                @foreach($trendSeries as $series)
                    <span class="csa-chart-legend-item"><span class="csa-status-dot" style="background:{{ $series['color'] }}"></span>{{ $series['label'] }}</span>
                @endforeach
            </div>
        </section>

        <section class="csa-chart-panel">
            <div class="csa-panel-head">
                <h3 class="csa-chart-title">
                    <span class="csa-chart-icon" aria-hidden="true">
                        <svg width="19" height="19" viewBox="0 0 24 24" fill="none"><path d="M4 20V10m5 10V4m5 16v-7m5 7V7" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
                    </span>
                    Compliance pipeline
                </h3>
            </div>
            @php $donutOffset = 0; @endphp
            <div class="csa-status-overview">
                <svg class="csa-donut" viewBox="0 0 160 160" role="img" aria-label="Current CSA workflow: {{ collect($statusMeta)->map(fn ($status) => $status['label'].' '.$status['count'])->implode(', ') }}">
                    <circle class="csa-donut-track" cx="80" cy="80" r="46"/>
                    @foreach($statusMeta as $status)
                        @if($status['count'] > 0)
                            @php
                                $segmentLength = $status['count'] / max($statusSum, 1) * 289.03;
                                $segmentOffset = $donutOffset;
                                $donutOffset += $segmentLength;
                            @endphp
                            <circle class="csa-donut-segment" cx="80" cy="80" r="46" stroke="{{ $status['color'] }}"
                                    stroke-dasharray="{{ $segmentLength }} 289.03" stroke-dashoffset="{{ -$segmentOffset }}"/>
                        @endif
                    @endforeach
                    <text class="csa-donut-total" x="80" y="78">{{ $statusSum }}</text>
                    <text class="csa-donut-label" x="80" y="96">In workflow</text>
                </svg>
                <div class="csa-status-legend">
                    @foreach($statusMeta as $status)
                        <div class="csa-status-legend-row">
                            <span class="csa-status-legend-name"><span class="csa-status-dot" style="background:{{ $status['color'] }}"></span>{{ $status['label'] }}</span>
                            <span class="csa-status-count">{{ $status['count'] }}</span>
                            <span class="csa-status-percent">{{ $status['percent'] }}%</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    </div>

    <div class="csa-layout">
        <section class="csa-panel">
            <div class="csa-panel-head">
                <h3>Compliance Review Queue</h3>
                <a href="{{ route('projects.csa_review') }}" class="csa-panel-link">View full queue</a>
            </div>
            <p class="csa-panel-description">Proposals cleared by the CELP Head, waiting on final compliance clearance. The oldest submission is highlighted.</p>

            @if($queue->isEmpty())
                <div class="csa-empty">
                    Nothing is waiting on compliance clearance right now.
                </div>
            @else
                <ul class="csa-queue">
                    @foreach($queue as $project)
                        <li class="csa-queue-item {{ $loop->first ? 'is-oldest' : '' }}">
                            <div class="csa-queue-main">
                                <div class="csa-queue-titleline">
                                    <span class="csa-queue-title">{{ $project->title }}</span>
                                    @if($loop->first)
                                        <span class="csa-priority">Waiting longest</span>
                                    @endif
                                </div>
                                <div class="csa-queue-meta">
                                    <x-organization-avatar :organization="$project->creator" :size="28" />
                                    <span>{{ $project->creator->organization_name ?? $project->creator->name ?? 'Unknown organization' }}</span>
                                    <span>· Submitted {{ $project->created_at?->diffForHumans() ?? 'Date unavailable' }}</span>
                                    <span>· {{ $project->attachments_count }} {{ \Illuminate\Support\Str::plural('attachment', $project->attachments_count) }}</span>
                                </div>
                            </div>
                            <a href="{{ route('projects.show_review', $project) }}" class="csa-review-link">Review</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <aside class="csa-aside">
            <section class="csa-panel">
                <div class="csa-panel-head">
                    <h3>Recent org accounts</h3>
                    <a href="{{ route('users.index') }}" class="csa-panel-link">View all</a>
                </div>
                @if($recentOrgs->isEmpty())
                    <div class="csa-empty">No organization accounts yet.</div>
                @else
                    <ul class="csa-org-list">
                        @foreach($recentOrgs as $org)
                            <li>
                                <a href="{{ route('users.edit', $org) }}" class="csa-org-link">
                                    <x-organization-avatar :organization="$org" :size="34" />
                                    <span class="csa-org-name">{{ $org->organization_name ?? $org->name }}</span>
                                </a>
                                <div class="csa-org-meta">Added {{ $org->created_at?->diffForHumans() ?? 'Date unavailable' }}</div>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <div class="csa-panel-actions">
                    <a href="{{ route('users.create') }}" class="csa-panel-link">Create org account</a>
                </div>
            </section>

            <section class="csa-panel">
                <div class="csa-panel-head">
                    <h3>Review checklist</h3>
                </div>
                <ul class="csa-checklist">
                    <li><span class="csa-check" aria-hidden="true">✓</span> Confirm activity dates and venue</li>
                    <li><span class="csa-check" aria-hidden="true">✓</span> Check required compliance forms</li>
                    <li><span class="csa-check" aria-hidden="true">✓</span> Verify organization standing</li>
                    <li><span class="csa-check" aria-hidden="true">✓</span> Check for outstanding reports</li>
                </ul>
                <div class="csa-panel-actions">
                    <a href="{{ route('csa.reports.index') }}" class="csa-panel-link">Generate reports</a>
                    <a href="{{ route('templates.manage') }}" class="csa-panel-link">Compliance forms</a>
                </div>
            </section>
        </aside>
    </div>
    </div>
@endsection
