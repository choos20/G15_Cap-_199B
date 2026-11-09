@extends('layouts.admin')

@section('title', 'Activities by initiator')
@section('subtitle', 'What each organization, college or department carried out')

@section('content')
    @include('CELP.head.reports.partials.shell', ['reportTitle' => 'Activities Conducted by Project Initiator'])

    <form method="GET" class="rp-bar">
        <div class="rp-field">
            <label for="ay">Academic year</label>
            <select name="ay" id="ay">
                @foreach($years as $y)
                    <option value="{{ $y }}" @selected($f['ay'] === $y)>{{ $y }}</option>
                @endforeach
            </select>
        </div>
        <div class="rp-field">
            <label for="term">Term</label>
            <select name="term" id="term">
                <option value="">Whole year</option>
                @foreach(\App\Support\AcademicPeriod::TERMS as $k => $label)
                    <option value="{{ $k }}" @selected($f['term'] === $k)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="rp-field">
            <label for="type">Initiator type</label>
            <select name="type" id="type">
                <option value="">All types</option>
                <option value="student_org" @selected($type === 'student_org')>Student Organization</option>
                <option value="college_department" @selected($type === 'college_department')>College / Department</option>
            </select>
        </div>
        <div class="rp-field">
            <label for="initiator">Organization</label>
            <select name="initiator" id="initiator">
                <option value="">All organizations</option>
                @foreach($initiators as $org)
                    <option value="{{ $org->id }}" @selected((string) $f['initiator'] === (string) $org->id)>{{ $org->organization_name ?? $org->name }}</option>
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
            <a href="{{ route('reports.activities', array_merge(request()->query(), ['format' => 'csv'])) }}" class="rp-btn">CSV</a>
            <button type="button" class="rp-btn" onclick="window.print()">Print</button>
        </div>
    </form>

    <div class="card" style="padding:0; overflow:auto">
        @if($rows->isEmpty())
            <div class="rp-empty">
                No activities fall in this period.
                <div style="font-size:0.86rem; margin-top:8px">Only activities under an approved project are counted.</div>
            </div>
        @else
            <div style="padding:16px 18px; border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:baseline">
                <strong style="color:#0f172a">{{ $rows->count() }} {{ \Illuminate\Support\Str::plural('activity', $rows->count()) }}</strong>
                <span class="rp-muted" style="font-size:0.85rem">{{ $groups->count() }} {{ \Illuminate\Support\Str::plural('initiator', $groups->count()) }}</span>
            </div>

            @foreach($groups as $initiator => $items)
                <div class="rp-group">
                    <div class="rp-group-head">
                        {{ $initiator }}
                        <span class="rp-muted" style="font-weight:400">
                            &middot; {{ $items->first()['type'] }} &middot; {{ $items->count() }} {{ \Illuminate\Support\Str::plural('activity', $items->count()) }}
                        </span>
                    </div>
                    <table class="rp-table">
                        <thead>
                            <tr>
                                <th>Activity title</th><th>Date conducted</th><th>Venue</th>
                                <th>Volunteers</th><th>Attended</th><th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $r)
                                <tr>
                                    <td style="font-weight:600">{{ $r['title'] }}</td>
                                    <td class="rp-num">
                                        @if($r['dated']){{ $r['date']->format('M j, Y') }}
                                        @else<span class="rp-muted">Not scheduled</span>@endif
                                    </td>
                                    <td>{{ $r['venue'] ?? '—' }}</td>
                                    <td class="rp-num">{{ $r['volunteers'] }}</td>
                                    <td class="rp-num">{{ $r['attended'] }}</td>
                                    <td>
                                        <span style="display:inline-flex; padding:4px 10px; border-radius:999px; font-size:0.76rem; font-weight:700;
                                                     background:{{ $r['status'] === 'Conducted' ? '#ecfdf5' : '#eff6ff' }};
                                                     color:{{ $r['status'] === 'Conducted' ? '#047857' : '#1d4ed8' }}">{{ $r['status'] }}</span>
                                    </td>
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
            “Volunteers” counts approved applications; “Attended” counts distinct people with a recorded present scan.
        </p>
    @endunless

    @include('CELP.head.reports.partials.signature', ['blocks' => [
        ['name' => auth()->user()->name, 'role' => 'Prepared by · ' . role_label(auth()->user()->role)],
        ['role' => 'Noted by'],
    ]])
@endsection
