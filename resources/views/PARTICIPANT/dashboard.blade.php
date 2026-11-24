@extends('layouts.admin')

@section('title', 'Dashboard')
@section('subtitle', 'Your community, at a glance')

@section('content')
    <link rel="stylesheet" href="{{ asset('css/participant-dashboard.css') }}?v={{ filemtime(public_path('css/participant-dashboard.css')) }}">
    <div class="participant-home">
        @if(session('status'))
            <div class="ph-notice" role="status">{{ session('status') }}</div>
        @endif

        <x-dashboard-welcome class="ph-welcome-banner" />

        <section class="ph-stats" aria-label="Your participation summary">
            <a class="ph-stat ph-amber" href="{{ route('participant.applications') }}">
                <span class="ph-stat-icon"><x-ui-icon name="clock" /></span>
                <span class="ph-stat-copy"><span class="ph-stat-label">Pending applications</span><strong>{{ $pendingCount }}</strong></span>
                <x-ui-icon class="ph-stat-arrow" name="chevron-right" :size="16" />
            </a>
            <a class="ph-stat ph-green" href="{{ route('participant.applications') }}">
                <span class="ph-stat-icon"><x-ui-icon name="check-circle" /></span>
                <span class="ph-stat-copy"><span class="ph-stat-label">Approved / enrolled</span><strong>{{ $approvedCount }}</strong></span>
                <x-ui-icon class="ph-stat-arrow" name="chevron-right" :size="16" />
            </a>
            <a class="ph-stat ph-teal" href="{{ route('participant.attendance') }}">
                <span class="ph-stat-icon"><x-ui-icon name="calendar-check" /></span>
                <span class="ph-stat-copy"><span class="ph-stat-label">Activities attended</span><strong>{{ $totalAttendedActivities }}</strong><small>Last 6 months</small></span>
                <x-ui-icon class="ph-stat-arrow" name="chevron-right" :size="16" />
            </a>
            <a class="ph-stat ph-slate" href="#next-activity">
                <span class="ph-stat-icon"><x-ui-icon name="calendar" /></span>
                <span class="ph-stat-copy"><span class="ph-stat-label">Upcoming activities</span><strong>{{ $upcomingCount }}</strong></span>
                <x-ui-icon class="ph-stat-arrow" name="chevron-right" :size="16" />
            </a>
        </section>

        <div class="ph-grid">
            <section class="ph-panel ph-next" id="next-activity" aria-labelledby="ph-next-title">
                <div class="ph-panel-heading">
                    <h3 id="ph-next-title"><x-ui-icon name="calendar" :size="18" /> Your next activity</h3>
                    <span class="ph-tag">{{ $nextEnrolled ? 'Enrolled' : 'Discover something new' }}</span>
                </div>
                @if($nextEnrolled && $nextEnrolled->announcement)
                    @php($nextActivity = $nextEnrolled->announcement)
                    <div class="ph-next-body">
                        <div class="ph-date-tile" aria-label="{{ $nextActivity->event_date?->format('F j, Y') }}">
                            <span>{{ $nextActivity->event_date?->format('M') }}</span><strong>{{ $nextActivity->event_date?->format('d') }}</strong><span>{{ $nextActivity->event_date?->format('D') }}</span>
                        </div>
                        <div class="ph-next-copy">
                            <h4>{{ $nextActivity->title }}</h4>
                            <div class="ph-org"><x-organization-avatar :organization="$nextActivity->creator" :size="26" /><span>{{ $nextActivity->creator?->organization_name ?: $nextActivity->creator?->name ?: 'Organization' }}</span></div>
                            <div class="ph-next-meta"><span><x-ui-icon name="clock" :size="15" /> {{ $nextActivity->event_date?->format('g:i A') }}</span>@if($nextActivity->event_location)<span><x-ui-icon name="location" :size="15" /> {{ $nextActivity->event_location }}</span>@endif</div>
                        </div>
                    </div>
                    <div class="ph-next-footer"><span>You’re enrolled. See the details before you go.</span><a class="ph-button ph-button-light" href="{{ route('participant.activities.show', $nextActivity) }}">View details <x-ui-icon name="arrow-right" :size="16" /></a></div>
                @else
                    <div class="ph-next-body"><span class="ph-next-empty-icon"><x-ui-icon name="calendar" :size="28" /></span><div class="ph-next-copy"><h4>Your next opportunity is waiting.</h4><p>Explore community activities and apply for one that interests you.</p></div></div>
                    <div class="ph-next-footer"><span>Start with an activity that fits you.</span><a class="ph-button ph-button-light" href="{{ route('participant.activities') }}">Find an activity <x-ui-icon name="arrow-right" :size="16" /></a></div>
                @endif
            </section>

            <section class="ph-panel" aria-labelledby="ph-actions-title">
                <div class="ph-panel-heading"><h3 id="ph-actions-title">Quick actions</h3><span class="ph-muted">A shortcut to what you need</span></div>
                <div class="ph-actions">
                    <a class="ph-action" href="{{ route('participant.activities') }}"><span class="ph-action-icon"><x-ui-icon name="search" /></span><span><strong>Find activities</strong><small>Explore opportunities</small></span><x-ui-icon class="ph-action-arrow" name="chevron-right" :size="15" /></a>
                    <a class="ph-action ph-action-scan" href="{{ route('participant.scanner') }}"><span class="ph-action-icon"><x-ui-icon name="qr" /></span><span><strong>Scan QR</strong><small>Check in or check out</small></span><x-ui-icon class="ph-action-arrow" name="chevron-right" :size="15" /></a>
                    <a class="ph-action" href="{{ route('participant.applications') }}"><span class="ph-action-icon"><x-ui-icon name="clipboard" /></span><span><strong>Applications</strong><small>View your progress</small></span><x-ui-icon class="ph-action-arrow" name="chevron-right" :size="15" /></a>
                    <a class="ph-action" href="{{ route('participant.attendance') }}"><span class="ph-action-icon"><x-ui-icon name="calendar-check" /></span><span><strong>My attendance</strong><small>See your records</small></span><x-ui-icon class="ph-action-arrow" name="chevron-right" :size="15" /></a>
                </div>
            </section>

            <section class="ph-panel ph-participation" aria-labelledby="ph-participation-title">
                <div class="ph-panel-heading"><h3 id="ph-participation-title"><x-ui-icon name="chart" :size="19" /> Your participation</h3><a class="ph-link" href="{{ route('participant.attendance') }}">View attendance <x-ui-icon name="arrow-right" :size="16" /></a></div>
                <p class="ph-chart-caption">Activities attended per month <span>Last 6 months</span></p>
                <div class="ph-summary">
                    <span><strong>{{ $totalApplications }}</strong> {{ \Illuminate\Support\Str::plural('application', $totalApplications) }}</span>
                    <span><strong>{{ $organizationsSupported }}</strong> {{ \Illuminate\Support\Str::plural('organization', $organizationsSupported) }} supported</span>
                </div>
                @if($totalAttendedActivities > 0)
                    @php($chartMax = max(1, (int) $participationMonths->max('count')))
                    <div class="ph-chart" role="img" aria-labelledby="ph-participation-title" aria-describedby="ph-chart-summary">
                        <p class="ph-sr-only" id="ph-chart-summary">{{ $totalAttendedActivities }} {{ \Illuminate\Support\Str::plural('activity', $totalAttendedActivities) }} attended over the last six months. @foreach($participationMonths as $month){{ $month['label'] }}: {{ $month['count'] }}{{ $loop->last ? '.' : '; ' }}@endforeach</p>
                        <div class="ph-chart-plot" aria-hidden="true">
                            @foreach($participationMonths as $month)
                                <div class="ph-chart-month" style="--ph-bar:{{ number_format($month['count'] / $chartMax * 100, 2, '.', '') }}%">
                                    <div class="ph-chart-track"><span class="ph-chart-value">{{ $month['count'] }}</span><span class="ph-chart-bar"></span></div>
                                    <span class="ph-chart-label">{{ $month['label'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="ph-empty ph-chart-empty"><span class="ph-empty-icon"><x-ui-icon name="chart" :size="26" /></span><div><h4>Your journey starts here</h4><p>Attend your first activity to build your participation history.</p></div></div>
                @endif
            </section>

            <section class="ph-panel" aria-labelledby="ph-updates-title">
                <div class="ph-panel-heading"><h3 id="ph-updates-title">Recent updates</h3><a class="ph-link" href="{{ route('notifications.index') }}">View all <x-ui-icon name="arrow-right" :size="16" /></a></div>
                @if($dashboardNotifications->isEmpty())
                    <div class="ph-empty"><span class="ph-empty-icon"><x-ui-icon name="bell" :size="23" /></span><div><h4>You’re up to date</h4><p>Application updates and reminders will appear here.</p></div></div>
                @else
                    <ul class="ph-updates">
                        @foreach($dashboardNotifications as $notification)
                            @php
                                $notificationSource = $notificationSources[(string) $notification->id] ?? [
                                    'label' => 'ProjectLink',
                                    'detail' => null,
                                    'initials' => 'PL',
                                    'avatar_url' => null,
                                    'avatar_type' => 'fallback',
                                    'fallback' => true,
                                ];
                                $notificationTitle = $notification->data['title'] ?? 'Notification';
                                $notificationUnread = $notification->read_at === null;
                            @endphp
                            <li><form action="{{ route('notifications.read', $notification->id) }}" method="POST">@csrf<button class="ph-update" type="submit" aria-label="{{ $notificationUnread ? 'Open unread notification: ' : 'Open notification: ' }}{{ $notificationTitle }}"><x-notification-avatar :source="$notificationSource" :size="32" /><span class="ph-update-copy"><strong>{{ $notificationTitle }}</strong><small>{{ $notification->created_at->diffForHumans() }}</small></span>@if($notificationUnread)<span class="ph-unread-dot"><span class="ph-sr-only">Unread</span></span>@endif</button></form></li>
                        @endforeach
                    </ul>
                @endif
            </section>

        </div>

        <section class="ph-panel ph-opportunities-panel {{ $feedPreview->isEmpty() ? 'is-empty' : '' }}" aria-labelledby="ph-opportunities-title">
            <div class="ph-panel-heading"><h3 id="ph-opportunities-title">Open opportunities</h3><a class="ph-link" href="{{ route('participant.activities') }}">Browse all <x-ui-icon name="arrow-right" :size="16" /></a></div>
            @if($feedPreview->isEmpty())
                <div class="ph-empty"><span class="ph-empty-icon"><x-ui-icon name="search" :size="23" /></span><div><h4>No open opportunities right now</h4><p>Check back for new activities from the community.</p></div></div>
            @else
                <ul class="ph-opportunities">
                    @foreach($feedPreview->take(3) as $opportunity)
                        <li><a class="ph-opportunity" href="{{ route('participant.activities.show', $opportunity) }}"><x-organization-avatar :organization="$opportunity->creator" :size="38" /><div class="ph-opportunity-copy"><h4>{{ $opportunity->title }}</h4><p>{{ $opportunity->creator?->organization_name ?: $opportunity->creator?->name ?: 'Organization' }}</p><div class="ph-opportunity-meta">@if($opportunity->event_date)<span><x-ui-icon name="calendar" :size="13" />{{ $opportunity->event_date->format('M j') }}</span>@endif @if($opportunity->slots_needed)<span class="ph-spots">{{ max(0, $opportunity->slots_needed - $opportunity->approved_volunteers) }} spots available</span>@endif</div></div><x-ui-icon name="chevron-right" :size="18" /></a></li>
                    @endforeach
                </ul>
            @endif
        </section>

        @if($participantAnnouncements->isNotEmpty())
            <section class="ph-panel ph-announcements" aria-labelledby="ph-announcements-title"><div class="ph-panel-heading"><h3 id="ph-announcements-title"><x-ui-icon name="file" :size="18" /> Community announcements</h3><a class="ph-link" href="{{ route('participant.activities') }}">Browse all <x-ui-icon name="arrow-right" :size="16" /></a></div><div class="ph-announcement-grid">
                @foreach($participantAnnouncements as $announcement)
                    <a class="ph-announcement" href="{{ route('participant.activities.show', $announcement) }}"><x-organization-avatar :organization="$announcement->creator" :size="30" /><span><strong>{{ $announcement->title }}</strong><small>{{ $announcement->creator?->organization_name ?: $announcement->creator?->name ?: 'Community' }}</small></span><x-ui-icon name="chevron-right" :size="16" /></a>
                @endforeach
            </div></section>
        @endif
    </div>
@endsection
