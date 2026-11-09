@extends('layouts.admin')

@section('title', 'Evaluations')
@section('subtitle', 'Completed activities awaiting or holding beneficiary feedback')

@section('content')
    @php
        $statusMeta = [
            'not_started' => ['Not started', '#f1f5f9', '#475569'],
            'encoding' => ['Encoding in progress', '#fffbeb', '#92400e'],
            'submitted' => ['Submitted', '#ecfdf5', '#065f46'],
        ];
    @endphp

    <style>
        .ev-filters { display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:18px; }
        .ev-search { flex:1; min-width:240px; position:relative; }
        .ev-search input { width:100%; padding:12px 14px 12px 40px; border:1px solid #e2e8f0; border-radius:12px; background:#fff; font-size:0.95rem; }
        .ev-search svg { position:absolute; left:14px; top:50%; transform:translateY(-50%); color:#94a3b8; }
        .ev-select { padding:12px 14px; border:1px solid #e2e8f0; border-radius:12px; background:#fff; font-size:0.9rem; font-weight:600; color:#334155; cursor:pointer; }
        .ev-table { width:100%; border-collapse:collapse; }
        .ev-table th { text-align:left; padding:12px 18px; font-size:0.78rem; text-transform:uppercase; letter-spacing:0.05em; color:#94a3b8; font-weight:700; border-bottom:1px solid #e5e7eb; }
        .ev-table td { padding:16px 18px; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
        .ev-table tr:last-child td { border-bottom:none; }
        .ev-table tr:hover td { background:#f8fafc; }
        .ev-pill { display:inline-flex; padding:5px 12px; border-radius:999px; font-size:0.78rem; font-weight:700; white-space:nowrap; }
        .ev-act { color:#0b6a4a; font-weight:700; text-decoration:none; font-size:0.88rem; white-space:nowrap; }
        .ev-act:hover { text-decoration:underline; }
        .ev-note { margin-top:18px; display:flex; gap:10px; align-items:flex-start; padding:14px 18px; border-radius:14px; background:#f0fdf4; border:1px solid #dcfce7; color:#166534; font-size:0.88rem; }
    </style>

    @if(session('status'))
        <div style="margin-bottom:16px; padding:14px 18px; border-radius:12px; background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; font-weight:600">
            {{ session('status') }}
        </div>
    @endif

    <form method="GET" action="{{ route('student_org.evaluations') }}" class="ev-filters">
        <div class="ev-search">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M20 20l-3.5-3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            <input type="text" name="q" value="{{ $q }}" placeholder="Search activity or barangay...">
        </div>

        <select name="status" class="ev-select" onchange="this.form.submit()">
            <option value="">All statuses</option>
            @foreach(\App\Models\Evaluation::STATUS_LABELS as $key => $label)
                <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="ay" class="ev-select" onchange="this.form.submit()">
            @foreach($ayOptions as $option)
                <option value="{{ $option }}" @selected($ay === $option)>AY {{ $option }}</option>
            @endforeach
        </select>
    </form>

    <div class="card" style="padding:0; overflow:auto">
        @if($activities->isEmpty())
            <div style="padding:48px; text-align:center; color:#64748b">
                <div style="font-size:2rem; margin-bottom:10px">📋</div>
                <div style="font-weight:700; color:#334155; margin-bottom:6px">No completed activities for this period</div>
                <div style="font-size:0.9rem">
                    An activity appears here once its scheduled date has passed and its project has cleared review.
                </div>
            </div>
        @else
            <table class="ev-table">
                <thead>
                    <tr>
                        <th>Activity</th>
                        <th>Completed</th>
                        <th>Responses</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($activities as $activity)
                        @php
                            $evaluation = $activity->evaluation;
                            $beneficiaryEncoded = $evaluation->beneficiary_responses_count ?? 0;
                            $implementerEncoded = $evaluation->implementer_responses_count ?? 0;
                            $encoded = $beneficiaryEncoded + $implementerEncoded;
                            $target = $evaluation?->expected_beneficiaries;
                            $state = $evaluation?->status() ?? 'not_started';
                            [$label, $bg, $color] = $statusMeta[$state];
                        @endphp
                        <tr>
                            <td>
                                <div style="font-weight:700; color:#0f172a">{{ $activity->title }}</div>
                                <div style="display:flex; align-items:center; gap:7px; flex-wrap:wrap; font-size:0.85rem; color:#64748b; margin-top:2px">
                                    {{ $activity->event_location ?? 'Location not recorded' }}
                                    @if($activity->creator)
                                        <x-organization-avatar :organization="$activity->creator" :size="24" />
                                        <span>{{ $activity->creator->organization_name ?? $activity->creator->name }}</span>
                                    @endif
                                </div>
                            </td>
                            <td style="color:#475569; white-space:nowrap">{{ $activity->event_date->format('d M Y') }}</td>
                            <td>
                                @if($encoded > 0)
                                    <span style="font-weight:700; color:#0f172a">{{ $beneficiaryEncoded }}</span>
                                    <span style="color:#94a3b8">beneficiary{{ $target ? ' of '.$target : '' }}</span>
                                    <div style="margin-top:3px; color:#94a3b8; font-size:.8rem">{{ $implementerEncoded }} volunteer / implementer</div>
                                @else
                                    <span style="color:#94a3b8">None yet</span>
                                @endif
                            </td>
                            <td><span class="ev-pill" style="background:{{ $bg }}; color:{{ $color }}">{{ $label }}</span></td>
                            <td style="text-align:right">
                                @if($state === 'submitted')
                                    <a href="{{ route('evaluations.summary', $activity) }}" class="ev-act">View summary</a>
                                @elseif(! $canEncode)
                                    @if($encoded > 0)
                                        <a href="{{ route('evaluations.summary', $activity) }}" class="ev-act">View summary</a>
                                    @else
                                        <span style="color:#cbd5e1; font-size:0.88rem">Awaiting encoding</span>
                                    @endif
                                @elseif($state === 'encoding')
                                    <a href="{{ route('evaluations.encode', $activity) }}" class="ev-act">Continue</a>
                                @else
                                    <a href="{{ route('evaluations.encode', $activity) }}" class="ev-act">Encode</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

@endsection
