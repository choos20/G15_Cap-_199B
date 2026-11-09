@extends('layouts.admin')

@section('title', $definition['title'])
@section('subtitle', 'Student organization records only')

@section('content')
    <style>
        .csr-page { display:grid; gap:16px; }
        .csr-toolbar { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
        .csr-back { color:#08734e; font-size:0.86rem; font-weight:700; text-decoration:none; }
        .csr-back:hover { text-decoration:underline; }
        .csr-actions { display:flex; gap:8px; flex-wrap:wrap; }
        .csr-button { display:inline-flex; align-items:center; justify-content:center; min-height:38px; padding:8px 13px; border:1px solid #d9e4de; border-radius:9px; background:#fff; color:#334b43; font:inherit; font-size:0.82rem; font-weight:700; text-decoration:none; cursor:pointer; }
        .csr-button-primary { border-color:#0b7656; background:#0b7656; color:#fff; }
        .csr-button-primary:hover { background:#086247; }
        .csr-filter { display:grid; grid-template-columns:minmax(210px,1.5fr) repeat(3,minmax(145px,1fr)) auto; align-items:end; gap:12px; padding:16px; border:1px solid #e6ece9; border-radius:13px; background:#fff; }
        .csr-field label { display:block; margin-bottom:6px; color:#52645e; font-size:0.8rem; font-weight:700; }
        .csr-field select, .csr-field input { width:100%; min-height:40px; padding:8px 10px; border:1px solid #dbe3e0; border-radius:9px; background:#fff; color:#243b36; font:inherit; font-size:0.84rem; }
        .csr-table-wrap { overflow:auto; border:1px solid #e6ece9; border-radius:13px; background:#fff; }
        .csr-table { width:100%; border-collapse:collapse; min-width:720px; }
        .csr-table th { padding:12px 14px; background:#f7faf8; color:#52645e; font-size:0.75rem; font-weight:800; text-align:left; white-space:nowrap; }
        .csr-table td { padding:12px 14px; border-top:1px solid #edf1ef; color:#344b43; font-size:0.83rem; line-height:1.45; vertical-align:top; }
        .csr-table tr:nth-child(even) td { background:#fcfdfc; }
        .csr-count { color:#64756e; font-size:0.83rem; }
        .csr-empty { padding:38px 20px; color:#71817c; text-align:center; }
        .csr-print-heading { display:none; }
        @media(max-width:900px) { .csr-filter { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        @media(max-width:520px) { .csr-filter { grid-template-columns:1fr; } .csr-actions, .csr-actions .csr-button { width:100%; } }
        @media print {
            body { background:#fff !important; }
            .sidebar, .topbar, .csr-filter, .csr-actions, .csr-back { display:none !important; }
            .main { padding:0 !important; }
            .csa-report-shell, .csr-table-wrap { border:0 !important; box-shadow:none !important; }
            .csr-print-heading { display:block; margin-bottom:14px; }
            .csr-table { min-width:0; font-size:9pt; }
            .csr-table th, .csr-table td { padding:6px; }
            .csr-table-wrap { overflow:visible; }
        }
    </style>

    <div class="csr-page csa-report-shell">
        <div class="csr-toolbar">
            <a class="csr-back" href="{{ route('csa.reports.index', array_filter([
                'organization' => $filters['organization'],
                'ay' => $filters['ay'],
                'from' => $filters['from'],
                'to' => $filters['to'],
            ])) }}">← All reports</a>
            <div class="csr-actions">
                <a class="csr-button" href="{{ route('csa.reports.show', array_merge(['report' => $report], array_filter(request()->query(), fn ($value) => $value !== '' && $value !== null), ['format' => 'csv'])) }}">Download CSV</a>
                <button class="csr-button csr-button-primary" type="button" onclick="window.print()">Print / Save PDF</button>
            </div>
        </div>

        <form method="GET" action="{{ route('csa.reports.show', ['report' => $report]) }}" class="csr-filter">
            <div class="csr-field">
                <label for="organization">Student organization</label>
                <select name="organization" id="organization">
                    <option value="">All student organizations</option>
                    @foreach($organizations as $organization)
                        <option value="{{ $organization->id }}" @selected((string) $filters['organization'] === (string) $organization->id)>
                            {{ $organization->organization_name ?: $organization->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="csr-field">
                <label for="ay">Academic year</label>
                <select name="ay" id="ay">
                    @foreach($years as $year)
                        <option value="{{ $year }}" @selected($filters['ay'] === $year)>{{ $year }}</option>
                    @endforeach
                </select>
            </div>
            <div class="csr-field">
                <label for="from">From date (optional)</label>
                <input type="date" name="from" id="from" value="{{ $filters['from'] }}">
            </div>
            <div class="csr-field">
                <label for="to">To date (optional)</label>
                <input type="date" name="to" id="to" value="{{ $filters['to'] }}">
            </div>
            <button class="csr-button csr-button-primary" type="submit">Apply</button>
        </form>

        <div class="csr-print-heading">
            <h1 style="margin:0; color:#173e34; font-size:18pt">{{ $definition['title'] }}</h1>
            <p style="margin:5px 0; color:#52645e">Student organizations only · AY {{ $filters['ay'] }}</p>
            <p style="margin:0; color:#52645e">Generated {{ now()->format('M j, Y g:i A') }}</p>
        </div>

        <div class="csr-toolbar">
            <span class="csr-count"><strong>{{ count($rows) }}</strong> {{ \Illuminate\Support\Str::plural('record', count($rows)) }} · AY {{ $filters['ay'] }}</span>
            <span class="csr-count">{{ $definition['description'] }}</span>
        </div>

        <div class="csr-table-wrap">
            @if($rows === [])
                <div class="csr-empty">No records match the selected organization and reporting period.</div>
            @else
                <table class="csr-table">
                    <thead>
                        <tr>
                            @foreach($columns as $column)
                                <th>{{ $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            <tr>
                                @foreach($row as $value)
                                    <td>{{ $value }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
@endsection
