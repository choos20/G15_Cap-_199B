@extends('layouts.admin')

@section('title', 'Notifications')
@section('subtitle', 'All your notifications in one place')

@section('content')
    <div class="card" style="padding:24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap; margin-bottom:18px">
            <h3 style="margin:0">Notifications</h3>
            <form action="{{ route('notifications.read_all') }}" method="POST">
                @csrf
                <button type="submit" style="padding:10px 16px; border-radius:10px; background:#f8fafc; color:#0f172a; border:1px solid #e2e8f0; font-weight:600; cursor:pointer;">Mark all as read</button>
            </form>
        </div>

        @if(session('status'))
            <div style="margin-bottom:16px; padding:10px 14px; background:#ecfdf5; color:#134e4a; border-radius:8px">{{ session('status') }}</div>
        @endif

        <form action="{{ route('notifications.index') }}" method="GET" style="display:flex; gap:10px; align-items:end; flex-wrap:wrap; margin-bottom:18px;">
            <label style="display:grid; gap:6px; color:#475569; font-size:0.9rem; font-weight:600;">
                Search
                <input type="search" name="search" value="{{ $search }}" maxlength="100" placeholder="Search title or message"
                       style="min-width:240px; padding:10px 12px; border:1px solid #cbd5e1; border-radius:10px;">
            </label>
            <label style="display:grid; gap:6px; color:#475569; font-size:0.9rem; font-weight:600;">
                Show
                <select name="filter" style="min-width:140px; padding:10px 12px; border:1px solid #cbd5e1; border-radius:10px; background:#fff;">
                    <option value="all" @selected($filter === 'all')>All</option>
                    <option value="unread" @selected($filter === 'unread')>Unread</option>
                    <option value="read" @selected($filter === 'read')>Read</option>
                </select>
            </label>
            <button type="submit" style="padding:10px 16px; border-radius:10px; background:#2563eb; color:#fff; border:1px solid #2563eb; font-weight:600; cursor:pointer;">Apply</button>
            @if($search !== '' || $filter !== 'all')
                <a href="{{ route('notifications.index') }}" style="padding:10px 12px; color:#475569; font-weight:600;">Clear</a>
            @endif
        </form>

        @if($notifications->isEmpty())
            <div style="padding:22px; border-radius:18px; background:#f8fafc; border:1px solid #e2e8f0; color:#334155">
                @if($search !== '' || $filter !== 'all')
                    No notifications match your search and filter.
                @else
                    No notifications yet.
                @endif
            </div>
        @else
            <div style="display:grid; gap:12px">
                @foreach($notifications as $notification)
                    <form action="{{ route('notifications.read', $notification->id) }}" method="POST">
                        @csrf
                        <button type="submit" style="width:100%; text-align:left; border:1px solid {{ $notification->read_at ? '#e5e7eb' : '#93c5fd' }}; background:{{ $notification->read_at ? '#ffffff' : '#eff6ff' }}; border-radius:16px; padding:16px 18px; cursor:pointer; display:flex; gap:12px; align-items:flex-start;">
                            @unless($notification->read_at)
                                <span style="width:10px; height:10px; border-radius:50%; background:#2563eb; margin-top:6px; flex-shrink:0;"></span>
                            @else
                                <span style="width:10px; height:10px; flex-shrink:0;"></span>
                            @endunless
                            <span style="flex:1">
                                <span style="display:block; font-weight:700; color:#0f172a;">{{ $notification->data['title'] ?? 'Notification' }}</span>
                                <span style="display:block; color:#475569; margin-top:4px;">{{ $notification->data['body'] ?? '' }}</span>
                                <span style="display:block; color:#94a3b8; font-size:0.85rem; margin-top:6px;">{{ $notification->created_at->diffForHumans() }}</span>
                            </span>
                        </button>
                    </form>
                @endforeach
            </div>

            <div style="margin-top:18px">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
@endsection
