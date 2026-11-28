@extends('layouts.admin')

@section('title', $reportInfo['title'])
@section('subtitle', $reportInfo['description'])

@section('content')
    @if($report === 'attendance')<script src="{{ asset('js/attendance-report-filters.js') }}" defer></script>@endif
    @php
        $termLabel = $filters['term']
            ? (\App\Support\AcademicPeriod::TERMS[$filters['term']] ?? $filters['term'])
            : 'Whole year';
        $formatReportDate = static fn (string $date): string => filled($date)
            ? \Illuminate\Support\Carbon::parse($date)->format('M j, Y')
            : 'Any date';
    @endphp
    <style>
        .or-report { min-width:0; }
        .or-back { display:inline-flex; }
        .or-toolbar { display:flex; align-items:flex-end; gap:12px; flex-wrap:wrap; margin-bottom:10px; padding:18px; border:1px solid #e3ece7; border-radius:18px; background:linear-gradient(135deg,#ffffff 0%,#f9fcfa 100%); box-shadow:0 10px 26px rgba(11,42,42,.045); }
        .or-field { display:flex; flex:0 1 auto; flex-direction:column; gap:7px; min-width:142px; }
        .or-field--date { min-width:188px; }
        .or-field label { color:#5f756c; font-size:.7rem; font-weight:800; letter-spacing:.06em; text-transform:uppercase; }
        .or-field select { min-width:142px; min-height:44px; padding:10px 34px 10px 12px; border:1px solid #d9e5df; border-radius:11px; outline:0; background:#fff; color:#253c34; font:inherit; font-size:.86rem; cursor:pointer; transition:border-color .16s ease,box-shadow .16s ease,background .16s ease; }
        .or-field select:focus { border-color:#19a979; box-shadow:0 0 0 4px rgba(19,184,134,.12); }
        .or-date-picker { position:relative; min-width:0; }
        .or-date-input-shell { position:relative; display:flex; align-items:center; min-height:44px; border-radius:11px; background:#fff; }
        .or-date-input-shell > svg { position:absolute; left:12px; z-index:1; width:18px; height:18px; color:#087c5b; pointer-events:none; }
        .or-date-native { width:100%; min-width:0; min-height:44px; padding:10px 12px 10px 39px; border:1px solid #d9e5df; border-radius:11px; outline:0; background:#fff; color:#253c34; font:inherit; font-size:.86rem; cursor:pointer; transition:border-color .16s ease,box-shadow .16s ease,background .16s ease; }
        .or-date-native:focus { border-color:#19a979; box-shadow:0 0 0 4px rgba(19,184,134,.12); }
        .or-date-picker.is-enhanced .or-date-native { position:absolute; width:1px; height:1px; padding:0; border:0; opacity:0; pointer-events:none; clip:rect(0 0 0 0); clip-path:inset(50%); }
        .or-date-trigger { display:none; align-items:center; justify-content:space-between; gap:10px; width:100%; min-height:44px; padding:10px 12px 10px 39px; border:1px solid #d9e5df; border-radius:11px; outline:0; background:#fff; color:#253c34; font:inherit; font-size:.86rem; text-align:left; cursor:pointer; transition:border-color .16s ease,box-shadow .16s ease,background .16s ease; }
        .or-date-picker.is-enhanced .or-date-trigger { display:flex; }
        .or-date-trigger:hover { border-color:#9ed4bd; background:#fbfffd; }
        .or-date-trigger:focus-visible,.or-date-picker.is-open .or-date-trigger { border-color:#19a979; box-shadow:0 0 0 4px rgba(19,184,134,.12); }
        .or-date-value.is-placeholder { color:#91a19a; }
        .or-date-chevron { width:16px; height:16px; flex:0 0 16px; color:#6c8178; transition:transform .16s ease; }
        .or-date-picker.is-open .or-date-chevron { transform:rotate(180deg); }
        .or-calendar { position:absolute; top:calc(100% + 10px); left:0; z-index:70; width:min(320px,calc(100vw - 32px)); padding:14px; border:1px solid #dce9e3; border-radius:17px; background:#fff; box-shadow:0 22px 46px rgba(11,42,42,.18); }
        .or-calendar[hidden] { display:none; }
        .or-date-picker--to .or-calendar { right:0; left:auto; }
        .or-calendar-head { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:13px; }
        .or-calendar-title { color:#173e34; font-size:.9rem; font-weight:800; }
        .or-calendar-nav { display:grid; place-items:center; width:31px; height:31px; padding:0; border:1px solid #dfebe5; border-radius:9px; background:#fff; color:#49645a; cursor:pointer; transition:background .16s ease,border-color .16s ease,color .16s ease; }
        .or-calendar-nav:hover { border-color:#9ed4bd; background:#effbf5; color:#087c5b; }
        .or-calendar-nav svg { width:16px; height:16px; }
        .or-calendar-weekdays,.or-calendar-grid { display:grid; grid-template-columns:repeat(7,minmax(0,1fr)); gap:4px; }
        .or-calendar-weekdays { margin-bottom:5px; color:#8a9b94; font-size:.64rem; font-weight:800; letter-spacing:.04em; text-align:center; text-transform:uppercase; }
        .or-calendar-day { display:grid; place-items:center; width:100%; aspect-ratio:1; min-height:31px; padding:0; border:1px solid transparent; border-radius:9px; background:#fff; color:#304a40; font:inherit; font-size:.78rem; cursor:pointer; transition:background .14s ease,border-color .14s ease,color .14s ease,transform .14s ease; }
        .or-calendar-day:hover:not(:disabled) { border-color:#a9dcc4; background:#effbf5; color:#087c5b; transform:translateY(-1px); }
        .or-calendar-day.is-outside { color:#a5b3ad; }
        .or-calendar-day.is-today { border-color:#8ad0b0; font-weight:800; }
        .or-calendar-day.is-selected { border-color:#087c5b; background:#087c5b; color:#fff; font-weight:800; box-shadow:0 5px 12px rgba(8,124,91,.22); }
        .or-calendar-day:disabled { color:#c5d0cb; cursor:not-allowed; opacity:.65; }
        .or-calendar-foot { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-top:12px; padding-top:11px; border-top:1px solid #edf2ef; }
        .or-calendar-action { padding:4px 0; border:0; background:none; color:#087c5b; font:inherit; font-size:.77rem; font-weight:800; cursor:pointer; }
        .or-calendar-action:hover { color:#065f46; text-decoration:underline; }
        .or-actions { display:flex; gap:8px; margin-left:auto; }
        .or-button { display:inline-flex; align-items:center; justify-content:center; min-height:44px; padding:10px 15px; border:1px solid #d9e5df; border-radius:11px; background:#fff; color:#334b42; font:inherit; font-size:.84rem; font-weight:800; text-decoration:none; cursor:pointer; transition:background .16s ease,border-color .16s ease,box-shadow .16s ease,transform .16s ease; }
        .or-button:hover { border-color:#9ed4bd; background:#f8fffb; box-shadow:0 7px 16px rgba(11,42,42,.07); transform:translateY(-1px); }
        .or-button-primary { border-color:#0b7656; background:#0b7656; color:#fff; box-shadow:0 7px 14px rgba(11,118,86,.16); }
        .or-button-primary:hover { border-color:#075c43; background:#075c43; color:#fff; }
        .or-note { display:flex; align-items:flex-start; gap:8px; margin:0 0 16px; color:#52665d; font-size:.78rem; line-height:1.5; }
        .or-note svg { width:16px; height:16px; flex:0 0 16px; margin-top:1px; color:#0b8c65; }
        .or-summary { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:12px; margin-bottom:18px; }
        .or-metric { position:relative; overflow:hidden; padding:17px 18px; border:1px solid #e5ebe8; border-radius:15px; background:#fff; box-shadow:0 8px 20px rgba(11,42,42,.04); }
        .or-metric::after { position:absolute; right:-18px; bottom:-24px; width:70px; height:70px; border-radius:50%; background:#effbf5; content:''; }
        .or-metric-label { position:relative; z-index:1; color:#52665d; font-size:.77rem; font-weight:750; }
        .or-metric-value { position:relative; z-index:1; margin-top:7px; color:#173e34; font-size:1.55rem; font-weight:800; font-variant-numeric:tabular-nums; }
        .or-table-wrap { overflow:auto; border:1px solid #e5ebe8; border-radius:15px; background:#fff; box-shadow:0 8px 20px rgba(11,42,42,.04); }
        .or-table { width:100%; border-collapse:collapse; font-size:.86rem; }
        .or-table th { padding:12px 13px; border-bottom:1px solid #e5ebe8; background:#fbfdfc; color:#52665d; font-size:.7rem; font-weight:800; text-align:left; text-transform:uppercase; white-space:nowrap; }
        .or-table td { padding:13px; border-bottom:1px solid #f0f3f1; color:#263d34; vertical-align:top; }
        .or-table tbody tr:hover { background:#fbfefc; }
        .or-table tr:last-child td { border-bottom:0; }
        .attendance-roster { overflow:auto; border:1px solid #dfeae4; border-radius:16px; background:#fff; box-shadow:0 10px 24px rgba(11,42,42,.05); }
        .attendance-roster table { width:100%; min-width:1240px; border-collapse:collapse; font-size:.8rem; }
        .attendance-roster th { padding:13px 14px; border-bottom:1px solid #dfeae4; background:#f8fcfa; color:#668077; font-size:.68rem; font-weight:800; letter-spacing:.06em; text-align:left; text-transform:uppercase; white-space:nowrap; }
        .attendance-roster td { padding:14px; border-bottom:1px solid #edf3ef; color:#3f594e; vertical-align:top; }
        .attendance-roster tbody tr:hover { background:#fbfefc; }
        .attendance-roster tbody tr:last-child td { border-bottom:0; }
        .attendance-name { color:#173e34; font-weight:800; }
        .attendance-id { color:#087c5b; font-variant-numeric:tabular-nums; font-weight:800; white-space:nowrap; }
        .attendance-sub { display:block; margin-top:3px; color:#899a92; font-size:.72rem; }
        .attendance-status { display:inline-flex; align-items:center; gap:6px; padding:5px 9px; border-radius:999px; font-size:.7rem; font-weight:800; white-space:nowrap; }
        .attendance-status::before { width:6px; height:6px; border-radius:50%; background:currentColor; content:''; opacity:.75; }
        .attendance-status--attended,.attendance-status--completed { background:#ecfdf5; color:#047857; }
        .attendance-status--partial { background:#fffbeb; color:#92400e; }
        .attendance-status--other { background:#f1f5f9; color:#475569; }
        .attendance-roster-note { display:flex; align-items:flex-start; gap:8px; margin:0 0 14px; color:#60756b; font-size:.8rem; line-height:1.5; }
        .attendance-roster-note svg { width:17px; height:17px; flex:0 0 17px; margin-top:1px; color:#087c5b; }
        .or-empty { padding:42px 18px; color:#778780; text-align:center; }
        .or-section { margin:18px 0; padding:20px; border:1px solid #e5ebe8; border-radius:15px; background:#fff; box-shadow:0 8px 20px rgba(11,42,42,.04); }
        .or-section h2 { margin:0; color:#173e34; font-size:.98rem; }
        .or-criterion { display:grid; grid-template-columns:minmax(150px,260px) 1fr 54px; gap:12px; align-items:center; padding:8px 0; }
        .or-track { height:11px; overflow:hidden; border-radius:99px; background:#edf2ef; }
        .or-track span { display:block; height:100%; border-radius:99px; background:linear-gradient(90deg,#35ad7d,#08734e); }
        .or-feedback { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; }
        .or-comment { margin-top:9px; padding:11px 13px; border-left:3px solid #add9c2; border-radius:0 8px 8px 0; background:#f7faf8; color:#40564c; font-size:.84rem; line-height:1.5; white-space:pre-line; overflow-wrap:anywhere; }
        .or-print-header { display:none; }
        .or-report--completion .or-toolbar { background:#fff; border-color:#e1e5e3; border-radius:10px; box-shadow:none; }
        .or-report--completion .or-metric,.or-report--completion .or-table-wrap { border-color:#e1e5e3; border-radius:10px; box-shadow:none; }
        .or-report--completion .or-metric::after { display:none; }
        .or-report--completion .or-table th { background:#f8f9f8; color:#52605a; }
        .or-report--completion .or-table tbody tr:hover { background:#f8f9f8; }
        .or-actions { flex-wrap:wrap; }
        .or-button svg { width:16px; height:16px; margin-right:6px; }
        @media(max-width:700px) {
            .or-toolbar { padding:14px; }
            .or-field,.or-field--date { flex:1 1 calc(50% - 8px); min-width:0; }
            .or-actions { width:100%; margin-left:0; }
            .or-actions>* { flex:1; }
            .or-feedback { grid-template-columns:1fr; }
            .or-criterion { grid-template-columns:1fr 48px; gap:6px 10px; }
            .or-track { grid-column:1/-1; grid-row:2; }
        }
        @media(max-width:460px) {
            .or-field,.or-field--date { flex-basis:100%; }
            .or-calendar { position:fixed; top:auto; right:12px; bottom:12px; left:12px; width:auto; }
        }
        @media print {
            @page { size:landscape; margin:12mm; }
            .sidebar,.mobile-nav-backdrop,.topbar,.or-toolbar,.or-back,.pl-back-button,.or-no-print { display:none!important; }
            html,body { background:#fff!important; color:#172a23!important; }
            .main,.content,body { padding:0!important; margin:0!important; background:#fff!important; }
            .or-print-header { display:block; padding-bottom:10pt; margin-bottom:12pt; border-bottom:1pt solid #d9e5df; }
            .or-print-kicker { color:#087c5b; font-size:8pt; font-weight:800; letter-spacing:.12em; text-transform:uppercase; }
            .or-print-title { margin:4pt 0 2pt; color:#173e34; font-size:18pt; font-weight:800; }
            .or-print-meta { color:#60756c; font-size:9pt; }
            .or-note { margin-bottom:10pt; color:#5f756c; font-size:8.5pt; }
            .or-summary { grid-template-columns:repeat(3,1fr); gap:8pt; margin-bottom:12pt; }
            .or-metric { padding:10pt 12pt; border-color:#d9e5df; box-shadow:none; }
            .or-metric::after { display:none; }
            .or-metric-value { margin-top:4pt; font-size:15pt; }
            .or-table-wrap { overflow:visible; border-color:#d9e5df; box-shadow:none; }
            .or-table { font-size:8.5pt; }
            .or-table thead { display:table-header-group; }
            .or-table th { padding:7pt 8pt; background:#f4f8f5!important; color:#456158; font-size:7.5pt; }
            .or-table td { padding:8pt; color:#263d34; }
            .or-table tbody tr { break-inside:avoid; }
            .attendance-roster { overflow:visible; border-color:#d9e5df; box-shadow:none; }
            .attendance-roster table { min-width:0; font-size:7.5pt; }
            .attendance-roster th { padding:6pt 7pt; background:#f4f8f5!important; color:#456158; font-size:6.8pt; }
            .attendance-roster td { padding:6pt 7pt; color:#263d34; }
            .attendance-roster tbody tr { break-inside:avoid; }
            .attendance-roster-note { margin-bottom:10pt; color:#5f756c; font-size:8.5pt; }
            .attendance-sub { font-size:7pt; }
            .or-section { margin:12pt 0; padding:12pt; border-color:#d9e5df; box-shadow:none; break-inside:avoid; }
        }
    </style>
    <div class="or-report{{ $report === 'activity-completion' ? ' or-report--completion' : '' }}">
        <div class="or-print-header">
            <div class="or-print-kicker">ProjectLink · Performance report</div>
            <div class="or-print-title">{{ $reportInfo['title'] }}</div>
            <div class="or-print-meta">Academic year {{ $filters['ay'] }} · {{ $termLabel }} · Submitted {{ $formatReportDate($filters['from']) }} to {{ $formatReportDate($filters['to']) }}</div>
            @if($report === 'activity-completion')
                <div class="or-print-meta">Approval status: {{ $filters['approval_status'] ? \App\Models\Project::statusLabel($filters['approval_status']) : 'All' }} · Evaluation status: {{ match($filters['evaluation_status']) { 'submitted' => 'Submitted', 'not_submitted' => 'Not submitted', default => 'All' } }}</div>
            @endif
            @if($report === 'attendance')
                <div class="or-print-meta">Activity: {{ $activityOptions->firstWhere('id', $filters['project'])?->title ?? 'All activities' }} · College / department: {{ $filters['department'] === '_unspecified' ? 'Not specified' : ($filters['department'] ?: 'All') }} · Program: {{ $filters['program'] === '_unspecified' ? 'Not specified' : ($filters['program'] ?: 'All') }} · Participant type: {{ \App\Models\User::PARTICIPANT_TYPES[$filters['participant_type']] ?? match($filters['participant_type']) { 'external_participant' => 'External participant', '_unspecified' => 'Not specified', default => 'All' } }}</div>
            @endif
        </div>
        <x-back-button class="or-back" href="{{ route('student_org.reports') }}">Back to performance reports</x-back-button>
        <form method="GET" class="or-toolbar">
        <div class="or-field">
            <label for="ay">Academic year</label>
            <select name="ay" id="ay">
                @foreach($years as $year)
                    <option value="{{ $year }}" @selected($filters['ay'] === $year)>{{ $year }}</option>
                @endforeach
            </select>
        </div>
        <div class="or-field">
            <label for="term">Term</label>
            <select name="term" id="term">
                <option value="">Whole year</option>
                @foreach(\App\Support\AcademicPeriod::TERMS as $key => $label)
                    <option value="{{ $key }}" @selected($filters['term'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        @if($report === 'activity-completion')
        <div class="or-field">
            <label for="approval_status">Approval status</label>
            <select name="approval_status" id="approval_status">
                <option value="">All approval statuses</option>
                @foreach(\App\Models\Project::STATUS_LABELS as $value => $label)<option value="{{ $value }}" @selected($filters['approval_status'] === $value)>{{ $label }}</option>@endforeach
            </select>
        </div>
        <div class="or-field">
            <label for="evaluation_status">Evaluation status</label>
            <select name="evaluation_status" id="evaluation_status">
                <option value="">All evaluation statuses</option>
                <option value="submitted" @selected($filters['evaluation_status'] === 'submitted')>Submitted</option>
                <option value="not_submitted" @selected($filters['evaluation_status'] === 'not_submitted')>Not submitted</option>
            </select>
        </div>
        @endif
        @if($report === 'attendance')
            <div class="or-field" style="min-width:230px; flex:1 1 230px;">
                <label for="project">Activity</label>
                <select name="project" id="project">
                    <option value="">All activities</option>
                    @foreach($activityOptions as $activityOption)
                        <option value="{{ $activityOption->id }}" @selected((string) ($filters['project'] ?? '') === (string) $activityOption->id)>{{ $activityOption->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="or-field">
                <label for="department">College / department</label>
                <select name="department" id="department" data-attendance-department>
                    <option value="">All colleges / departments</option>
                    <option value="_unspecified" @selected($filters['department'] === '_unspecified')>Not specified</option>
                    @foreach($attendanceFilterOptions['departments'] as $department)
                        <option value="{{ $department }}" @selected($filters['department'] === $department)>{{ $department }}</option>
                    @endforeach
                    @if(filled($filters['department']) && $filters['department'] !== '_unspecified' && !$attendanceFilterOptions['departments']->contains($filters['department']))
                        <option value="{{ $filters['department'] }}" selected>{{ $filters['department'] }} (no matching records)</option>
                    @endif
                </select>
            </div>
            <div class="or-field">
                <label for="program">Program</label>
                <select name="program" id="program" data-attendance-program>
                    <option value="">All programs</option>
                    <option value="_unspecified" @selected($filters['program'] === '_unspecified')>Not specified / not applicable</option>
                    @foreach($attendanceFilterOptions['programs'] as $program)
                        <option value="{{ $program['code'] }}" data-departments="{{ json_encode($program['departments']) }}" @selected($filters['program'] === $program['code'])>{{ $program['code'] }}</option>
                    @endforeach
                    @if(filled($filters['program']) && $filters['program'] !== '_unspecified' && !$attendanceFilterOptions['programs']->contains('code', $filters['program']))
                        <option value="{{ $filters['program'] }}" selected>{{ $filters['program'] }} (no matching records)</option>
                    @endif
                </select>
            </div>
            <div class="or-field">
                <label for="participant_type">Participant type</label>
                <select name="participant_type" id="participant_type">
                    <option value="">All participant types</option>
                    @foreach(\App\Models\User::PARTICIPANT_TYPES as $value => $label)<option value="{{ $value }}" @selected($filters['participant_type'] === $value)>{{ $label }}</option>@endforeach
                </select>
            </div>
        @endif
        <div class="or-field or-field--date">
            <label for="from">Submitted from</label>
            <div class="or-date-picker" data-date-picker data-date-field="from">
                <div class="or-date-input-shell">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M8 3v4m8-4v4M4 10h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    <input type="date" name="from" id="from" value="{{ $filters['from'] }}" class="or-date-native pl-date-control">
                    <button type="button" class="or-date-trigger pl-date-control" aria-label="Choose submitted from date" aria-haspopup="dialog" aria-expanded="false" aria-controls="from-calendar">
                        <span class="or-date-value"></span>
                        <svg class="or-date-chevron" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m6 9 6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                </div>
                <div class="or-calendar" id="from-calendar" role="dialog" aria-label="Choose submitted from date" hidden>
                    <div class="or-calendar-head">
                        <button type="button" class="or-calendar-nav" data-calendar-prev aria-label="Previous month"><svg viewBox="0 0 24 24" fill="none"><path d="m15 5-7 7 7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                        <strong class="or-calendar-title" data-calendar-title aria-live="polite"></strong>
                        <button type="button" class="or-calendar-nav" data-calendar-next aria-label="Next month"><svg viewBox="0 0 24 24" fill="none"><path d="m9 5 7 7-7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                    </div>
                    <div class="or-calendar-weekdays" aria-hidden="true"><span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span></div>
                    <div class="or-calendar-grid" data-calendar-grid role="grid"></div>
                    <div class="or-calendar-foot"><button type="button" class="or-calendar-action pl-date-action pl-date-action-secondary" data-date-clear>Clear</button><button type="button" class="or-calendar-action pl-date-action pl-date-action-quick" data-date-today>Today</button></div>
                </div>
            </div>
        </div>
        <div class="or-field or-field--date">
            <label for="to">Submitted to</label>
            <div class="or-date-picker or-date-picker--to" data-date-picker data-date-field="to">
                <div class="or-date-input-shell">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M8 3v4m8-4v4M4 10h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    <input type="date" name="to" id="to" value="{{ $filters['to'] }}" class="or-date-native pl-date-control">
                    <button type="button" class="or-date-trigger pl-date-control" aria-label="Choose submitted to date" aria-haspopup="dialog" aria-expanded="false" aria-controls="to-calendar">
                        <span class="or-date-value"></span>
                        <svg class="or-date-chevron" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m6 9 6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                </div>
                <div class="or-calendar" id="to-calendar" role="dialog" aria-label="Choose submitted to date" hidden>
                    <div class="or-calendar-head">
                        <button type="button" class="or-calendar-nav" data-calendar-prev aria-label="Previous month"><svg viewBox="0 0 24 24" fill="none"><path d="m15 5-7 7 7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                        <strong class="or-calendar-title" data-calendar-title aria-live="polite"></strong>
                        <button type="button" class="or-calendar-nav" data-calendar-next aria-label="Next month"><svg viewBox="0 0 24 24" fill="none"><path d="m9 5 7 7-7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                    </div>
                    <div class="or-calendar-weekdays" aria-hidden="true"><span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span></div>
                    <div class="or-calendar-grid" data-calendar-grid role="grid"></div>
                    <div class="or-calendar-foot"><button type="button" class="or-calendar-action pl-date-action pl-date-action-secondary" data-date-clear>Clear</button><button type="button" class="or-calendar-action pl-date-action pl-date-action-quick" data-date-today>Today</button></div>
                </div>
            </div>
        </div>
        <div class="or-actions">
            <button type="submit" class="or-button or-button-primary pl-date-action pl-date-action-primary">@if($report === 'activity-completion')<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 6h16l-6.5 7.3V19l-3 1v-6.7L4 6Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>@endif Apply filters</button>
            @if(in_array($report, ['attendance', 'activity-completion'], true))<a class="or-button pl-date-action pl-date-action-secondary" href="{{ route('student_org.reports.show', ['report' => $report]) }}">Reset</a>@endif
            <a class="or-button" href="{{ route('student_org.reports.show', array_merge(['report' => $report], request()->query(), ['format' => 'csv'])) }}">CSV</a>
            <button type="button" class="or-button" onclick="window.print()">Print</button>
        </div>
        </form>
        @if($report === 'attendance')
            <p class="attendance-roster-note"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M12 10.5v5m0-8h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg><span>This roster lists people with recorded participation for your activities, including external names entered through an organizer CSV. Academic details follow each participant's current profile. Use “Not specified” to find missing details. Totals, CSV and print follow the applied filters. ID numbers retain leading zeroes.</span></p>
        @else
            <p class="or-note"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M12 10.5v5m0-8h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg><span>Reports include only projects submitted by your organization. The selected period and date range use the project submission date.</span></p>
        @endif

    <div class="or-summary">
        @foreach($summary as $label => $value)
            <div class="or-metric"><div class="or-metric-label">{{ $label }}</div><div class="or-metric-value">{{ $value }}</div></div>
        @endforeach
    </div>

    @if($report === 'attendance')
        <div class="attendance-roster">
            @if($attendanceRows->isEmpty())
                <div class="or-empty">No participants with recorded attendance match the selected filters.</div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>#</th><th>Activity</th><th>ID number</th><th>Full name</th>
                            <th>Participant type</th><th>Program</th><th>College / department</th><th>Participation role</th>
                            <th>Status</th><th>Check-in</th><th>Check-out</th><th>Source</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($attendanceRows as $index => $participant)
                            @php
                                $statusClass = match($participant['status']) {
                                    'Attended' => 'attendance-status--attended',
                                    'Completed' => 'attendance-status--completed',
                                    'Partial' => 'attendance-status--partial',
                                    default => 'attendance-status--other',
                                };
                            @endphp
                            <tr>
                                <td class="attendance-sub">{{ $index + 1 }}</td>
                                <td>{{ $participant['activity'] }}</td>
                                <td class="attendance-id">{{ $participant['id_number'] ?: '—' }}</td>
                                <td><span class="attendance-name">{{ $participant['name'] }}</span><span class="attendance-sub">{{ $participant['recorded_at'] }}</span></td>
                                <td>{{ $participant['participant_type'] }}</td>
                                <td>{{ $participant['program'] }}</td>
                                <td>{{ $participant['department'] }}</td>
                                <td>{{ $participant['participation_role'] }}</td>
                                <td><span class="attendance-status {{ $statusClass }}">{{ $participant['status'] }}</span></td>
                                <td>{{ $participant['check_in'] }}</td>
                                <td>{{ $participant['check_out'] }}</td>
                                <td>{{ $participant['source'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @else
    <div class="or-table-wrap">
        @if($rows->isEmpty())
            <div class="or-empty">No records match the selected period.</div>
        @else
            <table class="or-table">
                <thead><tr>@foreach($headings as $heading)<th>{{ $heading }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr>@foreach($row as $value)<td>{{ $value }}</td>@endforeach</tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    @endif

    @if($report === 'evaluation-results')
        <section class="or-section">
            <h2>Average rating by evaluation area</h2>
            <x-evaluation-ratings :items="$criteria" :overall="['Beneficiary average' => is_numeric(explode(' ', $summary['Beneficiary average'])[0]) ? (float) $summary['Beneficiary average'] : null, 'Volunteer / implementer average' => is_numeric(explode(' ', $summary['Volunteer / implementer average'])[0]) ? (float) $summary['Volunteer / implementer average'] : null]" />
        </section>
        <div class="or-feedback">
            @foreach(['suggestions' => 'Participant suggestions', 'observations' => 'Participant observations'] as $field => $label)
                <section class="or-section" style="margin:0">
                    <h2>{{ $label }}</h2>
                    @php $comments = $feedback->filter(fn ($response) => filled($response->{$field})); @endphp
                    @forelse($comments->take(20) as $response)
                        <div class="or-comment">{{ $response->{$field} }}<div style="margin-top:5px;color:#899790;font-size:.75rem">{{ $response->activity }}</div></div>
                    @empty
                        <div class="or-empty">No written feedback recorded.</div>
                    @endforelse
                    @if($comments->count() > 20)
                        <p class="or-no-print" style="color:#82918b;font-size:.78rem">Showing the first 20 comments.</p>
                    @endif
                </section>
            @endforeach
        </div>
        @endif
    </div>

    <script>
        (() => {
            const dateFormat = new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            const monthFormat = new Intl.DateTimeFormat('en-US', { month: 'long', year: 'numeric' });
            const today = new Date();

            const parseIso = (value) => {
                if (!value) return null;
                const [year, month, day] = value.split('-').map(Number);
                return new Date(year, month - 1, day);
            };
            const toIso = (date) => [
                date.getFullYear(),
                String(date.getMonth() + 1).padStart(2, '0'),
                String(date.getDate()).padStart(2, '0'),
            ].join('-');

            const syncRange = () => {
                const from = document.getElementById('from');
                const to = document.getElementById('to');
                if (from && to) {
                    from.max = to.value || '';
                    to.min = from.value || '';
                }
            };

            document.querySelectorAll('[data-date-picker]').forEach((picker) => {
                const nativeInput = picker.querySelector('.or-date-native');
                const trigger = picker.querySelector('.or-date-trigger');
                const valueLabel = picker.querySelector('.or-date-value');
                const calendar = picker.querySelector('.or-calendar');
                const title = picker.querySelector('[data-calendar-title]');
                const grid = picker.querySelector('[data-calendar-grid]');
                const previous = picker.querySelector('[data-calendar-prev]');
                const next = picker.querySelector('[data-calendar-next]');
                const clear = picker.querySelector('[data-date-clear]');
                const todayButton = picker.querySelector('[data-date-today]');
                if (!nativeInput || !trigger || !valueLabel || !calendar || !title || !grid) return;

                const state = { view: parseIso(nativeInput.value) || new Date() };
                state.view.setDate(1);
                picker.classList.add('is-enhanced');
                nativeInput.tabIndex = -1;

                const updateValueLabel = () => {
                    valueLabel.textContent = nativeInput.value ? dateFormat.format(parseIso(nativeInput.value)) : 'Any date';
                    valueLabel.classList.toggle('is-placeholder', !nativeInput.value);
                };

                const close = (restoreFocus = false) => {
                    picker.classList.remove('is-open');
                    calendar.hidden = true;
                    trigger.setAttribute('aria-expanded', 'false');
                    if (restoreFocus) trigger.focus();
                };

                const render = () => {
                    title.textContent = monthFormat.format(state.view);
                    grid.innerHTML = '';
                    const year = state.view.getFullYear();
                    const month = state.view.getMonth();
                    const firstDay = new Date(year, month, 1).getDay();
                    const daysInMonth = new Date(year, month + 1, 0).getDate();
                    const selected = nativeInput.value;
                    const todayIso = toIso(today);

                    for (let cell = 0; cell < 42; cell += 1) {
                        const date = new Date(year, month, cell - firstDay + 1);
                        const iso = toIso(date);
                        const day = document.createElement('button');
                        day.type = 'button';
                        day.className = 'or-calendar-day';
                        day.textContent = date.getDate();
                        day.dataset.date = iso;
                        day.setAttribute('role', 'gridcell');
                        day.setAttribute('aria-label', dateFormat.format(date));
                        if (date.getMonth() !== month) day.classList.add('is-outside');
                        if (iso === todayIso) {
                            day.classList.add('is-today');
                            day.setAttribute('aria-current', 'date');
                        }
                        if (iso === selected) {
                            day.classList.add('is-selected');
                            day.setAttribute('aria-pressed', 'true');
                        }
                        if ((nativeInput.min && iso < nativeInput.min) || (nativeInput.max && iso > nativeInput.max)) day.disabled = true;
                        if (cell >= firstDay && cell < firstDay + daysInMonth) day.classList.remove('is-outside');
                        day.addEventListener('click', () => {
                            nativeInput.value = iso;
                            nativeInput.dispatchEvent(new Event('change', { bubbles: true }));
                            updateValueLabel();
                            render();
                            close();
                        });
                        grid.appendChild(day);
                    }
                };

                const setValue = (value) => {
                    nativeInput.value = value;
                    const selected = parseIso(value);
                    if (selected) {
                        state.view = selected;
                        state.view.setDate(1);
                    }
                    nativeInput.dispatchEvent(new Event('change', { bubbles: true }));
                    updateValueLabel();
                    render();
                    close();
                };

                trigger.addEventListener('click', () => {
                    const isOpen = !calendar.hidden;
                    document.querySelectorAll('.or-date-picker.is-open').forEach((other) => {
                        if (other !== picker) other.querySelector('.or-date-trigger')?.click();
                    });
                    if (isOpen) {
                        close();
                        return;
                    }
                    picker.classList.add('is-open');
                    calendar.hidden = false;
                    trigger.setAttribute('aria-expanded', 'true');
                    render();
                });
                previous?.addEventListener('click', () => { state.view = new Date(state.view.getFullYear(), state.view.getMonth() - 1, 1); render(); });
                next?.addEventListener('click', () => { state.view = new Date(state.view.getFullYear(), state.view.getMonth() + 1, 1); render(); });
                clear?.addEventListener('click', () => setValue(''));
                todayButton?.addEventListener('click', () => setValue(toIso(today)));
                nativeInput.addEventListener('change', () => {
                    const selected = parseIso(nativeInput.value);
                    state.view = selected || new Date();
                    state.view.setDate(1);
                    updateValueLabel();
                    syncRange();
                    render();
                });
                document.addEventListener('click', (event) => {
                    if (!picker.contains(event.target)) close();
                });
                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && !calendar.hidden) close(true);
                });

                updateValueLabel();
                render();
                syncRange();
            });
        })();
    </script>
@endsection
