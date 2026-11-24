@extends('layouts.admin')

@section('title', 'Browse Activities')
@section('subtitle', 'Community extension activities across all organizations')

@section('content')
    <style>
        .ad-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(min(340px,100%),1fr)); gap:16px; }
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
        .ad-facts { display:flex; flex-wrap:wrap; gap:8px 16px; margin-top:14px; font-size:0.85rem; color:#52665d; }
        .ad-foot { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-top:16px; padding-top:14px; border-top:1px solid #f1f5f9; }
        .ad-view { display:inline-flex; align-items:center; gap:6px; padding:9px 16px; border-radius:10px;
                   background:#0b6a4a; color:#fff; text-decoration:none; font-weight:700; font-size:0.86rem; }
        .ad-view:hover { background:#0f8a61; }
        .ad-empty { padding:52px; text-align:center; color:#52665d; }
        .ad-fact { display:inline-flex; align-items:center; gap:6px; }
        .ad-fact svg { flex-shrink:0; }
    </style>

    @php
        $hasFilters = $term !== '' || $orgId || $status !== '' || $from !== '' || $to !== '';
        $filterValue = fn ($key, $default = '') => is_scalar(old($key, $default)) ? (string) old($key, $default) : '';
        $today = now('Asia/Manila');
        $dateError = collect(array_merge($errors->get('from'), $errors->get('to')))->unique()->implode(' ');
        $dateSummary = match (true) {
            $from !== '' && $to !== '' && $from === $to => \Illuminate\Support\Carbon::parse($from)->format('M j, Y'),
            $from !== '' && $to !== '' => \Illuminate\Support\Carbon::parse($from)->format('M j, Y').' – '.\Illuminate\Support\Carbon::parse($to)->format('M j, Y'),
            $from !== '' => 'From '.\Illuminate\Support\Carbon::parse($from)->format('M j, Y'),
            $to !== '' => 'Until '.\Illuminate\Support\Carbon::parse($to)->format('M j, Y'),
            default => 'All activity dates',
        };
    @endphp
    <link rel="stylesheet" href="{{ asset('css/activity-directory-filters.css') }}?v={{ filemtime(public_path('css/activity-directory-filters.css')) }}">

    <form method="GET" action="{{ route('activities.index') }}" class="ad-filter-card" aria-labelledby="ad-filter-heading" data-activity-filters>
        <div class="ad-filter-heading">
            <div><span class="ad-filter-icon"><x-ui-icon name="search" :size="19" /></span><h3 id="ad-filter-heading">Find an activity</h3></div>
            <p>Search across published community extension activities.</p>
        </div>
        <div class="ad-filter-fields">
            <div class="ad-filter-field">
                <label for="ad-search">Search activities</label>
                <div class="ad-search-wrap"><x-ui-icon name="search" :size="18" /><input type="search" id="ad-search" name="q" value="{{ $filterValue('q', $term) }}" placeholder="Activity, organization, or place..." autocomplete="off"></div>
            </div>
            <div class="ad-filter-field">
                <label for="ad-organization">Organization</label>
                <select id="ad-organization" name="org">
                    <option value="">All organizations</option>
                    @foreach($organizations as $org)<option value="{{ $org->id }}" @selected($filterValue('org', $orgId) === (string) $org->id)>{{ $org->organization_name ?? $org->name }}</option>@endforeach
                </select>
            </div>
            <div class="ad-filter-field">
                <label for="ad-status">Activity status</label>
                <select id="ad-status" name="status">
                    <option value="">All statuses</option>
                    <option value="recruiting" @selected($filterValue('status', $status) === 'recruiting')>Recruiting</option>
                    <option value="applications_closed" @selected($filterValue('status', $status) === 'applications_closed')>Applications closed</option>
                    <option value="full" @selected($filterValue('status', $status) === 'full')>Full</option>
                    <option value="completed" @selected($filterValue('status', $status) === 'completed')>Completed</option>
                </select>
            </div>
        </div>
        <div class="ad-filter-bottom">
            <fieldset class="ad-date-range" aria-describedby="ad-date-hint">
                <legend><x-ui-icon name="calendar" :size="17" />Activity date range</legend>
                <div class="ad-date-controls">
                    <div class="ad-date-pair">
                        <div class="ad-filter-field">
                            <label for="ad-date-from">From</label>
                            <input type="date" id="ad-date-from" name="from" value="{{ $filterValue('from', $from) }}" aria-describedby="ad-date-hint{{ $dateError !== '' ? ' ad-date-error' : '' }}" @error('from') aria-invalid="true" @enderror>
                        </div>
                        <span class="ad-range-divider" aria-hidden="true">→</span>
                        <div class="ad-filter-field">
                            <label for="ad-date-to">To</label>
                            <input type="date" id="ad-date-to" name="to" value="{{ $filterValue('to', $to) }}" aria-describedby="ad-date-hint{{ $dateError !== '' ? ' ad-date-error' : '' }}" @error('to') aria-invalid="true" @enderror>
                        </div>
                    </div>
                    <div class="ad-date-presets" role="group" aria-label="Date shortcuts">
                        <button type="button" data-date-preset data-from="{{ $today->toDateString() }}" data-to="{{ $today->toDateString() }}" aria-pressed="false">Today</button>
                        <button type="button" data-date-preset data-from="{{ $today->copy()->startOfMonth()->toDateString() }}" data-to="{{ $today->copy()->endOfMonth()->toDateString() }}" aria-pressed="false">This month</button>
                        <button type="button" data-date-clear>Clear dates</button>
                    </div>
                </div>
                <p class="ad-date-hint" id="ad-date-hint">Based on the activity's scheduled date. You may leave either date blank.</p>
                <p class="ad-field-error" id="ad-date-error" role="alert" data-server-error="{{ $dateError !== '' ? '1' : '0' }}" @if($dateError === '') hidden @endif>{{ $dateError ?: 'The To date must be on or after the From date.' }}</p>
            </fieldset>
            <div class="ad-filter-actions">
                <a href="{{ route('activities.index') }}" class="ad-reset">Reset</a>
                <button type="submit" class="ad-apply"><x-ui-icon name="search" :size="17" />Apply filters</button>
            </div>
        </div>
    </form>

    <div class="ad-results-summary" role="status">
        <div><strong>Showing {{ $activities->firstItem() ?? 0 }}–{{ $activities->lastItem() ?? 0 }} of {{ $activities->total() }} {{ \Illuminate\Support\Str::plural('activity', $activities->total()) }}</strong>@if($hasFilters)<span>Filtered from {{ $totalCount }} published {{ \Illuminate\Support\Str::plural('activity', $totalCount) }}</span>@endif</div>
        <span class="ad-date-summary"><x-ui-icon name="calendar" :size="15" />{{ $dateSummary }}</span>
    </div>

    @if($activities->isEmpty())
        <div class="card ad-empty">
            <div style="margin-bottom:12px"><x-ui-icon name="search" :size="30" /></div>
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
                            <span class="ad-fact"><x-ui-icon name="calendar" :size="15" /><time datetime="{{ $activity->event_date->toDateString() }}">{{ $activity->event_date->format('M j, Y') }}</time></span>
                        @endif
                        @if($activity->event_location)
                            <span class="ad-fact"><x-ui-icon name="location" :size="15" />{{ \Illuminate\Support\Str::limit($activity->event_location, 34) }}</span>
                        @endif
                        @if($isRecruiting)
                            <span class="ad-fact"><x-ui-icon name="user" :size="15" />{{ $activity->approved_volunteers }} of {{ $activity->slots_needed }} volunteers</span>
                        @endif
                    </div>

                    <div class="ad-foot">
                        <span style="color:#5b7067; font-size:0.8rem">
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
    <script src="{{ asset('js/activity-directory-filters.js') }}?v={{ filemtime(public_path('js/activity-directory-filters.js')) }}" defer></script>
@endsection
