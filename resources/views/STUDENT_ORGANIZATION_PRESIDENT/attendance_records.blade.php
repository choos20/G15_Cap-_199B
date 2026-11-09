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
        $csvCount = $records->whereIn('attendance_source', \App\Models\AttendanceRecord::CSV_SOURCES)->count();
        $invalidCount = $records->whereNotIn('status', ['valid', 'attended', 'completed', 'partial', 'absent'])->count();
    @endphp

    <style>
        .ar-table { width:100%; border-collapse:collapse; }
        .ar-table th { text-align:left; padding:12px 16px; font-size:0.8rem; text-transform:uppercase; letter-spacing:0.04em; color:#94a3b8; font-weight:700; border-bottom:1px solid #e5e7eb; }
        .ar-table td { padding:14px 16px; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
        .ar-table tr:last-child td { border-bottom:none; }
        .ar-table tr:hover td { background:#f8fafc; }
        .ar-pill { display:inline-flex; padding:5px 12px; border-radius:999px; font-size:0.8rem; font-weight:700; white-space:nowrap; }
    </style>

    <a href="{{ route('student_org.attendance') }}" style="display:inline-flex; align-items:center; gap:8px; color:#2563eb; text-decoration:none; font-weight:700; margin-bottom:18px;">&larr; Back to attendance sessions</a>

    <div class="cards" style="grid-template-columns:repeat(3,1fr)">
        <div class="card" style="padding:22px">
            <div style="font-size:0.9rem; color:#6b7280">Attended / completed</div>
            <div style="font-size:1.6rem; font-weight:800; color:#0f172a; margin-top:8px">{{ $validCount }}</div>
        </div>
        <div class="card" style="padding:22px">
            <div style="font-size:0.9rem; color:#6b7280">Organizer CSV records</div>
            <div style="font-size:1.6rem; font-weight:800; color:#0f172a; margin-top:8px">{{ $csvCount }}</div>
        </div>
        <div class="card" style="padding:22px">
            <div style="font-size:0.9rem; color:#6b7280">Rejected QR attempts</div>
            <div style="font-size:1.6rem; font-weight:800; color:#0f172a; margin-top:8px">{{ $invalidCount }}</div>
        </div>
    </div>

    <div class="card" style="margin-top:20px; padding:0; overflow:auto">
        @if($records->isEmpty())
            <div style="padding:40px; text-align:center; color:#64748b">
                <div style="font-size:2rem; margin-bottom:10px">📭</div>
                No attendance has been recorded for this session yet.
            </div>
        @else
            <table class="ar-table">
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
                        @php [$label, $bg, $color] = $statusMeta[$record->status] ?? [ucfirst($record->status), '#f1f5f9', '#475569']; @endphp
                        <tr>
                            <td>
                                <div style="font-weight:600; color:#0f172a">{{ $record->user->name ?? 'Unknown' }}</div>
                                <div style="font-size:0.85rem; color:#64748b">{{ $record->user->email ?? '—' }}</div>
                            </td>
                            <td><span class="ar-pill" style="background:{{ $bg }}; color:{{ $color }}">{{ $label }}</span></td>
                            <td style="color:#475569">{{ $record->check_in_status ?? '—' }} / {{ $record->check_out_status ?? '—' }}</td>
                            <td style="color:#475569">{{ in_array($record->attendance_source, \App\Models\AttendanceRecord::CSV_SOURCES, true) ? 'Organizer CSV' : 'QR' }}</td>
                            <td style="color:#475569">{{ $record->distance_meters }}m</td>
                            <td style="color:#475569; font-size:0.85rem">{{ $record->scanned_lat }}, {{ $record->scanned_lng }}</td>
                            <td style="color:#475569">{{ $record->scanned_at?->format('M j, Y g:i A') ?? $record->updated_at?->format('M j, Y g:i A') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
