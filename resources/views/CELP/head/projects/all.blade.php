@extends('layouts.admin')

@section('title', 'All Projects')
@section('subtitle', 'Every project, at every stage')

@section('content')
    @php
        // Tone per pipeline stage: green = cleared, amber = in flight, red = returned.
        $statusTones = [
            'submitted' => ['#eff6ff', '#1d4ed8'],
            'endorsed_by_secretary' => ['#eff6ff', '#1d4ed8'],
            'endorsed_by_coordinator' => ['#fffbeb', '#92400e'],
            'approved' => ['#ecfdf5', '#047857'],
            'csa_cleared' => ['#dcfce7', '#166534'],
            'returned_by_secretary' => ['#fef2f2', '#b91c1c'],
            'returned_by_coordinator' => ['#fef2f2', '#b91c1c'],
            'returned_by_celp_head' => ['#fef2f2', '#b91c1c'],
            'returned_by_csa_director' => ['#fef2f2', '#b91c1c'],
        ];
        $totalAll = $statusCounts->sum();
    @endphp

    <style>
        .ap-toolbar { display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:16px; }
        .ap-search { display:flex; align-items:center; gap:8px; background:#fff; border:1px solid #e2e8f0; border-radius:999px; padding:8px 14px; min-width:280px; }
        .ap-search input { border:none; outline:none; font:inherit; font-size:0.92rem; width:100%; background:transparent; color:#0f172a; }
        .ap-chips { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:18px; }
        .ap-chip { display:inline-flex; align-items:center; gap:7px; padding:7px 14px; border-radius:999px; background:#fff;
                   border:1px solid #e2e8f0; color:#334155; text-decoration:none; font-size:0.86rem; font-weight:700; }
        .ap-chip:hover { border-color:#cbd5e1; background:#f8fafc; }
        .ap-chip.is-on { background:#0b6a4a; border-color:#0b6a4a; color:#fff; }
        .ap-chip-n { color:#94a3b8; font-variant-numeric:tabular-nums; }
        .ap-chip.is-on .ap-chip-n { color:rgba(255,255,255,0.75); }
        .ap-row { display:flex; align-items:center; gap:18px; padding:18px 22px; border-bottom:1px solid #f1f5f9; flex-wrap:wrap; }
        .ap-row:last-child { border-bottom:none; }
        .ap-row:hover { background:#f8fafc; }
        .ap-title { font-weight:700; font-size:1.02rem; color:#0f172a; }
        .ap-meta { color:#64748b; font-size:0.86rem; margin-top:5px; }
        .ap-pill { display:inline-flex; padding:5px 12px; border-radius:999px; font-size:0.78rem; font-weight:700; white-space:nowrap; }
        .ap-open { flex-shrink:0; background:#0b6a4a; color:#fff; padding:9px 16px; border-radius:10px; text-decoration:none; font-weight:700; font-size:0.86rem; }
        .ap-open:hover { background:#0f8a61; }
        .ap-empty { padding:48px; text-align:center; color:#64748b; }
        .ap-note { margin-top:8px; padding:10px 14px; border-radius:10px; background:#fef2f2; border:1px solid #fecaca; color:#b91c1c; font-size:0.84rem; }
    </style>

    <div class="ap-toolbar">
        <form method="GET" action="{{ route('projects.all') }}" class="ap-search">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke="#94a3b8" stroke-width="2"/><path d="M20 20l-3.5-3.5" stroke="#94a3b8" stroke-width="2" stroke-linecap="round"/></svg>
            <input type="search" name="q" value="{{ $term }}" placeholder="Search title or organization..." aria-label="Search projects">
            @if($activeStatus)
                <input type="hidden" name="status" value="{{ $activeStatus }}">
            @endif
        </form>

        <span style="margin-left:auto; background:#f1f5f9; color:#334155; font-weight:700; padding:8px 16px; border-radius:999px; font-size:0.9rem">
            {{ $projects->total() }} {{ \Illuminate\Support\Str::plural('project', $projects->total()) }}
        </span>
    </div>

    <div class="ap-chips">
        <a href="{{ route('projects.all', array_filter(['q' => $term])) }}" class="ap-chip {{ $activeStatus ? '' : 'is-on' }}">
            All <span class="ap-chip-n">{{ $totalAll }}</span>
        </a>
        @foreach(\App\Models\Project::STATUS_LABELS as $key => $label)
            @if(($statusCounts[$key] ?? 0) > 0)
                <a href="{{ route('projects.all', array_filter(['status' => $key, 'q' => $term])) }}"
                   class="ap-chip {{ $activeStatus === $key ? 'is-on' : '' }}">
                    {{ $label }} <span class="ap-chip-n">{{ $statusCounts[$key] }}</span>
                </a>
            @endif
        @endforeach
    </div>

    <div class="card" style="padding:0; overflow:hidden">
        @if($projects->isEmpty())
            <div class="ap-empty">
                <div style="font-size:2rem; margin-bottom:10px">🔍</div>
                @if($term !== '' || $activeStatus)
                    No projects match this search or filter.
                @else
                    No projects have been submitted yet.
                @endif
            </div>
        @else
            @foreach($projects as $p)
                @php [$tBg, $tFg] = $statusTones[$p->status] ?? ['#f1f5f9', '#334155']; @endphp
                <div class="ap-row">
                    <div style="flex:1; min-width:260px">
                        <div class="ap-title">{{ $p->title }}</div>
                        <div class="ap-meta" style="display:flex; align-items:center; gap:8px; flex-wrap:wrap">
                            <x-organization-avatar :organization="$p->creator" :size="28" />
                            <span>{{ $p->creator->organization_name ?? $p->creator->name ?? 'Unknown organization' }}</span>
                            &middot; Submitted {{ $p->created_at->diffForHumans() }}
                            &middot; {{ $p->attachments_count }} {{ \Illuminate\Support\Str::plural('file', $p->attachments_count) }}
                        </div>
                        @if($p->review_comments)
                            <div class="ap-note"><strong>Reviewer note:</strong> {{ $p->review_comments }}</div>
                        @endif
                    </div>

                    <span class="ap-pill" style="background:{{ $tBg }}; color:{{ $tFg }}">
                        {{ \App\Models\Project::statusLabel($p->status) }}
                    </span>

                    <a href="{{ route('projects.show_review', $p) }}" class="ap-open">Open</a>
                </div>
            @endforeach
        @endif
    </div>

    @if($projects->hasPages())
        <div style="margin-top:18px">
            {{ $projects->links('partials.pagination') }}
        </div>
    @endif
@endsection
