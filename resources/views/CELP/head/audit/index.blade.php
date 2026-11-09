@extends('layouts.admin')

@section('title', 'Audit Log')
@section('subtitle', 'Who changed what, and when')

@section('content')
    @php
        $actionTones = [
            'account.created' => ['#eff6ff', '#1d4ed8', 'Account created'],
            'account.reactivated' => ['#ecfdf5', '#047857', 'Account reactivated'],
            'account.deactivated' => ['#fef2f2', '#b91c1c', 'Account deactivated'],
            'project.advanced' => ['#ecfdf5', '#047857', 'Project advanced'],
            'project.returned' => ['#fef2f2', '#b91c1c', 'Project returned'],
        ];
    @endphp

    <style>
        .al-toolbar { display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:16px; }
        .al-search { display:flex; align-items:center; gap:8px; background:#fff; border:1px solid #e2e8f0; border-radius:999px; padding:8px 14px; min-width:280px; }
        .al-search input { border:none; outline:none; font:inherit; font-size:0.92rem; width:100%; background:transparent; color:#0f172a; }
        .al-chips { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:18px; }
        .al-chip { display:inline-flex; align-items:center; gap:7px; padding:7px 14px; border-radius:999px; background:#fff;
                   border:1px solid #e2e8f0; color:#334155; text-decoration:none; font-size:0.86rem; font-weight:700; }
        .al-chip:hover { border-color:#cbd5e1; background:#f8fafc; }
        .al-chip.is-on { background:#0b6a4a; border-color:#0b6a4a; color:#fff; }
        .al-chip-n { color:#94a3b8; font-variant-numeric:tabular-nums; }
        .al-chip.is-on .al-chip-n { color:rgba(255,255,255,0.75); }

        .al-entry { display:flex; gap:16px; padding:16px 22px; border-bottom:1px solid #f1f5f9; }
        .al-entry:last-child { border-bottom:none; }
        .al-entry:hover { background:#f8fafc; }
        .al-when { flex-shrink:0; width:150px; color:#64748b; font-size:0.82rem; line-height:1.5; }
        .al-when strong { display:block; color:#0f172a; font-weight:600; }
        .al-body { flex:1; min-width:0; }
        .al-summary { color:#0f172a; font-size:0.94rem; }
        .al-actor { color:#64748b; font-size:0.84rem; margin-top:5px; }
        .al-pill { display:inline-flex; padding:4px 11px; border-radius:999px; font-size:0.74rem; font-weight:700; white-space:nowrap; }
        .al-context { margin-top:9px; padding:9px 13px; border-radius:9px; background:#f8fafc; border:1px solid #e2e8f0; font-size:0.84rem; color:#475569; }
        .al-context dt { display:inline; font-weight:700; color:#334155; }
        .al-context dd { display:inline; margin:0 14px 0 4px; }
        .al-empty { padding:48px; text-align:center; color:#64748b; }
        @media (max-width:640px) { .al-entry { flex-direction:column; gap:8px; } .al-when { width:auto; } }
    </style>

    <div class="al-toolbar">
        <form method="GET" action="{{ route('audit.index') }}" class="al-search">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke="#94a3b8" stroke-width="2"/><path d="M20 20l-3.5-3.5" stroke="#94a3b8" stroke-width="2" stroke-linecap="round"/></svg>
            <input type="search" name="q" value="{{ $term }}" placeholder="Search action, person, or subject..." aria-label="Search audit log">
            @if($scope)
                <input type="hidden" name="scope" value="{{ $scope }}">
            @endif
        </form>

        <span style="margin-left:auto; background:#f1f5f9; color:#334155; font-weight:700; padding:8px 16px; border-radius:999px; font-size:0.9rem">
            {{ $entries->total() }} {{ \Illuminate\Support\Str::plural('entry', $entries->total()) }}
        </span>
    </div>

    <div class="al-chips">
        <a href="{{ route('audit.index', array_filter(['q' => $term])) }}" class="al-chip {{ $scope ? '' : 'is-on' }}">
            All <span class="al-chip-n">{{ $totalEntries }}</span>
        </a>
        <a href="{{ route('audit.index', array_filter(['scope' => 'account', 'q' => $term])) }}" class="al-chip {{ $scope === 'account' ? 'is-on' : '' }}">
            Accounts <span class="al-chip-n">{{ $scopeCounts['account'] }}</span>
        </a>
        <a href="{{ route('audit.index', array_filter(['scope' => 'project', 'q' => $term])) }}" class="al-chip {{ $scope === 'project' ? 'is-on' : '' }}">
            Projects <span class="al-chip-n">{{ $scopeCounts['project'] }}</span>
        </a>
    </div>

    <div class="card" style="padding:0; overflow:hidden">
        @if($entries->isEmpty())
            <div class="al-empty">
                <div style="font-size:2rem; margin-bottom:10px">📋</div>
                @if($term !== '' || $scope)
                    No entries match this search or filter.
                @else
                    Nothing has been recorded yet. Account and review actions will appear here as they happen.
                @endif
            </div>
        @else
            @foreach($entries as $entry)
                @php [$aBg, $aFg, $aLabel] = $actionTones[$entry->action] ?? ['#f1f5f9', '#334155', $entry->action]; @endphp
                <div class="al-entry">
                    <div class="al-when">
                        <strong>{{ $entry->created_at->format('M j, Y') }}</strong>
                        {{ $entry->created_at->format('g:i A') }}
                        <div style="margin-top:2px">{{ $entry->created_at->diffForHumans() }}</div>
                    </div>

                    <div class="al-body">
                        <span class="al-pill" style="background:{{ $aBg }}; color:{{ $aFg }}">{{ $aLabel }}</span>
                        <div class="al-summary" style="margin-top:8px">{{ $entry->summary }}</div>
                        <div class="al-actor">
                            by <strong style="color:#334155">{{ $entry->actor_name }}</strong>
                            @if($entry->actor_role)
                                ({{ role_label($entry->actor_role) }})
                            @endif
                        </div>

                        @if(!empty($entry->context))
                            <dl class="al-context">
                                @foreach($entry->context as $key => $value)
                                    <dt>{{ ucfirst(str_replace('_', ' ', $key)) }}:</dt>
                                    <dd>{{ is_array($value) ? json_encode($value) : $value }}</dd>
                                @endforeach
                            </dl>
                        @endif
                    </div>
                </div>
            @endforeach
        @endif
    </div>

    @if($entries->hasPages())
        <div style="margin-top:18px">
            {{ $entries->links('partials.pagination') }}
        </div>
    @endif
@endsection
