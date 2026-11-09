@extends('layouts.admin')

@section('title', 'Participants per activity')
@section('subtitle', 'Attendance roster — printable with signature column')

@section('content')
    @include('CELP.head.reports.partials.shell', ['reportTitle' => 'Participants per Activity (Attendance)'])

    <style>
        /* The signature column is for the printed roster only. */
        .rp-sig-col { display:none; }
        @media print { .rp-sig-col { display:table-cell; width:22%; } }
    </style>

    <form method="GET" class="rp-bar">
        <div class="rp-field">
            <label for="project">Activity</label>
            <select name="project" id="project">
                <option value="">All activities</option>
                @foreach($projects as $p)
                    <option value="{{ $p->id }}" @selected((string) $projectId === (string) $p->id)>{{ $p->title }}</option>
                @endforeach
            </select>
        </div>
        <div class="rp-field">
            <label for="ay">Academic year</label>
            <select name="ay" id="ay">
                @foreach($years as $y)
                    <option value="{{ $y }}" @selected($f['ay'] === $y)>{{ $y }}</option>
                @endforeach
            </select>
        </div>
        <div class="rp-field">
            <label for="status">Attendance status</label>
            <select name="status" id="status">
                <option value="">All</option>
                @foreach(['valid' => 'Attended (QR)', 'attended' => 'Attended', 'completed' => 'Completed', 'partial' => 'Partial', 'absent' => 'Absent', 'expired' => 'Expired', 'out_of_range' => 'Out of range', 'not_enrolled' => 'Not enrolled'] as $k => $label)
                    <option value="{{ $k }}" @selected($f['status'] === $k)>{{ $label }}</option>
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
            <a href="{{ route('reports.attendance', array_merge(request()->query(), ['format' => 'csv'])) }}" class="rp-btn">CSV</a>
            <button type="button" class="rp-btn" onclick="window.print()">Print roster</button>
        </div>
    </form>

    <div class="card" style="padding:0; overflow:auto">
        @if($rows->isEmpty())
            <div class="rp-empty">
                No attendance has been recorded for this filter.
                <div style="font-size:0.86rem; margin-top:8px">Records appear after a QR check-in or an organizer CSV upload.</div>
            </div>
        @else
            <div style="padding:16px 18px; border-bottom:1px solid #f1f5f9" class="rp-noprint">
                <strong style="color:#0f172a">{{ $rows->count() }} {{ \Illuminate\Support\Str::plural('record', $rows->count()) }}</strong>
                <span class="rp-muted" style="font-size:0.85rem">
                    &middot; across {{ $groups->count() }} {{ \Illuminate\Support\Str::plural('activity', $groups->count()) }}
                </span>
            </div>

            @foreach($groups as $activity => $items)
                <div class="rp-group">
                    <div class="rp-group-head">
                        {{ $activity }}
                        <span class="rp-muted" style="font-weight:400">&middot; {{ $items->count() }} {{ \Illuminate\Support\Str::plural('participant', $items->count()) }}</span>
                    </div>
                    <table class="rp-table">
                        <thead>
                            <tr>
                                <th style="width:26px">#</th>
                                <th>Name</th><th>ID number</th><th>Participant type</th>
                                <th>Department / College</th><th>Recorded at</th><th>Check-In</th><th>Check-Out</th><th>Status</th>
                                <th class="rp-sig-col">Signature</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $i => $r)
                                <tr>
                                    <td class="rp-num rp-muted">{{ $i + 1 }}</td>
                                    <td style="font-weight:600">{{ $r['name'] }}</td>
                                    <td class="rp-num">{{ $r['id_number'] ?? '—' }}</td>
                                    <td>{{ $r['type'] }}</td>
                                    <td>{{ $r['department'] ?? '—' }}</td>
                                    <td class="rp-num">{{ $r['scanned_at']?->format('M j, Y g:i A') ?? '—' }}</td>
                                    <td>{{ $r['check_in_status'] ?? '—' }}</td>
                                    <td>{{ $r['check_out_status'] ?? '—' }}</td>
                                    <td>
                                        <span style="display:inline-flex; padding:4px 10px; border-radius:999px; font-size:0.76rem; font-weight:700;
                                                     background:{{ in_array($r['status'], ['Attended', 'Completed']) ? '#ecfdf5' : ($r['status'] === 'Partial' ? '#fffbeb' : ($r['status'] === 'Absent' ? '#f1f5f9' : '#fef2f2')) }};
                                                     color:{{ in_array($r['status'], ['Attended', 'Completed']) ? '#047857' : ($r['status'] === 'Partial' ? '#92400e' : ($r['status'] === 'Absent' ? '#475569' : '#b91c1c')) }}">{{ $r['status'] }}</span>
                                    </td>
                                    <td class="rp-sig-col"></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        @endif
    </div>

    @unless($rows->isEmpty())
        <p class="rp-muted rp-noprint" style="font-size:0.82rem; margin-top:12px">
            ID number and department come from the participant’s profile; “—” means the profile hasn’t been filled in.
            A signature column is added when you print.
        </p>
    @endunless

    @include('CELP.head.reports.partials.signature', ['blocks' => [
        ['name' => auth()->user()->name, 'role' => 'Prepared by · ' . role_label(auth()->user()->role)],
        ['role' => 'Attested by'],
    ]])
@endsection
