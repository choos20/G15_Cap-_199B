@extends('layouts.admin')

@section('title','Announcements')
@section('subtitle','Create volunteer opportunities or general announcements for participants.')

@section('content')
    @php
        $selectedCategory = old('category');
        if (! in_array($selectedCategory, ['Volunteer recruitment', 'General'], true)) {
            $selectedCategory = 'Volunteer recruitment';
        }
    @endphp
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <style>
        [data-category-fields][hidden] { display:none !important; }
        .ann-create-card { overflow:hidden; padding:0 !important; border:1px solid #e3ece8; border-radius:20px; box-shadow:0 12px 35px rgba(15,55,43,.07); }
        .ann-create-head { display:flex; align-items:flex-start; gap:15px; padding:20px 24px; background:linear-gradient(120deg,#f0faf6,#f8fbfa 68%,#eef7f3); border-bottom:1px solid #e3ece8; }
        .ann-create-icon { display:grid; place-items:center; flex:0 0 44px; height:44px; border-radius:14px; background:#d9f3e8; color:#08734e; }
        .ann-create-eyebrow { margin:1px 0 4px; color:#08734e; font-size:.7rem; font-weight:800; letter-spacing:.1em; text-transform:uppercase; }
        .ann-create-title { margin:0; color:#173b30; font-size:1.24rem; font-weight:800; letter-spacing:-.02em; }
        .ann-create-intro { margin:4px 0 0; color:#647a71; font-size:.87rem; line-height:1.45; }
        .ann-create-body { padding:20px 22px 22px; }
        .ann-composer { display:grid; grid-template-columns:minmax(0,1.18fr) minmax(300px,.82fr); gap:18px; align-items:start; }
        .ann-composer-form, .ann-create-body > *, .ann-create-field { min-width:0; }
        .ann-panel { min-width:0; padding:17px; border:1px solid #e2ebe6; border-radius:16px; background:#fff; }
        .ann-panel + .ann-panel { margin-top:15px; }
        .ann-panel-heading { display:flex; align-items:center; gap:10px; margin-bottom:15px; }
        .ann-panel-icon { display:grid; place-items:center; flex:0 0 31px; width:31px; height:31px; border-radius:10px; background:#eaf8f1; color:#08734e; }
        .ann-panel-title { margin:0; color:#173b30; font-size:1rem; font-weight:800; }
        .ann-panel-copy { margin:2px 0 0; color:#71827b; font-size:.78rem; line-height:1.35; }
        .ann-category-section { margin:0 0 15px; padding:15px; border:1px solid #e2ebe6; border-radius:16px; }
        .ann-category-label, .ann-form-label { display:block; margin-bottom:8px; color:#203b32; font-size:.86rem; font-weight:750; }
        .ann-category-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
        .ann-category-card { position:relative; display:flex; align-items:flex-start; gap:10px; min-height:84px; padding:13px; border:1px solid #dfe9e4; border-radius:13px; background:#fff; cursor:pointer; transition:border-color .18s ease,background .18s ease,box-shadow .18s ease; }
        .ann-category-card:hover { border-color:#8dcab0; background:#fbfefc; }
        .ann-category-card:has(.ann-category-radio:checked) { border-color:#15946a; background:#f0faf5; box-shadow:0 0 0 3px rgba(21,148,106,.1); }
        .ann-category-card:focus-within { outline:3px solid rgba(21,148,106,.14); outline-offset:1px; }
        .ann-category-radio { flex:0 0 auto; width:16px; height:16px; margin:2px 0 0; accent-color:#0b7656; }
        .ann-category-mark { display:grid; place-items:center; flex:0 0 28px; width:28px; height:28px; border-radius:9px; background:#eaf8f1; color:#08734e; }
        .ann-category-name { display:block; color:#183b30; font-size:.88rem; font-weight:800; }
        .ann-category-desc { display:block; margin-top:4px; color:#697d75; font-size:.76rem; line-height:1.4; }
        .ann-form-grid, .ann-recruitment-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:13px; }
        .ann-field-span { grid-column:1 / -1; }
        .ann-create-field input:not([type=hidden]), .ann-create-field select, .ann-create-field textarea,
        .ann-panel input:not([type=hidden]), .ann-panel select, .ann-panel textarea { box-sizing:border-box; width:100%; padding:10px 12px; border:1px solid #d6e1dc; border-radius:10px; background:#fff; color:#173b30; font:inherit; font-size:.88rem; transition:border-color .18s ease,box-shadow .18s ease; }
        .ann-create-field input:focus, .ann-create-field select:focus, .ann-create-field textarea:focus,
        .ann-panel input:focus, .ann-panel select:focus, .ann-panel textarea:focus { outline:none; border-color:#16845e; box-shadow:0 0 0 3px rgba(22,132,94,.12); }
        .ann-create-field textarea { min-height:112px; resize:vertical; }
        .ann-create-help { margin-top:5px; color:#71827b; font-size:.76rem; line-height:1.4; }
        .ann-description-meta { display:flex; justify-content:space-between; gap:12px; margin-top:5px; color:#71827b; font-size:.75rem; }
        #announcement-description-count { color:#08734e; font-weight:750; white-space:nowrap; }
        .ann-category-hint { display:flex; gap:9px; align-items:flex-start; margin-bottom:15px; padding:10px 12px; border-radius:10px; background:#f1f8f5; color:#49685b; font-size:.79rem; line-height:1.4; }
        .ann-category-hint svg { flex:0 0 auto; margin-top:1px; color:#13835c; }
        .ann-existing-notice { color:#8a5a00; }
        .ann-event-panel { background:#fbfdfc; }
        .ann-event-panel .ann-form-label { margin-bottom:7px; }
        .ann-event-field { margin-top:13px; }
        .ann-location-heading { display:flex; align-items:center; justify-content:space-between; gap:10px; }
        .ann-location-heading .ann-form-label { margin-bottom:0; }
        .ann-map-search-wrap { position:relative; }
        .ann-map-search-results {
            display:none; position:absolute; top:calc(100% + 6px); left:0; right:0; z-index:2000;
            background:#fff; border:1px solid #e5e7eb; border-radius:12px;
            box-shadow:0 18px 40px rgba(15,23,42,0.16); max-height:220px; overflow:auto;
        }
        .ann-map-search-item { padding:10px 12px; cursor:pointer; border-bottom:1px solid #f1f5f9; font-size:.8rem; color:#334155; }
        .ann-map-search-item:last-child { border-bottom:none; }
        .ann-map-search-item:hover { background:#eff6ff; }
        #ann-location-map { width:100%; height:235px; min-width:0; border-radius:13px; margin:9px 0 0; border:1px solid #d1d5db; overflow:hidden; }
        .ann-locate-btn { margin-top:8px; display:inline-flex; align-items:center; gap:7px; background:#f8fafc; color:#0f172a; border:1px solid #cbd5e1; border-radius:10px; padding:8px 11px; font-weight:700; font-size:.78rem; cursor:pointer; }
        .ann-locate-btn:hover { background:#eff6ff; border-color:#93c5fd; }
        .plink-pin svg { filter:drop-shadow(0 5px 8px rgba(15,23,42,0.4)); }
        .leaflet-control-layers { border-radius:10px !important; overflow:hidden; box-shadow:0 10px 25px rgba(15,23,42,0.15) !important; border:none !important; }
        .leaflet-bar { border-radius:8px !important; overflow:hidden; border:none !important; box-shadow:0 6px 16px rgba(15,23,42,0.15) !important; }
        .leaflet-control-scale-line { border-radius:0 0 6px 6px !important; background:rgba(255,255,255,0.85) !important; }
        .leaflet-control-attribution { padding:2px 5px !important; font-size:9px !important; line-height:1.2 !important; }
        .ann-upload-dropzone { display:flex; align-items:center; justify-content:center; gap:10px; min-height:88px; padding:13px; border:1px dashed #8dcab0; border-radius:12px; background:#f5fcf8; color:#08734e; text-align:center; cursor:pointer; transition:background .18s ease,border-color .18s ease; }
        .ann-upload-dropzone:hover, .ann-upload-dropzone.is-dragging { border-color:#15946a; background:#eaf8f1; }
        .ann-upload-dropzone strong { display:block; color:#173b30; font-size:.81rem; }
        .ann-upload-dropzone small { display:block; margin-top:3px; color:#71827b; font-size:.72rem; }
        .ann-upload-icon { display:grid; place-items:center; flex:0 0 31px; width:31px; height:31px; border-radius:10px; background:#d9f3e8; color:#08734e; }
        .ann-upload-list { display:grid; gap:7px; margin-top:10px; }
        .ann-upload-file { display:flex; align-items:center; gap:10px; padding:9px 10px; border:1px solid #e2e8f0; border-radius:9px; background:#f8fafc; }
        .ann-upload-file-name { flex:1; min-width:0; overflow-wrap:anywhere; color:#0f172a; font-size:.8rem; font-weight:600; }
        .ann-upload-file-size { display:block; margin-top:2px; color:#52665d; font-size:.7rem; font-weight:400; }
        .ann-upload-action { border:0; background:none; color:#0b6a4a; font:inherit; font-size:.75rem; font-weight:700; text-decoration:underline; cursor:pointer; }
        .ann-upload-remove { color:#b91c1c; }
        .ann-preview-panel { position:sticky; top:16px; min-width:0; padding:14px; border:1px solid #dbe9e2; border-radius:16px; background:#f7fbf9; box-shadow:0 10px 25px rgba(15,55,43,.06); }
        .ann-preview-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:10px; }
        .ann-preview-kicker { color:#08734e; font-size:.7rem; font-weight:800; letter-spacing:.08em; text-transform:uppercase; }
        .ann-preview-status { padding:4px 8px; border-radius:999px; background:#fff7e6; color:#946200; font-size:.68rem; font-weight:800; }
        .ann-preview-card { overflow:hidden; border:1px solid #e0e9e4; border-radius:13px; background:#fff; box-shadow:0 5px 15px rgba(15,55,43,.06); }
        .ann-preview-card-body { padding:14px; }
        .ann-preview-badge { display:inline-flex; align-items:center; gap:5px; padding:4px 8px; border-radius:999px; background:#e4f6ec; color:#08734e; font-size:.68rem; font-weight:800; }
        .ann-preview-title { margin:11px 0 0; color:#173b30; font-size:1.03rem; line-height:1.25; }
        .ann-preview-meta { display:flex; flex-wrap:wrap; gap:7px 12px; margin-top:8px; color:#71827b; font-size:.72rem; }
        .ann-preview-description { margin:12px 0 0; color:#334155; font-size:.8rem; line-height:1.5; white-space:pre-line; }
        .ann-preview-media { display:none; width:100%; height:115px; object-fit:cover; background:linear-gradient(135deg,#d9f3e8,#eef8f3); }
        .ann-preview-media.is-visible { display:block; }
        .ann-preview-facts { display:grid; gap:7px; margin-top:13px; padding-top:12px; border-top:1px solid #edf2ef; }
        .ann-preview-fact { display:flex; align-items:flex-start; gap:7px; color:#52665d; font-size:.74rem; line-height:1.35; }
        .ann-preview-fact svg { flex:0 0 auto; margin-top:1px; color:#0b7656; }
        .ann-preview-files { display:grid; gap:6px; margin-top:12px; padding-top:12px; border-top:1px solid #edf2ef; }
        .ann-preview-file { overflow:hidden; color:#2563eb; font-size:.73rem; text-overflow:ellipsis; white-space:nowrap; }
        .ann-preview-placeholder { margin-top:12px; padding:10px; border-radius:9px; background:#f7faf8; color:#71827b; font-size:.74rem; line-height:1.4; }
        .ann-form-divider { height:1px; margin:16px 0 13px; background:#e9efec; }
        .ann-create-actions { position:sticky; bottom:12px; z-index:5; display:flex; justify-content:flex-end; gap:9px; flex-wrap:wrap; padding:10px; border:1px solid #dfeae4; border-radius:12px; background:rgba(255,255,255,.94); box-shadow:0 7px 20px rgba(15,55,43,.08); backdrop-filter:blur(8px); }
        .ann-create-actions button { padding:10px 16px !important; border-radius:9px !important; font-size:.84rem; transition:transform .15s ease,box-shadow .15s ease; }
        .ann-create-actions button:hover { transform:translateY(-1px); box-shadow:0 6px 16px rgba(15,55,43,.12); }
        @media(max-width:1100px) { .ann-composer { grid-template-columns:minmax(0,1fr); } .ann-preview-panel { position:static; } }
        @media(max-width:640px) { .ann-create-head { padding:18px; } .ann-create-body { padding:16px; } .ann-category-grid, .ann-form-grid, .ann-recruitment-grid { grid-template-columns:minmax(0,1fr); } .ann-field-span { grid-column:auto; } .ann-panel { padding:14px; } #ann-location-map { height:210px; } .ann-create-actions { justify-content:stretch; } .ann-create-actions button { flex:1; } }
    </style>

    @if(session('status'))
        <div class="card" style="border-left:4px solid #16a34a; background:#ecfdf5; color:#134e4a; padding:18px 22px; margin-bottom:20px">
            <div style="font-weight:700; margin-bottom:6px">Success</div>
            <div>{{ session('status') }}</div>
        </div>
    @endif
    @if($errors->any())
        <div class="card" role="alert" style="border-left:4px solid #dc2626; background:#fef2f2; color:#7f1d1d; padding:16px 20px; margin-bottom:20px">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="card ann-create-card">
        <div class="ann-create-head">
            <div class="ann-create-icon" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M7 3.75h7l4 4v12.5H7a2 2 0 0 1-2-2v-12.5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M14 4v4h4M8.5 12h7m-7 3.5h7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
            </div>
            <div>
                <div class="ann-create-eyebrow">Organization updates</div>
                <h3 class="ann-create-title">Create an announcement</h3>
                <p class="ann-create-intro">Share an opportunity, reminder, or update with the community.</p>
            </div>
        </div>
        <form action="{{ route('student_org.announcements.store') }}" method="POST" enctype="multipart/form-data"
              data-confirm-navigation
              data-confirm-title="Leave announcement creation?"
              data-confirm-body="You have unsaved changes. If you leave now, they will be lost."
              data-confirm-tone="warning"
              data-confirm-ok="Leave page"
              data-confirm-cancel="Keep editing">
            @csrf
            <div class="ann-create-body">
                <div class="ann-composer">
                    <div class="ann-composer-form">
                <fieldset class="ann-category-section">
                    <legend class="ann-category-label">Announcement type <span style="color:#b91c1c">*</span></legend>
                    <div class="ann-category-grid">
                        <label class="ann-category-card">
                            <input class="ann-category-radio" type="radio" name="category" value="Volunteer recruitment" required data-confirm-ignore @checked($selectedCategory === 'Volunteer recruitment')>
                            <span class="ann-category-mark" aria-hidden="true"><svg width="17" height="17" viewBox="0 0 24 24" fill="none"><path d="M8 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm8 1a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5ZM3.5 19a4.5 4.5 0 0 1 9 0v1h-9v-1Zm9.5 1v-.5a4 4 0 0 1 7.4-2.1c.38.64.6 1.38.6 2.1v.5h-8Z" fill="currentColor"/></svg></span>
                            <span>
                                <span class="ann-category-name">Volunteer recruitment</span>
                                <span class="ann-category-desc">Invite participants to apply for an approved activity.</span>
                            </span>
                        </label>
                        <label class="ann-category-card">
                            <input class="ann-category-radio" type="radio" name="category" value="General" required data-confirm-ignore @checked($selectedCategory === 'General')>
                            <span class="ann-category-mark" aria-hidden="true"><svg width="17" height="17" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="2"/><path d="M12 10.5v5m0-8h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
                            <span>
                                <span class="ann-category-name">General announcement</span>
                                <span class="ann-category-desc">Share news, reminders, or updates with no application required.</span>
                            </span>
                        </label>
                    </div>
                    @error('category')<div role="alert" style="color:#b91c1c; font-size:.88rem; margin-top:8px">{{ $message }}</div>@enderror
                </fieldset>

                <div class="ann-category-hint" role="status" aria-live="polite">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M12 11v5m0-8h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    <span id="announcement-category-hint"></span>
                </div>

                <section class="ann-panel" aria-labelledby="announcement-details-heading">
                    <div class="ann-panel-heading">
                        <span class="ann-panel-icon" aria-hidden="true"><svg width="17" height="17" viewBox="0 0 24 24" fill="none"><path d="M7 3.75h7l4 4v12.5H7a2 2 0 0 1-2-2v-12.5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M14 4v4h4M8.5 12h7m-7 3.5h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></span>
                        <div><h4 class="ann-panel-title" id="announcement-details-heading">Announcement details</h4><p class="ann-panel-copy">Give participants the essential information.</p></div>
                    </div>
                    <div class="ann-form-grid">
                <div class="ann-create-field ann-field-span" data-category-fields="linked-activity">
                    <label class="ann-form-label" for="announcement-project">Link an approved activity <span style="font-weight:500; color:#74857d">(optional for general announcements)</span></label>
                    <select id="announcement-project" name="project_id">
                        <option value="">No linked activity</option>
                        @foreach($approvedProjects as $project)
                            <option value="{{ $project->id }}" @selected((string) old('project_id') === (string) $project->id)>
                                {{ $project->title }} — {{ ucfirst(str_replace('_', ' ', $project->status)) }}
                                @if($existingRecruitments->has($project->id)) (recruitment already exists) @endif
                            </option>
                        @endforeach
                    </select>
                    <div class="ann-create-help" id="announcement-project-help">Select the approved activity participants will apply to.</div>
                    <div class="ann-create-help" id="existing-recruitment-notice" role="status" aria-live="polite" hidden></div>
                    @error('project_id')<div role="alert" style="color:#b91c1c; font-size:.88rem; margin-top:6px">{{ $message }}</div>@enderror
                </div>

                <div class="ann-create-field">
                    <label class="ann-form-label" for="announcement-title">Title</label>
                    <input id="announcement-title" type="text" name="title" required maxlength="255" value="{{ old('title') }}" placeholder="Give your announcement a clear title">
                    @error('title')<div role="alert" style="color:#b91c1c; font-size:.88rem; margin-top:6px">{{ $message }}</div>@enderror
                </div>

                <div class="ann-create-field">
                    <label class="ann-form-label" for="announcement-description">Description</label>
                    <textarea id="announcement-description" name="description" rows="4" maxlength="300" placeholder="Add the details participants need to know">{{ old('description') }}</textarea>
                    <div class="ann-description-meta"><span>Keep it clear and participant-friendly.</span><span id="announcement-description-count">0 / 300</span></div>
                    @error('description')<div role="alert" style="color:#b91c1c; font-size:.88rem; margin-top:6px">{{ $message }}</div>@enderror
                </div>

                    </div>
                </section>

                <section class="ann-panel ann-event-panel" data-category-fields="recruitment" aria-labelledby="announcement-event-heading">
                    <div class="ann-panel-heading">
                        <span class="ann-panel-icon" aria-hidden="true"><svg width="17" height="17" viewBox="0 0 24 24" fill="none"><rect x="4" y="5.5" width="16" height="14" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M8 3.5v4M16 3.5v4M4 10h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></span>
                        <div><h4 class="ann-panel-title" id="announcement-event-heading">Event information</h4><p class="ann-panel-copy">Help volunteers know when and where to join.</p></div>
                    </div>
                <div data-category-fields="recruitment" class="ann-recruitment-grid">
                    <div>
                        <label class="ann-form-label" for="announcement-slots-needed">Volunteers needed <span style="color:#b91c1c">*</span></label>
                        <input id="announcement-slots-needed" type="number" name="slots_needed" min="1" step="1" value="{{ old('slots_needed') }}" placeholder="e.g. 30" />
                        <div class="ann-create-help">Number of volunteer slots.</div>
                        @error('slots_needed')<div style="color:#b91c1c; font-size:0.92rem; margin-top:6px">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="ann-form-label" for="announcement-application-deadline">Application deadline</label>
                        <input id="announcement-application-deadline" type="datetime-local" name="application_deadline" min="{{ now('Asia/Manila')->format('Y-m-d\TH:i') }}" max="{{ now('Asia/Manila')->addYear()->startOfMinute()->format('Y-m-d\TH:i') }}" value="{{ old('application_deadline') }}" />
                        <div class="ann-create-help">Leave blank for no deadline.</div>
                        @error('application_deadline')<div style="color:#b91c1c; font-size:0.92rem; margin-top:6px">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="ann-event-field" data-category-fields="recruitment">
                    <label class="ann-form-label" for="announcement-event-date">Event date &amp; time <span style="font-weight:500; color:#74857d">(optional)</span></label>
                    <input id="announcement-event-date" type="datetime-local" name="event_date" min="{{ now('Asia/Manila')->format('Y-m-d\TH:i') }}" value="{{ old('event_date') }}" />
                    @error('event_date')<div style="color:#b91c1c; font-size:0.92rem; margin-top:6px">{{ $message }}</div>@enderror
                </div>

                <div class="ann-event-field" data-category-fields="recruitment">
                    <label class="ann-form-label" for="ann-map-search">Event location <span style="font-weight:500; color:#74857d">(optional)</span></label>
                    <div class="ann-map-search-wrap">
                        <input type="text" id="ann-map-search" name="loc_q_{{ Str::random(8) }}" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" placeholder="Search for a venue, building, or address..." />
                        <div id="ann-map-search-results" class="ann-map-search-results"></div>
                    </div>
                    <input type="text" id="event_location" name="event_location" value="{{ old('event_location') }}" placeholder="e.g. Barangay Poblacion Gymnasium" style="margin-top:8px;" />
                    <div id="ann-location-map"></div>
                    <button type="button" id="ann-use-my-location" class="ann-locate-btn">Use my current location</button>
                    <input type="hidden" id="latitude" name="latitude" value="{{ old('latitude') }}">
                    <input type="hidden" id="longitude" name="longitude" value="{{ old('longitude') }}">
                    <div class="ann-create-help">Click the map or choose a search result to pin the exact activity location.</div>
                    @error('event_location')<div style="color:#b91c1c; font-size:0.92rem; margin-top:6px">{{ $message }}</div>@enderror
                </div>

                </section>

                <section class="ann-panel" aria-labelledby="announcement-attachments-heading">
                    <div class="ann-panel-heading">
                        <span class="ann-panel-icon" aria-hidden="true"><svg width="17" height="17" viewBox="0 0 24 24" fill="none"><path d="M8.5 12.5 14 7a3.54 3.54 0 0 1 5 5l-7.5 7.5a5 5 0 0 1-7-7L12 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        <div><h4 class="ann-panel-title" id="announcement-attachments-heading">Attachments</h4><p class="ann-panel-copy">Add a guide, poster, or supporting document.</p></div>
                    </div>
                    <div>
                    <label class="ann-upload-dropzone" for="announcement-attachments">
                        <span class="ann-upload-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 16V4m0 0L7.5 8.5M12 4l4.5 4.5M5 14.5v3A2.5 2.5 0 0 0 7.5 20h9a2.5 2.5 0 0 0 2.5-2.5v-3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        <span><strong>Drag and drop files here, or click to browse</strong><small>JPG, PNG, or PDF. Up to 5 files, 5MB each.</small></span>
                    </label>
                    <input id="announcement-attachments" type="file" name="attachments[]" multiple accept="image/jpeg,image/png,application/pdf" hidden />
                    <div id="announcement-attachment-error" role="alert" style="display:none; margin-top:7px; color:#b91c1c; font-size:.8rem;"></div>
                    @error('attachments')<div role="alert" style="margin-top:7px; color:#b91c1c; font-size:.8rem">{{ $message }}</div>@enderror
                    @foreach($errors->getMessages() as $field => $messages)
                        @if(str_starts_with($field, 'attachments.'))
                            @foreach($messages as $message)
                                <div role="alert" style="margin-top:7px; color:#b91c1c; font-size:.8rem">{{ $message }}</div>
                            @endforeach
                        @endif
                    @endforeach
                    <div id="announcement-attachment-list" class="ann-upload-list" style="display:none"></div>
                    </div>
                </section>

                <div class="ann-form-divider"></div>
                <div class="ann-create-actions">
                    <button type="submit" name="status" value="draft" style="padding:14px 22px; border-radius:12px; background:#f8fafc; color:#0b2a2a; border:1px solid #e2e8f0; font-weight:700;">Save draft</button>
                    <button type="submit" name="status" value="published" style="padding:14px 22px; border-radius:12px; background:#16a34a; color:#ffffff; border:none; font-weight:700;">Publish announcement</button>
                </div>

                    </div>

                    <aside class="ann-preview-panel" aria-label="Announcement preview">
                        <div class="ann-preview-head"><span class="ann-preview-kicker">Live preview</span><span class="ann-preview-status">Draft preview</span></div>
                        <div class="ann-preview-card">
                            <img id="announcement-preview-media" class="ann-preview-media" alt="Selected announcement image preview">
                            <div class="ann-preview-card-body">
                                <span id="announcement-preview-badge" class="ann-preview-badge">Volunteer recruitment</span>
                                <h4 id="announcement-preview-title" class="ann-preview-title">Your announcement title</h4>
                                <div class="ann-preview-meta"><span>ProjectLink organization</span><span id="announcement-preview-project" hidden></span></div>
                                <p id="announcement-preview-description" class="ann-preview-description">Your announcement description will appear here.</p>
                                <div id="announcement-preview-facts" class="ann-preview-facts">
                                    <div class="ann-preview-fact"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M8 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm8 1a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5ZM3.5 19a4.5 4.5 0 0 1 9 0v1h-9v-1Zm9.5 1v-.5a4 4 0 0 1 7.4-2.1c.38.64.6 1.38.6 2.1v.5h-8Z" fill="currentColor"/></svg><span id="announcement-preview-slots">Volunteers needed: Not set</span></div>
                                    <div class="ann-preview-fact"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><rect x="4" y="5.5" width="16" height="14" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M8 3.5v4M16 3.5v4M4 10h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg><span id="announcement-preview-deadline">Application deadline: No deadline</span></div>
                                    <div class="ann-preview-fact"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.8"/><path d="M12 7.5V12l3 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg><span id="announcement-preview-event">Event date: To be announced</span></div>
                                    <div class="ann-preview-fact"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M19 10.5c0 4.5-7 10-7 10s-7-5.5-7-10a7 7 0 1 1 14 0Z" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="10.5" r="2.2" stroke="currentColor" stroke-width="1.8"/></svg><span id="announcement-preview-location">Location: To be announced</span></div>
                                </div>
                                <div id="announcement-preview-files" class="ann-preview-files" hidden></div>
                                <div id="announcement-preview-placeholder" class="ann-preview-placeholder">Your finished announcement will be shown to participants in this format.</div>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </form>
    </div>

    <div class="card" style="margin-top:24px; padding:24px;">
        <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px">
            <div style="width:42px; height:42px; border-radius:14px; display:flex; align-items:center; justify-content:center; background:#eef2ff; color:#4338ca;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 9h2v6H4V9zm3 2h10l3-3v8l-3-3H7v-2z" fill="currentColor"/></svg>
            </div>
            <h3 style="margin:0">From the CELP Secretary</h3>
        </div>
        @if($broadcastAnnouncements->isEmpty())
            <div style="color:#52665d">No notices from the CELP Secretary right now.</div>
        @else
            <div style="display:grid; gap:14px;">
                @foreach($broadcastAnnouncements as $announcement)
                    <div style="padding:18px; border-radius:16px; border:1px solid #e5e7eb; background:#f8fafc;">
                        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                            <div style="font-size:1rem; font-weight:700; color:#0f172a;">{{ $announcement->title }}</div>
                            <span style="font-size:0.78rem; font-weight:700; padding:4px 10px; border-radius:999px; background:#eef2ff; color:#4338ca">{{ $announcement->category }}</span>
                        </div>
                        <div style="font-size:0.85rem; color:#52665d; margin-top:6px;">Posted {{ $announcement->published_at?->diffForHumans() ?? $announcement->created_at->diffForHumans() }}</div>
                        @if($announcement->description)
                            <div style="margin-top:10px; color:#334155; line-height:1.6; white-space:pre-line;">{{ $announcement->description }}</div>
                        @endif
                        @if($announcement->attachments->isNotEmpty())
                            <div style="margin-top:12px; display:flex; flex-wrap:wrap; gap:10px;">
                                @foreach($announcement->attachments as $attachment)
                                    <a href="{{ asset('storage/'.$attachment->file_path) }}" target="_blank" rel="noopener" style="font-size:0.85rem; color:#2563eb; text-decoration:none; font-weight:600;">📎 {{ $attachment->original_name }}</a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="card" style="margin-top:24px;">
        <h3 style="margin-top:0; margin-bottom:18px">Your announcements</h3>
        @if($announcements->isEmpty())
            <div>No announcements created yet.</div>
        @else
            <div style="display:grid; gap:14px;">
                @foreach($announcements as $announcement)
                    <div style="padding:18px; border-radius:16px; border:1px solid #e5e7eb; background:#ffffff; display:flex; justify-content:space-between; gap:16px; align-items:center; flex-wrap:wrap;">
                        <a href="{{ route('student_org.announcements.show', $announcement) }}" style="text-decoration:none; color:inherit; flex:1; min-width:220px;">
                            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                                <div style="font-size:1rem; font-weight:700; color:#0f172a;">{{ $announcement->title }}</div>
                                @if($announcement->slots_needed)
                                    @if($announcement->participantVisibilityIssue())
                                            <span title="{{ $announcement->participantVisibilityIssue() }}" style="font-size:0.75rem; font-weight:700; padding:3px 9px; border-radius:999px; background:#fffbeb; color:#92400e;">⚠️ {{ $announcement->participantVisibilityIssue() }}</span>
                                        @elseif($announcement->participantApplicationIssue())
                                            <span title="{{ $announcement->participantApplicationIssue() }}" style="font-size:0.75rem; font-weight:700; padding:3px 9px; border-radius:999px; background:#fffbeb; color:#92400e;">⚠️ {{ $announcement->participantApplicationIssue() }}</span>
                                        @else
                                            <span style="font-size:0.75rem; font-weight:700; padding:3px 9px; border-radius:999px; background:#ecfdf5; color:#065f46;">✅ Visible to volunteers</span>
                                        @endif
                                @endif
                            </div>
                            <div style="font-size:0.9rem; color:#52665d; margin-top:6px;">{{ $announcement->category }} · {{ ucfirst($announcement->status) }} · {{ $announcement->created_at->diffForHumans() }}</div>
                        </a>
                        <div style="display:flex; gap:10px; align-items:center;">
                            <span style="font-size:0.85rem; color:#475569;">{{ $announcement->attachments->count() }} files</span>
                            <a href="{{ route('student_org.announcements.show', $announcement) }}" style="color:#2563eb; text-decoration:none; font-weight:700;">View</a>
                            @if($announcement->category === 'Volunteer recruitment')
                                <a href="{{ route('student_org.announcements.edit', $announcement) }}" style="color:#0b7656; text-decoration:none; font-weight:700;">Edit</a>
                            @endif
                            <form action="{{ route('student_org.announcements.status', $announcement) }}" method="POST"
                                  @if($announcement->status === 'published')
                                      data-confirm
                                      data-confirm-title="Unpublish announcement?"
                                      data-confirm-body="{name} will be hidden from participants and volunteer applications."
                                      data-confirm-name="{{ $announcement->title }}"
                                      data-confirm-ok="Unpublish"
                                      data-confirm-cancel="Keep published"
                                  @endif>
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="{{ $announcement->status === 'published' ? 'draft' : 'published' }}">
                                <button type="submit" style="color:{{ $announcement->status === 'published' ? '#b45309' : '#08734e' }}; background:none; border:none; padding:0; font-weight:700; cursor:pointer; font-size:0.95rem; text-decoration:underline;">
                                    {{ $announcement->status === 'published' ? 'Unpublish' : 'Publish' }}
                                </button>
                            </form>
                            @if($announcement->category === 'Volunteer recruitment')
                                <span style="font-size:0.82rem; color:#52665d;">Recruitment retained for this activity</span>
                            @else
                            <form action="{{ route('student_org.announcements.destroy', $announcement) }}" method="POST" data-confirm
                                  data-confirm-title="Remove announcement?"
                                  data-confirm-body="{name} will be deleted and will no longer be visible to volunteers."
                                  data-confirm-name="{{ $announcement->title }}"
                                  data-confirm-note="This cannot be undone."
                                  data-confirm-ok="Remove announcement"
                                  data-confirm-cancel="Keep it">
                                @csrf
                                @method('DELETE')
                                <button type="submit" style="color:#b91c1c; background:none; border:none; padding:0; font-weight:700; cursor:pointer; font-size:0.95rem; text-decoration:underline;">Remove</button>
                            </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const input = document.getElementById('announcement-attachments');
            const list = document.getElementById('announcement-attachment-list');
            const error = document.getElementById('announcement-attachment-error');
            const dropzone = document.querySelector('.ann-upload-dropzone');
            if (!input || !list || !error || !dropzone) return;

            const maxFiles = 5;
            const maxBytes = 5 * 1024 * 1024;
            const selectedFiles = [];
            let previewUrls = [];

            const syncInput = () => {
                const transfer = new DataTransfer();
                selectedFiles.forEach((file) => transfer.items.add(file));
                input.files = transfer.files;
            };

            const render = () => {
                previewUrls.forEach((url) => URL.revokeObjectURL(url));
                previewUrls = [];
                list.replaceChildren();
                list.style.display = selectedFiles.length ? 'grid' : 'none';

                selectedFiles.forEach((file, index) => {
                    const row = document.createElement('div');
                    row.className = 'ann-upload-file';

                    const info = document.createElement('div');
                    info.className = 'ann-upload-file-name';
                    info.append(document.createTextNode(file.name));
                    const size = document.createElement('span');
                    size.className = 'ann-upload-file-size';
                    size.textContent = `${(file.size / 1024 / 1024).toFixed(2)} MB`;
                    info.appendChild(size);
                    row.appendChild(info);

                    const preview = document.createElement('a');
                    preview.className = 'ann-upload-action';
                    preview.textContent = 'View';
                    preview.href = URL.createObjectURL(file);
                    preview.target = '_blank';
                    preview.rel = 'noopener';
                    previewUrls.push(preview.href);
                    row.appendChild(preview);

                    const remove = document.createElement('button');
                    remove.type = 'button';
                    remove.className = 'ann-upload-action ann-upload-remove';
                    remove.textContent = 'Remove';
                    remove.addEventListener('click', () => {
                        selectedFiles.splice(index, 1);
                        syncInput();
                        render();
                        input.dispatchEvent(new Event('input', { bubbles: true }));
                    });
                    row.appendChild(remove);
                    list.appendChild(row);
                });
            };

            const appendFiles = (incoming) => {
                const duplicate = incoming.some((file) => selectedFiles.some((selected) =>
                    selected.name === file.name
                    && selected.size === file.size
                    && selected.lastModified === file.lastModified
                ));
                const tooLarge = incoming.some((file) => file.size > maxBytes);

                if (selectedFiles.length + incoming.length > maxFiles) {
                    error.textContent = 'You may upload up to 5 files only. Remove a file before adding another.';
                    error.style.display = 'block';
                } else if (tooLarge) {
                    error.textContent = 'Each attachment must be 5MB or smaller.';
                    error.style.display = 'block';
                } else if (duplicate) {
                    error.textContent = 'The same file cannot be selected more than once.';
                    error.style.display = 'block';
                } else {
                    error.textContent = '';
                    error.style.display = 'none';
                    selectedFiles.push(...incoming);
                }

                syncInput();
                render();
            };

            input.addEventListener('change', () => appendFiles(Array.from(input.files)));
            ['dragenter', 'dragover'].forEach((eventName) => {
                dropzone.addEventListener(eventName, (event) => {
                    event.preventDefault();
                    dropzone.classList.add('is-dragging');
                });
            });
            ['dragleave', 'drop'].forEach((eventName) => {
                dropzone.addEventListener(eventName, (event) => {
                    event.preventDefault();
                    dropzone.classList.remove('is-dragging');
                });
            });
            dropzone.addEventListener('drop', (event) => {
                appendFiles(Array.from(event.dataTransfer?.files || []));
                input.dispatchEvent(new Event('input', { bubbles: true }));
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const categoryInputs = document.querySelectorAll('input[name="category"]');
            const titleInput = document.getElementById('announcement-title');
            const descriptionInput = document.getElementById('announcement-description');
            const descriptionCount = document.getElementById('announcement-description-count');
            const projectInput = document.getElementById('announcement-project');
            const slotsInput = document.getElementById('announcement-slots-needed');
            const deadlineInput = document.getElementById('announcement-application-deadline');
            const eventInput = document.getElementById('announcement-event-date');
            const locationInput = document.getElementById('event_location');
            const attachmentInput = document.getElementById('announcement-attachments');
            const previewTitle = document.getElementById('announcement-preview-title');
            const previewBadge = document.getElementById('announcement-preview-badge');
            const previewProject = document.getElementById('announcement-preview-project');
            const previewDescription = document.getElementById('announcement-preview-description');
            const previewFacts = document.getElementById('announcement-preview-facts');
            const previewSlots = document.getElementById('announcement-preview-slots');
            const previewDeadline = document.getElementById('announcement-preview-deadline');
            const previewEvent = document.getElementById('announcement-preview-event');
            const previewLocation = document.getElementById('announcement-preview-location');
            const previewFiles = document.getElementById('announcement-preview-files');
            const previewMedia = document.getElementById('announcement-preview-media');
            const previewPlaceholder = document.getElementById('announcement-preview-placeholder');

            if (!categoryInputs.length || !titleInput || !descriptionInput || !projectInput || !previewTitle) return;

            let previewMediaUrl = null;
            const formatDate = (value, label, fallback) => {
                if (!value) return `${label}: ${fallback}`;
                const date = new Date(value);
                if (Number.isNaN(date.getTime())) return `${label}: ${value}`;
                return `${label}: ${date.toLocaleString([], { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' })}`;
            };

            const syncPreview = () => {
                const isRecruitment = document.querySelector('input[name="category"]:checked')?.value === 'Volunteer recruitment';
                const title = titleInput.value.trim();
                const description = descriptionInput.value.trim();
                const selectedProject = projectInput.value
                    ? projectInput.options[projectInput.selectedIndex].textContent.trim().replace(/\s+/g, ' ')
                    : '';
                const files = attachmentInput ? Array.from(attachmentInput.files || []) : [];

                previewBadge.textContent = isRecruitment ? 'Volunteer recruitment' : 'General announcement';
                previewTitle.textContent = title || 'Your announcement title';
                previewDescription.textContent = description || 'Your announcement description will appear here.';
                previewProject.textContent = selectedProject ? `Linked activity: ${selectedProject}` : '';
                previewProject.hidden = !selectedProject;
                previewFacts.hidden = !isRecruitment;
                if (descriptionCount) descriptionCount.textContent = `${descriptionInput.value.length} / 300`;

                if (isRecruitment) {
                    previewSlots.textContent = `Volunteers needed: ${slotsInput?.value || 'Not set'}`;
                    previewDeadline.textContent = formatDate(deadlineInput?.value, 'Application deadline', 'No deadline');
                    previewEvent.textContent = formatDate(eventInput?.value, 'Event date', 'To be announced');
                    previewLocation.textContent = `Location: ${locationInput?.value.trim() || 'To be announced'}`;
                }

                previewFiles.replaceChildren();
                previewFiles.hidden = files.length === 0;
                files.forEach((file) => {
                    const item = document.createElement('div');
                    item.className = 'ann-preview-file';
                    item.textContent = file.name;
                    previewFiles.appendChild(item);
                });

                if (previewMediaUrl) URL.revokeObjectURL(previewMediaUrl);
                const imageFile = files.find((file) => file.type.startsWith('image/'));
                if (imageFile) {
                    previewMediaUrl = URL.createObjectURL(imageFile);
                    previewMedia.src = previewMediaUrl;
                    previewMedia.classList.add('is-visible');
                } else {
                    previewMediaUrl = null;
                    previewMedia.removeAttribute('src');
                    previewMedia.classList.remove('is-visible');
                }

                previewPlaceholder.hidden = Boolean(title || description || files.length);
            };

            [titleInput, descriptionInput, projectInput, slotsInput, deadlineInput, eventInput, locationInput, attachmentInput]
                .filter(Boolean)
                .forEach((element) => element.addEventListener('input', syncPreview));
            [projectInput, slotsInput, deadlineInput, eventInput, locationInput, attachmentInput]
                .filter(Boolean)
                .forEach((element) => element.addEventListener('change', syncPreview));
            categoryInputs.forEach((input) => input.addEventListener('change', syncPreview));
            syncPreview();
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const categoryInputs = document.querySelectorAll('input[name="category"]');
            const project = document.getElementById('announcement-project');
            const projectHelp = document.getElementById('announcement-project-help');
            const categoryHint = document.getElementById('announcement-category-hint');
            if (!categoryInputs.length || !project || !projectHelp || !categoryHint) return;
            const recruitmentFields = document.querySelectorAll('[data-category-fields="recruitment"]');
            const slotsNeeded = document.getElementById('announcement-slots-needed');
            const existingRecruitments = @json($existingRecruitments->all());
            const recruitmentNotice = document.getElementById('existing-recruitment-notice');
            const submitButtons = document.querySelectorAll('button[type="submit"][name="status"]');
            const syncCategoryFields = () => {
                const isRecruitment = document.querySelector('input[name="category"]:checked')?.value === 'Volunteer recruitment';
                const existing = isRecruitment ? existingRecruitments[project.value] || [] : [];

                project.required = isRecruitment;
                slotsNeeded.required = isRecruitment;
                projectHelp.textContent = isRecruitment
                    ? 'Select the approved activity participants will apply to.'
                    : 'Optional: link this notice to an approved activity for context, or leave it unlinked for an organization-wide announcement.';
                categoryHint.textContent = isRecruitment
                    ? 'Participants can apply to volunteer through this announcement. Choose an approved activity and complete the recruitment details below.'
                    : 'This is an information-only post. It can be shared with everyone, with no volunteer application or map.';

                recruitmentFields.forEach((field) => {
                    field.hidden = !isRecruitment;
                    field.querySelectorAll('input, select, textarea, button').forEach((input) => {
                        input.disabled = !isRecruitment;
                    });
                });

                submitButtons.forEach((button) => {
                    button.disabled = existing.length > 0;
                });
                recruitmentNotice.hidden = existing.length === 0;
                recruitmentNotice.replaceChildren();
                if (existing.length > 0) {
                    recruitmentNotice.append(document.createTextNode(
                        'This activity already has a recruitment announcement. Edit the existing post instead: '
                    ));
                    existing.forEach((announcement, index) => {
                        if (index > 0) recruitmentNotice.append(document.createTextNode(' · '));
                        const link = document.createElement('a');
                        link.href = announcement.url;
                        link.textContent = announcement.title + ' (' + announcement.status + ')';
                        link.style.color = '#08734e';
                        link.style.fontWeight = '700';
                        recruitmentNotice.append(link);
                    });
                }

                const map = document.getElementById('ann-location-map')?._leafletMap;
                if (isRecruitment && map) {
                    window.setTimeout(() => map.invalidateSize(), 150);
                }
            };

            categoryInputs.forEach((input) => input.addEventListener('change', syncCategoryFields));
            project.addEventListener('change', syncCategoryFields);
            syncCategoryFields();
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const latInput = document.getElementById('latitude');
            const lngInput = document.getElementById('longitude');
            const locationInput = document.getElementById('event_location');
            const mapEl = document.getElementById('ann-location-map');
            if (!latInput || !mapEl || !window.L) return;

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

            const map = L.map(mapEl).setView([hasInitial ? initialLat : fallbackLat, hasInitial ? initialLng : fallbackLng], hasInitial ? 17 : 6);
            mapEl._leafletMap = map;
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
                    });
                }
                if (recenter) map.setView([lat, lng], Math.max(map.getZoom(), 17));
            };

            if (hasInitial) placeMarker(initialLat, initialLng, false);

            map.on('click', (event) => {
                placeMarker(event.latlng.lat, event.latlng.lng, false);
                updateFields(event.latlng.lat, event.latlng.lng);
            });

            const searchInput = document.getElementById('ann-map-search');
            const resultsBox = document.getElementById('ann-map-search-results');
            let searchTimer = null;
            let requestId = 0;

            const escapeHtml = (text) => text.replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));

            searchInput.addEventListener('input', () => {
                clearTimeout(searchTimer);
                const query = searchInput.value.trim();
                if (query.length < 3) {
                    resultsBox.style.display = 'none';
                    resultsBox.innerHTML = '';
                    return;
                }
                const thisRequest = ++requestId;
                resultsBox.innerHTML = '<div class="ann-map-search-item" style="cursor:default; color:#5b7067">Searching...</div>';
                resultsBox.style.display = 'block';
                searchTimer = setTimeout(() => {
                    fetch(`https://nominatim.openstreetmap.org/search?format=json&limit=6&q=${encodeURIComponent(query)}`)
                        .then((response) => response.json())
                        .then((results) => {
                            if (thisRequest !== requestId) return;
                            if (!results.length) {
                                resultsBox.innerHTML = '<div class="ann-map-search-item" style="cursor:default; color:#5b7067">No results found.</div>';
                                return;
                            }
                            resultsBox.innerHTML = results.map((result) => `
                                <div class="ann-map-search-item" data-lat="${result.lat}" data-lng="${result.lon}" data-label="${escapeHtml(result.display_name)}">${escapeHtml(result.display_name)}</div>
                            `).join('');
                        })
                        .catch(() => {
                            if (thisRequest !== requestId) return;
                            resultsBox.innerHTML = '<div class="ann-map-search-item" style="cursor:default; color:#5b7067">Search unavailable. Try clicking the map instead.</div>';
                        });
                }, 400);
            });

            resultsBox.addEventListener('click', (event) => {
                const item = event.target.closest('.ann-map-search-item[data-lat]');
                if (!item) return;
                const lat = parseFloat(item.dataset.lat);
                const lng = parseFloat(item.dataset.lng);
                placeMarker(lat, lng, true);
                updateFields(lat, lng);
                if (locationInput && !locationInput.value) locationInput.value = item.dataset.label;
                searchInput.value = item.dataset.label;
                resultsBox.style.display = 'none';
                resultsBox.innerHTML = '';
            });

            document.addEventListener('click', (event) => {
                if (!resultsBox.contains(event.target) && event.target !== searchInput) {
                    resultsBox.style.display = 'none';
                }
            });

            const locateBtn = document.getElementById('ann-use-my-location');
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
        });
    </script>
@endsection
