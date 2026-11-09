@extends('layouts.admin')

@section('title', 'Performance Reports')
@section('subtitle', 'Reports for activities initiated and managed by your organization')

@section('content')
    @php
        $colors = [
            'activity-completion' => ['#eff6ff', '#1d4ed8'],
            'participant-involvement' => ['#ecfdf5', '#047857'],
            'attendance' => ['#fff7ed', '#c2410c'],
            'evaluation-results' => ['#f5f3ff', '#6d28d9'],
        ];
        $icons = [
            'activity-completion' => '<path d="m5 12 4 4L19 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/>',
            'participant-involvement' => '<circle cx="9" cy="8" r="3" stroke="currentColor" stroke-width="1.8"/><path d="M3.5 20v-1.5A4.5 4.5 0 0 1 8 14h2a4.5 4.5 0 0 1 4.5 4.5V20M16 8h5m-5 4h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
            'attendance' => '<rect x="4" y="5" width="16" height="16" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M8 3v4m8-4v4M4 10h16m-9 4 1.5 1.5L16 12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
            'evaluation-results' => '<path d="M12 3.5 14.6 9l6 .8-4.4 4.2 1.1 6-5.3-2.9-5.3 2.9 1.1-6-4.4-4.2 6-.8L12 3.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>',
        ];
    @endphp
    <style>
        .or-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(270px,1fr)); gap:16px; }
        .or-card { display:flex; gap:15px; min-height:130px; padding:20px; border:1px solid #e4ebe7; border-radius:16px; background:#fff; color:inherit; text-decoration:none; transition:transform .16s ease,box-shadow .16s ease,border-color .16s ease; }
        .or-card:hover { transform:translateY(-2px); border-color:#b9d8c8; box-shadow:0 12px 26px rgba(11,42,42,.08); }
        .or-icon { display:grid; place-items:center; width:44px; height:44px; flex:0 0 44px; border-radius:14px; }
        .or-card h2 { margin:2px 0 7px; color:#173e34; font-size:1rem; }
        .or-card p { margin:0; color:#667971; font-size:.85rem; line-height:1.55; }
    </style>
    <div class="or-grid">
        @foreach($reports as $key => $report)
            <a class="or-card" href="{{ route('student_org.reports.show', ['report' => $key]) }}">
                <span class="or-icon" style="background:{{ $colors[$key][0] }}; color:{{ $colors[$key][1] }}" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none">{!! $icons[$key] !!}</svg>
                </span>
                <span>
                    <h2>{{ $report['title'] }}</h2>
                    <p>{{ $report['description'] }}</p>
                </span>
            </a>
        @endforeach
    </div>
@endsection
