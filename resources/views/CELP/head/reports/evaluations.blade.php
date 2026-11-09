@extends('layouts.admin')

@section('title', 'Participant feedback')
@section('subtitle', 'Evaluation ratings and written feedback from activity participants')

@section('content')
    @include('CELP.head.reports.partials.shell', ['reportTitle' => 'Participant Feedback Report'])

    <form method="GET" class="rp-bar">
        <div class="rp-field">
            <label for="ay">Academic year</label>
            <select name="ay" id="ay">
                @foreach($years as $year)
                    <option value="{{ $year }}" @selected($f['ay'] === $year)>{{ $year }}</option>
                @endforeach
            </select>
        </div>
        <div class="rp-field">
            <label for="term">Term</label>
            <select name="term" id="term">
                <option value="">Whole year</option>
                @foreach(\App\Support\AcademicPeriod::TERMS as $key => $label)
                    <option value="{{ $key }}" @selected($f['term'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="rp-field">
            <label for="initiator">Project initiator</label>
            <select name="initiator" id="initiator">
                <option value="">All initiators</option>
                @foreach($initiators as $initiator)
                    <option value="{{ $initiator->id }}" @selected((string) $f['initiator'] === (string) $initiator->id)>
                        {{ $initiator->organization_name ?? $initiator->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="rp-field">
            <label for="from">From</label>
            <input type="date" name="from" id="from" value="{{ $f['from'] }}">
        </div>
        <div class="rp-field">
            <label for="to">To</label>
            <input type="date" name="to" id="to" value="{{ $f['to'] }}">
        </div>
        <div class="rp-actions">
            <button type="submit" class="rp-btn rp-btn-primary">Apply</button>
            <a href="{{ route('reports.evaluations', array_merge(request()->query(), ['format' => 'csv'])) }}" class="rp-btn">CSV</a>
            <button type="button" class="rp-btn" onclick="window.print()">Print</button>
        </div>
    </form>

    <style>
        .ef-summary { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:13px; margin-bottom:18px; }
        .ef-metric { padding:18px 20px; border:1px solid #e5ebe8; border-radius:14px; background:#fff; }
        .ef-metric-label { color:#71817c; font-size:.78rem; font-weight:700; }
        .ef-metric-value { margin-top:7px; color:#173e34; font-size:1.75rem; font-weight:800; line-height:1.1; }
        .ef-metric-note { margin-top:4px; color:#94a3b8; font-size:.78rem; }
        .ef-panel { margin-bottom:18px; padding:22px; border:1px solid #e5ebe8; border-radius:14px; background:#fff; }
        .ef-panel h2 { margin:0; color:#173e34; font-size:1rem; }
        .ef-panel-intro { margin:5px 0 16px; color:#71817c; font-size:.83rem; }
        .ef-criterion { display:grid; grid-template-columns:minmax(150px,260px) 1fr 54px; gap:13px; align-items:center; padding:8px 0; }
        .ef-criterion-name { color:#334b42; font-size:.84rem; }
        .ef-track { height:12px; overflow:hidden; border-radius:99px; background:#edf2ef; }
        .ef-track span { display:block; height:100%; border-radius:99px; background:linear-gradient(90deg,#35ad7d,#08734e); }
        .ef-score { color:#173e34; font-size:.84rem; font-weight:800; text-align:right; font-variant-numeric:tabular-nums; }
        .ef-feedback-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; margin-bottom:18px; }
        .ef-feedback-card { min-width:0; padding:20px; border:1px solid #e5ebe8; border-radius:14px; background:#fff; }
        .ef-feedback-card h2 { margin:0; color:#173e34; font-size:.98rem; }
        .ef-feedback-card > p { margin:5px 0 14px; color:#71817c; font-size:.82rem; }
        .ef-quote { margin-top:10px; padding:11px 13px; border-left:3px solid #acd9c2; border-radius:0 9px 9px 0; background:#f7faf8; color:#40564c; font-size:.86rem; line-height:1.55; white-space:pre-line; overflow-wrap:anywhere; }
        .ef-quote-meta { margin-top:5px; color:#899790; font-size:.75rem; }
        .ef-empty { padding:25px 10px; color:#899790; font-size:.87rem; text-align:center; }
        @media(max-width:700px) { .ef-feedback-grid { grid-template-columns:1fr; } .ef-criterion { grid-template-columns:1fr 52px; gap:6px 12px; } .ef-track { grid-column:1/-1; grid-row:2; } }
        @media print { .ef-summary { grid-template-columns:repeat(4,1fr); } .ef-feedback-grid { grid-template-columns:1fr 1fr; } }
    </style>

    <div class="ef-summary">
        <div class="ef-metric">
            <div class="ef-metric-label">Activities evaluated</div>
            <div class="ef-metric-value">{{ $evaluationCount }}</div>
            <div class="ef-metric-note">in the selected period</div>
        </div>
        <div class="ef-metric">
            <div class="ef-metric-label">Forms encoded</div>
            <div class="ef-metric-value">{{ $responseCount }}</div>
            <div class="ef-metric-note">participant responses</div>
        </div>
        <div class="ef-metric">
            <div class="ef-metric-label">Beneficiary average</div>
            <div class="ef-metric-value">{{ $overallAverageByType['beneficiary'] !== null ? number_format($overallAverageByType['beneficiary'], 2) : '—' }}</div>
            <div class="ef-metric-note">{{ $responseCountsByType['beneficiary'] }} forms · out of 5</div>
        </div>
        <div class="ef-metric">
            <div class="ef-metric-label">Volunteer / implementer average</div>
            <div class="ef-metric-value">{{ $overallAverageByType['implementer'] !== null ? number_format($overallAverageByType['implementer'], 2) : '—' }}</div>
            <div class="ef-metric-note">{{ $responseCountsByType['implementer'] }} forms · out of 5</div>
        </div>
    </div>

    <section class="ef-panel">
        <h2>Average rating by evaluation area</h2>
        <p class="ef-panel-intro">Ratings use a 1 to 5 scale and are shown separately for beneficiaries and volunteers / implementers.</p>
        @if($responseCount === 0)
            <div class="ef-empty">No evaluation forms have been encoded for this period yet.</div>
        @else
            @foreach($criteria as $criterion)
                <div class="ef-criterion">
                    <div class="ef-criterion-name">{{ $criterion['label'] }} <small>({{ $criterion['respondent_type'] === 'implementer' ? 'Volunteer / implementer' : 'Beneficiary' }})</small></div>
                    <div class="ef-track"><span style="width:{{ $criterion['average'] !== null ? ($criterion['average'] / 5 * 100) : 0 }}%"></span></div>
                    <div class="ef-score">{{ $criterion['average'] !== null ? number_format($criterion['average'], 2) : '—' }}</div>
                </div>
            @endforeach
        @endif
    </section>

    <section class="card" style="padding:0; overflow:auto; margin-bottom:18px">
        <div style="padding:16px 18px; border-bottom:1px solid #f1f5f9">
            <h2 style="margin:0; color:#173e34; font-size:1rem">Activity evaluation results</h2>
        </div>
        @if($rows->isEmpty())
            <div class="rp-empty">No activity evaluations match these filters.</div>
        @else
            <table class="rp-table">
                <thead>
                    <tr>
                        <th>Initiator / activity</th><th>Date</th><th>Status</th>
                        <th>Beneficiary responses</th><th>Volunteer / implementer responses</th><th>Expected beneficiaries</th><th>Beneficiary average</th><th>Volunteer / implementer average</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr>
                            <td><strong>{{ $row['activity'] }}</strong><div class="rp-muted" style="font-size:.8rem; margin-top:3px">{{ $row['initiator'] }}</div></td>
                            <td>{{ $row['date']?->format('M j, Y') ?? '—' }}</td>
                            <td>{{ $row['status'] }}</td>
                            <td class="rp-num">{{ $row['beneficiary_responses'] }}</td>
                            <td class="rp-num">{{ $row['implementer_responses'] }}</td>
                            <td class="rp-num">{{ $row['expected'] ?? '—' }}</td>
                            <td class="rp-num">{{ $row['beneficiary_average'] !== null ? number_format($row['beneficiary_average'], 2).' / 5' : '—' }}</td>
                            <td class="rp-num">{{ $row['implementer_average'] !== null ? number_format($row['implementer_average'], 2).' / 5' : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <div class="ef-feedback-grid">
        @foreach(['suggestions' => 'Participant suggestions', 'observations' => 'Participant observations'] as $key => $title)
            <section class="ef-feedback-card">
                <h2>{{ $title }}</h2>
                <p>{{ count($feedback[$key]) }} {{ \Illuminate\Support\Str::plural('response', count($feedback[$key])) }} included</p>
                @forelse(collect($feedback[$key])->take(20) as $entry)
                    <blockquote class="ef-quote">
                        {{ $entry['text'] }}
                        <div class="ef-quote-meta">{{ $entry['activity'] }} · {{ $entry['initiator'] }}</div>
                    </blockquote>
                @empty
                    <div class="ef-empty">No written feedback was recorded.</div>
                @endforelse
                @if(count($feedback[$key]) > 20)
                    <p class="rp-muted rp-noprint" style="font-size:.78rem; margin:12px 0 0">
                        Showing the first 20 comments. Download the CSV to review all comments.
                    </p>
                @endif
            </section>
        @endforeach
    </div>

    @include('CELP.head.reports.partials.signature', ['blocks' => [
        ['name' => auth()->user()->name, 'role' => 'Prepared by · ' . role_label(auth()->user()->role)],
        ['role' => 'Noted by'],
    ]])
@endsection
