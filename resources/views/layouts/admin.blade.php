<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', config('app.name', 'ProjectLink'))</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root{--sidebar:#0b2926;--sidebar-deep:#08211f;--accent:#13b886;--muted:#6b7280;--card:#ffffff;--text:#0b2a2a}
        *{box-sizing:border-box}
        html{min-width:0; overflow-x:hidden}
        body{font-family:Inter,system-ui,Segoe UI,Roboto,Arial; margin:0; background:#f6f7fb; color:var(--text); font-size:15px; min-width:0; overflow-x:hidden}
        body.nav-open{overflow:hidden}
        .app-shell{min-height:100vh}
        .sidebar{position:fixed; inset:0 auto 0 0; z-index:100; width:260px; height:auto; overflow:hidden; background:radial-gradient(ellipse at 15% 0%,#12463d 0%,transparent 42%),linear-gradient(180deg,var(--sidebar) 0%,var(--sidebar-deep) 100%); color:#e6f7f2; padding:22px 16px 16px; display:flex; flex-direction:column; border-right:1px solid rgba(255,255,255,0.06);}
        .brand{display:flex; align-items:center; gap:12px; margin-bottom:18px; padding:0 4px}
        .brand .logo{width:44px; height:44px; background:linear-gradient(135deg,#10b981,#0ea5a0); border-radius:10px; display:flex; align-items:center; justify-content:center; font-weight:800; color:#05201f}
        .nav{flex:1 1 auto; margin-top:10px; padding:0 2px 8px; display:flex; flex-direction:column; gap:5px; min-height:0; overflow-y:auto; scrollbar-width:thin; scrollbar-color:rgba(148,163,184,0.35) transparent}
        .nav a{position:relative; min-height:44px; color:#d5e8e2; text-decoration:none; padding:10px 12px; border:1px solid transparent; border-radius:11px; display:flex; align-items:center; gap:12px; font-weight:600; font-size:0.9rem; line-height:1.3; transition:background 160ms ease,color 160ms ease,border-color 160ms ease,transform 160ms ease}
        .nav a svg{width:19px; height:19px; flex-shrink:0; opacity:0.86; color:#a8c9bd; transition:color 160ms ease,opacity 160ms ease}
        .nav a:hover{background:rgba(255,255,255,0.07); color:#fff; border-color:rgba(255,255,255,0.06); transform:translateX(2px)}
        .nav a:hover svg{color:#d1fae5; opacity:1}
        .nav a.active{background:linear-gradient(100deg,#087c5b 0%,#0c6a51 100%); color:#fff; border-color:rgba(110,231,183,0.26); box-shadow:0 8px 20px rgba(2,8,8,0.18),inset 0 1px 0 rgba(255,255,255,0.1)}
        .nav a.active svg{color:#d1fae5; opacity:1}
        .nav a.active::before{content:''; position:absolute; left:-1px; top:9px; bottom:9px; width:3px; background:#6ee7b7; border-radius:0 4px 4px 0}
        .nav .nav-group{min-width:0}
        .nav .nav-group summary{position:relative; min-height:44px; color:#d5e8e2; text-decoration:none; padding:10px 32px 10px 12px; border:1px solid transparent; border-radius:11px; display:flex; align-items:center; gap:12px; font-weight:600; font-size:0.9rem; line-height:1.3; cursor:pointer; list-style:none; transition:background 160ms ease,color 160ms ease,border-color 160ms ease}
        .nav .nav-group summary::-webkit-details-marker{display:none}
        .nav .nav-group summary::after{content:''; position:absolute; right:14px; width:7px; height:7px; border-right:1.5px solid #a8c9bd; border-bottom:1.5px solid #a8c9bd; transform:rotate(45deg) translateY(-2px); transition:transform 160ms ease}
        .nav .nav-group[open] summary::after{transform:rotate(225deg) translate(-1px,-1px)}
        .nav .nav-group summary svg{width:19px; height:19px; flex-shrink:0; opacity:0.86; color:#a8c9bd}
        .nav .nav-group summary:hover{background:rgba(255,255,255,0.07); color:#fff; border-color:rgba(255,255,255,0.06)}
        .nav .nav-group summary:hover svg{color:#d1fae5; opacity:1}
        .nav .nav-group-links{display:grid; gap:3px; margin:4px 0 7px 18px; padding-left:10px; border-left:1px solid rgba(168,201,189,0.28)}
        .nav .nav-group-links a{min-height:36px; padding:8px 10px; color:#bcd3cb; font-size:0.82rem; font-weight:550; border-radius:8px}
        .nav .nav-group-links a svg{width:16px; height:16px; flex:0 0 16px; color:#9ab9ae; opacity:0.9}
        .nav .nav-group-links a:hover svg,.nav .nav-group-links a.active svg{color:#d1fae5; opacity:1}
        .nav .nav-group-links a:hover{transform:none}
        .nav .nav-group-links a.active{background:rgba(8,124,91,0.4); border-color:rgba(110,231,183,0.2); color:#fff; box-shadow:none}
        .nav .nav-group-links a.active::before{top:7px; bottom:7px}
        .sidebar-footer{margin-top:auto; padding-top:14px; border-top:1px solid rgba(255,255,255,0.1); font-size:0.9rem; color:#e6f7f2}
        .profile-avatar-menu{position:relative; display:flex; align-items:center; gap:10px; width:100%; min-width:0; margin-top:18px}
        .profile-avatar-control{position:static; flex:0 0 36px; width:36px; height:36px}
        .profile-avatar-summary{display:flex; align-items:center; justify-content:center; width:36px; height:36px; flex:0 0 36px; padding:0; border:0; border-radius:50%; background:none; color:inherit; cursor:pointer; list-style:none}
        .profile-avatar-summary::-webkit-details-marker{display:none}
        .profile-avatar-summary:focus-visible{outline:2px solid #6ee7b7; outline-offset:4px; border-radius:8px}
        .profile-avatar-dropdown{position:absolute; bottom:calc(100% + 10px); left:0; z-index:40; width:100%; padding:14px; border:1px solid #d6e5df; border-radius:12px; background:#fff; color:#0f172a; box-shadow:0 12px 30px rgba(0,0,0,.2)}
        .profile-avatar-menu__copy{flex:1 1 auto; min-width:0; overflow:hidden; line-height:1.25}
        .profile-avatar-menu__name,.profile-avatar-menu__role{display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap}
        .profile-avatar-menu__name{font-weight:700}
        .profile-avatar-menu__role{margin-top:2px; font-size:.8rem; opacity:.85}
        .profile-avatar-dropdown label{display:block; margin:10px 0 6px; color:#475569; font-size:.78rem; font-weight:700}
        .profile-avatar-dropdown input[type=file]{width:100%; color:#475569; font-size:.75rem}
        .profile-avatar-action{width:100%; margin-top:9px; padding:8px 10px; border:1px solid #0b7656; border-radius:8px; background:#0b7656; color:#fff; font:inherit; font-size:.8rem; font-weight:700; cursor:pointer}
        .profile-avatar-action.is-remove{border-color:#fecaca; background:#fff; color:#b91c1c}
        .profile-avatar-error{margin-top:8px; color:#b91c1c; font-size:.75rem; line-height:1.4}
        .main{min-width:0; min-height:100vh; margin-left:260px; padding:28px}
        .content{min-width:0; max-width:100%; overflow-wrap:anywhere}
        .content :where(input,select,textarea){min-width:0; max-width:100%}
        .content fieldset{min-inline-size:0}
        .content :where(img,video,canvas,iframe){max-width:100%}
        .topbar{display:flex; align-items:center; justify-content:space-between; margin-bottom:18px; gap:14px; flex-wrap:nowrap}
        .topbar .title-group{min-width:0; max-width:none; flex:1 1 auto; flex-wrap:nowrap; overflow:hidden; overflow-wrap:normal}
        .topbar .title-group > *{min-width:0}
        .topbar .title-group h2{flex:0 1 auto; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap}
        .topbar .title-group > div{min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap}
        .topbar input{padding:10px 12px; border-radius:10px; border:1px solid #e6e9ef; min-width:180px; transition:border-color 180ms ease, box-shadow 180ms ease}
        .topbar-actions{display:flex; align-items:center; gap:12px; flex:0 0 auto; flex-wrap:nowrap; min-width:0; margin-left:auto}
        .search-wrapper{position:relative; max-width:360px; width:100%; min-width:0}
        .topbar a.logout{display:inline-flex; align-items:center; gap:8px; color:#0b2a2a; background:#fff; padding:9px 13px; border:1px solid #dce5e2; border-radius:11px; text-decoration:none; font-size:.86rem; font-weight:700; line-height:1; transition:background 180ms ease, border-color 180ms ease, color 180ms ease, box-shadow 180ms ease, transform 180ms ease}
        .topbar a.logout svg{width:18px; height:18px; flex:0 0 18px; color:#087c5b; transition:transform 180ms ease}
        .topbar a.logout:hover{background:#ecfdf5; border-color:#6ee7b7; color:#064134; box-shadow:0 8px 20px rgba(18,184,134,0.16); transform:translateY(-1px)}
        .topbar a.logout:hover svg{transform:translateX(2px)}
        .topbar input:focus{border-color:#12b886; box-shadow:0 0 0 4px rgba(18,184,134,0.12)}
        .cards{display:grid; grid-template-columns:repeat(5,1fr); gap:18px; margin-bottom:22px}
        .card{min-width:0; background:var(--card); padding:18px; border-radius:12px; box-shadow:0 6px 18px rgba(11,42,42,0.06); transition:box-shadow 180ms ease, border-color 180ms ease}
        .card:hover{box-shadow:0 10px 22px rgba(11,42,42,0.1)}
        .chart-row{display:grid; grid-template-columns:2fr 1fr; gap:18px}
        .two-col-panel{display:grid; grid-template-columns:1fr 320px; gap:18px}
        .responsive-grid-2{display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px}
        .button-row{display:flex; flex-wrap:wrap; gap:12px}
        .button-row a{flex:1 1 auto; min-width:180px; transition:box-shadow 180ms ease, border-color 180ms ease, background 180ms ease}
        .button-row a:hover{box-shadow:0 10px 24px rgba(0,0,0,0.08); border-color:#d1f7e0; background:#f8fffa}
        .quick-action-card{display:flex; align-items:center; gap:16px; padding:18px; border-radius:18px; background:#ffffff; color:#0f172a; text-decoration:none; border:1px solid #e5e7eb; box-shadow:0 10px 30px rgba(15,23,42,0.06); transition:transform 180ms ease, box-shadow 180ms ease, border-color 180ms ease, background 180ms ease; min-height:120px;}
        .quick-action-card:hover{transform:translateY(-1px); background:#f8fffa; border-color:#d1fae1; box-shadow:0 14px 32px rgba(18,184,134,0.12)}
        .quick-action-card span{flex-shrink:0;}
        .search-wrapper{position:relative; width:100%; max-width:360px}
        .search-input{width:100%; padding:10px 12px; border-radius:10px; border:1px solid #e6e9ef; outline:none}
        .search-input:focus{border-color:#2563eb; box-shadow:0 0 0 4px rgba(37,99,235,0.08)}
        .search-dropdown{position:absolute; top:100%; left:0; right:0; z-index:30; background:#fff; border:1px solid #e5e7eb; border-radius:14px; box-shadow:0 18px 40px rgba(15,23,42,0.12); margin-top:10px; display:none; max-height:420px; overflow:auto}
        .search-dropdown.active{display:block}
        .search-dropdown .group{padding:14px 0}
        .search-dropdown .group-label{padding:0 16px; font-size:0.88rem; font-weight:700; color:#0f172a; margin-bottom:10px; display:flex; justify-content:space-between; align-items:center}
        .search-dropdown .group-item{padding:12px 16px; display:flex; flex-direction:column; gap:4px; text-decoration:none; color:#0f172a; border-top:1px solid #f1f5f9}
        .search-dropdown .group-item:hover, .search-dropdown .group-item.active{background:#f8fafc}
        .search-dropdown .item-title{font-weight:700}
        .search-dropdown .item-subtitle{font-size:0.9rem; color:#64748b}
        .search-empty{padding:18px 16px; color:#64748b; font-size:0.95rem}
        .search-hint{font-size:0.82rem; color:#475569; opacity:0.85; margin-top:4px}
        .notif-wrapper{position:relative}
        #notif-dropdown{right:0; left:auto; width:340px}
        .mobile-menu-toggle,.mobile-nav-backdrop{display:none}
        @media (max-width:900px){
            .sidebar{width:min(86vw,320px); height:100dvh; min-width:0; overflow-y:auto; padding:18px 16px; transform:translateX(-105%); transition:transform 180ms ease; box-shadow:14px 0 34px rgba(2,8,8,0.22)}
            .sidebar.is-open{transform:translateX(0)}
            .sidebar-footer{order:1; margin:0 0 10px; padding:12px 0; border-top:0; border-bottom:1px solid rgba(255,255,255,0.1)}
            .sidebar-footer .profile-avatar-menu{margin-top:0}
            .profile-avatar-dropdown{top:calc(100% + 10px); bottom:auto; max-height:calc(100dvh - 190px); overflow-y:auto}
            .nav{order:2; overflow:visible}
            .nav .nav-group-links a{min-height:44px}
            .main{width:100%; margin-left:0; padding:18px 16px}
            .mobile-menu-toggle{display:inline-flex; align-items:center; justify-content:center; flex:0 0 auto; width:42px; height:42px; padding:0; border:1px solid #dce5e2; border-radius:11px; background:#fff; color:#0b2a2a; cursor:pointer}
            .mobile-menu-toggle svg{width:21px; height:21px}
            .mobile-nav-backdrop{position:fixed; inset:0; z-index:90; background:rgba(2,8,8,0.42)}
            .mobile-nav-backdrop.is-visible{display:block}
            .topbar{position:sticky; top:0; z-index:50; align-items:center; padding:6px 0 12px; background:#f6f7fb; gap:10px; flex-wrap:nowrap}
            .topbar .title-group{display:flex; align-items:center; min-width:0; max-width:none; flex:1 1 auto; flex-wrap:nowrap}
            .topbar .title-group h2{min-width:0; flex:1 1 auto; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:clamp(1.05rem,4vw,1.35rem)}
            .topbar .title-group > div{display:none}
            .topbar-actions{margin-left:auto; flex:0 0 auto; gap:8px}
            .topbar-actions .notif-wrapper,.topbar-actions .logout{flex:0 0 auto}
            .topbar a.logout{padding:8px 10px; font-size:.82rem}
            .topbar > div{min-width:0}
            .topbar .notif-wrapper{position:static}
            #notif-dropdown{left:0; right:0; width:min(340px,100%); margin-left:auto; max-height:min(420px,calc(100dvh - 110px))}
            .topbar input{min-width:0; width:100%}
            .cards{grid-template-columns:repeat(2,minmax(0,1fr))}
            .chart-row, .two-col-panel{grid-template-columns:1fr}
        }
        @media (max-width:640px){
            .cards{grid-template-columns:1fr}
            .main{padding:16px 12px}
            .sidebar{padding:14px 12px}
            .card{padding:14px}
            .button-row a{min-width:0; width:100%}
            .topbar-actions a.logout{font-size:0; width:42px; height:42px; padding:0; display:inline-flex; align-items:center; justify-content:center}
            .topbar-actions .logout span{display:none}
            .topbar-actions .logout svg{width:20px; height:20px; flex-basis:20px}
            .responsive-grid-2{grid-template-columns:1fr}
            .content :is(input,select,textarea){font-size:1rem}
            .quick-action-card{min-height:88px; padding:14px}
            .content table{display:block; max-width:100%; overflow-x:auto; white-space:nowrap}
        }
        @media (max-width:420px){
            .topbar{gap:8px}
            .topbar .title-group{gap:9px}
            .topbar-actions{gap:6px}
            .topbar-actions > *{flex:0 0 auto}
            .mobile-menu-toggle,.topbar-actions a.logout{width:40px; height:40px}
            .topbar-actions button[aria-label="Notifications"]{width:40px !important; height:40px !important}
        }
    </style>
</head>
<body>
<div class="app-shell">
    <aside class="sidebar" id="mobile-sidebar">
        <div class="brand">
            <img src="{{ asset('images/Logo_nobg.png') }}" alt="ProjectLink logo" style="width:44px; height:44px; border-radius:10px; object-fit:contain; background:#ffffff; padding:6px" />
            <div>
                <div style="font-weight:700">ProjectLink</div>
                <div style="font-size:0.75rem; opacity:0.85">HOLY NAME UNIVERSITY</div>
            </div>
        </div>

        <nav class="nav">
            @php
                $userRole = auth()->user()->role ?? null;
                $dashboardRoute = route(dashboard_route_for(auth()->user()));
                if ($userRole === 'student_org' || $userRole === 'college_department') {
                    $dashboardRoute = route('student_org.dashboard');
                } elseif ($userRole === 'celp_secretary') {
                    $dashboardRoute = route('celp.secretary.dashboard');
                } elseif ($userRole === 'coordinator') {
                    $dashboardRoute = route('celp.coordinator.dashboard');
                } elseif ($userRole === 'csa_director') {
                    $dashboardRoute = route('csa.director.dashboard');
                } elseif ($userRole === 'celp_head') {
                    $dashboardRoute = route('celp.head.dashboard');
                } elseif ($userRole === 'participant') {
                    $dashboardRoute = route('participant.dashboard');
                }
            @endphp

            @unless(in_array($userRole, ['student_org', 'college_department'], true))
                <a href="{{ $dashboardRoute }}" class="{{ request()->routeIs('*dashboard') ? 'active' : '' }}">
                    @if($userRole === 'participant')
                        <x-ui-icon name="home" :size="19" />
                    @else
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="m3 11 9-8 9 8v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
                    @endif
                    <span>Dashboard</span>
                </a>
            @endunless

                @if($userRole === 'participant')
                    @include('PARTICIPANT.sidebar')
                @elseif(in_array($userRole, ['student_org', 'college_department'], true))
                    @include('STUDENT_ORGANIZATION_PRESIDENT.sidebar')
                @elseif($userRole === 'celp_secretary')
                    @include('CELP.secretary.sidebar')
                @elseif($userRole === 'coordinator')
                    @include('CELP.coordinator.sidebar')
                @elseif($userRole === 'celp_head')
                    @include('CELP.head.sidebar')
                @elseif($userRole === 'csa_director')
                    @include('CSA_DIRECTOR.sidebar')
                @else
                    <a href="{{ route('activities.index') }}" class="{{ request()->routeIs('activities.index') || request()->routeIs('activities.show') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2a7 7 0 0 0-7 7c0 5 7 13 7 13s7-8 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 0 0 5z" fill="currentColor"/></svg>
                        <span>Browse Activities</span>
                    </a>
                    @include('partials.nav_organizations')
                    @include('partials.nav_templates')
                    <a href="#">
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 17h3v-6H5v6zm5 0h3v-10h-3v10zm5 0h3v-4h-3v4z" fill="currentColor"/></svg>
                        <span>Reports</span>
                    </a>
                    @include('partials.nav_notifications')
                @endif

                <a href="{{ route('account.security') }}" class="{{ request()->routeIs('account.*', 'login-audit.*') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 3 5 6v5c0 4.4 2.9 8.4 7 10 4.1-1.6 7-5.6 7-10V6l-7-3Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="m9 12 2 2 4-4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span>Security</span>
                </a>
        </nav>

        <div class="sidebar-footer">
            <div class="profile-avatar-menu">
                <details class="profile-avatar-control">
                    <summary class="profile-avatar-summary" aria-label="Change profile image" title="Change profile image">
                        <x-profile-avatar :name="auth()->user()->name" :photo-url="auth()->user()->profilePhotoUrl()" :size="36" />
                    </summary>
                    <div class="profile-avatar-dropdown">
                        @if(session('status'))
                            <div style="margin-top:8px; color:#08734e; font-size:.78rem">{{ session('status') }}</div>
                        @endif
                        @if(auth()->user()->must_change_password)
                            <p style="margin:8px 0 0; color:#64748b; font-size:.78rem; line-height:1.45">Change your temporary password before updating your image.</p>
                        @else
                            <form method="POST" action="{{ route('account.profile-photo.update') }}" enctype="multipart/form-data">
                                @csrf
                                <label for="profile-photo-upload">Personal profile photo</label>
                                <input id="profile-photo-upload" type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp" required>
                                @error('profile_photo')
                                    <div class="profile-avatar-error">{{ $message }}</div>
                                @enderror
                                <button class="profile-avatar-action" type="submit">Save image</button>
                            </form>
                            @if(auth()->user()->profile_photo_path || auth()->user()->profile_photo_data)
                                <form method="POST" action="{{ route('account.profile-photo.destroy') }}" data-confirm
                                      data-confirm-title="Remove profile photo?"
                                      data-confirm-body="Your account will return to its initials avatar."
                                      data-confirm-ok="Remove photo"
                                      data-confirm-cancel="Keep photo">
                                    @csrf
                                    @method('DELETE')
                                    <button class="profile-avatar-action is-remove" type="submit">Remove photo</button>
                                </form>
                            @endif
                            <p style="margin:8px 0 0; color:#64748b; font-size:.73rem; line-height:1.4">JPG, PNG, or WebP; maximum 2 MB. Square images work best.</p>
                        @endif
                    </div>
                </details>
                <span class="profile-avatar-menu__copy">
                    <span class="profile-avatar-menu__name">{{ auth()->user()->name }}</span>
                    <span class="profile-avatar-menu__role">{{ role_label(auth()->user()->role) }}</span>
                </span>
            </div>
        </div>
    </aside>

    <div class="mobile-nav-backdrop" id="mobile-nav-backdrop"></div>

    <main class="main">
        <div class="topbar">
            <div class="title-group" style="display:flex; align-items:center; gap:14px">
                <button type="button" class="mobile-menu-toggle" id="mobile-menu-toggle" aria-label="Open navigation" aria-controls="mobile-sidebar" aria-expanded="false">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                </button>
                <h2 style="margin:0">@yield('title')</h2>
                <div style="color:var(--muted)">@yield('subtitle','')</div>
            </div>
            <div class="topbar-actions">

                <div class="notif-wrapper">
                    <button type="button" id="notif-bell" aria-label="Notifications" aria-controls="notif-dropdown" aria-expanded="false" style="position:relative; width:40px; height:40px; border-radius:10px; border:1px solid #e6e9ef; background:#fff; cursor:pointer; display:flex; align-items:center; justify-content:center;">
                        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 22a2 2 0 0 0 2-2H10a2 2 0 0 0 2 2zm6-6V10c0-3.3-2.7-6-6-6S6 6.7 6 10v6l-2 2v1h16v-1l-2-2z" fill="#0b2a2a"/></svg>
                        @if($navUnreadCount > 0)
                            <span style="position:absolute; top:-4px; right:-4px; min-width:18px; height:18px; padding:0 4px; border-radius:9px; background:#ef4444; color:#fff; font-size:0.7rem; font-weight:700; display:flex; align-items:center; justify-content:center;">{{ $navUnreadCount > 9 ? '9+' : $navUnreadCount }}</span>
                        @endif
                    </button>
                    <div id="notif-dropdown" class="search-dropdown">
                        <div style="padding:14px 16px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f1f5f9;">
                            <strong style="font-size:0.95rem; color:#0f172a;">Notifications</strong>
                            <a href="{{ route('notifications.index') }}" style="font-size:0.85rem; color:#2563eb; text-decoration:none; font-weight:600;">View all</a>
                        </div>
                        @if($navRecentNotifications->isEmpty())
                            <div class="search-empty">No notifications yet.</div>
                        @else
                            @foreach($navRecentNotifications as $notification)
                                <form action="{{ route('notifications.read', $notification->id) }}" method="POST" style="border-top:1px solid #f1f5f9;">
                                    @csrf
                                    <button type="submit" style="width:100%; text-align:left; padding:12px 16px; background:{{ $notification->read_at ? 'transparent' : '#eff6ff' }}; border:none; cursor:pointer; display:flex; gap:8px; align-items:flex-start;">
                                        @unless($notification->read_at)
                                            <span style="width:8px; height:8px; border-radius:50%; background:#2563eb; margin-top:5px; flex-shrink:0;"></span>
                                        @else
                                            <span style="width:8px; flex-shrink:0;"></span>
                                        @endunless
                                        <span>
                                            <span class="item-title" style="display:block">{{ $notification->data['title'] ?? 'Notification' }}</span>
                                            <span class="item-subtitle" style="display:block">{{ \Illuminate\Support\Str::limit($notification->data['body'] ?? '', 70) }}</span>
                                        </span>
                                    </button>
                                </form>
                            @endforeach
                        @endif
                    </div>
                </div>

                <a href="{{ route('logout') }}" class="logout" aria-label="Logout" title="Logout">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M13 5H6.5A1.5 1.5 0 0 0 5 6.5v11A1.5 1.5 0 0 0 6.5 19H13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <path d="M12 12h8m0 0-3.5-3.5M20 12l-3.5 3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span>Logout</span>
                </a>
            </div>
        </div>

        <div class="content">
            @yield('content')
        </div>
    </main>
</div>
<script>
    (() => {
        const menuButton = document.getElementById('mobile-menu-toggle');
        const sidebar = document.getElementById('mobile-sidebar');
        const backdrop = document.getElementById('mobile-nav-backdrop');
        if (!menuButton || !sidebar || !backdrop) return;
        const mobileViewport = window.matchMedia('(max-width:900px)');
        sidebar.inert = mobileViewport.matches;

        const closeMenu = (restoreFocus = false) => {
            sidebar.classList.remove('is-open');
            backdrop.classList.remove('is-visible');
            document.body.classList.remove('nav-open');
            menuButton.setAttribute('aria-expanded', 'false');
            sidebar.inert = mobileViewport.matches;
            if (restoreFocus) menuButton.focus();
        };

        menuButton.addEventListener('click', () => {
            if (sidebar.classList.contains('is-open')) {
                closeMenu();
                return;
            }
            sidebar.inert = false;
            sidebar.classList.add('is-open');
            backdrop.classList.add('is-visible');
            document.body.classList.add('nav-open');
            menuButton.setAttribute('aria-expanded', 'true');
            sidebar.querySelector('a')?.focus();
        });
        backdrop.addEventListener('click', () => closeMenu(true));
        sidebar.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => closeMenu()));
        mobileViewport.addEventListener('change', () => closeMenu());
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && sidebar.classList.contains('is-open')) closeMenu(true);
        });
    })();

    // The global search box was removed from the topbar; its script was deleted
    // with it. Anything added here must null-guard its elements - an uncaught
    // TypeError in this block stops every later handler from registering.

    (() => {
        const bell = document.getElementById('notif-bell');
        const dropdown = document.getElementById('notif-dropdown');
        if (!bell || !dropdown) return;
        const fitDropdown = () => {
            if (!dropdown.classList.contains('active')) return;
            const availableHeight = window.innerHeight - dropdown.getBoundingClientRect().top - 12;
            dropdown.style.maxHeight = Math.max(0, Math.min(420, availableHeight)) + 'px';
        };

        bell.addEventListener('click', (event) => {
            event.stopPropagation();
            const open = dropdown.classList.toggle('active');
            bell.setAttribute('aria-expanded', String(open));
            fitDropdown();
        });
        window.addEventListener('resize', fitDropdown);

        document.addEventListener('click', (event) => {
            if (!event.target.closest('.notif-wrapper')) {
                dropdown.classList.remove('active');
                bell.setAttribute('aria-expanded', 'false');
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && dropdown.classList.contains('active')) {
                dropdown.classList.remove('active');
                bell.setAttribute('aria-expanded', 'false');
                bell.focus();
            }
        });
    })();
</script>

@include('partials.confirm_modal')
</body>
</html>
