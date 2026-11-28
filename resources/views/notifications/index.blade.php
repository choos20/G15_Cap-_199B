@extends('layouts.admin')

@section('title', 'Notifications')
@section('subtitle', 'All your notifications in one place')

@section('content')
    <style>
        .notification-read-all{padding:10px 16px;border-radius:10px;background:#f8fafc;color:#0f172a;border:1px solid #e2e8f0;font-weight:600;cursor:pointer}
        .notification-read-all:disabled{color:#5b7067;background:#f8fafc;cursor:not-allowed}
    </style>
    <div class="card" style="padding:24px;">
        <div class="notification-page-header">
            <div><h3 style="margin:0">Notifications</h3><span>{{ $unreadCount }} unread</span></div>
            <form action="{{ route('notifications.read_all') }}" method="POST">
                @csrf
                <button type="submit" class="notification-read-all" @disabled($unreadCount === 0) title="{{ $unreadCount === 0 ? ($hasNotifications ? 'All notifications are already read.' : 'No notifications to mark as read.') : 'Mark all unread notifications as read' }}">Mark all as read</button>
            </form>
        </div>

        @if($unreadCount === 0 && $hasNotifications)
            <div class="notification-caught-up" role="status"><span aria-hidden="true">✓</span> You’re all caught up. All notifications have been read.</div>
        @endif

        @if(session('status'))
            <div style="margin-bottom:16px; padding:10px 14px; background:#ecfdf5; color:#134e4a; border-radius:8px">{{ session('status') }}</div>
        @endif

        <div id="notification-results" aria-live="polite">
        <form action="{{ route('notifications.index') }}" method="GET" class="notification-filter" data-live-notification-filters>
            <label>
                Search
                <input type="search" name="search" value="{{ $search }}" maxlength="100" placeholder="Search notifications"
                >
            </label>
            @if($advanced)
            <label>Sender role
                <select name="sender"><option value="all">All senders</option>
                    @foreach($senderOptions as $option)<option value="{{ $option }}" @selected($sender === $option)>{{ $option === 'unknown' ? 'System / Unknown' : role_label($option) }}</option>@endforeach
                </select>
            </label>
            <label>Category
                <select name="category"><option value="all">All categories</option>
                    @foreach($categoryOptions as $option)<option value="{{ $option }}" @selected($category === $option)>{{ ucwords(str_replace('_', ' ', $option)) }}</option>@endforeach
                </select>
            </label>
            <label>Date range
                <select name="date"><option value="all" @selected($date === 'all')>All time</option><option value="today" @selected($date === 'today')>Today</option><option value="7" @selected($date === '7')>Last 7 days</option><option value="30" @selected($date === '30')>Last 30 days</option><option value="custom" @selected($date === 'custom')>Custom</option></select>
            </label>
            <label>From <input type="date" name="from" value="{{ $from }}"></label>
            <label>To <input type="date" name="to" value="{{ $to }}"></label>
            @endif
            <label>
                Show
                <select name="filter">
                    <option value="all" @selected($filter === 'all')>All</option>
                    <option value="unread" @selected($filter === 'unread')>Unread</option>
                    <option value="read" @selected($filter === 'read')>Read</option>
                </select>
            </label>
            <button type="submit" class="notification-filter-apply">Apply</button>
            <a href="{{ route('notifications.index') }}" class="notification-filter-clear" data-notification-reset>Reset filters</a>
        </form>

        <p class="notification-count">{{ $notifications->total() }} matching notifications</p>

        @if($notifications->isEmpty())
            <div class="notification-empty">
                @if($search !== '' || $filter !== 'all' || $sender !== 'all' || $category !== 'all' || $date !== 'all')
                    No notifications match your search and filter.
                @else
                    No notifications yet.
                @endif
            </div>
        @else
            <div class="notification-list">
                @php $previousGroup = null; @endphp
                @foreach($notifications as $notification)
                    @php
                        $notificationDate = $notification->created_at;
                        $group = $notificationDate->isToday() ? 'Today' : ($notificationDate->isYesterday() ? 'Yesterday' : ($notificationDate->isCurrentWeek() ? 'This week' : 'Earlier'));
                        $notificationSource = $notificationSources[(string) $notification->id] ?? [
                            'label' => 'ProjectLink',
                            'detail' => null,
                            'initials' => 'PL',
                            'avatar_url' => null,
                            'fallback' => true,
                        ];
                        $notificationTitle = $notification->data['title'] ?? 'Notification';
                        $notificationBody = $notification->data['body'] ?? '';
                        $notificationUnread = $notification->read_at === null;
                    @endphp
                    @if($group !== $previousGroup)
                        <h4 class="notification-date-group">{{ $group }}</h4>
                        @php $previousGroup = $group; @endphp
                    @endif
                    <form action="{{ route('notifications.read', $notification->id) }}" method="POST" class="notification-form">
                        @csrf
                        <button type="submit" class="notification-row{{ $notificationUnread ? ' is-unread' : '' }}" aria-label="{{ $notificationUnread ? 'Open unread notification: ' : 'Open notification: ' }}{{ $notificationTitle }}">
                            <x-notification-avatar :source="$notificationSource" :size="44" />
                            <span class="notification-copy">
                                <span class="notification-source-row">
                                    <span class="notification-source{{ ($notificationSource['fallback'] ?? false) ? ' notification-source--fallback' : '' }}">{{ $notificationSource['label'] }}</span>
                                    @if(!empty($notificationSource['detail']))<span class="notification-source-detail">· {{ $notificationSource['detail'] }}</span>@endif
                                    @if($notificationUnread)<span class="notification-new-label">New</span>@endif
                                </span>
                                <span class="notification-title">{{ $notificationTitle }}</span>
                                <span class="notification-body">{{ $notificationBody }}</span>
                            </span>
                            <span class="notification-row__right">
                                <time class="notification-time" datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->diffForHumans() }}</time>
                                @if($notificationUnread)
                                    <span class="notification-unread-dot"><span class="notification-sr-only">Unread</span></span>
                                @endif
                            </span>
                        </button>
                    </form>
                @endforeach
            </div>

            <div style="margin-top:18px">
                {{ $notifications->links('partials.pagination') }}
            </div>
        @endif
        </div>
    </div>
    @if($advanced)
    <script>
    (() => {
        const container = document.getElementById('notification-results');
        let timer, pending;
        const update = async (url, focusName = null) => {
            pending?.abort();
            const controller = new AbortController(); pending = controller;
            container.setAttribute('aria-busy', 'true');
            try {
                const response = await fetch(url, {signal: controller.signal, headers: {'X-Requested-With': 'XMLHttpRequest'}});
                if (!response.ok) throw new Error('Unable to load notifications');
                const page = new DOMParser().parseFromString(await response.text(), 'text/html');
                const replacement = page.getElementById('notification-results');
                if (!replacement) throw new Error('Missing results');
                container.innerHTML = replacement.innerHTML;
                history.replaceState(null, '', url);
                if (focusName) { const field = container.querySelector(`[name="${focusName}"]`); field?.focus(); if (field?.type === 'search') field.setSelectionRange(field.value.length, field.value.length); }
            } catch (error) { if (error.name !== 'AbortError') window.location.assign(url); }
            finally { if (pending === controller) container.removeAttribute('aria-busy'); }
        };
        const apply = (focusName) => { const form = container.querySelector('form[data-live-notification-filters]'); if (!form.reportValidity()) return; update(form.action + '?' + new URLSearchParams(new FormData(form)), focusName); };
        container.addEventListener('submit', event => { if (event.target.matches('[data-live-notification-filters]')) { event.preventDefault(); clearTimeout(timer); apply(); } });
        container.addEventListener('input', event => { if (event.target.name === 'search') { pending?.abort(); clearTimeout(timer); timer = setTimeout(() => apply('search'), 350); } });
        container.addEventListener('change', event => { if (event.target.closest('[data-live-notification-filters]') && event.target.name !== 'search') { clearTimeout(timer); apply(event.target.name); } });
        container.addEventListener('click', event => { const link = event.target.closest('a'); if (link && (link.matches('[data-notification-reset]') || link.closest('nav[role="navigation"]'))) { event.preventDefault(); clearTimeout(timer); update(link.href); } });
    })();
    </script>
    @endif
@endsection
