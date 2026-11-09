@extends('layouts.admin')

@section('title', $reportInfo['title'])
@section('subtitle', $reportInfo['description'])

@section('content')
    <style>
        .cr-toolbar { display:flex; align-items:flex-end; gap:11px; flex-wrap:wrap; margin-bottom:18px; padding:16px; border:1px solid #e5ebe8; border-radius:14px; background:#fff; }
        .cr-field { display:flex; flex-direction:column; gap:5px; }
        .cr-field label { color:#71817c; font-size:.73rem; font-weight:750; letter-spacing:.04em; text-transform:uppercase; }
        .cr-field input, .cr-field select { min-width:135px; min-height:39px; padding:8px 10px; border:1px solid #dfe7e3; border-radius:9px; background:#fff; color:#253c34; font:inherit; font-size:.84rem; }
        .cr-actions { display:flex; gap:8px; margin-left:auto; }
        .cr-button { display:inline-flex; align-items:center; justify-content:center; min-height:39px; padding:8px 14px; border:1px solid #dfe7e3; border-radius:9px; background:#fff; color:#334b42; font:inherit; font-size:.84rem; font-weight:750; text-decoration:none; cursor:pointer; }
        .cr-button-primary { border-color:#0b7656; background:#0b7656; color:#fff; }
        .cr-back { display:inline-flex; margin-bottom:14px; color:#08734e; font-size:.85rem; font-weight:700; text-decoration:none; }
        .cr-summary { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:12px; margin-bottom:18px; }
        .cr-metric { padding:16px 18px; border:1px solid #e5ebe8; border-radius:13px; background:#fff; }
        .cr-metric-label { color:#71817c; font-size:.77rem; font-weight:700; }
        .cr-metric-value { margin-top:7px; color:#173e34; font-size:1.55rem; font-weight:800; }
        .cr-table-wrap { overflow:auto; border:1px solid #e5ebe8; border-radius:13px; background:#fff; }
        .cr-table { width:100%; border-collapse:collapse; font-size:.86rem; }
        .cr-table th { padding:11px 13px; border-bottom:1px solid #e5ebe8; color:#71817c; font-size:.72rem; text-align:left; text-transform:uppercase; white-space:nowrap; }
        .cr-table td { padding:12px 13px; border-bottom:1px solid #f0f3f1; color:#263d34; vertical-align:top; }
        .cr-table tr:last-child td { border-bottom:0; }
        .cr-num { font-variant-numeric:tabular-nums; }
        .cr-empty { padding:42px 18px; color:#778780; text-align:center; }
        .cr-section { margin:18px 0; padding:20px; border:1px solid #e5ebe8; border-radius:13px; background:#fff; }
        .cr-section h2 { margin:0; color:#173e34; font-size:.98rem; }
        .cr-section-intro { margin:5px 0 12px; color:#82918b; font-size:.81rem; }
        .cr-filter-note { margin:-8px 0 16px; color:#71817c; font-size:.78rem; }
        .cr-criterion { display:grid; grid-template-columns:minmax(150px,260px) 1fr 54px; gap:12px; align-items:center; padding:8px 0; }
        .cr-track { height:11px; overflow:hidden; border-radius:99px; background:#edf2ef; }
        .cr-track span { display:block; height:100%; border-radius:99px; background:linear-gradient(90deg,#35ad7d,#08734e); }
        .cr-feedback { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; }
        .cr-comment { margin-top:9px; padding:11px 13px; border-left:3px solid #add9c2; border-radius:0 8px 8px 0; background:#f7faf8; color:#40564c; font-size:.84rem; line-height:1.5; white-space:pre-line; overflow-wrap:anywhere; }
        @media(max-width:700px) { .cr-actions { width:100%; margin-left:0; } .cr-actions > * { flex:1; } .cr-feedback { grid-template-columns:1fr; } .cr-criterion { grid-template-columns:1fr 48px; gap:6px 10px; } .cr-track { grid-column:1/-1; grid-row:2; } }
        @media print { .sidebar, .topbar, .cr-toolbar, .cr-back, .cr-no-print { display:none !important; } .main, .content, body { padding:0!important; margin:0!important; background:#fff!important; } }
    </style>

    <a class="cr-back" href="{{ route($reportRoutes['index']) }}">&larr; All reports</a>

    <form method="GET" class="cr-toolbar">
        <div class="cr-field">
            <label for="ay">Academic year</label>
            <select name="ay" id="ay">
                @foreach($years as $year)
                    <option value="{{ $year }}" @selected($filters['ay'] === $year)>{{ $year }}</option>
                @endforeach
            </select>
        </div>
        <div class="cr-field">
            <label for="term">Term</label>
            <select name="term" id="term">
                <option value="">Whole year</option>
                @foreach(\App\Support\AcademicPeriod::TERMS as $key => $label)
                    <option value="{{ $key }}" @selected($filters['term'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="cr-field">
            <label for="initiator">Project initiator</label>
            <select name="initiator" id="initiator">
                <option value="">All initiators</option>
                @foreach($initiators as $initiator)
                    <option value="{{ $initiator->id }}" @selected((string) $filters['initiator'] === (string) $initiator->id)>
                        {{ $initiator->organization_name ?? $initiator->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="cr-field">
            <label for="from">From</label>
            <input type="date" name="from" id="from" value="{{ $filters['from'] }}">
        </div>
        <div class="cr-field">
            <label for="to">To</label>
            <input type="date" name="to" id="to" value="{{ $filters['to'] }}">
        </div>
        <div class="cr-actions">
            <button type="submit" class="cr-button cr-button-primary">Apply filters</button>
            <a class="cr-button" href="{{ route($reportRoutes['show'], array_merge(['report' => $report], request()->query(), ['format' => 'csv'])) }}">CSV</a>
            <button type="button" class="cr-button" onclick="window.print()">Print</button>
        </div>
    </form>
    <p class="cr-filter-note">The academic year and date range are based on when projects were submitted.</p>

    <div class="cr-summary">
        @foreach($summary as $label => $value)
            <div class="cr-metric">
                <div class="cr-metric-label">{{ $label }}</div>
                <div class="cr-metric-value">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <div class="cr-table-wrap">
        @if($rows->isEmpty())
            <div class="cr-empty">No records match the selected filters.</div>
        @else
            <table class="cr-table">
                <thead>
                    <tr>
                        @foreach($headings as $heading)
                            <th>{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr>
                            @foreach($row as $value)
                                <td class="{{ is_numeric($value) ? 'cr-num' : '' }}">{{ $value }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if($report === 'evaluation-results')
        <section class="cr-section">
            <h2>Average rating by evaluation area</h2>
            <p class="cr-section-intro">Ratings are averaged separately for beneficiary and volunteer / implementer forms.</p>
            @forelse($criteria as $criterion)
                <div class="cr-criterion">
                    <span>{{ $criterion->short_label }} <small style="color:#82918b">({{ $criterion->respondent_type === 'implementer' ? 'Volunteer / implementer' : 'Beneficiary' }})</small></span>
                    <span class="cr-track"><span style="width:{{ $criterion->average / 5 * 100 }}%"></span></span>
                    <strong style="text-align:right">{{ number_format($criterion->average, 2) }}</strong>
                </div>
            @empty
                <div class="cr-empty">No participant ratings have been encoded yet.</div>
            @endforelse
        </section>

        <div class="cr-feedback">
            @foreach(['suggestions' => 'Participant suggestions', 'observations' => 'Participant observations'] as $field => $label)
                <section class="cr-section" style="margin:0">
                    <h2>{{ $label }}</h2>
                    @php $comments = $feedback->filter(fn ($response) => filled($response->{$field})); @endphp
                    @forelse($comments->take(20) as $response)
                        <div class="cr-comment">
                            {{ $response->{$field} }}
                            <div style="margin-top:5px; color:#899790; font-size:.75rem">{{ $response->activity }}</div>
                        </div>
                    @empty
                        <div class="cr-empty">No written feedback recorded.</div>
                    @endforelse
                    @if($comments->count() > 20)
                        <p class="cr-no-print" style="color:#82918b; font-size:.78rem">Showing the first 20. Download CSV for all results.</p>
                    @endif
                </section>
            @endforeach
        </div>
    @endif
@endsection
