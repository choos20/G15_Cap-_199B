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
        .sv-caption { color:#cbd5e1; font-size:0.82rem; margin-top:10px; }
        .sv-modal-note { color:#94a3b8; font-size:0.82rem; margin-top:6px; }
        .sv-modal-note a { color:#93c5fd; }
        .map-field { position:relative; margin-bottom:12px; }
        .map-search-wrap { position:relative; }
        .map-search-wrap input { padding-right:44px; }
        .map-search-icon { position:absolute; right:14px; top:50%; transform:translateY(-50%); color:#94a3b8; pointer-events:none; }
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
        .map-search-item.map-search-empty { color:#94a3b8; cursor:default; }
        .map-search-item.map-search-empty:hover { background:transparent; }
        #location-map {
            aspect-ratio: 4 / 3;
            max-height:480px;
            min-height:320px;
            max-width:640px;
            border-radius:16px;
            margin:12px auto 0;
            border:1px solid #d1d5db;
        }
        .plink-pin svg { filter:drop-shadow(0 5px 8px rgba(15,23,42,0.4)); }
        .leaflet-control-layers { border-radius:12px !important; overflow:hidden; box-shadow:0 10px 25px rgba(15,23,42,0.15) !important; border:none !important; }
        .leaflet-bar { border-radius:10px !important; overflow:hidden; border:none !important; box-shadow:0 6px 16px rgba(15,23,42,0.15) !important; }
        .leaflet-control-scale-line { border-radius:0 0 6px 6px !important; background:rgba(255,255,255,0.85) !important; }
        .leaflet-control-attribution { padding:2px 5px !important; font-size:10px !important; line-height:1.3 !important; }
        .map-locate-btn {
            margin-top:10px; display:inline-flex; align-items:center; gap:8px;
            background:#f8fafc; color:#0f172a; border:1px solid #cbd5e1; border-radius:12px;
            padding:9px 14px; font-weight:700; font-size:0.85rem; cursor:pointer;
        }
        .map-locate-btn:hover { background:#eff6ff; border-color:#93c5fd; }
        .map-locate-btn:disabled { opacity:0.6; cursor:progress; }
        .attendance-grid {
            display:grid;
            grid-template-columns:1fr 360px;
            gap:24px;
        }
        .attendance-card {
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
            color:#64748b;
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
            color:#64748b;
            font-size:0.9rem;
            display:grid;
            gap:4px;
        }
        .qr-modal-backdrop {
            position:fixed;
            inset:0;
            background:rgba(15,23,42,0.45);
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
            padding:24px;
            width:100%;
            max-width:360px;
            box-shadow:0 24px 60px rgba(15,23,42,0.18);
        }
        .qr-modal img {
            width:100%;
            border-radius:12px;
            margin:16px 0;
            border:1px solid #e5e7eb;
        }
        .qr-modal-link {
            display:block;
            word-break:break-all;
            color:#2563eb;
            font-size:0.9rem;
            margin-bottom:12px;
        }
        @media (max-width:980px) {
            .attendance-grid { grid-template-columns:1fr; }
            .session-strip { grid-template-columns:1fr; }
        }
    </style>

    @if(session('status'))
        <div class="card" style="border-left:4px solid #16a34a; background:#ecfdf5; color:#134e4a; padding:18px 22px; margin-bottom:20px">
            <div style="font-weight:700; margin-bottom:6px">Success</div>
            <div>{{ session('status') }}</div>
        </div>
    @endif
    @error('attendance_file')
        <div class="card" style="border-left:4px solid #dc2626; background:#fef2f2; color:#991b1b; padding:18px 22px; margin-bottom:20px">
            {{ $message }}
        </div>
    @enderror

    <div class="attendance-grid">
        <div class="attendance-card">
            <h3>Open attendance session</h3>
            <p class="form-note">Choose an approved activity, set the session details, and generate a QR token that participants will scan on-site. Each activity can only have one attendance session.</p>

            <form method="post" action="{{ route('student_org.attendance.store') }}">
                @csrf
                <div class="form-field">
                    <label for="project_id">Approved activity</label>
                    <select id="project_id" name="project_id" class="@error('project_id') input-error @enderror">
                        <option value="">Select activity</option>
                        @foreach($approvedProjects as $project)
                            <option value="{{ $project->id }}" @selected(old('project_id') == $project->id)>{{ $project->title }}</option>
                        @endforeach
                    </select>
                    @if($approvedProjects->isEmpty())
                        <div class="form-note">All approved activities already have attendance sessions, or none are approved yet.</div>
                    @endif
                    @error('project_id')<div class="error-text">{{ $message }}</div>@enderror
                </div>
                <div class="form-field map-field">
                    <label for="map-search">Activity location</label>
                    <div class="map-search-wrap">
                        <input type="text" id="map-search" name="loc_q_{{ Str::random(8) }}" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" role="combobox" aria-autocomplete="list" aria-expanded="false" placeholder="Search for a venue, building, or address...">
                        <span class="map-search-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16zm10 2-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        <div id="map-search-results" class="map-search-results"></div>
                    </div>
                    <div id="location-map"></div>
                    <button type="button" id="use-my-location" class="map-locate-btn">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2v3m0 14v3M2 12h3m14 0h3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="12" r="5" stroke="currentColor" stroke-width="2"/></svg>
                        Use my current location
                    </button>
                    <button type="button" id="street-view-btn" class="sv-btn" disabled>Street view</button>
                    <div class="form-note">Search, click the map, or drag the pin to set the exact activity location — the fields below fill in automatically.</div>
                </div>
                <div class="session-strip">
                    <div class="form-field">
                        <label for="latitude">Activity latitude</label>
                        <input id="latitude" name="latitude" type="text" maxlength="20" placeholder="e.g. 8.4155" value="{{ old('latitude') }}" class="@error('latitude') input-error @enderror">
                        @error('latitude')<div class="error-text">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-field">
                        <label for="longitude">Activity longitude</label>
                        <input id="longitude" name="longitude" type="text" maxlength="20" placeholder="e.g. 124.6069" value="{{ old('longitude') }}" class="@error('longitude') input-error @enderror">
                        @error('longitude')<div class="error-text">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="form-field">
                    <label>Check-in radius</label>
                    <input type="text" value="50 meters (fixed)" disabled>
                    <div class="form-note">Participants must be within 50 meters of the activity location to check in.</div>
                </div>
                <div class="session-strip">
                    <div class="form-field">
                        <label for="opens_at">Opens at</label>
                        <input id="opens_at" name="opens_at" type="datetime-local" min="{{ $minOpenAt }}" value="{{ old('opens_at') }}" class="@error('opens_at') input-error @enderror">
                        <div class="form-note">Choose today or a future date. If starting today, the opening time must not be in the past.</div>
                        @error('opens_at')<div class="error-text">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-field">
                        <label for="closes_at">Closes at</label>
                        <input id="closes_at" name="closes_at" type="datetime-local" min="{{ $minCloseAt }}" max="{{ $maxCloseAt }}" value="{{ old('closes_at') }}" class="@error('closes_at') input-error @enderror">
                        <div class="form-note">Must be after the opening time and within one year from today.</div>
                        @error('closes_at')<div class="error-text">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="session-actions">
                    <button type="submit" class="btn-primary" @disabled($approvedProjects->isEmpty())>Open session</button>
                </div>
            </form>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const opensAt = document.getElementById('opens_at');
                const closesAt = document.getElementById('closes_at');
                if (!opensAt || !closesAt) return;

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

                opensAt.addEventListener('input', updateCloseMinimum);
                updateCloseMinimum();
            });
        </script>

        <div>
            <div class="attendance-card banner">
                <div>
                    <h3>How this works</h3>
                    <p class="form-note">Organizers create one session per approved activity. Participants scan the QR with their account and share GPS coordinates for verification.</p>
                </div>
                <div class="session-strip">
                    <div class="card">
                        <strong>Step 1</strong>
                        <div>Create session with location and duration.</div>
                    </div>
                    <div class="card">
                        <strong>Step 2</strong>
                        <div>Participants scan QR and share GPS coordinates.</div>
                    </div>
                </div>
                <div class="session-strip">
                    <div class="card">
                        <strong>Step 3</strong>
                        <div>Server checks token, session window, and 50m radius.</div>
                    </div>
                    <div class="card">
                        <strong>Step 4</strong>
                        <div>Valid check-ins are recorded with distance audit data.</div>
                    </div>
                </div>
            </div>

            <div class="attendance-card" style="margin-top:24px;">
                <h3>Sessions</h3>
                @if($sessions->isEmpty())
                    <p style="color:#475569;">No attendance sessions yet. Open one using the form above after your activity is approved.</p>
                @else
                    <div class="session-list">
                        @foreach($sessions as $session)
                            <div class="session-item">
                                <div class="session-item-header">
                                    <div>
                                        <p class="session-item-title">{{ $session->project->title }}</p>
                                        <div class="session-item-meta">
                                            <span>Opens: {{ $session->opens_at->format('M d, Y H:i') }}</span>
                                            <span>Closes: {{ $session->closes_at->format('M d, Y H:i') }}</span>
                                            <span>Radius: {{ $session->radius_meters }} meters</span>
                                        </div>
                                    </div>
                                    <span class="badge badge-{{ $session->status }}">{{ ucfirst($session->status) }}</span>
                                </div>
                                <div class="session-actions">
                                    @if($session->qr_code_data_uri)
                                        <button type="button"
                                            class="btn-primary show-qr"
                                            data-title="{{ $session->project->title }}"
                                            data-qr="{{ $session->qr_code_data_uri }}"
                                            data-url="{{ $session->scan_url }}">
                                            View QR
                                        </button>
                                    @else
                                        <span class="form-note">QR unavailable. Try rotating the token.</span>
                                    @endif
                                    <a href="{{ route('student_org.attendance.csv-template', $session) }}" class="btn-secondary">Download attendance CSV</a>
                                    <a href="{{ route('student_org.attendance.signature-sheet', $session) }}" class="btn-secondary">Download signature sheet (DOCX)</a>
                                    <a href="{{ route('student_org.attendance.import', $session) }}" class="btn-secondary">Upload attendance CSV (online)</a>
                                    <a href="{{ route('student_org.attendance.records', $session) }}" class="btn-secondary">View check-ins</a>
                                    @if(!$session->isClosed())
                                        <form method="post" action="{{ route('student_org.attendance.regenerate', $session) }}" style="display:inline;">
                                            @csrf
                                            <button type="submit" class="btn-secondary" title="Generate a new QR token">Rotate token</button>
                                        </form>
                                        <form method="post" action="{{ route('student_org.attendance.close', $session) }}" style="display:inline;" data-confirm
                                              data-confirm-title="Close this session?"
                                              data-confirm-body="Scanning stops immediately and no new session can be created for this activity."
                                              data-confirm-note="Attendance already recorded is kept. This cannot be undone."
                                              data-confirm-ok="Close session"
                                              data-confirm-cancel="Keep it open">
                                            @csrf
                                            <button type="submit" class="btn-danger">Close session</button>
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

    <div id="sv-modal-backdrop" class="sv-modal-backdrop" aria-hidden="true">
        <div class="sv-modal" role="dialog" aria-modal="true" aria-labelledby="sv-modal-title">
            <div class="sv-modal-header">
                <h3 id="sv-modal-title">Street view</h3>
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
        <div class="qr-modal attendance-card" role="dialog" aria-modal="true" aria-labelledby="qr-modal-title">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
                <h3 id="qr-modal-title" style="margin:0;">Attendance QR</h3>
                <button type="button" id="qr-modal-close" class="btn-secondary">Close</button>
            </div>
            <p id="qr-modal-subtitle" class="form-note" style="margin:8px 0 0;"></p>
            <img id="qr-modal-image" src="" alt="Attendance QR code">
            <a id="qr-modal-link" class="qr-modal-link" href="#" target="_blank" rel="noopener">Open scan link</a>
            <a id="qr-modal-download" class="btn-secondary" href="#" download="attendance-qr.png">Download QR</a>
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

                const formatCaption = (capturedAt) => {
                    if (!capturedAt) return '';
                    const date = new Date(capturedAt);
                    return 'Photo captured ' + date.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
                };

                window.__plinkCheckStreetView = (lat, lng) => {
                    svBtn.disabled = true;
                    svBtn.textContent = 'Checking street view…';
                    currentImageId = null;
                    const thisCheck = ++checkToken;
                    const d = 0.0008;
                    const bbox = [lng - d, lat - d, lng + d, lat + d].join(',');
                    fetch(`https://graph.mapillary.com/images?access_token=${MAPILLARY_TOKEN}&fields=id,captured_at&bbox=${bbox}&limit=20`)
                        .then((r) => r.json())
                        .then((json) => {
                            if (thisCheck !== checkToken) return;
                            const results = (json.data || []).slice().sort((a, b) => (b.captured_at || 0) - (a.captured_at || 0));
                            const found = results.length ? results[0] : null;
                            currentImageId = found ? found.id : null;
                            svBtn.disabled = !found;
                            svBtn.textContent = found ? 'Street view available' : 'No street view here yet';
                        })
                        .catch(() => {
                            if (thisCheck !== checkToken) return;
                            svBtn.disabled = true;
                            svBtn.textContent = 'Street view unavailable';
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

                const map = L.map(mapEl).setView(
                    [hasInitial ? initialLat : fallbackLat, hasInitial ? initialLng : fallbackLng],
                    hasInitial ? 17 : 6
                );

                const streetLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}', {
                    maxZoom: 19,
                    attribution: 'Tiles &copy; Esri — Sources: Esri, TomTom, Garmin, FAO, NOAA, USGS, &copy; OpenStreetMap contributors, and the GIS User Community',
                }).addTo(map);

                const satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                    maxZoom: 20,
                    maxNativeZoom: 18,
                    attribution: 'Tiles &copy; Esri',
                });

                L.control.layers({ 'Street': streetLayer, 'Satellite': satelliteLayer }, null, { position: 'topright' }).addTo(map);
                L.control.scale({ imperial: false, position: 'bottomleft' }).addTo(map);
                setTimeout(() => map.invalidateSize(), 150);

                let marker = null;
                let radiusCircle = null;

                const updateFields = (lat, lng) => {
                    latInput.value = lat.toFixed(6);
                    lngInput.value = lng.toFixed(6);
                };

                const placeMarker = (lat, lng, recenter) => {
                    if (marker) {
                        marker.setLatLng([lat, lng]);
                        radiusCircle.setLatLng([lat, lng]);
                    } else {
                        marker = L.marker([lat, lng], { draggable: true, icon: pinIcon }).addTo(map);
                        marker.bindPopup('Participants must check in within 50m of this pin.');
                        radiusCircle = L.circle([lat, lng], {
                            radius: 50,
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
                                        <span class="msi-pin">📍</span><span>${escapeHtml(result.display_name)}</span>
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
                    locateBtn.textContent = 'Locating...';
                    navigator.geolocation.getCurrentPosition(
                        (position) => {
                            placeMarker(position.coords.latitude, position.coords.longitude, true);
                            updateFields(position.coords.latitude, position.coords.longitude);
                            locateBtn.disabled = false;
                            locateBtn.textContent = 'Use my current location';
                        },
                        () => {
                            alert('Could not get your location. Please search or click the map instead.');
                            locateBtn.disabled = false;
                            locateBtn.textContent = 'Use my current location';
                        }
                    );
                });
            }
        });

        document.addEventListener('DOMContentLoaded', function () {
            const backdrop = document.getElementById('qr-modal-backdrop');
            const closeBtn = document.getElementById('qr-modal-close');
            const modalTitle = document.getElementById('qr-modal-title');
            const modalSubtitle = document.getElementById('qr-modal-subtitle');
            const modalImage = document.getElementById('qr-modal-image');
            const modalLink = document.getElementById('qr-modal-link');
            const modalDownload = document.getElementById('qr-modal-download');

            const closeModal = () => {
                backdrop.classList.remove('active');
                backdrop.setAttribute('aria-hidden', 'true');
            };

            document.querySelectorAll('.show-qr').forEach((btn) => {
                btn.addEventListener('click', function () {
                    const qrSrc = this.dataset.qr;
                    const scanUrl = this.dataset.url;
                    const title = this.dataset.title;

                    if (!qrSrc || !scanUrl) {
                        alert('QR data is not available for this session.');
                        return;
                    }

                    modalTitle.textContent = 'Attendance QR';
                    modalSubtitle.textContent = title;
                    modalImage.src = qrSrc;
                    modalLink.href = scanUrl;
                    modalLink.textContent = scanUrl;
                    modalDownload.href = qrSrc;
                    backdrop.classList.add('active');
                    backdrop.setAttribute('aria-hidden', 'false');
                });
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
