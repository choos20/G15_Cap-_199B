@php
    $participantNavigation = [
        ['route' => 'participant.activities', 'pattern' => 'participant.activities*', 'label' => 'Browse Activities', 'icon' => 'search'],
        ['route' => 'organizations.index', 'pattern' => 'organizations.*', 'label' => 'Organizations', 'icon' => 'building'],
        ['route' => 'templates.index', 'pattern' => 'templates.*', 'label' => 'Forms & Requirements', 'icon' => 'file'],
        ['route' => 'participant.applications', 'pattern' => 'participant.applications*', 'label' => 'My Applications', 'icon' => 'clipboard'],
        ['route' => 'participant.scanner', 'pattern' => 'participant.scanner*', 'label' => 'Scanner', 'icon' => 'qr'],
        ['route' => 'participant.attendance', 'pattern' => 'participant.attendance', 'label' => 'My Attendance', 'icon' => 'calendar-check'],
        ['route' => 'notifications.index', 'pattern' => 'notifications.index', 'label' => 'Notifications', 'icon' => 'bell'],
    ];
@endphp
@foreach($participantNavigation as $item)
    <a href="{{ route($item['route']) }}" class="{{ request()->routeIs($item['pattern']) ? 'active' : '' }}" @if($item['route'] === 'notifications.index' && ($navUnreadCount ?? 0) > 0) aria-label="Notifications, {{ $navUnreadCount }} unread" @endif>
        <x-ui-icon :name="$item['icon']" :size="19" />
        <span>{{ $item['label'] }}</span>
        @if($item['route'] === 'notifications.index' && ($navUnreadCount ?? 0) > 0)
            <span style="margin-left:auto;min-width:20px;height:20px;padding:0 6px;border-radius:999px;background:#ef4444;color:#fff;font-size:.72rem;font-weight:700;display:inline-flex;align-items:center;justify-content:center;font-variant-numeric:tabular-nums">{{ $navUnreadCount > 99 ? '99+' : $navUnreadCount }}</span>
        @endif
    </a>
@endforeach