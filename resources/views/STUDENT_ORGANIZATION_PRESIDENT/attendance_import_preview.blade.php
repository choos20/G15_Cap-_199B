@extends('layouts.admin')

@section('title', 'Review Attendance CSV')
@section('subtitle', $attendanceSession->project->title ?? 'Attendance session')

@section('content')
    <style>
        .attendance-import-preview-table { width:100%; border-collapse:collapse; }
        .attendance-import-preview-table th, .attendance-import-preview-table td { padding:12px 14px; text-align:left; vertical-align:top; border-bottom:1px solid #e5e7eb; }
        .attendance-import-preview-table th { color:#64748b; font-size:0.78rem; text-transform:uppercase; letter-spacing:0.04em; }
        .attendance-import-preview-table tr.invalid { background:#fff7f7; }
        .attendance-import-summary { display:flex; flex-wrap:wrap; gap:12px; margin:18px 0; }
        .attendance-import-summary div { padding:16px 20px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:14px; }
        .attendance-import-summary strong { display:block; color:#0f172a; font-size:1.3rem; }
        .attendance-import-primary { border:0; border-radius:12px; padding:12px 16px; background:#0fbf91; color:#fff; font-weight:700; cursor:pointer; }
        .attendance-import-primary:disabled { opacity:.55; cursor:not-allowed; }
        .attendance-import-secondary { display:inline-flex; align-items:center; border:1px solid #cbd5e1; border-radius:12px; padding:12px 16px; background:#f8fafc; color:#0f172a; font-weight:700; text-decoration:none; }
        .attendance-import-stepper { display:grid; grid-template-columns:repeat(4,1fr); gap:8px; margin:18px 0; color:#64748b; font-size:.82rem; }
        .attendance-import-stepper span { display:flex; align-items:center; gap:8px; }
        .attendance-import-stepper b { display:grid; place-items:center; width:26px; height:26px; border-radius:50%; background:#e2e8f0; color:#475569; }
        .attendance-import-stepper .done, .attendance-import-stepper .active { color:#0b6a4a; font-weight:700; }
        .attendance-import-stepper .done b, .attendance-import-stepper .active b { background:#0b6a4a; color:#fff; }
        @media(max-width:600px) { .attendance-import-stepper { grid-template-columns:repeat(2,1fr); } }
    </style>

    <a href="{{ route('student_org.attendance') }}" style="display:inline-flex; color:#2563eb; text-decoration:none; font-weight:700; margin-bottom:18px;">&larr; Back to attendance sessions</a>

    <div class="card" style="padding:24px;">
        <h2 style="margin:0 0 8px; color:#0f172a;">Review imported attendance</h2>
        <p style="color:#475569; margin:0;">Check the matched Participant IDs and activity details before saving. Names are shown as a reference only; the account match is based on Participant ID. Only valid matched rows will be saved.</p>
        <div class="attendance-import-stepper" aria-label="Online attendance CSV import steps">
            <span class="done"><b>1</b>Upload CSV online</span>
            <span class="done"><b>2</b>Match participants</span>
            <span class="active"><b>3</b>Review attendance</span>
            <span><b>4</b>Save records</span>
        </div>
        <div style="margin-top:14px; padding:12px 14px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:10px; color:#1e40af;">
            Server validation is complete. Attendance has not been saved yet. Confirm below to write the valid rows to ProjectLink.
        </div>

        <div class="attendance-import-summary">
            <div><strong>{{ $matchedCount }}</strong>Matched</div>
            <div><strong>{{ $presentCount }}</strong>Present volunteers</div>
            <div><strong>{{ $absentCount }}</strong>Absent volunteers</div>
            <div><strong>{{ $unmatchedCount }}</strong>Unmatched participant IDs</div>
            <div><strong>{{ $invalidCount }}</strong>Invalid / duplicate records</div>
            <div><strong>{{ $partialCount }}</strong>Partial records</div>
        </div>

        <div style="overflow:auto;">
            <table class="attendance-import-preview-table">
                <thead>
                    <tr>
                        <th>CSV row</th>
                        <th>Participant ID</th>
                        <th>Participant</th>
                        <th>Check-In</th>
                        <th>Check-Out</th>
                        <th>Result</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr @class(['invalid' => ! $row['valid']])>
                            <td>{{ $row['line'] }}</td>
                            <td>{{ $row['participant_id'] ?: '—' }}</td>
                            <td>
                                <div>{{ $row['matched_name'] ?? $row['submitted_name'] ?: 'Unknown participant' }}</div>
                                @if($row['matched_name'] && $row['submitted_name'] && mb_strtolower($row['matched_name']) !== mb_strtolower($row['submitted_name']))
                                    <div style="color:#a16207; font-size:0.82rem;">CSV name differs; matched using Participant ID.</div>
                                @endif
                            </td>
                            <td>{{ $row['check_in'] ?: '—' }}</td>
                            <td>{{ $row['check_out'] ?: '—' }}</td>
                            <td>
                                @if($row['valid'])
                                    <strong>{{ ucfirst($row['status']) }}</strong>
                                    @if($row['warnings'])
                                        <ul style="margin:6px 0 0; padding-left:18px; color:#92400e; font-size:0.85rem;">
                                            @foreach($row['warnings'] as $warning)
                                                <li>{{ $warning }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                @else
                                    <ul style="margin:0; padding-left:18px; color:#b91c1c;">
                                        @foreach($row['issues'] as $issue)
                                            <li>{{ $issue }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="display:flex; flex-wrap:wrap; gap:10px; margin-top:22px;">
            <a href="{{ route('student_org.attendance') }}" class="attendance-import-secondary">Cancel and correct CSV</a>
            <form method="post" action="{{ route('student_org.attendance.confirm', $attendanceSession) }}">
                @csrf
                <input type="hidden" name="preview_token" value="{{ $token }}">
                <button type="submit" class="attendance-import-primary" @disabled($importableCount === 0)>Confirm and save {{ $importableCount }} valid row(s)</button>
            </form>
        </div>
        @if($invalidCount > 0)
            <p style="margin:14px 0 0; color:#92400e;">{{ $invalidCount }} invalid or duplicate row(s) will be skipped if you confirm. Fix and upload again if those participants should be included.</p>
        @endif
    </div>
@endsection
