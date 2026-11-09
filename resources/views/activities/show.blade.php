@extends('layouts.admin')

@section('title', $announcement->title)
@section('subtitle', 'Activity details')

@section('content')
    @if($announcement->latitude && $announcement->longitude)
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    @endif

    <style>
        .av-fact { padding:16px; border-radius:14px; background:#f8fafc; border:1px solid #e2e8f0; }
        .av-fact-label { font-size:0.78rem; color:#94a3b8; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; }
        .av-fact-value { margin-top:6px; font-weight:600; color:#0f172a; }
        .av-tag { font-size:0.76rem; font-weight:700; padding:4px 11px; border-radius:999px; white-space:nowrap; }
        .av-notice { margin-top:26px; padding:14px 18px; border-radius:14px; background:#f8fafc;
                     border:1px solid #e2e8f0; color:#475569; font-size:0.9rem; display:flex; gap:10px; align-items:flex-start; }
        #av-map { aspect-ratio:16/9; max-height:380px; min-height:260px; border-radius:16px; margin-top:20px; border:1px solid #d1d5db; }
        .leaflet-control-layers { border-radius:12px !important; overflow:hidden; box-shadow:0 10px 25px rgba(15,23,42,0.15) !important; border:none !important; }
        .leaflet-bar { border-radius:10px !important; overflow:hidden; border:none !important; box-shadow:0 6px 16px rgba(15,23,42,0.15) !important; }
        .leaflet-control-scale-line { border-radius:0 0 6px 6px !important; background:rgba(255,255,255,0.85) !important; }
        .leaflet-control-attribution { padding:2px 5px !important; font-size:10px !important; line-height:1.3 !important; }
    </style>

    <a href="{{ route('activities.index') }}" style="display:inline-flex; align-items:center; gap:8px; color:#2563eb; text-decoration:none; font-weight:700; margin-bottom:18px;">&larr; Back to activities</a>

    @php
        $isRecruiting = $announcement->slots_needed !== null;
        $isPast = $announcement->event_date && $announcement->event_date->isPast();
        $deadlinePassed = $announcement->application_deadline && $announcement->application_deadline->isPast();
    @endphp

    <div class="card" style="padding:28px">
        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap">
            <h2 style="margin:0; font-size:1.5rem; color:#0f172a;">{{ $announcement->title }}</h2>
            @if($announcement->category)
                <span class="av-tag" style="background:#eef2ff; color:#4338ca">{{ $announcement->category }}</span>
            @endif
            @if($isPast)
                <span class="av-tag" style="background:#f1f5f9; color:#475569">Completed</span>
            @elseif($isRecruiting && $announcement->isFull())
                <span class="av-tag" style="background:#fef2f2; color:#b91c1c">Full</span>
            @elseif($isRecruiting && $deadlinePassed)
                <span class="av-tag" style="background:#fff7ed; color:#9a3412">Applications closed</span>
            @elseif($isRecruiting)
                <span class="av-tag" style="background:#dcfce7; color:#166534">Recruiting</span>
            @endif
        </div>

        {{-- Links through to the organizer's About Us profile, so "who is running
             this?" is one click away from any activity. --}}
        <div style="margin-top:8px">
            @if($announcement->creator)
                <a href="{{ route('organizations.show', $announcement->creator) }}"
                   style="display:inline-flex; align-items:center; gap:9px; color:#0b6a4a; font-weight:600; text-decoration:none">
                    <x-organization-avatar :organization="$announcement->creator" :size="30" />
                    {{ $announcement->creator->organization_name ?? $announcement->creator->name }}
                    <span style="font-weight:400; color:#94a3b8; font-size:0.86rem">· view profile</span>
                </a>
            @else
                <span style="color:#64748b">Unknown organization</span>
            @endif
        </div>

        @if($announcement->project)
            <div style="color:#64748b; font-size:0.88rem; margin-top:4px">
                Under project: <strong style="color:#334155">{{ $announcement->project->title }}</strong>
                &middot; {{ \App\Models\Project::statusLabel($announcement->project->status) }}
            </div>
        @endif

        @if($announcement->description)
            <p style="margin-top:18px; color:#334155; line-height:1.7; white-space:pre-line;">{{ $announcement->description }}</p>
        @endif

        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:14px; margin-top:22px">
            <div class="av-fact">
                <div class="av-fact-label">Schedule</div>
                <div class="av-fact-value">{{ $announcement->event_date ? $announcement->event_date->format('M j, Y g:i A') : 'To be announced' }}</div>
            </div>
            <div class="av-fact">
                <div class="av-fact-label">Location</div>
                <div class="av-fact-value">{{ $announcement->event_location ?? 'To be announced' }}</div>
            </div>
            @if($isRecruiting)
                <div class="av-fact">
                    <div class="av-fact-label">Volunteers</div>
                    <div class="av-fact-value">{{ $approvedVolunteers }} of {{ $announcement->slots_needed }} confirmed</div>
                </div>
                <div class="av-fact">
                    <div class="av-fact-label">Application deadline</div>
                    <div class="av-fact-value">{{ $announcement->application_deadline ? $announcement->application_deadline->timezone('Asia/Manila')->format('M j, Y g:i A') : 'Open' }}</div>
                </div>
            @endif
        </div>

        @if($announcement->latitude && $announcement->longitude)
            <div id="av-map" data-lat="{{ $announcement->latitude }}" data-lng="{{ $announcement->longitude }}"></div>
        @endif

        @if($announcement->attachments->isNotEmpty())
            <div style="margin-top:24px">
                <h4 style="margin:0 0 12px; color:#0f172a">Attachments</h4>
                <div style="display:grid; gap:10px">
                    @foreach($announcement->attachments as $attachment)
                        <a href="{{ asset('storage/'.$attachment->file_path) }}" target="_blank" rel="noopener"
                           style="display:flex; align-items:center; gap:14px; padding:12px 16px; border-radius:14px; border:1px solid #e5e7eb; background:#fff; text-decoration:none; color:inherit">
                            <div style="width:38px; height:38px; border-radius:10px; background:#eef2ff; color:#4338ca; display:flex; align-items:center; justify-content:center;">📄</div>
                            <div style="font-weight:600; color:#0f172a">{{ $attachment->original_name }}</div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- This directory is awareness-only: joining is a participant action. --}}
        <div class="av-notice">
            <span aria-hidden="true">👁️</span>
            <div>You're viewing this activity for oversight. Volunteer applications are handled by participant accounts, so there's no action to take here.</div>
        </div>
    </div>

    @if($announcement->latitude && $announcement->longitude)
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const el = document.getElementById('av-map');
                if (!el || typeof L === 'undefined') return;

                const lat = parseFloat(el.dataset.lat);
                const lng = parseFloat(el.dataset.lng);
                if (Number.isNaN(lat) || Number.isNaN(lng)) return;

                const map = L.map(el).setView([lat, lng], 16);
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
                L.marker([lat, lng]).addTo(map);
            });
        </script>
    @endif
@endsection
