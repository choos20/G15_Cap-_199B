{{--
    Sidebar Notifications link. Shared by every role so the destination and the
    unread count stay identical to the topbar bell — both read $navUnreadCount
    from the layouts.admin view composer and both open notifications.index.
--}}
<a href="{{ route('notifications.index') }}"
   class="{{ request()->routeIs('notifications.index') ? 'active' : '' }}"
   aria-label="Notifications{{ !empty($navUnreadCount) && $navUnreadCount > 0 ? ', ' . $navUnreadCount . ' unread' : '' }}">
    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 22a2 2 0 0 0 2-2H10a2 2 0 0 0 2 2zm6-6V10c0-3.3-2.7-6-6-6S6 6.7 6 10v6l-2 2v1h16v-1l-2-2z" fill="currentColor"/></svg>
    <span>Notifications</span>
    @if(!empty($navUnreadCount) && $navUnreadCount > 0)
        <span style="margin-left:auto; min-width:20px; height:20px; padding:0 6px; border-radius:999px;
                     background:#ef4444; color:#fff; font-size:0.72rem; font-weight:700;
                     display:inline-flex; align-items:center; justify-content:center; font-variant-numeric:tabular-nums;">
            {{ $navUnreadCount > 99 ? '99+' : $navUnreadCount }}
        </span>
    @endif
</a>
