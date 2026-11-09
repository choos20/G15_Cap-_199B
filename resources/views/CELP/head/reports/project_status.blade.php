@extends('layouts.admin')

@section('title', 'Project status summary')
@section('subtitle', 'Review pipeline for the selected period')

@section('content')
    @include('CELP.head.reports.partials.shell', ['reportTitle' => 'Project Status Summary'])

    @php
        // Status palette, matched to the dashboard chart so the two agree.
        $tones = [
            'Approved' => ['#0ca30c', '#ecfdf5', '#047857'],
            'Pending'  => ['#fab219', '#fffbeb', '#92400e'],
            'Returned' => ['#d03b3b', '#fef2f2', '#b91c1c'],
        ];
        $delta = function (int $now, int $was) {
            if ($was === 0) return $now === 0 ? '—' : "+{$now}";
            $diff = $now - $was;
            return ($diff > 0 ? '+' : '') . $diff;
        };
    @endphp

    <form method="GET" class="rp-bar">
        <div class="rp-field">
            <label for="ay">Academic year</label>
            <select name="ay" id="ay">
                @foreach($years as $y)
                    <option value="{{ $y }}" @selected($f['ay'] === $y)>{{ $y }}</option>
                @endforeach
            </select>
        </div>
        <div class="rp-field">
            <label for="term">Term</label>
            <select name="term" id="term">
                <option value="">Whole year</option>
                @foreach(\App\Support\AcademicPeriod::TERMS as $k => $label)
                    <option value="{{ $k }}" @selected($f['term'] === $k)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="rp-field">
            <label for="status">Status</label>
            <select name="status" id="status">
                <option value="">All statuses</option>
                @foreach(\App\Models\Project::STATUS_LABELS as $k => $label)
                    <option value="{{ $k }}" @selected($f['status'] === $k)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="rp-field">
            <label for="initiator">Initiator</label>
            <select name="initiator" id="initiator">
                <option value="">All initiators</option>
                @foreach($initiators as $org)
                    <option value="{{ $org->id }}" @selected((string) $f['initiator'] === (string) $org->id)>{{ $org->organization_name ?? $org->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="rp-actions">
            <button type="submit" class="rp-btn rp-btn-primary">Apply</button>
            <a href="{{ route('reports.project_status', array_merge(request()->query(), ['format' => 'csv'])) }}" class="rp-btn">CSV</a>
            <button type="button" class="rp-btn" onclick="window.print()">Print</button>
        </div>
    </form>

    <div class="card" style="padding:24px; margin-bottom:18px">
        <div style="display:flex; align-items:baseline; justify-content:space-between; gap:16px">
            <h3 style="margin:0; font-size:1.05rem; color:#0f172a">Pipeline breakdown</h3>
            <span style="font-size:0.85rem; color:#52514e">{{ $total }} {{ \Illuminate\Support\Str::plural('project', $total) }}</span>
        </div>

        @if($total === 0)
            <div class="rp-empty">No projects were submitted in this period.</div>
        @else
            <div style="display:flex; gap:2px; height:24px; margin-top:20px">
                @foreach($counts as $group => $n)
                    @if($n > 0)
                        <div style="flex:{{ $n }} 1 0; background:{{ $tones[$group][0] }}; display:flex; align-items:center; justify-content:center;
                                    border-radius:{{ $loop->first ? '6px 2px 2px 6px' : ($loop->last ? '2px 6px 6px 2px' : '2px') }};
                                    color:{{ $group === 'Returned' ? '#fff' : '#0b0b0b' }}; font-size:0.8rem; font-weight:700">
                            @if(round($n / $total * 100) >= 8){{ $n }}@endif
                        </div>
                    @endif
                @endforeach
            </div>

            <table class="rp-table" style="margin-top:20px">
                <thead>
                    <tr>
                        <th>Status</th><th>Count</th><th>Share</th>
                        <th>A.Y. {{ $prevAy }}</th><th>Change</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($counts as $group => $n)
                        <tr>
                            <td>
                                <span style="display:inline-flex; align-items:center; gap:8px">
                                    <span style="width:10px; height:10px; border-radius:3px; background:{{ $tones[$group][0] }}"></span>
                                    {{ $group }}
                                </span>
                            </td>
                            <td class="rp-num">{{ $n }}</td>
                            <td class="rp-num">{{ $total ? round($n / $total * 100) : 0 }}%</td>
                            <td class="rp-num rp-muted">{{ $prevCounts[$group] }}</td>
                            <td class="rp-num">{{ $delta($n, $prevCounts[$group]) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="card" style="padding:0; overflow:auto">
        @if($rows->isEmpty())
            <div class="rp-empty">Nothing to list for this filter.</div>
        @else
            <table class="rp-table">
                <thead>
                    <tr>
                        <th>Project title</th><th>Initiator</th><th>Date submitted</th>
                        <th>Current status</th><th>Days in queue</th><th>Reviewer</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $r)
                        <tr>
                            <td style="font-weight:600">{{ $r['title'] }}</td>
                            <td>{{ $r['initiator'] }}</td>
                            <td class="rp-num">{{ $r['submitted']->format('M j, Y') }}</td>
                            <td>
                                <span style="display:inline-flex; padding:4px 10px; border-radius:999px; font-size:0.76rem; font-weight:700;
                                             background:{{ $tones[$r['group']][1] }}; color:{{ $tones[$r['group']][2] }}">{{ $r['status'] }}</span>
                            </td>
                            <td class="rp-num">
                                {{ $r['days'] }}
                                @if($r['open'])<span class="rp-muted" style="font-size:0.8rem"> (open)</span>@endif
                            </td>
                            <td>{{ $r['reviewer'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @unless($rows->isEmpty())
        <p class="rp-muted rp-noprint" style="font-size:0.82rem; margin-top:12px">
            Reviewer is taken from the audit log, which began recording on Aug 12, 2026 — decisions made before then show “—”.
        </p>
    @endunless

    @include('CELP.head.reports.partials.signature', ['blocks' => [
        ['name' => auth()->user()->name, 'role' => 'Prepared by · ' . role_label(auth()->user()->role)],
        ['role' => 'Noted by'],
    ]])
@endsection
