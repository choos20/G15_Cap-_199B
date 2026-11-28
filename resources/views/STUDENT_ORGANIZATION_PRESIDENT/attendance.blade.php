@extends('layouts.admin')

@section('title','Attendance Sessions')
@section('subtitle','Open a session for an approved activity and generate a secure QR check-in link.')

@section('content')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <link rel="stylesheet" href="https://unpkg.com/mapillary-js@4.1.2/dist/mapillary.css" />
    <style>
        .sv-btn {
            margin-top:10px; margin-left:8px; display:inline-flex; align-items:center; gap:8px;
            background:#f8fafc; color:#0f172a; border:1px solid #cbd5e1; border-radius:12px;
            padding:9px 14px; font-weight:700; font-size:0.85rem; cursor:pointer;
        }
        .sv-btn:hover:not(:disabled) { background:#eff6ff; border-color:#93c5fd; }
        .sv-btn:disabled { opacity:0.55; cursor:not-allowed; }
        .sv-modal-backdrop {
            position:fixed; inset:0; background:rgba(15,23,42,0.55); display:none;
            align-items:center; justify-content:center; z-index:3000; padding:20px;
        }
        .sv-modal-backdrop.active { display:flex; }
        .sv-modal { background:#0f172a; border-radius:20px; padding:18px; width:100%; max-width:760px; box-shadow:0 30px 70px rgba(15,23,42,0.4); }
        .sv-modal-header { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px; }
        .sv-modal-header h3 { margin:0; color:#fff; font-size:1.1rem; }
        .sv-modal-close { background:#1e293b; color:#fff; border:1px solid #334155; border-radius:10px; padding:8px 14px; font-weight:700; cursor:pointer; font-size:0.85rem; }
        .sv-viewer-wrap { position:relative; }
        .sv-viewer { width:100%; height:440px; border-radius:14px; overflow:hidden; background:#000; }
        .sv-look-btn {
            position:absolute; top:50%; transform:translateY(-50%);
            width:44px; height:44px; border-radius:50%; border:none;
            background:rgba(15,23,42,0.55); color:#fff; font-size:1.5rem; font-weight:700;
            cursor:pointer; display:flex; align-items:center; justify-content:center; z-index:10;
        }
        .sv-look-btn:hover { background:rgba(15,23,42,0.85); }
        .sv-look-left { left:12px; }
        .sv-look-right { right:12px; }
        .sv-caption { color:#52665d; font-size:0.82rem; margin-top:10px; }
        .sv-modal-note { color:#5b7067; font-size:0.82rem; margin-top:6px; }
        .sv-modal-note a { color:#93c5fd; }
        .map-field { position:relative; margin-bottom:12px; }
        .map-search-wrap { position:relative; }
        .map-search-wrap input { padding-right:44px; }
        .map-search-icon { position:absolute; right:14px; top:50%; transform:translateY(-50%); color:#5b7067; pointer-events:none; }
        .map-search-results {
            display:none;
            position:absolute; top:calc(100% + 6px); left:0; right:0; z-index:2000;
            background:#fff; border:1px solid #e5e7eb; border-radius:14px;
            box-shadow:0 18px 40px rgba(15,23,42,0.16); max-height:280px; overflow:auto;
        }
        .map-search-item { display:flex; align-items:flex-start; gap:10px; padding:12px 14px; cursor:pointer; border-bottom:1px solid #f1f5f9; font-size:0.9rem; color:#334155; line-height:1.4; }
        .map-search-item:last-child { border-bottom:none; }
        .map-search-item:hover, .map-search-item.active { background:#eff6ff; }
        .map-search-item .msi-pin { flex-shrink:0; color:#3b82f6; margin-top:1px; }
        .map-search-item.map-search-empty { color:#5b7067; cursor:default; }
        .map-search-item.map-search-empty:hover { background:transparent; }
        #location-map {
            width:min(100%, 310px);
            min-width:0;
            height:auto;
            aspect-ratio:1 / 1;
            max-height:none;
            min-height:0;
            max-width:310px;
            border-radius:16px;
            margin:12px 0 0;
            border:1px solid #d1d5db;
        }
        .plink-pin svg { filter:drop-shadow(0 5px 8px rgba(15,23,42,0.4)); }
        .leaflet-control-layers { border-radius:12px !important; overflow:hidden; box-shadow:0 10px 25px rgba(15,23,42,0.15) !important; border:none !important; }
        .leaflet-bar { border-radius:10px !important; overflow:hidden; border:none !important; box-shadow:0 6px 16px rgba(15,23,42,0.15) !important; }
        .leaflet-control-attribution {
            max-width:calc(100% - 10px);
            padding:2px 6px !important;
            border-radius:6px 0 0 0;
            background:rgba(255,255,255,0.68) !important;
            color:rgba(15,23,42,0.72) !important;
            box-shadow:0 0 10px rgba(15,23,42,0.06);
            backdrop-filter:blur(2px);
            font-size:9px !important;
            line-height:1.25 !important;
        }
        .leaflet-control-attribution a { color:inherit !important; }
        .map-locate-btn {
            margin-top:10px; display:inline-flex; align-items:center; gap:8px;
            background:#f8fafc; color:#0f172a; border:1px solid #cbd5e1; border-radius:12px;
            padding:9px 14px; font-weight:700; font-size:0.85rem; cursor:pointer;
        }
        .map-locate-btn:hover { background:#eff6ff; border-color:#93c5fd; }
        .map-locate-btn:disabled { opacity:0.6; cursor:progress; }
        .attendance-grid {
            display:grid;
            grid-template-columns:minmax(0,1fr) minmax(0,360px);
            gap:24px;
        }
        .attendance-card {
            min-width:0;
            background:#ffffff;
            border:1px solid #e5e7eb;
            border-radius:28px;
            box-shadow:0 22px 45px rgba(15,23,42,0.08);
            padding:28px;
        }
        .attendance-card h3 {
            margin-top:0;
            margin-bottom:16px;
            font-size:1.4rem;
            color:#0f172a;
        }
        .form-field {
            margin-bottom:20px;
        }
        .form-field label {
            display:block;
            margin-bottom:10px;
            color:#0f172a;
            font-weight:700;
        }
        .form-field input,
        .form-field select {
            width:100%;
            padding:14px 16px;
            border-radius:14px;
            border:1px solid #d1d5db;
            background:#f8fafc;
            color:#0f172a;
            font-size:0.98rem;
        }
        .form-field input:focus,
        .form-field select:focus {
            border-color:#3b82f6;
            box-shadow:0 0 0 4px rgba(59,130,246,0.12);
            outline:none;
        }
        .form-field input.input-error,
        .form-field select.input-error {
            border-color:#dc2626;
        }
        .form-note {
            color:#52665d;
            font-size:0.92rem;
            margin-top:8px;
        }
        .error-text {
            color:#b91c1c;
            font-size:0.9rem;
            margin-top:8px;
        }
        .badge {
            display:inline-flex;
            align-items:center;
            padding:8px 12px;
            border-radius:999px;
            font-size:0.85rem;
            font-weight:700;
        }
        .badge-open { background:#e0f2fe; color:#075985; }
        .badge-closed { background:#fee2e2; color:#991b1b; }
        .banner {
            display:grid;
            gap:16px;
            padding:22px;
            border-radius:24px;
            background:linear-gradient(180deg, #eff6ff 0%, #f8fbff 100%);
            border:1px solid #bfdbfe;
            margin-bottom:24px;
        }
        .session-actions {
            display:flex;
            flex-wrap:wrap;
            gap:10px;
        }
        .btn-primary,
        .btn-secondary,
        .btn-danger {
            border:none;
            border-radius:14px;
            padding:12px 16px;
            font-weight:700;
            cursor:pointer;
            text-decoration:none;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            font-size:0.92rem;
        }
        .btn-primary {
            background:#0fbf91;
            color:#ffffff;
        }
        .btn-secondary {
            background:#f8fafc;
            color:#0f172a;
            border:1px solid #cbd5e1;
        }
        .btn-danger {
            background:#fef2f2;
            color:#991b1b;
            border:1px solid #fecaca;
        }
        .session-strip {
            display:grid;
            grid-template-columns:repeat(2,minmax(0,1fr));
            gap:14px;
            margin-top:18px;
        }
        .session-strip .card {
            background:#ffffff;
            border:1px solid #e5e7eb;
            border-radius:18px;
            padding:16px;
            color:#0f172a;
        }
        .session-list {
            display:grid;
            gap:16px;
            margin-top:16px;
        }
        .session-item {
            border:1px solid #e5e7eb;
            border-radius:20px;
            padding:18px;
            background:#f8fafc;
        }
        .session-item-header {
            display:flex;
            justify-content:space-between;
            align-items:flex-start;
            gap:12px;
            margin-bottom:12px;
        }
        .session-item-title {
            font-weight:700;
            color:#0f172a;
            margin:0 0 6px;
        }
        .session-item-meta {
            color:#52665d;
            font-size:0.9rem;
            display:grid;
            gap:4px;
        }
        .qr-modal-backdrop {
            position:fixed;
            inset:0;
            background:rgba(15,23,42,0.58);
            backdrop-filter:blur(5px);
            display:none;
            align-items:center;
            justify-content:center;
            z-index:1200;
            padding:20px;
        }
        .qr-modal-backdrop.active {
            display:flex;
        }
        .qr-modal {
            background:#ffffff;
            border-radius:24px;
            border:1px solid rgba(16,185,129,0.18);
            width:100%;
            max-width:460px;
            max-height:calc(100dvh - 40px);
            overflow-y:auto;
            box-shadow:0 30px 80px rgba(15,23,42,0.3);
        }
        .qr-modal-header {
            display:flex;
            align-items:flex-start;
            justify-content:space-between;
            gap:16px;
            padding:22px 24px 18px;
            background:linear-gradient(135deg,#063f35 0%,#087c5b 100%);
            color:#ffffff;
        }
        .qr-modal-eyebrow {
            display:inline-flex;
            align-items:center;
            gap:7px;
            margin-bottom:7px;
            color:#bbf7d0;
            font-size:.7rem;
            font-weight:800;
            letter-spacing:.08em;
            text-transform:uppercase;
        }
        .qr-live-dot {
            width:8px;
            height:8px;
            border-radius:50%;
            background:#86efac;
            box-shadow:0 0 0 5px rgba(134,239,172,.16);
            animation:qr-live-pulse 1.8s ease-in-out infinite;
        }
        .qr-modal-header h3 { margin:0; font-size:1.25rem; }
        .qr-modal-close {
            display:inline-flex;
            align-items:center;
            justify-content:center;
            flex:0 0 36px;
            width:36px;
            height:36px;
            border:1px solid rgba(255,255,255,.28);
            border-radius:10px;
            background:rgba(255,255,255,.1);
            color:#ffffff;
            font-size:1.35rem;
            line-height:1;
            cursor:pointer;
        }
        .qr-modal-close:hover { background:rgba(255,255,255,.2); }
        .qr-modal-close:focus-visible,
        .qr-modal-action:focus-visible,
        .qr-modal-link-panel summary:focus-visible,
        .qr-modal-copy:focus-visible { outline:3px solid #6ee7b7; outline-offset:3px; }
        .qr-modal-body { padding:20px 24px 24px; }
        .qr-modal-subtitle { margin:0; color:#475569; font-size:.9rem; line-height:1.45; }
        .qr-modal-status {
            display:flex;
            align-items:center;
            gap:10px;
            margin-top:16px;
            padding:11px 12px;
            border:1px solid #bbf7d0;
            border-radius:13px;
            background:#f0fdf4;
            color:#166534;
        }
        .qr-modal-status.is-ended { border-color:#fecaca; background:#fef2f2; color:#991b1b; }
        .qr-modal-status-dot { width:10px; height:10px; flex:0 0 10px; border-radius:50%; background:#16a34a; box-shadow:0 0 0 5px rgba(22,163,74,.12); }
        .qr-modal-status.is-ended .qr-modal-status-dot { background:#dc2626; box-shadow:0 0 0 5px rgba(220,38,38,.1); }
        .qr-modal-status-copy { display:grid; gap:2px; min-width:0; }
        .qr-modal-status-copy strong { font-size:.82rem; }
        .qr-modal-status-copy span { color:inherit; font-size:.75rem; opacity:.82; }
        .qr-display {
            position:relative;
            display:flex;
            align-items:center;
            justify-content:center;
            width:min(100%,340px);
            aspect-ratio:1;
            margin:20px auto 16px;
            padding:24px;
            border:1px solid #d1fae5;
            border-radius:26px;
            background:#ffffff;
            box-shadow:0 16px 34px rgba(6,95,70,.13),0 0 0 8px #f0fdf4;
        }
        .qr-display::after {
            content:'';
            position:absolute;
            inset:10px;
            border:2px solid rgba(16,185,129,.55);
            border-radius:19px;
            pointer-events:none;
            animation:qr-frame-pulse 2.4s ease-in-out infinite;
        }
        .qr-display img { position:relative; z-index:1; display:block; width:100%; max-width:280px; height:auto; border-radius:4px; image-rendering:pixelated; }
        .qr-corner { position:absolute; z-index:2; width:34px; height:34px; border-color:#059669; border-style:solid; pointer-events:none; }
        .qr-corner--tl { top:15px; left:15px; border-width:4px 0 0 4px; border-radius:13px 0 0 0; }
        .qr-corner--tr { top:15px; right:15px; border-width:4px 4px 0 0; border-radius:0 13px 0 0; }
        .qr-corner--bl { bottom:15px; left:15px; border-width:0 0 4px 4px; border-radius:0 0 0 13px; }
        .qr-corner--br { right:15px; bottom:15px; border-width:0 4px 4px 0; border-radius:0 0 13px 0; }
        .qr-modal-meta { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
        .qr-modal-meta > div { padding:11px 12px; border:1px solid #e2e8f0; border-radius:12px; background:#f8fafc; }
        .qr-modal-meta span,.qr-modal-meta strong { display:block; }
        .qr-modal-meta span { color:#52665d; font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; }
        .qr-modal-meta strong { margin-top:3px; color:#0f172a; font-size:.85rem; }
        .qr-modal-hint { margin:14px 0 0; color:#52665d; font-size:.78rem; line-height:1.5; text-align:center; }
        .qr-modal-actions { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; margin-top:18px; }
        .qr-modal-action { display:inline-flex; align-items:center; justify-content:center; min-height:42px; padding:10px 12px; border-radius:11px; font:inherit; font-size:.82rem; font-weight:700; text-decoration:none; cursor:pointer; }
        .qr-modal-action.primary { border:1px solid #087c5b; background:#087c5b; color:#ffffff; }
        .qr-modal-action.secondary { border:1px solid #cbd5e1; background:#ffffff; color:#0f172a; }
        .qr-modal-action:hover { transform:translateY(-1px); box-shadow:0 8px 18px rgba(15,23,42,.1); }
        .qr-modal-link-panel { margin-top:14px; border-top:1px solid #e2e8f0; padding-top:12px; }
        .qr-modal-link-panel summary { color:#08734e; cursor:pointer; font-size:.78rem; font-weight:700; }
        .qr-modal-link-body { display:flex; align-items:center; gap:8px; margin-top:10px; }
        .qr-modal-link { min-width:0; flex:1 1 auto; overflow:hidden; color:#2563eb; font-size:.75rem; text-overflow:ellipsis; white-space:nowrap; }
        .qr-modal-copy { flex:0 0 auto; padding:7px 9px; border:1px solid #cbd5e1; border-radius:8px; background:#f8fafc; color:#334155; font:inherit; font-size:.72rem; font-weight:700; cursor:pointer; }
        .qr-modal:fullscreen { display:flex; flex-direction:column; width:min(100vw,720px); max-width:none; height:100vh; max-height:none; border-radius:0; }
        .qr-modal:fullscreen .qr-modal-body { overflow-y:auto; }
        .qr-modal:fullscreen .qr-display { width:min(68vh,460px); }
        @keyframes qr-live-pulse { 50% { opacity:.45; transform:scale(.78); } }
        @keyframes qr-frame-pulse { 50% { opacity:.35; transform:scale(.985); } }
        .attendance-grid > * { min-width:0; }
        @media (max-width:1200px) {
            .attendance-grid { grid-template-columns:minmax(0,1fr); }
            .session-strip { grid-template-columns:1fr; }
        }
        @media (max-width:600px) {
            .attendance-card { padding:18px; border-radius:18px; }
            #location-map { width:min(100%, 280px); height:auto; min-height:0; max-width:280px; }
            .sv-viewer { height:clamp(180px,45dvh,320px); }
            .qr-modal-header { padding:18px 18px 16px; }
            .qr-modal-body { padding:18px; }
            .qr-display { padding:18px; border-radius:21px; }
            .qr-corner { width:28px; height:28px; }
            .qr-corner--tl { top:12px; left:12px; }
            .qr-corner--tr { top:12px; right:12px; }
            .qr-corner--bl { bottom:12px; left:12px; }
            .qr-corner--br { right:12px; bottom:12px; }
        }
        @media (prefers-reduced-motion:reduce) {
            .qr-live-dot,.qr-display::after,.qr-modal-action { animation:none; transition:none; }
        }
        .qr-modal, .sv-modal { max-height:calc(100dvh - 40px); overflow-y:auto; }

        /* Attendance workspace refresh: clearer hierarchy, branded surfaces, and icon-led actions. */
        .attendance-page { display:grid; gap:16px; }
        .attendance-hero {
            position:relative; display:flex; align-items:center; justify-content:space-between; gap:24px;
            overflow:hidden; padding:24px 28px; border:1px solid #d5eee2; border-radius:26px;
            background:linear-gradient(118deg,#effaf4 0%,#ffffff 60%,#e4f7ed 100%);
            box-shadow:0 18px 42px rgba(15,118,86,.08);
        }
        .attendance-hero::after {
            content:''; position:absolute; right:-36px; bottom:-70px; width:300px; height:150px;
            border:1px solid rgba(15,148,105,.16); border-radius:50%; transform:rotate(-12deg); pointer-events:none;
        }
        .attendance-hero-copy { position:relative; z-index:1; display:flex; align-items:center; gap:16px; min-width:0; }
        .attendance-hero-icon, .attendance-heading-icon {
            display:grid; place-items:center; flex:0 0 52px; width:52px; height:52px;
            border-radius:16px; background:linear-gradient(145deg,#087c5b,#0fbf91); color:#fff;
            box-shadow:0 10px 20px rgba(8,124,91,.2);
        }
        .attendance-hero-icon svg, .attendance-heading-icon svg { width:26px; height:26px; }
        .attendance-eyebrow, .attendance-section-kicker {
            display:block; margin-bottom:5px; color:#08734e; font-size:.7rem; font-weight:800;
            letter-spacing:.1em; text-transform:uppercase;
        }
        .attendance-hero h1 { margin:0; color:#0b2a2a; font-size:clamp(1.3rem,2.2vw,1.85rem); line-height:1.15; }
        .attendance-hero p { margin:7px 0 0; max-width:650px; color:#58736b; font-size:.9rem; line-height:1.5; }
        .attendance-hero-stats { position:relative; z-index:1; display:grid; grid-template-columns:repeat(3,minmax(78px,1fr)); gap:10px; min-width:280px; }
        .attendance-hero-stat { padding:12px 14px; border:1px solid rgba(166,220,197,.8); border-radius:15px; background:rgba(255,255,255,.72); }
        .attendance-hero-stat span { display:block; color:#6a8178; font-size:.72rem; font-weight:700; }
        .attendance-hero-stat strong { display:block; margin-top:3px; color:#0b2a2a; font-size:1.35rem; }
        .attendance-grid { gap:18px; }
        .attendance-card { border-color:#dce9e3; border-radius:24px; box-shadow:0 18px 40px rgba(15,42,42,.07); padding:22px; }
        .attendance-card-header { display:flex; align-items:flex-start; gap:13px; margin-bottom:16px; }
        .attendance-card-header > div:last-child { min-width:0; }
        .attendance-card h3 { margin:0; color:#0b2a2a; font-size:1.25rem; line-height:1.25; }
        .attendance-card-header .form-note { margin:6px 0 0; max-width:690px; }
        .attendance-heading-icon { flex-basis:42px; width:42px; height:42px; border-radius:13px; box-shadow:none; }
        .attendance-heading-icon svg { width:21px; height:21px; }
        .attendance-form-section { margin-top:16px; padding-top:14px; border-top:1px solid #edf3ef; }
        .attendance-form-section:first-of-type { margin-top:0; padding-top:0; border-top:0; }
        .attendance-form-section-title { display:flex; align-items:center; gap:8px; margin:0 0 10px; color:#173f35; font-size:.92rem; font-weight:800; }
        .attendance-form-section-title svg { color:#087c5b; }
        .form-field { margin-bottom:12px; }
        .form-field label { margin-bottom:7px; color:#1b3c34; font-size:.84rem; }
        .form-field input, .form-field select { padding:12px 14px; border-color:#d7e4de; border-radius:12px; background:#fbfdfc; font-size:.92rem; }
        .form-field input:focus, .form-field select:focus { border-color:#0fbf91; box-shadow:0 0 0 4px rgba(15,191,145,.12); }
        .form-note { color:#71847d; font-size:.8rem; line-height:1.45; }
        .error-text { font-size:.8rem; }
        .session-strip { gap:12px; margin-top:0; }
        .session-strip .form-field { min-width:0; }
        .attendance-location-shell { padding:12px; border:1px solid #dcece4; border-radius:17px; background:#f8fcfa; }
        .attendance-location-details { min-width:0; display:flex; flex-direction:column; gap:10px; }
        .attendance-location-settings { display:grid; grid-template-columns:minmax(0,1fr); gap:10px; min-width:0; }
        .attendance-location-settings .attendance-form-section { margin:0; padding:0; border-top:0; }
        .attendance-location-settings .attendance-form-section-title { margin-bottom:7px; font-size:.82rem; }
        .attendance-location-settings .form-field { margin-bottom:0; }
        .attendance-location-settings .form-note { margin-top:5px; font-size:.72rem; line-height:1.35; }
        .attendance-readiness {
            flex:1 1 auto;
            display:flex;
            flex-direction:column;
            justify-content:center;
            gap:10px;
            min-height:108px;
            padding:14px;
            border:1px solid #cfe9db;
            border-radius:14px;
            background:linear-gradient(145deg,#effbf4,#f8fcfa);
        }
        .attendance-readiness-header { display:flex; align-items:center; gap:9px; }
        .attendance-readiness-icon { display:grid; place-items:center; flex:0 0 30px; width:30px; height:30px; border-radius:10px; background:#d9f5e5; color:#087c5b; }
        .attendance-readiness-heading { min-width:0; }
        .attendance-readiness-heading .attendance-section-kicker { margin-bottom:2px; font-size:.6rem; }
        .attendance-readiness-title { margin:0; color:#153c32; font-size:.82rem; font-weight:800; line-height:1.25; }
        .attendance-readiness-statuses { display:flex; flex-wrap:wrap; gap:6px; }
        .attendance-readiness-status { display:inline-flex; align-items:center; gap:5px; padding:5px 8px; border:1px solid #eadfb8; border-radius:999px; background:#fffdf5; color:#876b1b; font-size:.67rem; font-weight:700; line-height:1.2; }
        .attendance-readiness-status svg { flex:0 0 13px; }
        .attendance-readiness-status.is-ready { border-color:#bce6ce; background:#f5fcf7; color:#08734e; }
        .attendance-readiness-note { margin:0; color:#668076; font-size:.72rem; line-height:1.35; }
        .attendance-location-action { display:flex; align-items:center; justify-content:flex-end; gap:12px; margin-top:12px; padding-top:12px; border-top:1px solid #edf3ef; }
        .attendance-session-rule { display:inline-flex; align-items:center; gap:6px; margin-right:auto; color:#668076; font-size:.72rem; line-height:1.35; }
        .attendance-session-rule svg { flex:0 0 15px; color:#087c5b; }
        .attendance-location-actions { display:flex; flex-wrap:wrap; align-items:center; gap:8px; margin-top:10px; }
        .map-locate-btn, .sv-btn { margin:0; padding:9px 12px; border-color:#cfe2d9; border-radius:10px; background:#fff; color:#17624e; font-size:.78rem; }
        .map-locate-btn:hover:not(:disabled), .sv-btn:hover:not(:disabled) { background:#effaf4; border-color:#8ad5b7; }
        #location-map { width:min(100%, 310px); height:auto; aspect-ratio:1 / 1; margin-top:10px; max-width:310px; min-height:0; border-color:#cfe3d9; border-radius:14px; box-shadow:inset 0 0 0 1px rgba(255,255,255,.7); }
        .attendance-create-actions { margin:0; }
        .attendance-create-actions .btn-primary { min-height:44px; padding:12px 18px; box-shadow:0 10px 18px rgba(8,124,91,.18); }
        .attendance-card.banner { display:block; padding:22px; border-color:#dce9e3; background:linear-gradient(145deg,#f7fcf9,#ffffff); }
        .attendance-how-header { display:flex; align-items:flex-start; gap:12px; margin-bottom:18px; }
        .attendance-how-header h3 { font-size:1.1rem; }
        .attendance-step-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; }
        .attendance-step { position:relative; min-width:0; padding:14px 11px; border:1px solid #dfece5; border-radius:15px; background:#fff; }
        .attendance-step-icon { display:grid; place-items:center; width:32px; height:32px; margin-bottom:10px; border-radius:10px; background:#e9f8ef; color:#087c5b; }
        .attendance-step-icon svg { width:17px; height:17px; }
        .attendance-step-number { display:block; color:#6d827a; font-size:.66rem; font-weight:800; letter-spacing:.08em; text-transform:uppercase; }
        .attendance-step strong { display:block; margin-top:3px; color:#153c32; font-size:.78rem; line-height:1.3; }
        .attendance-step p { margin:5px 0 0; color:#71847d; font-size:.72rem; line-height:1.4; }
        .attendance-sessions-card { padding:22px; }
        .attendance-sessions-header { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:16px; }
        .attendance-sessions-title { display:flex; align-items:center; gap:10px; }
        .attendance-sessions-title .attendance-heading-icon { flex-basis:36px; width:36px; height:36px; border-radius:11px; }
        .attendance-sessions-title .attendance-heading-icon svg { width:18px; height:18px; }
        .attendance-sessions-count { padding:6px 10px; border:1px solid #d6e9df; border-radius:999px; background:#f0faf4; color:#08734e; font-size:.72rem; font-weight:800; }
        .session-list { gap:12px; margin-top:0; }
        .session-item { padding:16px; border-color:#dfece5; border-radius:17px; background:#fbfdfc; }
        .session-item:hover { border-color:#b6dec9; background:#fff; box-shadow:0 10px 25px rgba(8,124,91,.07); }
        .session-item-header { margin-bottom:14px; }
        .session-item-title { margin-bottom:7px; color:#123a31; font-size:.98rem; }
        .session-item-meta { display:flex; flex-wrap:wrap; gap:7px 12px; color:#71847d; font-size:.76rem; }
        .session-item-meta span { display:inline-flex; align-items:center; gap:5px; }
        .session-item-meta svg { width:14px; height:14px; color:#0c9870; }
        .badge { padding:6px 10px; font-size:.7rem; letter-spacing:.02em; }
        .badge-open { background:#e6f8ed; color:#08734e; }
        .badge-closed { background:#fff1f1; color:#b42318; }
        .session-actions { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px; }
        .btn-primary, .btn-secondary, .btn-danger { min-width:0; gap:7px; padding:10px 12px; border-radius:10px; font-size:.78rem; line-height:1.25; text-align:center; transition:transform 160ms ease, box-shadow 160ms ease, border-color 160ms ease, background 160ms ease; }
        .btn-primary svg, .btn-secondary svg, .btn-danger svg { flex:0 0 16px; width:16px; height:16px; }
        .btn-primary:hover, .btn-secondary:hover, .btn-danger:hover { transform:translateY(-1px); box-shadow:0 7px 15px rgba(15,42,42,.08); }
        .btn-primary { background:#087c5b; }
        .btn-primary:hover { background:#06694d; }
        .btn-secondary { border-color:#d2e2da; background:#fff; color:#275146; }
        .btn-secondary:hover { border-color:#8ed6b7; background:#f2fbf6; }
        .btn-danger { border-color:#fecaca; background:#fff7f7; color:#b42318; }
        .btn-danger:hover { background:#feecec; }
        .attendance-empty { display:grid; justify-items:center; gap:8px; padding:30px 18px; border:1px dashed #cfe2d8; border-radius:15px; background:#fbfdfc; text-align:center; color:#71847d; }
        .attendance-empty-icon { display:grid; place-items:center; width:42px; height:42px; border-radius:13px; background:#e9f8ef; color:#087c5b; }
        .attendance-empty strong { color:#214a3e; font-size:.9rem; }
        .attendance-alert { display:flex; align-items:flex-start; gap:10px; padding:14px 16px !important; border:1px solid transparent; border-radius:15px !important; box-shadow:none; }
        .attendance-alert svg { flex:0 0 18px; margin-top:1px; }
        .attendance-alert-success { border-color:#bce6ce !important; background:#effaf4 !important; color:#145c43 !important; }
        .attendance-alert-error { border-color:#fecaca !important; background:#fff5f5 !important; color:#991b1b !important; }
        .attendance-alert-title { margin-bottom:3px; font-weight:800; }
        .map-search-icon { right:13px; color:#789188; }
        .map-search-icon svg { width:17px; height:17px; }
        .map-search-results { border-color:#d9e8e0; border-radius:12px; }
        .map-search-item:hover, .map-search-item.active { background:#effaf4; }
        .map-search-item .msi-pin { display:grid; place-items:center; width:18px; color:#087c5b; }
        .map-search-item .msi-pin svg { width:16px; height:16px; }
        @media (min-width:1101px) { .attendance-grid { grid-template-columns:minmax(0,1.18fr) minmax(360px,.82fr); } }
        @media (min-width:1201px) {
            .attendance-grid > div:last-child { position:sticky; top:16px; align-self:start; }
            .attendance-card > form { display:grid; grid-template-columns:minmax(0,.8fr) minmax(0,1.2fr); column-gap:16px; align-items:start; }
            .attendance-activity-section, .attendance-location-section { grid-column:1 / -1; }
            .attendance-location-shell { display:grid; grid-template-columns:minmax(260px,310px) minmax(0,1fr); gap:12px; align-items:stretch; }
            .attendance-location-shell .map-field { margin-bottom:0; }
            .attendance-location-shell .session-strip { grid-template-columns:1fr; gap:10px; }
            .attendance-location-settings { grid-template-columns:minmax(140px,.58fr) minmax(0,1fr) minmax(0,1fr); gap:10px; }
            .attendance-location-settings .attendance-rules-section { grid-column:1; }
            .attendance-location-settings .attendance-schedule-section { grid-column:2 / -1; }
            .attendance-location-settings .attendance-schedule-section .session-strip { grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
            .attendance-location-shell .session-strip .form-field,
            .attendance-rules-section .form-field,
            .attendance-schedule-section .form-field { margin-bottom:0; }
            .attendance-rules-section .form-note,
            .attendance-schedule-section .form-note { font-size:.72rem; line-height:1.35; }
            .attendance-card.banner { padding:18px; }
            .attendance-how-header { margin-bottom:14px; }
            .attendance-sessions-card { padding:18px; }
            .attendance-grid > div:last-child .attendance-sessions-card { margin-top:14px !important; }
        }
        @media (max-width:1100px) {
            .attendance-grid { grid-template-columns:minmax(0,1fr); }
            .attendance-location-settings { grid-template-columns:minmax(140px,.58fr) minmax(0,1fr) minmax(0,1fr); }
            .attendance-location-settings .attendance-rules-section { grid-column:1; }
            .attendance-location-settings .attendance-schedule-section { grid-column:2 / -1; }
        }
        @media (max-width:760px) {
            .attendance-hero { align-items:flex-start; flex-direction:column; padding:20px; }
            .attendance-hero-stats { width:100%; min-width:0; }
            .attendance-card { padding:20px; }
            .attendance-step-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
            .attendance-location-settings { grid-template-columns:minmax(0,1fr); }
            .attendance-location-settings .attendance-rules-section,
            .attendance-location-settings .attendance-schedule-section { grid-column:auto; }
            .attendance-location-settings .attendance-schedule-section .session-strip { grid-template-columns:minmax(0,1fr); }
            .attendance-location-action { align-items:stretch; flex-direction:column; }
            .attendance-location-action .attendance-create-actions,
            .attendance-location-action .attendance-create-actions .btn-primary { width:100%; }
        }
        @media (max-width:520px) {
            .attendance-hero-copy { align-items:flex-start; }
            .attendance-hero-icon { flex-basis:44px; width:44px; height:44px; border-radius:13px; }
            .attendance-hero-icon svg { width:22px; height:22px; }
            .attendance-hero-stats { gap:7px; }
            .attendance-hero-stat { padding:10px 9px; }
            .attendance-hero-stat strong { font-size:1.15rem; }
            .attendance-sessions-header { align-items:flex-start; flex-direction:column; }
            .session-actions { grid-template-columns:1fr; }
            .session-item-header { align-items:flex-start; flex-direction:column; }
            .session-strip { grid-template-columns:1fr; }
            .attendance-step-grid { gap:8px; }
            .attendance-step { padding:11px 9px; }
        }
    </style>

    @php
        $activeSessionCount = $sessions->where('status', 'open')->count();
        $closedSessionCount = $sessions->where('status', 'closed')->count();
        $availableActivityCount = $approvedProjects->count();
    @endphp

    <div class="attendance-page">
        @if(session('status'))
            <div class="attendance-alert attendance-alert-success" role="status">
                <x-ui-icon name="check-circle" :size="18" />
                <div><div class="attendance-alert-title">Attendance updated</div><div>{{ session('status') }}</div></div>
            </div>
        @endif
        @error('attendance_file')
            <div class="attendance-alert attendance-alert-error" role="alert">
                <x-ui-icon name="settings" :size="18" />
                <div>{{ $message }}</div>
            </div>
        @enderror

        <section class="attendance-hero" aria-labelledby="attendance-workspace-title">
            <div class="attendance-hero-copy">
                <div class="attendance-hero-icon" aria-hidden="true"><x-ui-icon name="calendar-check" :size="26" /></div>
                <div>
                    <span class="attendance-eyebrow">Attendance management</span>
                    <h1 id="attendance-workspace-title">Run smoother attendance sessions</h1>
                    <p>Create a secure QR check-in session, verify participants within the activity radius, and keep every record ready for reporting.</p>
                </div>
            </div>
            <div class="attendance-hero-stats" aria-label="Attendance session summary">
                <div class="attendance-hero-stat"><span>Open sessions</span><strong>{{ $activeSessionCount }}</strong></div>
                <div class="attendance-hero-stat"><span>Closed sessions</span><strong>{{ $closedSessionCount }}</strong></div>
                <div class="attendance-hero-stat"><span>Ready activities</span><strong>{{ $availableActivityCount }}</strong></div>
            </div>
        </section>

        <div class="attendance-grid">
            <div class="attendance-card">
                <form method="post" action="{{ route('student_org.attendance.store') }}">
                    @csrf
                    <div class="attendance-form-section attendance-activity-section">
                        <p class="attendance-form-section-title"><x-ui-icon name="building" :size="16" /> Activity</p>
                        <div class="form-field">
                            <label for="project_id">Approved activity</label>
                            <select id="project_id" name="project_id" class="@error('project_id') input-error @enderror">
                                <option value="">Select activity</option>
                                @foreach($approvedProjects as $project)
                                    <option value="{{ $project->id }}" @selected(old('project_id') == $project->id)>{{ $project->title }}</option>
                                @endforeach
                            </select>
                            @if($approvedProjects->isEmpty())
                                <div class="form-note">All approved activities already have sessions, or none are approved yet.</div>
                            @endif
                            @error('project_id')<div class="error-text">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="attendance-form-section attendance-location-section">
                        <p class="attendance-form-section-title"><x-ui-icon name="location" :size="16" /> Activity location</p>
                        <div class="attendance-location-shell">
                            <div class="form-field map-field">
                                <label for="map-search">Search for a venue, building, or address</label>
                                <div class="map-search-wrap">
                                    <input type="text" id="map-search" name="loc_q_{{ Str::random(8) }}" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" role="combobox" aria-autocomplete="list" aria-expanded="false" placeholder="Search a location...">
                                    <span class="map-search-icon"><x-ui-icon name="search" :size="17" /></span>
                                    <div id="map-search-results" class="map-search-results"></div>
                                </div>
                                <div id="location-map"></div>
                                <div class="attendance-location-actions">
                                    <button type="button" id="use-my-location" class="map-locate-btn"><x-ui-icon name="location" :size="15" /><span>Use my current location</span></button>
                                    <button type="button" id="street-view-btn" class="sv-btn" disabled><x-ui-icon name="desktop" :size="15" /><span>Street-level imagery</span></button>
                                </div>
                                <div class="form-note">Search, click the map, or drag the pin to set the exact activity location.</div>
                            </div>
                            <div class="attendance-location-details">
                                <div class="session-strip attendance-coordinate-fields">
                                    <div class="form-field">
                                        <label for="latitude"><x-ui-icon name="location" :size="14" /> Activity latitude</label>
                                        <input id="latitude" name="latitude" type="text" maxlength="20" placeholder="e.g. 8.4155" value="{{ old('latitude') }}" class="@error('latitude') input-error @enderror">
                                        @error('latitude')<div class="error-text">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="form-field">
                                        <label for="longitude"><x-ui-icon name="location" :size="14" /> Activity longitude</label>
                                        <input id="longitude" name="longitude" type="text" maxlength="20" placeholder="e.g. 124.6069" value="{{ old('longitude') }}" class="@error('longitude') input-error @enderror">
                                        @error('longitude')<div class="error-text">{{ $message }}</div>@enderror
                                    </div>
                                </div>

                                <div class="attendance-location-settings">
                                    <div class="attendance-form-section attendance-rules-section">
                                        <p class="attendance-form-section-title"><x-ui-icon name="qr" :size="16" /> Check-in rules</p>
                                        <div class="form-field">
                                            <label for="radius_meters">Check-in radius</label>
                                            <input id="radius_meters" type="text" value="{{ \App\Models\AttendanceSession::RADIUS_METERS }} meters (fixed)" disabled>
                                            <div class="form-note">Participants must be within {{ \App\Models\AttendanceSession::RADIUS_METERS }} meters of the activity location to check in.</div>
                                        </div>
                                    </div>

                                    <div class="attendance-form-section attendance-schedule-section">
                                        <p class="attendance-form-section-title"><x-ui-icon name="clock" :size="16" /> Session schedule</p>
                                        <div class="session-strip">
                                            <div class="form-field">
                                                <label for="opens_at"><x-ui-icon name="calendar" :size="14" /> Opens at</label>
                                                <input id="opens_at" name="opens_at" type="datetime-local" min="{{ $minOpenAt }}" value="{{ old('opens_at') }}" class="@error('opens_at') input-error @enderror">
                                                <div class="form-note">Today or a future date. Same-day sessions cannot start in the past.</div>
                                                @error('opens_at')<div class="error-text">{{ $message }}</div>@enderror
                                            </div>
                                            <div class="form-field">
                                                <label for="closes_at"><x-ui-icon name="calendar" :size="14" /> Closes at</label>
                                                <input id="closes_at" name="closes_at" type="datetime-local" min="{{ $minCloseAt }}" max="{{ $maxCloseAt }}" value="{{ old('closes_at') }}" class="@error('closes_at') input-error @enderror">
                                                <div class="form-note">Must be after opening and within one year from today.</div>
                                                @error('closes_at')<div class="error-text">{{ $message }}</div>@enderror
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="attendance-readiness" aria-live="polite">
                                    <div class="attendance-readiness-header">
                                        <div class="attendance-readiness-icon" aria-hidden="true"><x-ui-icon name="check-circle" :size="16" /></div>
                                        <div class="attendance-readiness-heading">
                                            <span class="attendance-section-kicker">Session readiness</span>
                                            <p class="attendance-readiness-title" id="attendance-readiness-title">Complete the details below</p>
                                        </div>
                                    </div>
                                    <div class="attendance-readiness-statuses">
                                        <span class="attendance-readiness-status" id="attendance-location-status"><x-ui-icon name="location" :size="13" /><span data-status-text>Location needed</span></span>
                                        <span class="attendance-readiness-status is-ready" id="attendance-radius-status"><x-ui-icon name="check-circle" :size="13" /><span data-status-text>{{ \App\Models\AttendanceSession::RADIUS_METERS }} m radius</span></span>
                                        <span class="attendance-readiness-status" id="attendance-schedule-status"><x-ui-icon name="clock" :size="13" /><span data-status-text>Schedule needed</span></span>
                                    </div>
                                    <p class="attendance-readiness-note" id="attendance-readiness-note">Set the location and session times to enable a verified QR check-in window.</p>
                                </div>

                                <div class="attendance-location-action">
                                    <span class="attendance-session-rule" title="Each approved activity can have one session. You can rotate the QR token or close an open session later.">
                                        <x-ui-icon name="check-circle" :size="15" />
                                        <span>One session per activity</span>
                                    </span>
                                    <div class="attendance-create-actions">
                                        <button type="submit" class="btn-primary" @disabled($approvedProjects->isEmpty())><x-ui-icon name="qr" :size="16" /><span>Open session</span></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const latitude = document.getElementById('latitude');
                    const longitude = document.getElementById('longitude');
                    const opensAt = document.getElementById('opens_at');
                    const closesAt = document.getElementById('closes_at');
                    if (!opensAt || !closesAt) return;

                    const readinessTitle = document.getElementById('attendance-readiness-title');
                    const readinessNote = document.getElementById('attendance-readiness-note');
                    const locationStatus = document.getElementById('attendance-location-status');
                    const scheduleStatus = document.getElementById('attendance-schedule-status');

                    const formatLocalDateTime = (date) => {
                        const pad = (value) => String(value).padStart(2, '0');
                        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
                    };
                    const updateCloseMinimum = () => {
                        const start = opensAt.value ? new Date(opensAt.value) : new Date(@json($minOpenAt));
                        if (Number.isNaN(start.getTime())) return;
                        start.setMinutes(start.getMinutes() + 1);
                        closesAt.min = formatLocalDateTime(start);
                    };

                    const setReadinessStatus = (element, isReady, readyText, pendingText) => {
                        if (!element) return;
                        element.classList.toggle('is-ready', isReady);
                        const label = element.querySelector('[data-status-text]');
                        if (label) label.textContent = isReady ? readyText : pendingText;
                    };

                    const updateReadiness = () => {
                        const hasLocation = Boolean(latitude && latitude.value.trim() && longitude && longitude.value.trim());
                        const hasSchedule = Boolean(opensAt.value && closesAt.value);
                        const isReady = hasLocation && hasSchedule;

                        setReadinessStatus(locationStatus, hasLocation, 'Location set', 'Location needed');
                        setReadinessStatus(scheduleStatus, hasSchedule, 'Schedule set', 'Schedule needed');

                        if (readinessTitle) readinessTitle.textContent = isReady ? 'Ready to open session' : 'Complete the details below';
                        if (readinessNote) readinessNote.textContent = isReady
                            ? 'Everything is set for a verified QR check-in window.'
                            : 'Set the location and session times to enable a verified QR check-in window.';
                    };

                    const refreshSchedule = () => {
                        updateCloseMinimum();
                        updateReadiness();
                    };

                    opensAt.addEventListener('input', refreshSchedule);
                    opensAt.addEventListener('change', refreshSchedule);
                    closesAt.addEventListener('input', updateReadiness);
                    closesAt.addEventListener('change', updateReadiness);
                    latitude?.addEventListener('input', updateReadiness);
                    longitude?.addEventListener('input', updateReadiness);
                    updateCloseMinimum();
                    updateReadiness();
                });
            </script>

            <div>
                <div class="attendance-card banner">
                    <div class="attendance-how-header">
                        <div class="attendance-heading-icon" aria-hidden="true"><x-ui-icon name="clipboard" :size="21" /></div>
                        <div>
                            <span class="attendance-section-kicker">Quick guide</span>
                            <h3>How this works</h3>
                            <p class="form-note">Four simple steps from an approved activity to a verified attendance record.</p>
                        </div>
                    </div>
                    <div class="attendance-step-grid">
                        <div class="attendance-step"><div class="attendance-step-icon"><x-ui-icon name="building" :size="17" /></div><span class="attendance-step-number">Step 1</span><strong>Choose activity</strong><p>Use an approved activity.</p></div>
                        <div class="attendance-step"><div class="attendance-step-icon"><x-ui-icon name="location" :size="17" /></div><span class="attendance-step-number">Step 2</span><strong>Set location</strong><p>Place the {{ \App\Models\AttendanceSession::RADIUS_METERS }}m check-in pin.</p></div>
                        <div class="attendance-step"><div class="attendance-step-icon"><x-ui-icon name="qr" :size="17" /></div><span class="attendance-step-number">Step 3</span><strong>Generate QR</strong><p>Participants scan on-site.</p></div>
                        <div class="attendance-step"><div class="attendance-step-icon"><x-ui-icon name="check-circle" :size="17" /></div><span class="attendance-step-number">Step 4</span><strong>Track attendance</strong><p>Review verified records.</p></div>
                    </div>
                </div>

                <div class="attendance-card attendance-sessions-card" style="margin-top:20px;">
                    <div class="attendance-sessions-header">
                        <div class="attendance-sessions-title">
                            <div class="attendance-heading-icon" aria-hidden="true"><x-ui-icon name="chart" :size="18" /></div>
                            <div><span class="attendance-section-kicker">Your workspace</span><h3>Sessions</h3></div>
                        </div>
                        <span class="attendance-sessions-count">{{ $sessions->count() }} total</span>
                    </div>
                    @if($sessions->isEmpty())
                        <div class="attendance-empty">
                            <div class="attendance-empty-icon"><x-ui-icon name="calendar" :size="20" /></div>
                            <strong>No attendance sessions yet</strong>
                            <span>Open one using the form after an activity is approved.</span>
                        </div>
                    @else
                        <div class="session-list">
                            @foreach($sessions as $session)
                                <div class="session-item">
                                    <div class="session-item-header">
                                        <div>
                                            <p class="session-item-title">{{ $session->project->title }}</p>
                                            <div class="session-item-meta">
                                                <span><x-ui-icon name="calendar" :size="14" /> {{ $session->opens_at->format('M d, Y H:i') }} – {{ $session->closes_at->format('M d, Y H:i') }}</span>
                                                <span><x-ui-icon name="location" :size="14" /> {{ $session->radius_meters }}m radius</span>
                                            </div>
                                        </div>
                                        <span class="badge badge-{{ $session->status }}">{{ ucfirst($session->status) }}</span>
                                    </div>
                                    <div class="session-actions">
                                        @if($session->qr_code_data_uri)
                                            <button type="button" class="btn-primary show-qr" data-title="{{ $session->project->title }}" data-qr="{{ $session->qr_code_data_uri }}" data-url="{{ $session->scan_url }}" data-status="{{ $session->status }}" data-closes="{{ $session->closes_at->toIso8601String() }}"><x-ui-icon name="qr" :size="16" /><span>View QR code</span></button>
                                        @else
                                            <span class="form-note">QR unavailable. Try rotating the token.</span>
                                        @endif
                                        <a href="{{ route('student_org.attendance.csv-template', $session) }}" class="btn-secondary"><x-ui-icon name="file" :size="16" /><span>Download attendance CSV</span></a>
                                        <a href="{{ route('student_org.attendance.signature-sheet', $session) }}" class="btn-secondary"><x-ui-icon name="clipboard" :size="16" /><span>Download signature sheet (DOCX)</span></a>
                                        <a href="{{ route('student_org.attendance.import', $session) }}" class="btn-secondary"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v11m0 0 4-4m-4 4-4-4M5 20h14"/></svg><span>Upload attendance CSV (online)</span></a>
                                        <a href="{{ route('student_org.attendance.records', $session) }}" class="btn-secondary"><x-ui-icon name="clipboard" :size="16" /><span>View check-ins</span></a>
                                        @if(!$session->isClosed())
                                            <form method="post" action="{{ route('student_org.attendance.regenerate', $session) }}" style="display:contents;">
                                                @csrf
                                                <button type="submit" class="btn-secondary" title="Generate a new QR token"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 11a8 8 0 0 0-14.9-4L3 10m0 0V5m0 5h5M4 13a8 8 0 0 0 14.9 4L21 14m0 0v5m0-5h-5"/></svg><span>Rotate token</span></button>
                                            </form>
                                            <form method="post" action="{{ route('student_org.attendance.close', $session) }}" style="display:contents;" data-confirm data-confirm-title="Close this session?" data-confirm-body="Scanning stops immediately and no new session can be created for this activity." data-confirm-note="Attendance already recorded is kept. This cannot be undone." data-confirm-ok="Close session" data-confirm-cancel="Keep it open">
                                                @csrf
                                                <button type="submit" class="btn-danger"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v9m0 0-3-3m3 3 3-3M5.6 10a8 8 0 1 0 12.8 0"/></svg><span>Close session</span></button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div id="sv-modal-backdrop" class="sv-modal-backdrop" aria-hidden="true">
        <div class="sv-modal" role="dialog" aria-modal="true" aria-labelledby="sv-modal-title">
            <div class="sv-modal-header">
                <h3 id="sv-modal-title">Street-level imagery</h3>
                <button type="button" id="sv-modal-close" class="sv-modal-close">Close</button>
            </div>
            <div class="sv-viewer-wrap">
                <div id="sv-viewer" class="sv-viewer"></div>
                <button type="button" id="sv-look-left" class="sv-look-btn sv-look-left" title="Look left" aria-label="Look left">&#8249;</button>
                <button type="button" id="sv-look-right" class="sv-look-btn sv-look-right" title="Look right" aria-label="Look right">&#8250;</button>
            </div>
            <p id="sv-caption" class="sv-caption"></p>
            <p class="sv-modal-note">Drag the photo, or use &lsaquo; &rsaquo; to look around; the on-image arrows walk along the street. Community-contributed street-level imagery via <a href="https://www.mapillary.com" target="_blank" rel="noopener">Mapillary</a>.</p>
        </div>
    </div>

    <div id="qr-modal-backdrop" class="qr-modal-backdrop" aria-hidden="true">
        <div class="qr-modal" role="dialog" aria-modal="true" aria-labelledby="qr-modal-title">
            <div class="qr-modal-header">
                <div>
                    <div class="qr-modal-eyebrow"><span class="qr-live-dot" aria-hidden="true"></span>Live attendance session</div>
                    <h3 id="qr-modal-title">Attendance QR</h3>
                </div>
                <button type="button" id="qr-modal-close" class="qr-modal-close" aria-label="Close QR code">&times;</button>
            </div>
            <div class="qr-modal-body">
                <p id="qr-modal-subtitle" class="qr-modal-subtitle"></p>
                <div id="qr-modal-status" class="qr-modal-status" role="status" aria-live="polite">
                    <span class="qr-modal-status-dot" aria-hidden="true"></span>
                    <div class="qr-modal-status-copy">
                        <strong id="qr-modal-status-label">Ready to scan</strong>
                        <span id="qr-modal-status-detail">Ask participants to open their scanner.</span>
                    </div>
                </div>
                <div class="qr-display" aria-label="Attendance QR code display">
                    <span class="qr-corner qr-corner--tl" aria-hidden="true"></span>
                    <span class="qr-corner qr-corner--tr" aria-hidden="true"></span>
                    <span class="qr-corner qr-corner--bl" aria-hidden="true"></span>
                    <span class="qr-corner qr-corner--br" aria-hidden="true"></span>
                    <img id="qr-modal-image" src="" alt="Attendance QR code">
                </div>
                <div class="qr-modal-meta">
                    <div><span>Session status</span><strong id="qr-modal-session-status">Open</strong></div>
                    <div><span>Closes in</span><strong id="qr-modal-countdown">Calculating...</strong></div>
                </div>
                <p class="qr-modal-hint">Keep this screen bright and steady. The QR code itself stays static for reliable camera scanning.</p>
                <div class="qr-modal-actions">
                    <button type="button" id="qr-modal-fullscreen" class="qr-modal-action primary">Full screen</button>
                    <a id="qr-modal-download" class="qr-modal-action secondary" href="#" download="attendance-qr.png">Download QR</a>
                </div>
                <details class="qr-modal-link-panel">
                    <summary>Need the direct link?</summary>
                    <div class="qr-modal-link-body">
                        <a id="qr-modal-link" class="qr-modal-link" href="#" target="_blank" rel="noopener" title="Open the participant scanner in a separate tab">Open scan link</a>
                        <button type="button" id="qr-modal-copy" class="qr-modal-copy">Copy link</button>
                    </div>
                </details>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="https://unpkg.com/mapillary-js@4.1.2/dist/mapillary.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const MAPILLARY_TOKEN = @json(config('services.mapillary.token'));
            const svBtn = document.getElementById('street-view-btn');
            const svBackdrop = document.getElementById('sv-modal-backdrop');
            const svClose = document.getElementById('sv-modal-close');
            const svViewerEl = document.getElementById('sv-viewer');
            const svLookLeft = document.getElementById('sv-look-left');
            const svLookRight = document.getElementById('sv-look-right');
            const svCaption = document.getElementById('sv-caption');

            if (svBtn && MAPILLARY_TOKEN && window.mapillary) {
                let currentImageId = null;
                let mlyViewer = null;
                let checkToken = 0;
                const setIconButtonLabel = (button, label) => {
                    const icon = button.querySelector('svg')?.outerHTML || '';
                    button.innerHTML = `${icon}<span>${label}</span>`;
                };

                const formatCaption = (capturedAt) => {
                    if (!capturedAt) return '';
                    const date = new Date(capturedAt);
                    return 'Photo captured ' + date.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
                };

                window.__plinkCheckStreetView = (lat, lng) => {
                    svBtn.disabled = true;
                    setIconButtonLabel(svBtn, 'Checking street-level imagery…');
                    currentImageId = null;
                    const thisCheck = ++checkToken;
                    const d = 0.0008;
                    const bbox = [lng - d, lat - d, lng + d, lat + d].join(',');
                    const params = new URLSearchParams({ fields: 'id,captured_at', bbox, limit: '30' });
                    fetch(`https://graph.mapillary.com/images?${params.toString()}`, {
                        headers: { Authorization: `OAuth ${MAPILLARY_TOKEN}` },
                    })
                        .then((r) => r.json())
                        .then((json) => {
                            if (thisCheck !== checkToken) return;
                            const results = (json.data || []).slice().sort((a, b) => (b.captured_at || 0) - (a.captured_at || 0));
                            const found = results.length ? results[0] : null;
                            currentImageId = found ? found.id : null;
                            svBtn.disabled = !found;
                            setIconButtonLabel(svBtn, found ? 'Street-level imagery available' : 'No street imagery here yet');
                        })
                        .catch(() => {
                            if (thisCheck !== checkToken) return;
                            svBtn.disabled = true;
                            setIconButtonLabel(svBtn, 'Street imagery unavailable');
                        });
                };

                svBtn.addEventListener('click', () => {
                    if (!currentImageId) return;
                    svBackdrop.classList.add('active');
                    svBackdrop.setAttribute('aria-hidden', 'false');
                    if (!mlyViewer) {
                        mlyViewer = new mapillary.Viewer({
                            accessToken: MAPILLARY_TOKEN,
                            container: svViewerEl,
                            imageId: currentImageId,
                        });
                        mlyViewer.on('image', (event) => {
                            svCaption.textContent = formatCaption(event.image && event.image.capturedAt);
                        });
                    } else {
                        mlyViewer.moveTo(currentImageId).catch(() => {});
                    }
                });

                const nudgeLook = (deltaX) => {
                    if (!mlyViewer) return;
                    mlyViewer.getCenter().then((center) => {
                        let x = center[0] + deltaX;
                        if (x < 0) x += 1;
                        if (x > 1) x -= 1;
                        mlyViewer.setCenter([x, center[1]]);
                    }).catch(() => {});
                };
                svLookLeft.addEventListener('click', () => nudgeLook(-0.18));
                svLookRight.addEventListener('click', () => nudgeLook(0.18));

                const closeSv = () => {
                    svBackdrop.classList.remove('active');
                    svBackdrop.setAttribute('aria-hidden', 'true');
                };
                svClose.addEventListener('click', closeSv);
                svBackdrop.addEventListener('click', (event) => { if (event.target === svBackdrop) closeSv(); });
                document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeSv(); });
            } else if (svBtn) {
                svBtn.style.display = 'none';
            }
        });

        document.addEventListener('DOMContentLoaded', function () {
            const latInput = document.getElementById('latitude');
            const lngInput = document.getElementById('longitude');
            const mapEl = document.getElementById('location-map');

            if (latInput && lngInput && mapEl && window.L) {
                const pinIcon = L.divIcon({
                    className: 'plink-pin',
                    html: '<svg width="34" height="44" viewBox="0 0 34 44" xmlns="http://www.w3.org/2000/svg"><path d="M17 0C7.6 0 0 7.6 0 17c0 12.7 17 27 17 27s17-14.3 17-27C34 7.6 26.4 0 17 0z" fill="#0fbf91"/><circle cx="17" cy="17" r="7" fill="#ffffff"/></svg>',
                    iconSize: [34, 44],
                    iconAnchor: [17, 44],
                    popupAnchor: [0, -40],
                });

                const fallbackLat = 8.4155;
                const fallbackLng = 124.6069;
                const initialLat = parseFloat(latInput.value);
                const initialLng = parseFloat(lngInput.value);
                const hasInitial = !isNaN(initialLat) && !isNaN(initialLng);

                const map = L.map(mapEl, {
                    zoomControl: false,
                    attributionControl: false,
                }).setView(
                    [hasInitial ? initialLat : fallbackLat, hasInitial ? initialLng : fallbackLng],
                    hasInitial ? 17 : 6
                );

                const satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                    tileSize: 256,
                    detectRetina: true,
                    maxZoom: 19,
                    maxNativeZoom: 19,
                    keepBuffer: 3,
                    updateWhenZooming: false,
                    updateWhenIdle: true,
                    attribution: 'Imagery &copy; Esri, Maxar, Earthstar Geographics, and the GIS User Community',
                }).addTo(map);
                const hybridLayer = L.layerGroup([
                    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                        tileSize: 256, detectRetina: true, maxZoom: 19, maxNativeZoom: 19, attribution: 'Imagery &copy; Esri',
                    }),
                    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Transportation/MapServer/tile/{z}/{y}/{x}', {
                        tileSize: 256, detectRetina: true, maxZoom: 19, maxNativeZoom: 19, opacity: 0.88, attribution: 'Labels &copy; Esri',
                    }),
                ]);
                L.control.layers({ 'Satellite': satelliteLayer, 'Hybrid': hybridLayer }, null, { position: 'topright' }).addTo(map);
                L.control.attribution({ prefix: false, position: 'bottomright' }).addTo(map);
                setTimeout(() => map.invalidateSize(), 150);

                let marker = null;
                let radiusCircle = null;

                const updateFields = (lat, lng) => {
                    latInput.value = lat.toFixed(6);
                    lngInput.value = lng.toFixed(6);
                    latInput.dispatchEvent(new Event('input', { bubbles: true }));
                    lngInput.dispatchEvent(new Event('input', { bubbles: true }));
                };

                const placeMarker = (lat, lng, recenter) => {
                    if (marker) {
                        marker.setLatLng([lat, lng]);
                        radiusCircle.setLatLng([lat, lng]);
                    } else {
                        marker = L.marker([lat, lng], { draggable: true, icon: pinIcon }).addTo(map);
                        marker.bindPopup('Participants must check in within {{ \App\Models\AttendanceSession::RADIUS_METERS }}m of this pin.');
                        radiusCircle = L.circle([lat, lng], {
                            radius: {{ \App\Models\AttendanceSession::RADIUS_METERS }},
                            color: '#0fbf91',
                            weight: 2,
                            fillColor: '#0fbf91',
                            fillOpacity: 0.12,
                        }).addTo(map);
                        marker.on('dragend', () => {
                            const pos = marker.getLatLng();
                            radiusCircle.setLatLng(pos);
                            updateFields(pos.lat, pos.lng);
                            if (window.__plinkCheckStreetView) window.__plinkCheckStreetView(pos.lat, pos.lng);
                        });
                    }
                    if (recenter) {
                        map.setView([lat, lng], Math.max(map.getZoom(), 17));
                    }
                    if (window.__plinkCheckStreetView) window.__plinkCheckStreetView(lat, lng);
                };

                if (hasInitial) {
                    placeMarker(initialLat, initialLng, false);
                }

                map.on('click', (event) => {
                    placeMarker(event.latlng.lat, event.latlng.lng, false);
                    updateFields(event.latlng.lat, event.latlng.lng);
                });

                // Keep the map in sync if someone types/pastes exact coordinates.
                const syncFromInputs = () => {
                    const lat = parseFloat(latInput.value);
                    const lng = parseFloat(lngInput.value);
                    if (!isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
                        placeMarker(lat, lng, true);
                    }
                };
                latInput.addEventListener('change', syncFromInputs);
                lngInput.addEventListener('change', syncFromInputs);

                // Place search (OpenStreetMap Nominatim — free, no API key).
                const searchInput = document.getElementById('map-search');
                const resultsBox = document.getElementById('map-search-results');
                let searchTimer = null;
                let requestId = 0;
                let activeIndex = -1;

                const escapeHtml = (text) => text.replace(/[&<>"']/g, (char) => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
                }[char]));

                const openResults = (html) => {
                    resultsBox.innerHTML = html;
                    resultsBox.style.display = 'block';
                    searchInput.setAttribute('aria-expanded', 'true');
                    activeIndex = -1;
                };

                const closeResults = () => {
                    resultsBox.style.display = 'none';
                    resultsBox.innerHTML = '';
                    searchInput.setAttribute('aria-expanded', 'false');
                    activeIndex = -1;
                };

                const highlight = (index) => {
                    const items = resultsBox.querySelectorAll('.map-search-item[data-lat]');
                    items.forEach((item) => item.classList.remove('active'));
                    if (index >= 0 && index < items.length) {
                        items[index].classList.add('active');
                        items[index].scrollIntoView({ block: 'nearest' });
                    }
                    activeIndex = index;
                };

                const selectItem = (item) => {
                    const lat = parseFloat(item.dataset.lat);
                    const lng = parseFloat(item.dataset.lng);
                    placeMarker(lat, lng, true);
                    updateFields(lat, lng);
                    searchInput.value = item.dataset.label;
                    closeResults();
                };

                searchInput.addEventListener('input', () => {
                    clearTimeout(searchTimer);
                    const query = searchInput.value.trim();
                    if (query.length < 3) {
                        closeResults();
                        return;
                    }

                    openResults('<div class="map-search-item map-search-empty">Searching...</div>');
                    const thisRequest = ++requestId;

                    searchTimer = setTimeout(() => {
                        fetch(`https://nominatim.openstreetmap.org/search?format=json&limit=6&addressdetails=1&q=${encodeURIComponent(query)}`)
                            .then((response) => response.json())
                            .then((results) => {
                                if (thisRequest !== requestId) return; // a newer keystroke already superseded this one
                                if (!results.length) {
                                    openResults('<div class="map-search-item map-search-empty">No results found. Try a different search or click the map.</div>');
                                    return;
                                }
                                openResults(results.map((result) => `
                                    <div class="map-search-item" data-lat="${result.lat}" data-lng="${result.lon}" data-label="${escapeHtml(result.display_name)}">
                                        <span class="msi-pin"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 11-8 11S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg></span><span>${escapeHtml(result.display_name)}</span>
                                    </div>
                                `).join(''));
                            })
                            .catch(() => {
                                if (thisRequest !== requestId) return;
                                openResults('<div class="map-search-item map-search-empty">Search unavailable right now. Try clicking the map instead.</div>');
                            });
                    }, 400);
                });

                searchInput.addEventListener('keydown', (event) => {
                    const items = resultsBox.querySelectorAll('.map-search-item[data-lat]');
                    if (resultsBox.style.display !== 'block' || !items.length) return;

                    if (event.key === 'ArrowDown') {
                        event.preventDefault();
                        highlight(Math.min(activeIndex + 1, items.length - 1));
                    } else if (event.key === 'ArrowUp') {
                        event.preventDefault();
                        highlight(Math.max(activeIndex - 1, 0));
                    } else if (event.key === 'Enter') {
                        event.preventDefault();
                        const target = activeIndex >= 0 ? items[activeIndex] : items[0];
                        selectItem(target);
                    } else if (event.key === 'Escape') {
                        closeResults();
                    }
                });

                resultsBox.addEventListener('click', (event) => {
                    const item = event.target.closest('.map-search-item[data-lat]');
                    if (!item) return;
                    selectItem(item);
                });

                document.addEventListener('click', (event) => {
                    if (!resultsBox.contains(event.target) && event.target !== searchInput) {
                        closeResults();
                    }
                });

                // Use my current location (browser geolocation).
                const locateBtn = document.getElementById('use-my-location');
                locateBtn.addEventListener('click', () => {
                    if (!navigator.geolocation) {
                        alert('Geolocation is not supported by your browser.');
                        return;
                    }
                    locateBtn.disabled = true;
                    const locateIcon = locateBtn.querySelector('svg')?.outerHTML || '';
                    locateBtn.innerHTML = `${locateIcon}<span>Locating...</span>`;
                    navigator.geolocation.getCurrentPosition(
                        (position) => {
                            placeMarker(position.coords.latitude, position.coords.longitude, true);
                            updateFields(position.coords.latitude, position.coords.longitude);
                            locateBtn.disabled = false;
                            locateBtn.innerHTML = `${locateIcon}<span>Use my current location</span>`;
                        },
                        () => {
                            alert('Could not get your location. Please search or click the map instead.');
                            locateBtn.disabled = false;
                            locateBtn.innerHTML = `${locateIcon}<span>Use my current location</span>`;
                        }
                    );
                });
            }
        });

        document.addEventListener('DOMContentLoaded', function () {
            const backdrop = document.getElementById('qr-modal-backdrop');
            const modal = backdrop.querySelector('.qr-modal');
            const closeBtn = document.getElementById('qr-modal-close');
            const modalTitle = document.getElementById('qr-modal-title');
            const modalSubtitle = document.getElementById('qr-modal-subtitle');
            const modalStatus = document.getElementById('qr-modal-status');
            const modalStatusLabel = document.getElementById('qr-modal-status-label');
            const modalStatusDetail = document.getElementById('qr-modal-status-detail');
            const modalSessionStatus = document.getElementById('qr-modal-session-status');
            const modalCountdown = document.getElementById('qr-modal-countdown');
            const modalImage = document.getElementById('qr-modal-image');
            const modalLink = document.getElementById('qr-modal-link');
            const modalDownload = document.getElementById('qr-modal-download');
            const modalCopy = document.getElementById('qr-modal-copy');
            const modalFullscreen = document.getElementById('qr-modal-fullscreen');
            let countdownTimer = null;
            let currentScanUrl = '';
            let previouslyFocused = null;

            const updateCountdown = (closesAt, status) => {
                window.clearInterval(countdownTimer);
                const closeTime = Date.parse(closesAt);
                const isClosed = status === 'closed';

                const render = () => {
                    const remaining = closeTime - Date.now();
                    const ended = isClosed || !Number.isFinite(closeTime) || remaining <= 0;
                    modalStatus.classList.toggle('is-ended', ended);

                    if (ended) {
                        modalStatusLabel.textContent = 'Session ended';
                        modalStatusDetail.textContent = 'This QR code is no longer accepting attendance scans.';
                        modalSessionStatus.textContent = isClosed ? 'Closed' : 'Expired';
                        modalCountdown.textContent = 'Ended';
                        return;
                    }

                    const totalSeconds = Math.floor(remaining / 1000);
                    const hours = Math.floor(totalSeconds / 3600);
                    const minutes = Math.floor((totalSeconds % 3600) / 60);
                    const seconds = totalSeconds % 60;
                    const parts = [];
                    if (hours) parts.push(`${hours}h`);
                    if (minutes || hours) parts.push(`${minutes}m`);
                    parts.push(`${seconds}s`);

                    modalStatusLabel.textContent = 'Ready to scan';
                    modalStatusDetail.textContent = 'Ask participants to open their scanner.';
                    modalSessionStatus.textContent = 'Open';
                    modalCountdown.textContent = parts.join(' ');
                };

                render();
                if (!isClosed && Number.isFinite(closeTime) && closeTime > Date.now()) {
                    countdownTimer = window.setInterval(render, 1000);
                }
            };

            const closeModal = () => {
                window.clearInterval(countdownTimer);
                if (document.fullscreenElement === modal && document.exitFullscreen) {
                    document.exitFullscreen().catch(() => {});
                }
                backdrop.classList.remove('active');
                backdrop.setAttribute('aria-hidden', 'true');
                if (previouslyFocused) previouslyFocused.focus();
            };

            document.querySelectorAll('.show-qr').forEach((btn) => {
                btn.addEventListener('click', function () {
                    const qrSrc = this.dataset.qr;
                    const scanUrl = this.dataset.url;
                    const title = this.dataset.title;
                    const sessionStatus = this.dataset.status || 'open';
                    const closesAt = this.dataset.closes || '';

                    if (!qrSrc || !scanUrl) {
                        alert('QR data is not available for this session.');
                        return;
                    }

                    modalTitle.textContent = 'Attendance QR';
                    modalSubtitle.textContent = title;
                    modalImage.src = qrSrc;
                    modalImage.alt = `Attendance QR code for ${title}`;
                    modalLink.href = scanUrl;
                    modalLink.textContent = scanUrl;
                    modalDownload.href = qrSrc;
                    currentScanUrl = scanUrl;
                    modalCopy.textContent = 'Copy link';
                    updateCountdown(closesAt, sessionStatus);
                    previouslyFocused = document.activeElement;
                    backdrop.classList.add('active');
                    backdrop.setAttribute('aria-hidden', 'false');
                    closeBtn.focus();
                });
            });

            modalFullscreen.addEventListener('click', async () => {
                try {
                    if (document.fullscreenElement === modal) {
                        await document.exitFullscreen();
                    } else if (modal.requestFullscreen) {
                        await modal.requestFullscreen();
                    } else {
                        modalStatusDetail.textContent = 'Full screen is not supported by this browser.';
                    }
                } catch {
                    modalStatusDetail.textContent = 'Full screen was not available. You can still use the QR normally.';
                }
            });

            document.addEventListener('fullscreenchange', () => {
                modalFullscreen.textContent = document.fullscreenElement === modal ? 'Exit full screen' : 'Full screen';
            });

            modalCopy.addEventListener('click', async () => {
                if (!currentScanUrl) return;
                try {
                    await navigator.clipboard.writeText(currentScanUrl);
                    modalCopy.textContent = 'Copied';
                    window.setTimeout(() => { modalCopy.textContent = 'Copy link'; }, 1800);
                } catch {
                    modalStatusDetail.textContent = 'Copy is unavailable. Open the direct link instead.';
                }
            });

            closeBtn.addEventListener('click', closeModal);
            backdrop.addEventListener('click', (event) => {
                if (event.target === backdrop) {
                    closeModal();
                }
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeModal();
                }
            });
        });
    </script>
@endsection
