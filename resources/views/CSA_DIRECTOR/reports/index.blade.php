@extends('layouts.admin')

@section('title', 'Generate Reports')
@section('subtitle', 'Reports for registered student organizations')

@section('content')
    <style>
        .csr-page { display:grid; gap:18px; }
        .csr-filter { display:grid; grid-template-columns:minmax(210px,1.5fr) repeat(3,minmax(145px,1fr)) auto; align-items:end; gap:12px; padding:18px; border:1px solid #e6ece9; border-radius:14px; background:#fff; }
        .csr-field label { display:block; margin-bottom:6px; color:#52645e; font-size:0.8rem; font-weight:700; }
        .csr-field select, .csr-field input { width:100%; min-height:41px; padding:9px 11px; border:1px solid #dbe3e0; border-radius:9px; background:#fff; color:#243b36; font:inherit; font-size:0.85rem; }
        .csr-button { min-height:41px; padding:9px 15px; border:0; border-radius:9px; background:#0b7656; color:#fff; font:inherit; font-size:0.84rem; font-weight:700; text-decoration:none; cursor:pointer; }
        .csr-button:hover { background:#086247; }
        .csr-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(265px,1fr)); gap:14px; }
        .csr-card { display:flex; gap:13px; min-width:0; padding:17px; border:1px solid #e5ebe8; border-radius:13px; background:#fff; color:inherit; text-decoration:none; transition:border-color 150ms ease,transform 150ms ease,box-shadow 150ms ease; }
        .csr-card:hover { transform:translateY(-2px); border-color:#b8d9c8; box-shadow:0 8px 22px rgba(11,42,42,0.07); }
        .csr-card-icon { display:flex; align-items:center; justify-content:center; width:40px; height:40px; flex:0 0 40px; border-radius:12px; background:#edf7f1; color:#08734e; }
        .csr-card-icon svg { width:21px; height:21px; }
        .csr-card h3 { margin:1px 0 5px; color:#173e34; font-size:0.92rem; }
        .csr-card p { margin:0; color:#71817c; font-size:0.81rem; line-height:1.5; }
        .csr-scope-note { padding:13px 16px; border:1px solid #dcebe2; border-radius:11px; background:#f5faf7; color:#52645e; font-size:0.83rem; line-height:1.5; }
        @media(max-width:900px) { .csr-filter { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        @media(max-width:520px) { .csr-filter { grid-template-columns:1fr; } .csr-button { width:100%; text-align:center; } }
    </style>

    <div class="csr-page">
        <form method="GET" action="{{ route('csa.reports.index') }}" class="csr-filter">
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
            <button class="csr-button" type="submit">Apply filters</button>
        </form>

        <div class="csr-grid">
            @foreach($reports as $key => $report)
                <a class="csr-card" href="{{ route('csa.reports.show', array_merge(['report' => $key], array_filter([
                    'organization' => $filters['organization'],
                    'ay' => $filters['ay'],
                    'from' => $filters['from'],
                    'to' => $filters['to'],
                ]))) }}">
                    <span class="csr-card-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            @switch($key)
                                @case('accomplishment')
                                    <path d="M5 19V5m0 14h14M9 15l3-4 3 2 4-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                    @break
                                @case('volunteer-roster')
                                    <circle cx="9" cy="8" r="3" stroke="currentColor" stroke-width="1.8"/>
                                    <path d="M3.5 20v-1.5A4.5 4.5 0 0 1 8 14h2a4.5 4.5 0 0 1 4.5 4.5V20M16 7h5m-5 4h5m-5 4h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                    @break
                                @case('participation')
                                    <path d="M12 21s-7-4.3-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 11c0 5.7-7 10-7 10Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                    @break
                                @case('attendance')
                                    <rect x="4" y="5" width="16" height="16" rx="2" stroke="currentColor" stroke-width="1.8"/>
                                    <path d="M8 3v4m8-4v4M4 10h16m-9 4 1.5 1.5L16 12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                    @break
                                @case('compliance')
                                    <path d="M7 3.75h7l4.25 4.3V20A1.25 1.25 0 0 1 17 21.25H7A1.25 1.25 0 0 1 5.75 20V5A1.25 1.25 0 0 1 7 3.75Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                    <path d="M14 4v4h4m-9 4h6m-6 3.5h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                    @break
                                @case('activity-status')
                                    <path d="M5 5h14v14H5zM8 9h8m-8 3h5m-5 3h3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                    @break
                                @case('evaluation-summary')
                                    <path d="M4 19h16M7 16v-5m5 5V5m5 11v-8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                    @break
                            @endswitch
                        </svg>
                    </span>
                    <span>
                        <h3>{{ $report['title'] }}</h3>
                        <p>{{ $report['description'] }}</p>
                    </span>
                </a>
            @endforeach
        </div>

    </div>
@endsection
