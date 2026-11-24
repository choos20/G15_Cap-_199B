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
            <h3 style="margin:0">Notifications</h3>
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

        <form action="{{ route('notifications.index') }}" method="GET" class="notification-filter">
            <label>
                Search
                <input type="search" name="search" value="{{ $search }}" maxlength="100" placeholder="Search title or message"
                >
            </label>
            <label>
                Show
                <select name="filter">
                    <option value="all" @selected($filter === 'all')>All</option>
                    <option value="unread" @selected($filter === 'unread')>Unread</option>
                    <option value="read" @selected($filter === 'read')>Read</option>
                </select>
            </label>
            <button type="submit" class="notification-filter-apply">Apply</button>
            @if($search !== '' || $filter !== 'all')
                <a href="{{ route('notifications.index') }}" class="notification-filter-clear">Clear</a>
            @endif
        </form>

        @if($notifications->isEmpty())
            <div class="notification-empty">
                @if($search !== '' || $filter !== 'all')
                    No notifications match your search and filter.
                @else
                    No notifications yet.
                @endif
            </div>
        @else
            <div class="notification-list">
                @foreach($notifications as $notification)
                    @php
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
@endsection
