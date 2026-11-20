@extends('layouts.admin')

@section('title', 'Check-in Records')
@section('subtitle', $session->project->title ?? 'Attendance session')

@section('content')
    @php
        $statusMeta = [
            'valid' => ['Checked in', '#ecfdf5', '#065f46'],
            'attended' => ['Attended', '#ecfdf5', '#065f46'],
            'completed' => ['Completed', '#dcfce7', '#166534'],
            'partial' => ['Partial', '#fffbeb', '#92400e'],
            'absent' => ['Absent', '#f1f5f9', '#475569'],
            'out_of_range' => ['Outside geofence', '#fef2f2', '#991b1b'],
            'expired' => ['Session closed', '#f1f5f9', '#475569'],
            'duplicate' => ['Duplicate scan', '#eff6ff', '#1d4ed8'],
            'not_enrolled' => ['Not enrolled', '#fffbeb', '#92400e'],
        ];
        $validCount = $records->whereIn('status', ['valid', 'attended', 'completed'])->count();
        $invalidCount = $records->whereNotIn('status', ['valid', 'attended', 'completed', 'partial', 'absent'])->count();
        $totalCount = $records->count();
    @endphp

    <style>
        .attendance-records-page { display:grid; gap:20px; }
        .records-hero { display:flex; align-items:center; justify-content:space-between; gap:22px; padding:24px 28px; border:1px solid #d5eee2; border-radius:24px; background:linear-gradient(118deg,#effaf4 0%,#fff 65%,#e4f7ed 100%); box-shadow:0 18px 42px rgba(15,118,86,.08); }
        .records-hero-copy { display:flex; align-items:center; gap:14px; min-width:0; }
        .records-hero-icon { display:grid; place-items:center; flex:0 0 48px; width:48px; height:48px; border-radius:15px; background:linear-gradient(145deg,#087c5b,#0fbf91); color:#fff; }
        .records-hero-icon svg { width:24px; height:24px; }
        .records-kicker { display:block; margin-bottom:4px; color:#08734e; font-size:.68rem; font-weight:800; letter-spacing:.1em; text-transform:uppercase; }
        .records-hero h1 { margin:0; color:#0b2a2a; font-size:clamp(1.25rem,2vw,1.7rem); line-height:1.2; }
        .records-hero p { margin:6px 0 0; color:#668076; font-size:.82rem; line-height:1.45; }
        .records-hero-stats { display:grid; grid-template-columns:repeat(3,minmax(80px,1fr)); gap:9px; min-width:270px; }
        .records-hero-stat { padding:11px 13px; border:1px solid rgba(166,220,197,.8); border-radius:14px; background:rgba(255,255,255,.75); }
        .records-hero-stat span { display:block; color:#6a8178; font-size:.7rem; font-weight:700; }
        .records-hero-stat strong { display:block; margin-top:3px; color:#0b2a2a; font-size:1.25rem; }
        .records-toolbar { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:14px 16px; border:1px solid #dce9e3; border-radius:16px; background:#fff; }
        .records-search { position:relative; flex:1 1 auto; min-width:0; }
        .records-search svg { position:absolute; top:50%; left:13px; width:16px; height:16px; color:#789188; transform:translateY(-50%); pointer-events:none; }
        .records-search input, .records-filter { width:100%; min-height:40px; border:1px solid #d7e4de; border-radius:10px; background:#fbfdfc; color:#173f35; font:inherit; font-size:.82rem; }
        .records-search input { padding:9px 12px 9px 36px; }
        .records-filter { flex:0 0 180px; padding:9px 12px; }
        .records-search input:focus, .records-filter:focus { border-color:#0fbf91; box-shadow:0 0 0 4px rgba(15,191,145,.12); outline:none; }
        .records-filter-note { color:#71847d; font-size:.75rem; white-space:nowrap; }
        .records-table-card { overflow:hidden; padding:0; border:1px solid #dce9e3; border-radius:20px; background:#fff; box-shadow:0 18px 40px rgba(15,42,42,.06); }
        .ar-table { width:100%; border-collapse:collapse; }
        .ar-table th { padding:13px 16px; border-bottom:1px solid #dfece5; background:#fbfdfc; color:#6d827a; font-size:.68rem; font-weight:800; letter-spacing:.07em; text-align:left; text-transform:uppercase; white-space:nowrap; }
        .ar-table td { padding:15px 16px; border-bottom:1px solid #edf3ef; color:#506960; font-size:.78rem; vertical-align:middle; }
        .ar-table tr:last-child td { border-bottom:none; }
        .ar-table tbody tr:hover td { background:#f8fcfa; }
        .record-participant { display:flex; align-items:center; gap:9px; min-width:170px; }
        .record-avatar { display:grid; place-items:center; flex:0 0 32px; width:32px; height:32px; border-radius:10px; background:#e8f7ed; color:#087c5b; }
        .record-avatar svg { width:16px; height:16px; }
        .record-name, .record-email { display:block; }
        .record-name { color:#173f35; font-weight:800; }
        .record-email { margin-top:3px; color:#82958d; font-size:.7rem; }
        .ar-pill { display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; font-size:.7rem; font-weight:800; white-space:nowrap; }
        .ar-pill::before { content:''; width:6px; height:6px; border-radius:50%; background:currentColor; opacity:.7; }
        .record-source { display:inline-flex; align-items:center; gap:6px; font-weight:700; white-space:nowrap; }
        .record-source svg { width:15px; height:15px; color:#0c9870; }
        .record-muted { color:#82958d; }
        .records-empty { display:grid; justify-items:center; gap:8px; padding:45px 20px; color:#71847d; text-align:center; }
        .records-empty-icon { display:grid; place-items:center; width:46px; height:46px; border-radius:14px; background:#e9f8ef; color:#087c5b; }
        .records-empty-icon svg { width:23px; height:23px; }
        .records-empty strong { color:#214a3e; font-size:.92rem; }
        @media (max-width:760px) {
            .records-hero { align-items:flex-start; flex-direction:column; padding:20px; }
            .records-hero-stats { width:100%; min-width:0; }
            .records-toolbar { align-items:stretch; flex-direction:column; }
            .records-filter { flex-basis:auto; }
            .records-filter-note { white-space:normal; }
            .records-table-card { overflow:auto; }
            .ar-table { min-width:820px; }
        }
        @media (max-width:460px) { .records-hero-stats { gap:7px; } .records-hero-stat { padding:10px 9px; } .records-hero-stat strong { font-size:1.1rem; } }
    </style>

    <x-back-button href="{{ route('student_org.attendance') }}">Back to attendance sessions</x-back-button>

    <div class="attendance-records-page">
        <section class="records-hero" aria-labelledby="attendance-records-title">
            <div class="records-hero-copy">
                <div class="records-hero-icon" aria-hidden="true"><x-ui-icon name="clipboard" :size="24" /></div>
                <div>
                    <span class="records-kicker">Attendance audit</span>
                    <h1 id="attendance-records-title">{{ $session->project->title ?? 'Check-in records' }}</h1>
                    <p>Review every QR check-in and organizer CSV entry captured for this session.</p>
                </div>
            </div>
            <div class="records-hero-stats" aria-label="Record summary">
                <div class="records-hero-stat"><span>Total records</span><strong>{{ $totalCount }}</strong></div>
                <div class="records-hero-stat"><span>Attended</span><strong>{{ $validCount }}</strong></div>
                <div class="records-hero-stat"><span>Rejected scans</span><strong>{{ $invalidCount }}</strong></div>
            </div>
        </section>

    <div class="records-toolbar">
        <label class="records-search" for="records-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/></svg>
            <input id="records-search" type="search" placeholder="Search participants or email..." autocomplete="off">
        </label>
        <select class="records-filter" id="records-filter" aria-label="Filter attendance records">
            <option value="all">All results</option>
            <option value="valid">Attended / completed</option>
            <option value="partial">Partial</option>
            <option value="absent">Absent</option>
            <option value="rejected">Rejected scans</option>
        </select>
        <span class="records-filter-note" id="records-count" aria-live="polite">Showing {{ $totalCount }} records</span>
    </div>

    <div class="card records-table-card" style="margin-top:0;">
        @if($records->isEmpty())
            <div class="records-empty">
                <div class="records-empty-icon"><x-ui-icon name="clipboard" :size="23" /></div>
                <strong>No attendance has been recorded yet</strong>
                <span>QR scans and organizer CSV entries will appear here.</span>
            </div>
        @else
            <table class="ar-table" id="attendance-records-table">
                <thead>
                    <tr>
                        <th>Participant</th>
                        <th>Result</th>
                        <th>Check-In / Check-Out</th>
                        <th>Source</th>
                        <th>Distance</th>
                        <th>Coordinates</th>
                        <th>Recorded at</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($records as $record)
                        @php
                            [$label, $bg, $color] = $statusMeta[$record->status] ?? [ucfirst($record->status), '#f1f5f9', '#475569'];
                            $isCsv = in_array($record->attendance_source, \App\Models\AttendanceRecord::CSV_SOURCES, true);
                            $recordSearch = strtolower(($record->user->name ?? 'Unknown').' '.($record->user->email ?? '').' '.$label);
                            $filterGroup = in_array($record->status, ['valid', 'attended', 'completed'], true) ? 'valid' : (in_array($record->status, ['partial', 'absent'], true) ? $record->status : 'rejected');
                        @endphp
                        <tr data-record-search="{{ $recordSearch }}" data-record-filter="{{ $filterGroup }}">
                            <td>
                                <div class="record-participant">
                                    <span class="record-avatar"><x-ui-icon name="home" :size="16" /></span>
                                    <span><span class="record-name">{{ $record->user->name ?? 'Unknown' }}</span>
                                    <span class="record-email">{{ $record->user->email ?? 'No email provided' }}</span></span>
                                </div>
                            </td>
                            <td><span class="ar-pill" style="background:{{ $bg }}; color:{{ $color }}">{{ $label }}</span></td>
                            <td style="color:#475569">{{ $record->check_in_status ?? '—' }} / {{ $record->check_out_status ?? '—' }}</td>
                            <td><span class="record-source"><x-ui-icon name="{{ $isCsv ? 'file' : 'qr' }}" :size="15" />{{ $isCsv ? 'Organizer CSV' : 'QR scan' }}</span></td>
                            <td>{{ $record->distance_meters !== null ? $record->distance_meters.'m' : '—' }}</td>
                            <td class="record-muted">{{ $record->scanned_lat !== null ? $record->scanned_lat.', '.$record->scanned_lng : '—' }}</td>
                            <td style="color:#475569">{{ $record->scanned_at?->format('M j, Y g:i A') ?? $record->updated_at?->format('M j, Y g:i A') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const search = document.getElementById('records-search');
            const filter = document.getElementById('records-filter');
            const count = document.getElementById('records-count');
            const rows = Array.from(document.querySelectorAll('#attendance-records-table tbody tr'));
            if (!search || !filter || !count || !rows.length) return;

            const applyFilters = () => {
                const query = search.value.trim().toLowerCase();
                const selected = filter.value;
                let visible = 0;
                rows.forEach((row) => {
                    const matchesSearch = !query || row.dataset.recordSearch.includes(query);
                    const matchesFilter = selected === 'all' || row.dataset.recordFilter === selected;
                    const show = matchesSearch && matchesFilter;
                    row.hidden = !show;
                    if (show) visible += 1;
                });
                count.textContent = `Showing ${visible} record${visible === 1 ? '' : 's'}`;
            };

            search.addEventListener('input', applyFilters);
            filter.addEventListener('change', applyFilters);
        });
    </script>
@endsection
