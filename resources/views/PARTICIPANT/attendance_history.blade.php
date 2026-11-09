@extends('layouts.admin')

@section('title', 'My Attendance')
@section('subtitle', 'Your attendance records for approved activities')

@section('content')
    @php
        $statusMeta = [
            'valid' => ['Checked in', '#ecfdf5', '#065f46'],
            'completed' => ['Completed', '#dcfce7', '#166534'],
            'attended' => ['Attended', '#ecfdf5', '#065f46'],
            'partial' => ['Partial attendance', '#fffbeb', '#92400e'],
            'absent' => ['Absent', '#f1f5f9', '#475569'],
        ];
    @endphp

    @if($records->isEmpty())
        <div class="card" style="padding:40px; text-align:center; color:#64748b">
            <div style="font-size:2rem; margin-bottom:10px">📋</div>
            No attendance records yet.
        </div>
    @else
        <div style="display:grid; gap:14px">
            @foreach($records as $record)
                @php
                    [$label, $background, $color] = $statusMeta[$record->status] ?? [ucfirst(str_replace('_', ' ', $record->status)), '#f1f5f9', '#475569'];
                    $isOrganizerCsv = in_array($record->attendance_source, \App\Models\AttendanceRecord::CSV_SOURCES, true);
                    $checkInAt = $record->check_in_at ?? (!$isOrganizerCsv ? $record->scanned_at : null);
                @endphp
                <article class="card" style="padding:20px 22px">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap">
                        <div>
                            <h3 style="margin:0; color:#0f172a">{{ $record->session?->project?->title ?? 'Activity unavailable' }}</h3>
                            <div style="margin-top:8px; color:#64748b">Attendance session</div>
                        </div>
                        <span style="display:inline-flex; padding:6px 12px; border-radius:999px; background:{{ $background }}; color:{{ $color }}; font-size:0.82rem; font-weight:700">
                            {{ $label }}
                        </span>
                    </div>
                    <div style="display:flex; gap:24px; flex-wrap:wrap; margin-top:16px; color:#475569; font-size:0.92rem">
                        <div><strong>Check-in:</strong> {{ $checkInAt?->format('M j, Y g:i A') ?? ($record->check_in_status ?? '—') }}</div>
                        <div><strong>Check-out:</strong> {{ $record->check_out_at?->format('M j, Y g:i A') ?? ($record->check_out_status ?? '—') }}</div>
                        <div><strong>Recorded via:</strong> {{ $isOrganizerCsv ? 'Organizer CSV' : 'QR scan' }}</div>
                    </div>
                </article>
            @endforeach
        </div>
        <div style="margin-top:20px">{{ $records->links() }}</div>
    @endif
@endsection
