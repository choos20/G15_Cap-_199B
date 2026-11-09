@extends('layouts.admin')

@section('title', $org->organization_name ?? $org->name)
@section('subtitle', 'Organization profile')

@section('content')
    @php
        $logo = $org->orgLogoUrl();
        $goals = $org->goalList();
        $hasProfile = filled($org->org_description) || filled($org->org_vision)
            || filled($org->org_mission) || $goals || $org->officers->isNotEmpty()
            || $org->role === 'student_org';
    @endphp

    <style>
        .op-hero { background:linear-gradient(180deg,#0e2a2b 0%,#12463a 100%); border-radius:18px 18px 0 0; height:96px; }
        .op-head { background:#fff; border-radius:0 0 18px 18px; padding:0 28px 24px; margin-bottom:18px;
                   box-shadow:0 6px 18px rgba(11,42,42,0.06); }
        .op-logo { width:84px; height:84px; border-radius:20px; border:3px solid #fff; background:#f8fafc; margin-top:-42px;
                   display:flex; align-items:center; justify-content:center; overflow:hidden; font-size:2rem;
                   box-shadow:0 6px 18px rgba(11,42,42,0.12); }
        .op-logo img { width:100%; height:100%; object-fit:cover; }
        .op-title { font-size:1.5rem; font-weight:700; color:#0f172a; margin:14px 0 0; }
        .op-sub { color:#64748b; font-size:0.92rem; margin-top:5px; }
        .op-tags { display:flex; flex-wrap:wrap; gap:8px; margin-top:14px; }
        .op-tag { background:#ecfdf5; color:#047857; font-size:0.78rem; font-weight:700; padding:5px 12px; border-radius:999px; }
        .op-badge { display:inline-flex; align-items:center; gap:7px; background:#ecfdf5; color:#047857;
                    font-size:0.82rem; font-weight:700; padding:7px 14px; border-radius:999px; }

        .op-card { background:#fff; border-radius:16px; padding:24px; box-shadow:0 6px 18px rgba(11,42,42,0.06); margin-bottom:18px; }
        .op-card h3 { margin:0 0 14px; font-size:1rem; color:#0f172a; display:flex; align-items:center; gap:9px; }
        .op-icon { width:24px; height:24px; border-radius:7px; background:#ecfdf5; color:#047857;
                   display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .op-body { color:#334155; line-height:1.75; white-space:pre-line; }
        .op-two { display:grid; grid-template-columns:1fr 1fr; gap:18px; }
        @media (max-width:820px) { .op-two { grid-template-columns:1fr; } }

        .op-goal { display:flex; gap:14px; align-items:flex-start; padding:11px 0; border-bottom:1px solid #f1f5f9; }
        .op-goal:last-child { border-bottom:none; }
        .op-goal-n { width:24px; height:24px; border-radius:50%; background:#ecfdf5; color:#047857; font-size:0.78rem;
                     font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0; }

        .op-officers { display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:18px; }
        .op-officer { text-align:center; }
        .op-avatar { width:54px; height:54px; border-radius:50%; background:#0b6a4a; color:#fff; font-weight:700;
                     display:flex; align-items:center; justify-content:center; margin:0 auto 10px; font-size:0.95rem; }
        .op-of-name { font-weight:700; color:#0f172a; font-size:0.9rem; }
        .op-of-pos { color:#64748b; font-size:0.82rem; margin-top:2px; }

        .op-activity { display:flex; gap:14px; align-items:flex-start; padding:14px 0; border-bottom:1px solid #f1f5f9; }
        .op-activity:last-child { border-bottom:none; }
        .op-act-title { font-weight:700; color:#0f172a; }
        .op-act-meta { color:#64748b; font-size:0.85rem; margin-top:3px; }
        .op-cat { background:#eff6ff; color:#1d4ed8; font-size:0.72rem; font-weight:700; padding:4px 10px;
                  border-radius:999px; white-space:nowrap; flex-shrink:0; }

        .op-foot { display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;
                   color:#64748b; font-size:0.85rem; padding:16px 4px 0; }
        .op-foot a { color:#0b6a4a; font-weight:700; text-decoration:none; }
        .op-foot a:hover { text-decoration:underline; }
        .op-empty { padding:36px; text-align:center; color:#94a3b8; }
        .op-back-link { display:inline-flex; align-items:center; margin-bottom:16px; padding:9px 14px; border:1px solid #bfdbfe;
                        border-radius:9px; background:#eff6ff; color:#1d4ed8; font-weight:700; text-decoration:none;
                        transition:background-color 160ms ease,border-color 160ms ease; }
        .op-back-link:hover { border-color:#93c5fd; background:#dbeafe; }
        .op-back-link:focus-visible { outline:3px solid rgba(37,99,235,0.35); outline-offset:2px; }
    </style>

    <a class="op-back-link" href="{{ route('organizations.index') }}">Back to all organizations</a>

    <div class="op-hero"></div>
    <div class="op-head">
        <div style="display:flex; align-items:flex-end; justify-content:space-between; gap:16px; flex-wrap:wrap">
            <div class="op-logo">
                @if($logo)<img src="{{ $logo }}" alt="{{ $org->organization_name }} logo">@else🏢@endif
            </div>
            <div style="display:flex; gap:10px; align-items:center; padding-bottom:4px">
                <span class="op-badge">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Recognized organization
                </span>
                @if($isOwner)
                    <a href="{{ route('org.about.edit') }}" style="background:#0b6a4a; color:#fff; padding:9px 16px; border-radius:10px; text-decoration:none; font-weight:700; font-size:0.85rem">Edit profile</a>
                @endif
            </div>
        </div>

        <h2 class="op-title">{{ $org->organization_name ?? $org->name }}</h2>
        <div class="op-sub">
            {{ collect([
                $org->org_acronym,
                $org->org_college ?: role_label($org->role),
                $org->org_year_founded ? 'Founded ' . $org->org_year_founded : null,
            ])->filter()->implode(' · ') }}
        </div>

        @if($org->advocacyLabels())
            <div class="op-tags">
                @foreach($org->advocacyLabels() as $label)
                    <span class="op-tag">{{ $label }}</span>
                @endforeach
            </div>
        @endif
    </div>

    @unless($hasProfile)
        <div class="card op-empty">
            <div style="font-size:1.8rem; margin-bottom:8px">📝</div>
            This organization hasn't written its About Us profile yet.
        </div>
    @endunless

    @if($org->org_description)
        <div class="op-card"><div class="op-body">{{ $org->org_description }}</div></div>
    @endif

    @if($org->org_vision || $org->org_mission)
        <div class="op-two">
            @if($org->org_vision)
                <div class="op-card">
                    <h3><span class="op-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="2"/></svg></span>Vision</h3>
                    <div class="op-body">{{ $org->org_vision }}</div>
                </div>
            @endif
            @if($org->org_mission)
                <div class="op-card">
                    <h3><span class="op-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M5 21V4m0 0h11l-2 4 2 4H5" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg></span>Mission</h3>
                    <div class="op-body">{{ $org->org_mission }}</div>
                </div>
            @endif
        </div>
    @endif

    @if($goals)
        <div class="op-card">
            <h3><span class="op-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/></svg></span>Goals and objectives</h3>
            @foreach($goals as $i => $goal)
                <div class="op-goal">
                    <span class="op-goal-n">{{ $i + 1 }}</span>
                    <span style="color:#334155">{{ $goal }}</span>
                </div>
            @endforeach
        </div>
    @endif

    @if($org->role === 'student_org' || $org->officers->isNotEmpty())
        <div class="op-card">
            <h3><span class="op-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M8 8a3 3 0 1 1 6 0 3 3 0 0 1-6 0zM4 20a7 7 0 0 1 14 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>Officers</h3>
            <div class="op-officers">
                @if($org->role === 'student_org')
                    <div class="op-officer">
                        <x-profile-avatar :name="$org->name" :photo-url="$org->profilePhotoUrl()" :size="54" />
                        <div class="op-of-name">{{ $org->name }}</div>
                        <div class="op-of-pos">President</div>
                    </div>
                @endif
                @foreach($org->officers as $officer)
                    <div class="op-officer">
                        <x-profile-avatar :name="$officer->name" :photo-url="$officer->avatarUrl()" :size="54" />
                        <div class="op-of-name">{{ $officer->name }}</div>
                        <div class="op-of-pos">{{ $officer->position }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="op-card">
        <div style="display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:6px">
            <h3 style="margin:0"><span class="op-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M4 5h16v15H4V5zm0 5h16M9 3v4m6-4v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>Activities conducted</h3>
            @if($activities->isNotEmpty())
                <a href="{{ route('activities.index', ['q' => $org->organization_name]) }}"
                   style="color:#0b6a4a; font-weight:700; font-size:0.86rem; text-decoration:none">View all</a>
            @endif
        </div>
        <div style="color:#94a3b8; font-size:0.84rem; margin-bottom:12px">Pulled automatically from approved projects.</div>

        @if($activities->isEmpty())
            <div class="op-empty" style="padding:26px">No approved activities published yet.</div>
        @else
            @foreach($activities as $a)
                @php $when = $a->event_date ?? $a->published_at ?? $a->created_at; @endphp
                <div class="op-activity">
                    @if($a->category)<span class="op-cat">{{ $a->category }}</span>@endif
                    <div style="flex:1; min-width:0">
                        <div class="op-act-title">{{ $a->title }}</div>
                        <div class="op-act-meta">
                            {{ collect([
                                $a->event_location,
                                $when?->format('j F Y'),
                                ($attended[$a->project_id] ?? 0) > 0
                                    ? $attended[$a->project_id] . ' participants' : null,
                            ])->filter()->implode(' · ') }}
                        </div>
                    </div>
                    <a href="{{ route('activities.show', $a) }}" style="color:#0b6a4a; font-weight:700; font-size:0.85rem; text-decoration:none; flex-shrink:0">View</a>
                </div>
            @endforeach
        @endif
    </div>

    <div class="op-foot">
        <span>
            @if($org->org_profile_updated_at)
                Profile updated {{ $org->org_profile_updated_at->format('j F Y') }}
            @else
                Profile not yet updated
            @endif
        </span>
        <span>
            @if($org->org_contact_email)
                <a href="mailto:{{ $org->org_contact_email }}">{{ $org->org_contact_email }}</a>
            @endif
            @if($org->org_contact_email && $org->org_facebook) · @endif
            @if($org->org_facebook)
                <span style="color:#0b6a4a; font-weight:700">{{ $org->org_facebook }}</span>
            @endif
        </span>
    </div>
@endsection
