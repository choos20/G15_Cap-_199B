@extends('layouts.admin')

@section('title', 'Organizations')
@section('subtitle', 'Registered student organizations, colleges and departments')

@section('content')
    <style>
        .od-toolbar { display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:16px; }
        .od-toolbar-form { display:flex; align-items:center; gap:10px; flex:1; flex-wrap:wrap; }
        .od-search { display:flex; align-items:center; gap:8px; flex:1 1 280px; min-width:0; max-width:520px;
                     background:#fff; border:1px solid #e2e8f0; border-radius:999px; padding:8px 14px; }
        .od-search input { border:none; outline:none; font:inherit; font-size:0.92rem; width:100%; min-width:0; background:transparent; color:#0f172a; }
        .od-classification { display:flex; align-items:center; gap:8px; min-height:40px; padding:0 12px;
                             border:1px solid #e2e8f0; border-radius:999px; background:#fff; color:#475569; }
        .od-classification svg { width:16px; height:16px; flex:0 0 16px; color:#64748b; }
        .od-classification select { max-width:195px; border:0; outline:0; background:transparent; color:#334155;
                                   font:inherit; font-size:0.84rem; cursor:pointer; }
        .od-sr-only { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }
        .od-chips { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:18px; }
        .od-chip { display:inline-flex; align-items:center; gap:7px; padding:7px 14px; border-radius:999px; background:#fff;
                   border:1px solid #e2e8f0; color:#334155; text-decoration:none; font-size:0.86rem; font-weight:700; }
        .od-chip:hover { border-color:#cbd5e1; background:#f8fafc; }
        .od-chip.is-on { background:#0b6a4a; border-color:#0b6a4a; color:#fff; }

        .od-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(330px,1fr)); gap:16px; }
        .od-card { display:flex; flex-direction:column; background:#fff; border-radius:16px; padding:22px;
                   box-shadow:0 6px 18px rgba(11,42,42,0.06); text-decoration:none; color:inherit;
                   transition:box-shadow 180ms ease, transform 180ms ease; }
        .od-card:hover { box-shadow:0 14px 30px rgba(11,42,42,0.12); transform:translateY(-2px); }
        .od-head { display:flex; align-items:center; gap:14px; }
        .od-name { font-weight:700; font-size:1.02rem; color:#0f172a; line-height:1.35; }
        .od-sub { color:#64748b; font-size:0.84rem; margin-top:3px; }
        .od-desc { color:#475569; font-size:0.89rem; line-height:1.6; margin-top:14px;
                   overflow:hidden; display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical; min-height:0; }
        .od-tags { display:flex; flex-wrap:wrap; gap:7px; margin-top:14px; }
        .od-tag { background:#ecfdf5; color:#047857; font-size:0.74rem; font-weight:700; padding:4px 10px; border-radius:999px; }
        .od-foot { display:flex; align-items:center; gap:14px; margin-top:auto; padding-top:14px;
                   border-top:1px solid #f1f5f9; color:#94a3b8; font-size:0.8rem; font-weight:600; }
        .od-empty { padding:52px; text-align:center; color:#64748b; }
        .od-thin { color:#cbd5e1; font-style:italic; }
    </style>

    <div class="od-toolbar">
        <form method="GET" action="{{ route('organizations.index') }}" class="od-toolbar-form">
            <label class="od-search">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke="#94a3b8" stroke-width="2"/><path d="M20 20l-3.5-3.5" stroke="#94a3b8" stroke-width="2" stroke-linecap="round"/></svg>
                <input type="search" name="q" value="{{ $term }}" placeholder="Search organization, acronym or college..." aria-label="Search organizations">
            </label>
            @if($advocacy)<input type="hidden" name="advocacy" value="{{ $advocacy }}">@endif
            <label class="od-classification">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 5h16l-6.2 7.1v5.2l-3.6 1.8v-7L4 5Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span class="od-sr-only">Filter by organization classification</span>
                <select name="classification" aria-label="Filter by organization classification" onchange="this.form.submit()">
                    <option value="">All classifications</option>
                    @foreach(\App\Models\User::ORG_CLASSIFICATIONS as $key => $label)
                        <option value="{{ $key }}" @selected($classification === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </form>

        <span style="margin-left:auto; background:#f1f5f9; color:#334155; font-weight:700; padding:8px 16px; border-radius:999px; font-size:0.9rem">
            {{ $organizations->total() }} of {{ $totalCount }} {{ \Illuminate\Support\Str::plural('organization', $totalCount) }}
        </span>
    </div>

    @if($advocacyOptions->isNotEmpty())
        <div class="od-chips">
            <a href="{{ route('organizations.index', array_filter(['q' => $term, 'classification' => $classification])) }}"
               class="od-chip {{ $advocacy ? '' : 'is-on' }}">All</a>
            @foreach($advocacyOptions as $key => $label)
                <a href="{{ route('organizations.index', array_filter(['advocacy' => $key, 'q' => $term, 'classification' => $classification])) }}"
                   class="od-chip {{ $advocacy === $key ? 'is-on' : '' }}">{{ $label }}</a>
            @endforeach
        </div>
    @endif

    @if($organizations->isEmpty())
        <div class="card od-empty">
            <div style="font-size:2rem; margin-bottom:10px">🏛️</div>
            @if($term !== '' || $advocacy || $classification)
                No organizations match this search.
            @else
                No organizations have been registered yet.
            @endif
        </div>
    @else
        <div class="od-grid">
            @foreach($organizations as $o)
                @php $activityCount = $activityCounts[$o->id] ?? 0; @endphp
                <a href="{{ route('organizations.show', $o) }}" class="od-card">
                    <div class="od-head">
                        <x-organization-avatar :organization="$o" :size="52" />
                        <div style="min-width:0">
                            <div class="od-name">{{ $o->organization_name ?? $o->name }}</div>
                            <div class="od-sub">
                                {{ $o->org_acronym ? $o->org_acronym . ' · ' : '' }}{{ $o->org_college ?: role_label($o->role) }}
                            </div>
                        </div>
                    </div>

                    <div class="od-desc">
                        @if($o->org_description)
                            {{ $o->org_description }}
                        @else
                            <span class="od-thin">This organization hasn't written its profile yet.</span>
                        @endif
                    </div>

                    @if($o->advocacyLabels())
                        <div class="od-tags">
                            @foreach($o->advocacyLabels() as $label)
                                <span class="od-tag">{{ $label }}</span>
                            @endforeach
                        </div>
                    @endif

                    <div class="od-foot">
                        <span>{{ $activityCount }} {{ \Illuminate\Support\Str::plural('activity', $activityCount) }}</span>
                        @php $officerCount = $o->officers_count + ($o->role === 'student_org' ? 1 : 0); @endphp
                        <span>{{ $officerCount }} {{ \Illuminate\Support\Str::plural('officer', $officerCount) }}</span>
                        @if($o->org_year_founded)<span>Est. {{ $o->org_year_founded }}</span>@endif
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    @if($organizations->hasPages())
        <div style="margin-top:20px">{{ $organizations->links('partials.pagination') }}</div>
    @endif
@endsection
