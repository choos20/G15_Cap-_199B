@extends('layouts.admin')

@section('title', 'Activity participation record')
@section('subtitle', 'Approved volunteers and assigned activity participants')

@section('content')
    @php $individual = (bool) $volunteerId && $groups->count() === 1; @endphp

    @include('CELP.head.reports.partials.shell', [
        'reportTitle' => $individual
            ? 'Activity Participation Record — ' . $groups->keys()->first()
            : 'Activity Participation Record (Consolidated)',
    ])

    <form method="GET" class="rp-bar">
        <div class="rp-field">
            <label for="volunteer">View</label>
            <select name="volunteer" id="volunteer">
                <option value="">Consolidated — all participants</option>
                @foreach($volunteers as $v)
                    <option value="{{ $v->id }}" @selected((string) $volunteerId === (string) $v->id)>
                        {{ $v->name }}{{ $v->id_number ? ' (' . $v->id_number . ')' : '' }}
                    </option>
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
            <label for="term">Term</label>
            <select name="term" id="term">
                <option value="">Whole year</option>
                @foreach(\App\Support\AcademicPeriod::TERMS as $k => $label)
                    <option value="{{ $k }}" @selected($f['term'] === $k)>{{ $label }}</option>
                @endforeach
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
        <div class="rp-actions">
            <button type="submit" class="rp-btn rp-btn-primary">Apply</button>
            <a href="{{ route('reports.volunteers', array_merge(request()->query(), ['format' => 'csv'])) }}" class="rp-btn">CSV</a>
            <button type="button" class="rp-btn" onclick="window.print()">Print</button>
        </div>
    </form>

    <div class="card" style="padding:0; overflow:auto">
        @if($rows->isEmpty())
            <div class="rp-empty">
                No activity participation records in this period.
                <div style="font-size:0.86rem; margin-top:8px">Approved volunteer applications and assigned participants appear here.</div>
            </div>
        @else
            <div style="padding:16px 18px; border-bottom:1px solid #f1f5f9" class="rp-noprint">
                <strong style="color:#0f172a">{{ $groups->count() }} {{ \Illuminate\Support\Str::plural('participant', $groups->count()) }}</strong>
                <span class="rp-muted" style="font-size:0.85rem">
                    &middot; {{ $rows->count() }} {{ \Illuminate\Support\Str::plural('participation', $rows->count()) }} recorded
                </span>
            </div>

            @foreach($groups as $name => $items)
                @php $first = $items->first(); @endphp
                <div class="rp-group">
                    <div class="rp-group-head">
                        {{ $name }}
                        <span class="rp-muted" style="font-weight:400">
                            &middot; ID {{ $first['id_number'] ?? '—' }}
                            &middot; {{ $first['department'] ?? 'Department not set' }}
                            &middot; {{ $items->count() }} {{ \Illuminate\Support\Str::plural('activity', $items->count()) }}
                        </span>
                    </div>
                    <table class="rp-table">
                        <thead>
                            <tr>
                                <th style="width:26px">#</th>
                                <th>Activity</th><th>Date</th><th>Initiator</th><th>Participation type</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $i => $r)
                                <tr>
                                    <td class="rp-num rp-muted">{{ $i + 1 }}</td>
                                    <td style="font-weight:600">{{ $r['activity'] }}</td>
                                    <td class="rp-num">
                                        {{ $r['date'] ? \Illuminate\Support\Carbon::parse($r['date'])->format('M j, Y') : '—' }}
                                    </td>
                                    <td>{{ $r['initiator'] }}</td>
                                    <td>{{ $r['participation_type'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        @endif
    </div>

    @if($individual)
        {{-- Certification wording belongs on an individual record, not the consolidated list. --}}
        <div class="rp-sign">
            <p style="font-size:0.84rem; color:#000; line-height:1.7; margin:26px 0 0">
                This certifies that <strong>{{ $groups->keys()->first() }}</strong> was recorded as participating in the
                {{ $rows->count() }} {{ \Illuminate\Support\Str::plural('activity', $rows->count()) }} listed above
                during A.Y. {{ $f['ay'] }}{{ $f['term'] ? ' · ' . \App\Support\AcademicPeriod::termLabel($f['term']) : '' }},
                as recorded in ProjectLink.
            </p>
        </div>
    @endif

    @include('CELP.head.reports.partials.signature', ['blocks' => [
        ['name' => $certifier->name, 'role' => 'Certified by · ' . role_label($certifier->role)],
        ['role' => 'Received by'],
    ]])

    @unless($rows->isEmpty())
        <p class="rp-muted rp-noprint" style="font-size:0.82rem; margin-top:12px">
            Pick a single participant above to produce their individual certified record. Approved volunteers and people enrolled by the activity initiator are included.
        </p>
    @endunless
@endsection
