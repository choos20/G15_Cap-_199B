@extends('layouts.admin')

@section('title', 'Browse Activities')
@section('subtitle', 'Browse volunteer opportunities and community announcements')

@section('content')
    <style>
        .pf-org { display:inline-flex; align-items:center; gap:8px; color:#0b6a4a; font-weight:600;
                  font-size:0.9rem; text-decoration:none; }
        .pf-org:hover { text-decoration:underline; }
        .pf-filters { display:grid; grid-template-columns:minmax(220px, 1fr) minmax(180px, 260px) auto auto; gap:12px; align-items:end; }
        .pf-filter-field label { display:block; margin-bottom:6px; color:#334155; font-size:0.85rem; font-weight:700; }
        .pf-filter-field input, .pf-filter-field select { width:100%; padding:11px 12px; border:1px solid #cbd5e1; border-radius:10px; background:#fff; color:#0f172a; }
        .pf-filter-btn { display:inline-flex; justify-content:center; align-items:center; padding:11px 16px; border:0; border-radius:10px; background:#0b6a4a; color:#fff; text-decoration:none; font-weight:700; cursor:pointer; }
        .pf-status-tabs { display:flex; flex-wrap:wrap; gap:9px; margin:0 0 16px; }
        .pf-status-tab { display:inline-flex; align-items:center; gap:8px; padding:9px 14px; border:1px solid #dfe7e5; border-radius:999px; background:#fff; color:#475569; font-size:0.87rem; font-weight:700; text-decoration:none; }
        .pf-status-tab.is-active { border-color:#0b6a4a; background:#0b6a4a; color:#fff; }
        .pf-status-count { font-size:0.76rem; opacity:0.78; }
        .pf-status-badge { display:inline-flex; align-items:center; padding:4px 10px; border-radius:999px; font-size:0.76rem; font-weight:800; }
        .pf-results { margin:16px 0; color:#64748b; font-size:0.9rem; }
        .pf-announcements { display:grid; gap:10px; }
        .pf-announcement { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; padding:16px; border:1px solid #e6edf0; border-radius:12px; background:#fff; }
        .pf-announcement-title { color:#172f2a; font-size:.96rem; font-weight:750; }
        .pf-announcement-meta { display:flex; align-items:center; gap:8px; margin-top:6px; color:#71817c; font-size:.82rem; }
        .pf-announcement-description { margin-top:8px; color:#475569; font-size:.88rem; line-height:1.5; }
        .pf-announcement-link { flex-shrink:0; color:#08734e; font-size:.85rem; font-weight:700; text-decoration:none; }
        .pf-announcement-link:hover { text-decoration:underline; }
        @media(max-width:700px) { .pf-filters { grid-template-columns:1fr 1fr; } }
        @media(max-width:460px) { .pf-filters { grid-template-columns:1fr; } }
    </style>

    <form method="GET" action="{{ route('participant.activities') }}" class="card pf-filters" style="padding:18px; margin-bottom:18px;">
        <div class="pf-filter-field">
            <label for="activity-search">Search opportunities</label>
            <input id="activity-search" name="q" type="search" maxlength="100" value="{{ $filters['q'] }}" placeholder="Activity, organizer, place...">
        </div>
        <div class="pf-filter-field">
            <label for="activity-category">Category</label>
            <select id="activity-category" name="category">
                <option value="">All categories</option>
                @foreach($categories as $category)
                    <option value="{{ $category }}" @selected($filters['category'] === $category)>{{ $category }}</option>
                @endforeach
            </select>
        </div>
        <input type="hidden" name="status" value="{{ $filters['status'] }}">
        <button type="submit" class="pf-filter-btn">Search</button>
        @if($filters['q'] !== '' || $filters['category'] !== '' || $filters['status'] !== '')
            <a href="{{ route('participant.activities') }}" class="pf-filter-btn" style="background:#f1f5f9; color:#334155;">Clear</a>
        @endif
    </form>

    <nav class="pf-status-tabs" aria-label="Filter activities by status">
        <a class="pf-status-tab {{ $filters['status'] === '' ? 'is-active' : '' }}"
           href="{{ route('participant.activities', request()->except('status', 'page')) }}">
            All <span class="pf-status-count">{{ $statusCounts['all'] }}</span>
        </a>
        <a class="pf-status-tab {{ $filters['status'] === 'open' ? 'is-active' : '' }}"
           href="{{ route('participant.activities', request()->except('page', 'status') + ['status' => 'open']) }}">
            Open <span class="pf-status-count">{{ $statusCounts['open'] }}</span>
        </a>
        <a class="pf-status-tab {{ $filters['status'] === 'ongoing' ? 'is-active' : '' }}"
           href="{{ route('participant.activities', request()->except('page', 'status') + ['status' => 'ongoing']) }}">
            Ongoing <span class="pf-status-count">{{ $statusCounts['ongoing'] }}</span>
        </a>
        <a class="pf-status-tab {{ $filters['status'] === 'closed' ? 'is-active' : '' }}"
           href="{{ route('participant.activities', request()->except('page', 'status') + ['status' => 'closed']) }}">
            Closed for applications <span class="pf-status-count">{{ $statusCounts['closed'] }}</span>
        </a>
    </nav>

    <div class="pf-results" aria-live="polite">
        Showing {{ $opportunities->firstItem() ?? 0 }}–{{ $opportunities->lastItem() ?? 0 }} of {{ $opportunities->total() }} {{ \Illuminate\Support\Str::plural('activity', $opportunities->total()) }}
    </div>

    @if(session('status'))
        <div class="card" style="border-left:4px solid #16a34a; background:#ecfdf5; color:#134e4a; padding:16px 20px; margin-bottom:20px">
            {{ session('status') }}
        </div>
    @endif

    @if($participantAnnouncements->isNotEmpty())
        <section class="card" style="padding:20px; margin-bottom:18px" aria-labelledby="pf-announcements-title">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:14px">
                <h2 id="pf-announcements-title" style="margin:0; color:#123d33; font-size:1.05rem">Community announcements</h2>
                <span style="color:#64748b; font-size:.82rem">General notices, with optional activity links</span>
            </div>
            <div class="pf-announcements">
                @foreach($participantAnnouncements as $participantAnnouncement)
                    <article class="pf-announcement">
                        <div style="min-width:0">
                            <div class="pf-announcement-title">{{ $participantAnnouncement->title }}</div>
                            <div class="pf-announcement-meta">
                                <x-organization-avatar :organization="$participantAnnouncement->creator" :size="26" />
                                <span>{{ $participantAnnouncement->creator->organization_name ?? $participantAnnouncement->creator->name ?? 'Unknown organization' }}</span>
                                <span>· {{ $participantAnnouncement->category }}</span>
                                @if($participantAnnouncement->project)
                                    <span>· {{ $participantAnnouncement->project->title }}</span>
                                @endif
                            </div>
                            @if($participantAnnouncement->description)
                                <div class="pf-announcement-description">{{ \Illuminate\Support\Str::limit($participantAnnouncement->description, 220) }}</div>
                            @endif
                        </div>
                        <a class="pf-announcement-link" href="{{ route('participant.activities.show', $participantAnnouncement) }}">Read</a>
                    </article>
                @endforeach
            </div>
            <div style="margin-top:14px">{{ $participantAnnouncements->links() }}</div>
        </section>
    @endif

    @if($opportunities->isEmpty())
        <div class="card" style="padding:40px; text-align:center; color:#64748b">
            <div style="font-size:2rem; margin-bottom:10px">{{ $filters['q'] !== '' || $filters['category'] !== '' ? '🔎' : '🔍' }}</div>
            @if($filters['q'] !== '' || $filters['category'] !== '')
                No activities match these filters. Try another search or clear the filters.
            @else
                No activities match this status right now.
            @endif
        </div>
    @else
        <div style="display:grid; gap:14px">
            @foreach($opportunities as $opportunity)
                @php
                    $remaining = $opportunity->remainingSlots();
                    $full = $opportunity->approved_volunteers >= $opportunity->slots_needed;
                    $alreadyApplied = $appliedIds->contains($opportunity->id);
                    $activityStatus = $opportunity->participantApplicationStatus();
                    [$statusBackground, $statusColor] = match ($activityStatus) {
                        'ongoing' => ['#eff6ff', '#1d4ed8'],
                        'closed' => ['#f1f5f9', '#475569'],
                        default => ['#ecfdf5', '#065f46'],
                    };
                @endphp
                <div class="card" style="padding:22px">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap">
                        <div style="flex:1; min-width:260px">
                            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap">
                                <div style="font-weight:700; font-size:1.1rem; color:#0f172a">{{ $opportunity->title }}</div>
                                <span style="font-size:0.78rem; font-weight:700; padding:4px 10px; border-radius:999px; background:#eef2ff; color:#4338ca">{{ $opportunity->category }}</span>
                                <span class="pf-status-badge" style="background:{{ $statusBackground }}; color:{{ $statusColor }}">
                                    {{ $activityStatus === 'closed' ? 'Closed for applications' : ucfirst($activityStatus) }}
                                </span>
                                @if($full)
                                    <span style="font-size:0.78rem; font-weight:700; padding:4px 10px; border-radius:999px; background:#fef2f2; color:#b91c1c">Full</span>
                                @elseif($alreadyApplied)
                                    <span style="font-size:0.78rem; font-weight:700; padding:4px 10px; border-radius:999px; background:#dcfce7; color:#166534">Applied</span>
                                @endif
                            </div>
                            {{-- Organizer logo + link to their About Us profile, so a
                                 volunteer can see who is running the activity. --}}
                            <div style="margin-top:8px">
                                @if($opportunity->creator)
                                    <a href="{{ route('organizations.show', $opportunity->creator) }}" class="pf-org">
                                        <x-organization-avatar :organization="$opportunity->creator" :size="28" />
                                        {{ $opportunity->creator->organization_name ?? $opportunity->creator->name }}
                                    </a>
                                @else
                                    <span style="color:#64748b">Unknown organization</span>
                                @endif
                            </div>
                            @if($opportunity->description)
                                <div style="color:#475569; margin-top:10px; max-width:640px; overflow:hidden; text-overflow:ellipsis; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;">{{ $opportunity->description }}</div>
                            @endif
                            <div style="font-size:0.88rem; color:#64748b; margin-top:12px; display:flex; gap:16px; flex-wrap:wrap">
                                @if($opportunity->event_date)
                                    <span>🗓️ {{ $opportunity->event_date->format('M j, Y g:i A') }}</span>
                                @endif
                                @if($opportunity->event_location)
                                    <span>📍 {{ $opportunity->event_location }}</span>
                                @endif
                                <span>👥 {{ $remaining }} {{ \Illuminate\Support\Str::plural('slot', $remaining) }} left</span>
                                @if($opportunity->application_deadline)
                                    <span>⏰ Apply by {{ $opportunity->application_deadline->timezone('Asia/Manila')->format('M j, Y g:i A') }}</span>
                                @endif
                            </div>
                        </div>
                        <a href="{{ route('participant.activities.show', $opportunity) }}" style="flex-shrink:0; display:inline-flex; align-items:center; gap:6px; padding:12px 20px; border-radius:12px; background:#0b6a4a; color:#fff; text-decoration:none; font-weight:700">
                            View
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
        <div style="margin-top:20px">{{ $opportunities->links() }}</div>
    @endif
@endsection
