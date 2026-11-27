@extends('layouts.admin')

@section('title', $announcement->title)
@section('subtitle', 'Activity details')

@section('content')
    @if($announcement->category === 'Volunteer recruitment' && $announcement->latitude && $announcement->longitude)
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
        <link rel="stylesheet" href="https://unpkg.com/mapillary-js@4.1.2/dist/mapillary.css" />
    @endif
    <style>
        #activity-map-shell { position:relative; width:100%; max-width:920px; margin:18px auto 0; padding:10px;
            border:1px solid #d7e8df; border-radius:20px; background:linear-gradient(145deg,#f8fcfa,#eef6f2);
            box-shadow:0 12px 28px rgba(15,55,43,.08); }
        #activity-map { position:relative; width:100%; min-width:0; aspect-ratio:16 / 10; max-height:520px;
            min-height:320px; border-radius:14px; border:1px solid #cbded5; overflow:hidden; background:#dfeae5; }
        .activity-map-toolbar { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; padding:4px 4px 10px; }
        .activity-map-heading { display:flex; align-items:baseline; gap:8px; min-width:0; }
        .activity-map-heading strong { color:#173b30; font-size:.9rem; }
        .activity-map-heading span { color:#71827b; font-size:.75rem; }
        .activity-map-actions { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
        .activity-map-action { display:inline-flex; align-items:center; justify-content:center; min-height:32px; padding:0 10px;
            border:1px solid #cfe1d8; border-radius:9px; background:#fff; color:#28604c; font:inherit; font-size:.74rem;
            font-weight:800; cursor:pointer; transition:background .16s ease,border-color .16s ease,color .16s ease,transform .16s ease; }
        .activity-map-action:hover { border-color:#8ec9ad; background:#f3fbf6; transform:translateY(-1px); }
        .activity-map-action.is-active { border-color:#087c5b; background:#087c5b; color:#fff; }
        .activity-map-action:focus-visible { outline:3px solid rgba(8,124,91,.25); outline-offset:2px; }
        .activity-map-loading { position:absolute; inset:0; z-index:500; display:flex; align-items:center; justify-content:center;
            background:linear-gradient(135deg,rgba(238,246,242,.94),rgba(255,255,255,.86)); color:#28604c; font-size:.82rem; font-weight:800; }
        .activity-map-loading.is-ready { opacity:0; pointer-events:none; transition:opacity .25s ease; }
        .activity-map-status { min-height:18px; margin:7px 3px 0; color:#71827b; font-size:.74rem; }
        #activity-map-shell:fullscreen { width:100vw; max-width:none; height:100vh; margin:0; padding:18px; border:0; border-radius:0; background:#e8f0ec; }
        #activity-map-shell:fullscreen #activity-map { height:calc(100vh - 76px); max-height:none; aspect-ratio:auto; }
        .plink-pin svg { filter:drop-shadow(0 5px 8px rgba(15,23,42,0.4)); }
        .leaflet-control-layers { border-radius:12px !important; overflow:hidden; box-shadow:0 10px 25px rgba(15,23,42,0.15) !important; border:none !important; }
        .leaflet-bar { border-radius:10px !important; overflow:hidden; border:none !important; box-shadow:0 6px 16px rgba(15,23,42,0.15) !important; }
        .leaflet-control-scale-line { border-radius:0 0 6px 6px !important; background:rgba(255,255,255,0.85) !important; }
        .leaflet-control-attribution { padding:2px 5px !important; font-size:10px !important; line-height:1.3 !important; }
        .sv-btn {
            margin-top:10px; display:inline-flex; align-items:center; gap:8px;
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
        @media (max-width:640px) {
            #activity-map { min-height:300px; aspect-ratio:4 / 3; }
            .activity-map-toolbar { align-items:flex-start; }
            .activity-map-actions { width:100%; }
            .activity-map-action { flex:1 1 auto; }
        }
    </style>

    <x-back-button href="{{ route('participant.activities') }}">Back to activities</x-back-button>

    @if(session('status'))
        <div class="card" style="border-left:4px solid #16a34a; background:#ecfdf5; color:#134e4a; padding:16px 20px; margin-bottom:20px">
            {{ session('status') }}
        </div>
    @endif

    @if($errors->any())
        <div class="card" style="border-left:4px solid #dc2626; background:#fef2f2; color:#7f1d1d; padding:16px 20px; margin-bottom:20px">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @php
        $isRecruitment = $announcement->category === 'Volunteer recruitment';
        $remaining = $isRecruitment ? $announcement->remainingSlots() : null;
        $full = $isRecruitment && $announcement->isFull();
        $deadlinePassed = $isRecruitment && $announcement->application_deadline && $announcement->application_deadline->isPast();
        $activityStarted = $isRecruitment && $announcement->event_date && $announcement->event_date->isPast();
    @endphp

    <div class="card" style="padding:28px">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap">
            <div>
                <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap">
                    <h2 style="margin:0; font-size:1.5rem; color:#0f172a;">{{ $announcement->title }}</h2>
                    <span style="font-size:0.78rem; font-weight:700; padding:4px 10px; border-radius:999px; background:#eef2ff; color:#4338ca">{{ $announcement->category }}</span>
                </div>
                <div style="margin-top:8px">
                    @if($announcement->creator)
                        <a href="{{ route('organizations.show', $announcement->creator) }}"
                           style="display:inline-flex; align-items:center; gap:9px; color:#0b6a4a; font-weight:600; text-decoration:none">
                            <x-organization-avatar :organization="$announcement->creator" :size="30" />
                            {{ $announcement->creator->organization_name ?? $announcement->creator->name }}
                            <span style="font-weight:400; color:#5b7067; font-size:0.86rem">· view profile</span>
                        </a>
                    @else
                        <span style="color:#52665d">Unknown organization</span>
                    @endif
                </div>
            </div>
        </div>

        @if($announcement->description)
            <p style="margin-top:18px; color:#334155; line-height:1.7; white-space:pre-line;">{{ $announcement->description }}</p>
        @endif

        @if($announcement->project)
            <div style="margin-top:18px; padding:15px 16px; border-radius:12px; background:#f8fafc; border:1px solid #e2e8f0; color:#334155">
                <strong>Linked activity:</strong> {{ $announcement->project->title }}
            </div>
        @endif

        @if($isRecruitment)
            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:14px; margin-top:20px">
                <div style="padding:16px; border-radius:14px; background:#f8fafc; border:1px solid #e2e8f0">
                    <div style="font-size:0.8rem; color:#5b7067; font-weight:700; text-transform:uppercase; letter-spacing:0.04em">Schedule</div>
                    <div style="margin-top:6px; font-weight:600; color:#0f172a">{{ $announcement->event_date ? $announcement->event_date->format('M j, Y g:i A') : 'To be announced' }}</div>
                </div>
                <div style="padding:16px; border-radius:14px; background:#f8fafc; border:1px solid #e2e8f0">
                    <div style="font-size:0.8rem; color:#5b7067; font-weight:700; text-transform:uppercase; letter-spacing:0.04em">Location</div>
                    <div style="margin-top:6px; font-weight:600; color:#0f172a">{{ $announcement->event_location ?? 'To be announced' }}</div>
                </div>
                <div style="padding:16px; border-radius:14px; background:#f8fafc; border:1px solid #e2e8f0">
                    <div style="font-size:0.8rem; color:#5b7067; font-weight:700; text-transform:uppercase; letter-spacing:0.04em">Volunteer slots</div>
                    <div style="margin-top:6px; font-weight:600; color:#0f172a">{{ $remaining }} of {{ $announcement->slots_needed }} remaining</div>
                </div>
                <div style="padding:16px; border-radius:14px; background:#f8fafc; border:1px solid #e2e8f0">
                    <div style="font-size:0.8rem; color:#5b7067; font-weight:700; text-transform:uppercase; letter-spacing:0.04em">Application deadline</div>
                    <div style="margin-top:6px; font-weight:600; color:#0f172a">{{ $announcement->application_deadline ? $announcement->application_deadline->timezone('Asia/Manila')->format('M j, Y g:i A') : 'Open' }}</div>
                </div>
            </div>
        @endif

        @if($isRecruitment && $announcement->latitude && $announcement->longitude)
            <div id="activity-map-shell" aria-label="Activity location map">
                <div class="activity-map-toolbar">
                    <div class="activity-map-heading">
                        <strong>Activity location</strong>
                        <span id="activity-map-mode-label">Satellite imagery</span>
                    </div>
                    <div class="activity-map-actions" aria-label="Map controls">
                        <button type="button" class="activity-map-action is-active" data-map-mode="satellite">Satellite</button>
                        <button type="button" class="activity-map-action" data-map-mode="hybrid">Hybrid</button>
                        <button type="button" class="activity-map-action" id="activity-map-locate">Locate me</button>
                        <button type="button" class="activity-map-action" id="activity-map-reset">Reset</button>
                        <button type="button" class="activity-map-action" id="activity-map-fullscreen">Full screen</button>
                    </div>
                </div>
                <div id="activity-map">
                    <div id="activity-map-loading" class="activity-map-loading" role="status">Loading satellite imagery...</div>
                </div>
                <div id="activity-map-status" class="activity-map-status" role="status" aria-live="polite">Satellite imagery is ready for exploring.</div>
            </div>
            <div style="text-align:center;">
                <button type="button" id="street-view-btn" class="sv-btn" disabled>Street-level imagery</button>
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
        @endif

        @if($announcement->attachments->isNotEmpty())
            <div style="margin-top:22px">
                <h4 style="margin:0 0 12px; color:#0f172a">Attachments</h4>
                <div style="display:grid; gap:10px">
                    @foreach($announcement->attachments as $attachment)
                        <a href="{{ asset('storage/'.$attachment->file_path) }}" target="_blank" rel="noopener" style="display:flex; align-items:center; gap:14px; padding:12px 16px; border-radius:14px; border:1px solid #e5e7eb; background:#ffffff; text-decoration:none; color:inherit">
                            <div style="width:38px; height:38px; border-radius:10px; background:#eef2ff; color:#4338ca; display:flex; align-items:center; justify-content:center;">📄</div>
                            <div style="font-weight:600; color:#0f172a">{{ $attachment->original_name }}</div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        @if($isRecruitment)
        <div style="margin-top:26px; border-top:1px solid #e5e7eb; padding-top:22px">
            @if($myApplication && $myApplication->status === 'approved')
                <div style="padding:16px 18px; border-radius:14px; background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; font-weight:600;">
                    ✅ You're enrolled. Bring your QR check-in on activity day.
                </div>
            @elseif($myApplication && $myApplication->status === 'pending')
                <div style="padding:16px 18px; border-radius:14px; background:#fffbeb; border:1px solid #fde68a; color:#92400e; font-weight:600;">
                    ⏳ Your application is pending review.
                </div>
            @elseif($myApplication && $myApplication->status === 'declined')
                <div style="padding:16px 18px; border-radius:14px; background:#fef2f2; border:1px solid #fecaca; color:#991b1b; font-weight:600;">
                    Your application was not approved{{ $myApplication->decision_notes ? ' — '.$myApplication->decision_notes : '' }}.
                </div>
            @elseif($deadlinePassed)
                <div style="color:#52665d">The application deadline for this activity has passed.</div>
            @elseif($activityStarted)
                <div style="color:#52665d">This activity has already started. Applications are closed.</div>
            @elseif($full)
                <div style="color:#52665d">This activity has reached its volunteer capacity.</div>
            @else
                <form method="post" action="{{ route('participant.activities.apply', $announcement) }}" enctype="multipart/form-data">
                    @csrf
                    <label style="display:block; font-weight:700; margin-bottom:8px; color:#0f172a">Supporting documents</label>
                    <div style="color:#52665d; font-size:0.9rem; margin-bottom:10px">Upload 1–3 files the organizer needs to review your application — e.g. a parental consent form or ID. PDF, DOC, or DOCX, max 5MB each.</div>
                    <input id="application-documents" type="file" name="documents[]" multiple accept=".pdf,.doc,.docx" required style="width:100%; margin-bottom:8px;">
                    <div id="application-documents-count" style="color:#52665d; font-size:0.85rem; margin-bottom:8px" aria-live="polite">0 of 3 documents selected</div>
                    <div id="application-documents-error" role="alert" style="display:none; color:#b91c1c; font-size:0.9rem; margin-bottom:10px"></div>
                    <ul id="application-documents-list" style="display:grid; gap:8px; list-style:none; margin:0 0 14px; padding:0"></ul>
                    @error('documents')<div style="color:#b91c1c; font-size:0.9rem; margin-bottom:14px">{{ $message }}</div>@enderror
                    <button type="submit" style="padding:13px 24px; border-radius:12px; background:#0b6a4a; color:#fff; border:none; font-weight:700; cursor:pointer;">Apply to volunteer</button>
                </form>
                <script>
                    (() => {
                        const input = document.getElementById('application-documents');
                        const list = document.getElementById('application-documents-list');
                        const count = document.getElementById('application-documents-count');
                        const error = document.getElementById('application-documents-error');
                        if (!input || !list || !count || !error) return;
                        const previewUrls = [];

                        const render = () => {
                            previewUrls.forEach((url) => URL.revokeObjectURL(url));
                            previewUrls.length = 0;
                            list.replaceChildren();
                            count.textContent = `${input.files.length} of 3 documents selected`;
                            Array.from(input.files).forEach((file, index) => {
                                const item = document.createElement('li');
                                item.style.cssText = 'display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; padding:10px 12px; border:1px solid #e2e8f0; border-radius:10px; background:#f8fafc';

                                const name = document.createElement('span');
                                name.textContent = `${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
                                name.style.cssText = 'min-width:0; overflow-wrap:anywhere; color:#334155; font-size:0.9rem';

                                const actions = document.createElement('span');
                                actions.style.cssText = 'display:flex; align-items:center; gap:12px';
                                const view = document.createElement('a');
                                const previewUrl = URL.createObjectURL(file);
                                previewUrls.push(previewUrl);
                                view.href = previewUrl;
                                view.target = '_blank';
                                view.rel = 'noopener noreferrer';
                                view.textContent = 'View';
                                view.style.cssText = 'color:#2563eb; font-weight:700; font-size:0.88rem';

                                const remove = document.createElement('button');
                                remove.type = 'button';
                                remove.textContent = 'Remove';
                                remove.style.cssText = 'padding:0; border:0; background:none; color:#b91c1c; font-weight:700; cursor:pointer; font-size:0.88rem';
                                remove.addEventListener('click', () => {
                                    const files = Array.from(input.files);
                                    files.splice(index, 1);
                                    const transfer = new DataTransfer();
                                    files.forEach((selectedFile) => transfer.items.add(selectedFile));
                                    input.files = transfer.files;
                                    error.style.display = 'none';
                                    render();
                                });

                                actions.append(view, remove);
                                item.append(name, actions);
                                list.append(item);
                            });
                        };

                        input.addEventListener('change', () => {
                            const files = Array.from(input.files);
                            const tooMany = files.length > 3;
                            const invalidFile = files.find((file) => {
                                const extension = file.name.split('.').pop().toLowerCase();
                                return !['pdf', 'doc', 'docx'].includes(extension) || file.size > 5 * 1024 * 1024;
                            });

                            if (tooMany || invalidFile) {
                                input.value = '';
                                error.textContent = tooMany
                                    ? 'You may select no more than 3 documents.'
                                    : `${invalidFile.name} is not a PDF, DOC, or DOCX file under 5MB.`;
                                error.style.display = 'block';
                            } else {
                                error.style.display = 'none';
                            }
                            render();
                        });

                        render();
                        window.addEventListener('beforeunload', () => {
                            previewUrls.forEach((url) => URL.revokeObjectURL(url));
                        });
                    })();
                </script>
            @endif
            @else
                <div style="margin-top:26px; border-top:1px solid #e5e7eb; padding-top:22px; color:#52665d">
                    This announcement is for information only; no volunteer application is needed.
                </div>
            @endif
        </div>
    </div>

    @if($isRecruitment && $announcement->latitude && $announcement->longitude)
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

                    const formatCaption = (capturedAt) => {
                        if (!capturedAt) return '';
                        const date = new Date(capturedAt);
                        return 'Photo captured ' + date.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
                    };

                    const checkStreetView = (lat, lng) => {
                        svBtn.disabled = true;
                        svBtn.textContent = 'Checking street-level imagery…';
                        const d = 0.0008;
                        const bbox = [lng - d, lat - d, lng + d, lat + d].join(',');
                        const params = new URLSearchParams({ fields: 'id,captured_at', bbox, limit: '30' });
                        fetch(`https://graph.mapillary.com/images?${params.toString()}`, {
                            headers: { Authorization: `OAuth ${MAPILLARY_TOKEN}` },
                        })
                            .then((r) => r.json())
                            .then((json) => {
                                const results = (json.data || []).slice().sort((a, b) => (b.captured_at || 0) - (a.captured_at || 0));
                                const found = results.length ? results[0] : null;
                                currentImageId = found ? found.id : null;
                                svBtn.disabled = !found;
                                svBtn.textContent = found ? 'Street-level imagery available' : 'No street imagery here yet';
                            })
                            .catch(() => {
                                svBtn.disabled = true;
                                svBtn.textContent = 'Street imagery unavailable';
                            });
                    };
                    window.__plinkCheckStreetView = checkStreetView;

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

                    checkStreetView({{ $announcement->latitude }}, {{ $announcement->longitude }});
                } else if (svBtn) {
                    svBtn.style.display = 'none';
                }

                const pinIcon = L.divIcon({
                    className: 'plink-pin',
                    html: '<svg width="34" height="44" viewBox="0 0 34 44" xmlns="http://www.w3.org/2000/svg"><path d="M17 0C7.6 0 0 7.6 0 17c0 12.7 17 27 17 27s17-14.3 17-27C34 7.6 26.4 0 17 0z" fill="#0fbf91"/><circle cx="17" cy="17" r="7" fill="#ffffff"/></svg>',
                    iconSize: [34, 44],
                    iconAnchor: [17, 44],
                    popupAnchor: [0, -40],
                });
                const lat = {{ $announcement->latitude }};
                const lng = {{ $announcement->longitude }};
                const map = L.map('activity-map', { scrollWheelZoom: true, zoomControl: true, preferCanvas: true }).setView([lat, lng], 16);
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
                const fallbackImageryLayer = L.tileLayer('https://services.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                    tileSize: 256,
                    detectRetina: true,
                    maxZoom: 19,
                    maxNativeZoom: 19,
                    keepBuffer: 3,
                    updateWhenZooming: false,
                    updateWhenIdle: true,
                    attribution: 'Imagery &copy; Esri',
                });
                const referenceLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Transportation/MapServer/tile/{z}/{y}/{x}', {
                    tileSize: 256,
                    detectRetina: true,
                    maxZoom: 19,
                    maxNativeZoom: 19,
                    opacity: 0.88,
                    attribution: 'Labels &copy; Esri',
                });
                let hybridMode = false;
                const modeLabel = document.getElementById('activity-map-mode-label');
                const status = document.getElementById('activity-map-status');
                const loading = document.getElementById('activity-map-loading');
                const setMapStatus = (message) => { if (status) status.textContent = message; };
                let tileErrors = 0;
                let fallbackActivated = false;
                const activateFallback = () => {
                    if (fallbackActivated) return;
                    fallbackActivated = true;
                    if (map.hasLayer(satelliteLayer)) map.removeLayer(satelliteLayer);
                    fallbackImageryLayer.addTo(map);
                    setMapStatus('Using the satellite fallback layer. Imagery detail depends on source coverage.');
                };
                document.querySelectorAll('[data-map-mode]').forEach((button) => {
                    button.addEventListener('click', () => {
                        hybridMode = button.dataset.mapMode === 'hybrid';
                        if (hybridMode) {
                            referenceLayer.addTo(map);
                            if (modeLabel) modeLabel.textContent = 'Hybrid imagery with labels';
                            setMapStatus('Hybrid mode adds readable roads and place labels over the satellite imagery.');
                        } else {
                            if (map.hasLayer(referenceLayer)) map.removeLayer(referenceLayer);
                            if (modeLabel) modeLabel.textContent = 'Satellite imagery';
                            setMapStatus('Satellite imagery is ready for exploring.');
                        }
                        document.querySelectorAll('[data-map-mode]').forEach((item) => item.classList.toggle('is-active', item === button));
                    });
                });
                satelliteLayer.once('load', () => loading?.classList.add('is-ready'));
                fallbackImageryLayer.once('load', () => loading?.classList.add('is-ready'));
                satelliteLayer.on('tileerror', () => {
                    tileErrors += 1;
                    if (tileErrors >= 3) activateFallback();
                    else setMapStatus('Some imagery tiles could not be loaded.');
                });
                const mapShell = document.getElementById('activity-map-shell');
                document.getElementById('activity-map-reset')?.addEventListener('click', () => map.setView([lat, lng], 16, { animate: true }));
                document.getElementById('activity-map-locate')?.addEventListener('click', () => {
                    setMapStatus('Requesting your current location...');
                    map.locate({ setView: true, maxZoom: 18, enableHighAccuracy: true });
                });
                let userLocation = null;
                map.on('locationfound', (event) => {
                    if (userLocation) map.removeLayer(userLocation);
                    userLocation = L.circleMarker(event.latlng, { radius: 7, color: '#fff', weight: 3, fillColor: '#2563eb', fillOpacity: 1 }).addTo(map);
                    setMapStatus('Your approximate location is shown in blue.');
                });
                map.on('locationerror', () => setMapStatus('Location permission was unavailable. You can still explore the map manually.'));
                const fullscreenButton = document.getElementById('activity-map-fullscreen');
                fullscreenButton?.addEventListener('click', () => {
                    if (document.fullscreenElement) document.exitFullscreen?.();
                    else mapShell?.requestFullscreen?.();
                });
                document.addEventListener('fullscreenchange', () => {
                    if (fullscreenButton) fullscreenButton.textContent = document.fullscreenElement ? 'Exit full screen' : 'Full screen';
                    window.setTimeout(() => map.invalidateSize({ animate: true }), 250);
                });
                map.on('click', (event) => {
                    if (window.__plinkCheckStreetView) {
                        setMapStatus('Checking for street-level imagery near the selected point...');
                        window.__plinkCheckStreetView(event.latlng.lat, event.latlng.lng);
                    }
                });
                L.control.scale({ imperial: false, position: 'bottomleft' }).addTo(map);
                setTimeout(() => map.invalidateSize(), 150);
                if (window.ResizeObserver && mapShell) new ResizeObserver(() => map.invalidateSize()).observe(mapShell);
                L.marker([lat, lng], { icon: pinIcon }).addTo(map).bindPopup('Activity location').openPopup();
                L.circle([lat, lng], {
                    radius: 50,
                    color: '#0fbf91',
                    weight: 2,
                    fillColor: '#0fbf91',
                    fillOpacity: 0.12,
                }).addTo(map);
            });
        </script>
    @endif
@endsection
