@extends('layouts.admin')

@section('title', 'Browse Activities')
@section('subtitle', 'Community extension activities across all organizations')

@section('content')
    <style>
        .ad-toolbar { display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:18px; }
        .ad-form { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
        .ad-search { display:flex; align-items:center; gap:8px; background:#fff; border:1px solid #e2e8f0; border-radius:999px; padding:8px 14px; min-width:280px; }
        .ad-search input { border:none; outline:none; font:inherit; font-size:0.92rem; width:100%; background:transparent; color:#0f172a; }
        .ad-select { background:#fff; border:1px solid #e2e8f0; border-radius:999px; padding:9px 14px; font:inherit; font-size:0.9rem; font-weight:600; color:#334155; cursor:pointer; max-width:260px; }
        .ad-date { background:#fff; border:1px solid #e2e8f0; border-radius:999px; padding:9px 14px; font:inherit; font-size:0.9rem; color:#334155; }
        .ad-clear { color:#2563eb; font-weight:700; font-size:0.86rem; text-decoration:none; }
        .ad-clear:hover { text-decoration:underline; }

        .ad-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(340px,1fr)); gap:16px; }
        .ad-card { background:#fff; border-radius:16px; box-shadow:0 6px 18px rgba(11,42,42,0.06); padding:22px; display:flex; flex-direction:column; }
        .ad-card:hover { box-shadow:0 12px 26px rgba(11,42,42,0.11); }
        .ad-tags { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:10px; }
        .ad-tag { font-size:0.74rem; font-weight:700; padding:4px 10px; border-radius:999px; white-space:nowrap; }
        .ad-title { font-weight:700; font-size:1.06rem; color:#0f172a; line-height:1.4; }
        .ad-org { color:#0b6a4a; font-weight:600; font-size:0.88rem; margin-top:8px; }
        .ad-org-link { display:inline-flex; align-items:center; gap:8px; color:inherit; text-decoration:none; }
        .ad-org-link:hover { text-decoration:underline; }
        .ad-desc { color:#475569; font-size:0.9rem; margin-top:10px; line-height:1.6;
                   overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; }
        .ad-facts { display:flex; flex-wrap:wrap; gap:8px 16px; margin-top:14px; font-size:0.85rem; color:#64748b; }
        .ad-foot { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-top:16px; padding-top:14px; border-top:1px solid #f1f5f9; }
        .ad-view { display:inline-flex; align-items:center; gap:6px; padding:9px 16px; border-radius:10px;
                   background:#0b6a4a; color:#fff; text-decoration:none; font-weight:700; font-size:0.86rem; }
        .ad-view:hover { background:#0f8a61; }
        .ad-empty { padding:52px; text-align:center; color:#64748b; }
    </style>

    <div class="ad-toolbar">
        <form method="GET" action="{{ route('activities.index') }}" class="ad-form">
            <div class="ad-search">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke="#94a3b8" stroke-width="2"/><path d="M20 20l-3.5-3.5" stroke="#94a3b8" stroke-width="2" stroke-linecap="round"/></svg>
                <input type="search" name="q" value="{{ $term }}" placeholder="Search activity, organization, or place..." aria-label="Search activities">
            </div>
            <select name="org" class="ad-select" aria-label="Filter by organization">
                <option value="">All organizations</option>
                @foreach($organizations as $org)
                    <option value="{{ $org->id }}" @selected((string) $orgId === (string) $org->id)>
                        {{ $org->organization_name ?? $org->name }}
                    </option>
                @endforeach
            </select>
            <select name="status" class="ad-select" aria-label="Filter by activity status">
                <option value="">All statuses</option>
                <option value="recruiting" @selected($status === 'recruiting')>Recruiting</option>
                <option value="applications_closed" @selected($status === 'applications_closed')>Applications closed</option>
                <option value="full" @selected($status === 'full')>Full</option>
                <option value="completed" @selected($status === 'completed')>Completed</option>
            </select>
            <input type="date" name="from" class="ad-date" value="{{ $from }}" aria-label="Activity date from">
            <input type="date" name="to" class="ad-date" value="{{ $to }}" aria-label="Activity date to">
            <button type="submit" class="ad-select">Filter</button>
            @if($term !== '' || $orgId || $status !== '' || $from !== '' || $to !== '')
                <a href="{{ route('activities.index') }}" class="ad-clear">Clear</a>
            @endif
        </form>

        <span style="margin-left:auto; background:#f1f5f9; color:#334155; font-weight:700; padding:8px 16px; border-radius:999px; font-size:0.9rem">
            {{ $activities->total() }} of {{ $totalCount }} {{ \Illuminate\Support\Str::plural('activity', $totalCount) }}
        </span>
    </div>

    @if($activities->isEmpty())
        <div class="card ad-empty">
            <div style="font-size:2rem; margin-bottom:10px">🔍</div>
            @if($term !== '' || $orgId || $status !== '' || $from !== '' || $to !== '')
                No activities match these filters.
            @else
                No organization has published an approved activity yet.
            @endif
        </div>
    @else
        <div class="ad-grid">
            @foreach($activities as $activity)
                @php
                    $isRecruiting = $activity->slots_needed !== null;
                    $remaining = $activity->remainingSlots();
                    $deadlinePassed = $activity->application_deadline && $activity->application_deadline->isPast();
                    $isPast = $activity->event_date && $activity->event_date->isPast();
                @endphp
                <div class="ad-card">
                    <div class="ad-tags">
                        @if($activity->category)
                            <span class="ad-tag" style="background:#eef2ff; color:#4338ca">{{ $activity->category }}</span>
                        @endif
                        @if($isPast)
                            <span class="ad-tag" style="background:#f1f5f9; color:#475569">Completed</span>
                        @elseif($isRecruiting && $activity->isFull())
                            <span class="ad-tag" style="background:#fef2f2; color:#b91c1c">Full</span>
                        @elseif($isRecruiting && $deadlinePassed)
                            <span class="ad-tag" style="background:#fff7ed; color:#9a3412">Applications closed</span>
                        @elseif($isRecruiting)
                            <span class="ad-tag" style="background:#dcfce7; color:#166534">Recruiting</span>
                        @endif
                    </div>

                    <div class="ad-title">{{ $activity->title }}</div>
                    {{-- The organizer's logo makes the initiator recognisable at a
                         glance, and links through to their About Us profile. --}}
                    <div class="ad-org">
                        @if($activity->creator)
                            <a href="{{ route('organizations.show', $activity->creator) }}" class="ad-org-link">
                                <x-organization-avatar :organization="$activity->creator" :size="26" />
                                {{ $activity->creator->organization_name ?? $activity->creator->name }}
                            </a>
                        @else
                            Unknown organization
                        @endif
                    </div>

                    @if($activity->description)
                        <div class="ad-desc">{{ $activity->description }}</div>
                    @endif

                    <div class="ad-facts">
                        @if($activity->event_date)
                            <span>🗓️ {{ $activity->event_date->format('M j, Y') }}</span>
                        @endif
                        @if($activity->event_location)
                            <span>📍 {{ \Illuminate\Support\Str::limit($activity->event_location, 34) }}</span>
                        @endif
                        @if($isRecruiting)
                            <span>👥 {{ $activity->approved_volunteers }} of {{ $activity->slots_needed }} volunteers</span>
                        @endif
                    </div>

                    <div class="ad-foot">
                        <span style="color:#94a3b8; font-size:0.8rem">
                            {{ $activity->published_at ? 'Posted ' . $activity->published_at->diffForHumans() : '' }}
                        </span>
                        <a href="{{ route('activities.show', $activity) }}" class="ad-view">
                            View
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if($activities->hasPages())
        <div style="margin-top:20px">
            {{ $activities->links('partials.pagination') }}
        </div>
    @endif
@endsection
