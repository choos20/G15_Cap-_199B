@extends('layouts.admin')

@section('title', 'About Us')
@section('subtitle', 'This is what participants see in the organization directory.')

@section('content')
    @php
        $logoUrl = $org->orgLogoUrl();
        $advocacies = $org->org_advocacies ?? [];
    @endphp

    <style>
        .ab-tabs { display:flex; gap:26px; border-bottom:1px solid #e2e8f0; margin-bottom:22px; }
        .ab-tab { padding:0 0 12px; color:#64748b; text-decoration:none; font-weight:600; font-size:0.95rem;
                  border-bottom:2px solid transparent; margin-bottom:-1px; }
        .ab-tab:hover { color:#0f172a; }
        .ab-tab.is-on { color:#0b6a4a; border-bottom-color:#0b6a4a; font-weight:700; }

        .ab-layout { display:grid; grid-template-columns:minmax(0,1fr) 340px; gap:20px; align-items:start; }
        @media (max-width:1080px) { .ab-layout { grid-template-columns:1fr; } }

        .ab-card { background:#fff; border-radius:16px; box-shadow:0 6px 18px rgba(11,42,42,0.06); padding:24px; margin-bottom:18px; }
        .ab-card-head { display:flex; align-items:center; gap:11px; padding-bottom:14px; border-bottom:1px solid #f1f5f9; margin-bottom:18px; }
        .ab-card-head h3 { margin:0; font-size:1.02rem; color:#0f172a; }
        .ab-icon { width:26px; height:26px; border-radius:8px; display:flex; align-items:center; justify-content:center;
                   background:#ecfdf5; color:#047857; flex-shrink:0; }

        .ab-field { margin-bottom:18px; }
        .ab-field:last-child { margin-bottom:0; }
        .ab-label { display:flex; align-items:center; justify-content:space-between; gap:10px;
                    font-size:0.86rem; font-weight:600; color:#334155; margin-bottom:7px; }
        .ab-count { font-size:0.78rem; color:#94a3b8; font-variant-numeric:tabular-nums; }
        .ab-input, .ab-textarea, .ab-select {
            width:100%; padding:11px 13px; border:1px solid #d1d5db; border-radius:11px; font:inherit;
            font-size:0.93rem; color:#0f172a; background:#fff; transition:border-color 150ms ease, box-shadow 150ms ease;
        }
        .ab-input:focus, .ab-textarea:focus { outline:none; border-color:#0b6a4a; box-shadow:0 0 0 4px rgba(11,106,74,0.10); }
        .ab-input[readonly] { background:#f1f5f9; color:#64748b; cursor:not-allowed; }
        .ab-textarea { resize:vertical; min-height:84px; line-height:1.6; }
        .ab-hint { font-size:0.8rem; color:#94a3b8; margin-top:6px; }
        .ab-grid2 { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
        @media (max-width:620px) { .ab-grid2 { grid-template-columns:1fr; } }

        .ab-logo-row { display:flex; align-items:center; gap:18px; flex-wrap:wrap; }
        .ab-logo { width:96px; height:96px; border-radius:18px; border:1px solid #e2e8f0; background:#f8fafc;
                   display:flex; align-items:center; justify-content:center; overflow:hidden; flex-shrink:0; font-size:2rem; }
        .ab-logo img { width:100%; height:100%; object-fit:cover; }
        .ab-logo-actions { display:flex; gap:10px; align-items:center; }
        .ab-link-btn { background:none; border:none; padding:0; font:inherit; font-size:0.86rem; font-weight:700; cursor:pointer; }
        .ab-link-btn.is-danger { color:#b91c1c; }
        .ab-link-btn.is-go { color:#0b6a4a; }
        .ab-link-btn:hover { text-decoration:underline; }

        .ab-chips { display:flex; flex-wrap:wrap; gap:10px; }
        .ab-chip { position:relative; }
        .ab-chip input { position:absolute; opacity:0; pointer-events:none; }
        .ab-chip span { display:inline-flex; align-items:center; gap:7px; padding:10px 16px; border-radius:999px;
                        border:1px solid #d1d5db; background:#fff; color:#334155; font-size:0.88rem;
                        font-weight:600; cursor:pointer; user-select:none; transition:all 140ms ease; }
        .ab-chip input:checked + span { background:#0b6a4a; border-color:#0b6a4a; color:#fff; }
        .ab-chip input:focus-visible + span { box-shadow:0 0 0 4px rgba(11,106,74,0.18); }

        .ab-preview-label { font-size:0.74rem; font-weight:700; color:#94a3b8; letter-spacing:0.08em;
                            text-transform:uppercase; margin-bottom:10px; }
        .ab-preview { background:#fff; border-radius:16px; box-shadow:0 6px 18px rgba(11,42,42,0.06); padding:22px; }
        .ab-pv-logo { width:56px; height:56px; border-radius:14px; border:1px solid #e2e8f0; background:#f8fafc;
                      display:flex; align-items:center; justify-content:center; overflow:hidden; margin-bottom:14px; }
        .ab-pv-logo img { width:100%; height:100%; object-fit:cover; }
        .ab-pv-name { font-weight:700; font-size:1.08rem; color:#0f172a; }
        .ab-pv-sub { color:#64748b; font-size:0.86rem; margin-top:3px; }
        .ab-pv-desc { color:#475569; font-size:0.9rem; line-height:1.6; margin-top:12px; }
        .ab-pv-tags { display:flex; flex-wrap:wrap; gap:8px; margin-top:14px; padding-top:14px; border-top:1px solid #f1f5f9; }
        .ab-pv-tag { background:#ecfdf5; color:#047857; font-size:0.76rem; font-weight:700; padding:5px 11px; border-radius:999px; }

        .ab-todo { background:#fffbeb; border:1px solid #fde68a; border-radius:16px; padding:18px 20px; margin-top:16px; }
        .ab-todo h4 { margin:0 0 10px; font-size:0.92rem; color:#92400e; display:flex; align-items:center; gap:8px; }
        .ab-todo ul { margin:0; padding-left:18px; color:#92400e; font-size:0.86rem; line-height:1.9; }

        .ab-pill { display:inline-flex; align-items:center; gap:8px; padding:8px 16px; border-radius:999px;
                   background:#ecfdf5; color:#047857; font-weight:700; font-size:0.88rem; }
        .ab-pill-dot { width:8px; height:8px; border-radius:50%; background:#0ca30c; }

        .ab-bar { display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;
                  background:#fff; border-radius:14px; box-shadow:0 6px 18px rgba(11,42,42,0.06);
                  padding:14px 20px; margin-top:18px; position:sticky; bottom:14px; }
        .ab-saved { color:#64748b; font-size:0.85rem; }
        .ab-btn { padding:12px 22px; border-radius:11px; border:1px solid #d1d5db; background:#fff; color:#0f172a;
                  font:inherit; font-weight:700; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; }
        .ab-btn-primary { background:#0b6a4a; border-color:#0b6a4a; color:#fff; }
        .ab-btn-primary:hover { background:#0f8a61; }

        .ab-officer { display:flex; align-items:center; gap:14px; padding:14px 0; border-bottom:1px solid #f1f5f9; }
        .ab-officer:last-of-type { border-bottom:none; }
        .ab-avatar { width:42px; height:42px; border-radius:50%; background:#0b6a4a; color:#fff; font-weight:700;
                     font-size:0.88rem; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .ab-of-name { font-weight:700; color:#0f172a; }
        .ab-of-pos { color:#64748b; font-size:0.86rem; margin-top:2px; }
        .ab-of-actions { margin-left:auto; display:flex; gap:6px; align-items:center; }
        .ab-mini { width:30px; height:30px; border-radius:8px; border:1px solid #e2e8f0; background:#fff;
                   cursor:pointer; color:#475569; font-size:0.9rem; line-height:1; }
        .ab-mini:hover:not(:disabled) { background:#f8fafc; border-color:#cbd5e1; }
        .ab-mini:disabled { opacity:0.35; cursor:default; }
        .ab-empty { padding:34px; text-align:center; color:#64748b; }
    </style>

    @if(session('status'))
        <div class="card" style="border-left:4px solid #16a34a; background:#ecfdf5; color:#134e4a; padding:14px 18px; margin-bottom:18px">
            {{ session('status') }}
        </div>
    @endif

    @if($errors->any())
        <div class="card" style="border-left:4px solid #dc2626; background:#fef2f2; color:#7f1d1d; padding:14px 18px; margin-bottom:18px">
            <div style="font-weight:700; margin-bottom:6px">Please fix the following:</div>
            <ul style="margin:0; padding-left:18px">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:16px">
        <div></div>
        <span class="ab-pill"><span class="ab-pill-dot"></span>{{ $completeness['percent'] }}% complete</span>
    </div>

    <div class="ab-tabs">
        <a href="{{ route('org.about.edit', ['tab' => 'profile']) }}"  class="ab-tab {{ $tab === 'profile' ? 'is-on' : '' }}">Profile</a>
        <a href="{{ route('org.about.edit', ['tab' => 'officers']) }}" class="ab-tab {{ $tab === 'officers' ? 'is-on' : '' }}">Officers</a>
        <a href="{{ route('org.about.edit', ['tab' => 'contact']) }}"  class="ab-tab {{ $tab === 'contact' ? 'is-on' : '' }}">Contact</a>
    </div>

    <div class="ab-layout">
        <div>
            @if($tab === 'profile')
                <form method="POST" action="{{ route('org.about.update') }}" enctype="multipart/form-data" id="ab-form"
                      data-confirm-navigation
                      data-confirm-title="Leave profile editing?"
                      data-confirm-body="You have unsaved profile changes. If you leave now, they will be lost."
                      data-confirm-tone="warning"
                      data-confirm-ok="Leave page"
                      data-confirm-cancel="Keep editing">
                    @csrf

                    <div class="ab-card">
                        <div class="ab-card-head">
                            <span class="ab-icon">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M3 5h18v14H3V5zm3 4h5v6H6V9zm8 0h5M14 13h5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                            </span>
                            <h3>Identity</h3>
                        </div>

                        <div class="ab-logo-row">
                            <div class="ab-logo" id="ab-logo-box">
                                @if($logoUrl)<img src="{{ $logoUrl }}" alt="Organization logo" id="ab-logo-img">@else<span id="ab-logo-ph">🏢</span>@endif
                            </div>
                            <div>
                                <input type="file" name="org_logo" id="ab-logo-input" accept="image/png,image/jpeg,image/webp" style="display:none">
                                <div class="ab-logo-actions">
                                    <button type="button" class="ab-link-btn is-go" onclick="document.getElementById('ab-logo-input').click()">
                                        {{ $org->org_logo ? 'Replace' : 'Upload logo' }}
                                    </button>
                                    @if($org->org_logo)
                                        <span style="color:#cbd5e1">·</span>
                                        <button type="submit" form="ab-logo-remove" class="ab-link-btn is-danger"
                                                data-confirm
                                                data-confirm-title="Remove the logo?"
                                                data-confirm-body="Your organization will fall back to a placeholder icon in the directory and on every activity you post."
                                                data-confirm-note="You can upload a new logo at any time."
                                                data-confirm-ok="Remove logo"
                                                data-confirm-cancel="Keep it">Remove</button>
                                    @endif
                                </div>
                                <div class="ab-hint" id="ab-logo-name">PNG, JPG or WEBP · up to 2MB</div>
                            </div>
                        </div>

                        <div class="ab-field" style="margin-top:20px">
                            <div class="ab-label"><label for="organization_name">Organization name</label></div>
                            <input class="ab-input" id="organization_name" type="text"
                                   value="{{ $org->organization_name }}" readonly>
                            <div class="ab-hint">Set by the CSA Director when your account was created. Ask them to change it.</div>
                        </div>

                        <div class="ab-grid2" style="margin-top:18px">
                            <div class="ab-field">
                                <div class="ab-label"><label for="org_acronym">Acronym</label></div>
                                <input class="ab-input" id="org_acronym" name="org_acronym" type="text" maxlength="32"
                                       value="{{ old('org_acronym', $org->org_acronym) }}" placeholder="e.g. SYCOMP">
                            </div>
                            <div class="ab-field">
                                <div class="ab-label"><label for="org_year_founded">Year founded</label></div>
                                <input class="ab-input" id="org_year_founded" name="org_year_founded" type="number"
                                       min="1900" max="{{ now()->year }}"
                                       value="{{ old('org_year_founded', $org->org_year_founded) }}" placeholder="e.g. 2004">
                            </div>
                        </div>

                        <div class="ab-field">
                            <div class="ab-label"><label for="org_college">College or department</label></div>
                            <input class="ab-input" id="org_college" name="org_college" type="text" maxlength="150"
                                   value="{{ old('org_college', $org->org_college) }}" placeholder="e.g. College of Computer Studies">
                        </div>
                    </div>

                    <div class="ab-card">
                        <div class="ab-card-head">
                            <span class="ab-icon">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M4 6h16M4 12h16M4 18h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                            </span>
                            <h3>About</h3>
                        </div>

                        <div class="ab-field">
                            <div class="ab-label">
                                <label for="org_description">Short description</label>
                                <span class="ab-count"><span id="ab-desc-count">0</span> / 200</span>
                            </div>
                            <textarea class="ab-textarea" id="org_description" name="org_description" maxlength="200"
                                      placeholder="One or two sentences on what your organization does.">{{ old('org_description', $org->org_description) }}</textarea>
                        </div>
                    </div>

                    <div class="ab-card">
                        <div class="ab-card-head">
                            <span class="ab-icon">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/></svg>
                            </span>
                            <h3>Vision, mission and goals</h3>
                        </div>

                        <div class="ab-field">
                            <div class="ab-label"><label for="org_vision">Vision</label></div>
                            <textarea class="ab-textarea" id="org_vision" name="org_vision" maxlength="500"
                                      placeholder="The change your organization wants to see.">{{ old('org_vision', $org->org_vision) }}</textarea>
                        </div>

                        <div class="ab-field">
                            <div class="ab-label"><label for="org_mission">Mission</label></div>
                            <textarea class="ab-textarea" id="org_mission" name="org_mission" maxlength="500"
                                      placeholder="How your organization works towards it.">{{ old('org_mission', $org->org_mission) }}</textarea>
                        </div>

                        <div class="ab-field">
                            <div class="ab-label"><label for="org_goals">Goals and objectives</label></div>
                            <textarea class="ab-textarea" id="org_goals" name="org_goals" rows="4" maxlength="1000"
                                      placeholder="One goal per line.">{{ old('org_goals', $org->org_goals) }}</textarea>
                            <div class="ab-hint">Write one goal per line — they appear as a numbered list on your profile.</div>
                        </div>
                    </div>

                    <div class="ab-card">
                        <div class="ab-card-head">
                            <span class="ab-icon">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M12 3l7 4v10l-7 4-7-4V7l7-4z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                            </span>
                            <h3>Advocacy areas</h3>
                            <span class="ab-count" style="margin-left:auto"><span id="ab-adv-count">{{ count($advocacies) }}</span> of {{ count(\App\Models\User::ADVOCACY_AREAS) }} selected</span>
                        </div>

                        <div class="ab-chips" id="ab-chips">
                            @foreach(\App\Models\User::ADVOCACY_AREAS as $key => $label)
                                <label class="ab-chip">
                                    <input type="checkbox" name="org_advocacies[]" value="{{ $key }}"
                                           @checked(in_array($key, old('org_advocacies', $advocacies), true))>
                                    <span>
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" class="ab-check" style="display:none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        {{ $label }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <div class="ab-hint" style="margin-top:12px">Used to match your organization with community needs. Select every area your organization actually works in.</div>
                    </div>

                    <div class="ab-bar">
                        <span class="ab-saved">
                            @if($org->org_profile_updated_at)
                                Last saved {{ $org->org_profile_updated_at->format('j F Y') }}
                            @else
                                Not saved yet
                            @endif
                        </span>
                        <div style="display:flex; gap:10px">
                            <a href="{{ route('org.about.edit') }}" class="ab-btn">Discard</a>
                            <button type="submit" class="ab-btn ab-btn-primary">Save changes</button>
                        </div>
                    </div>
                </form>

                {{-- Kept outside the main form: nested forms are invalid HTML. --}}
                <form method="POST" action="{{ route('org.about.logo.remove') }}" id="ab-logo-remove" style="display:none">
                    @csrf
                    @method('DELETE')
                </form>

            @elseif($tab === 'officers')
                <div class="ab-card">
                    <div class="ab-card-head">
                        <span class="ab-icon">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M8 8a3 3 0 1 1 6 0 3 3 0 0 1-6 0zM4 20a7 7 0 0 1 14 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                        </span>
                        <h3>Officers</h3>
                        @php $listedOfficerCount = $org->officers->count() + ($org->role === 'student_org' ? 1 : 0); @endphp
                        <span class="ab-count" style="margin-left:auto">{{ $listedOfficerCount }} listed</span>
                    </div>

                    @if($org->role === 'student_org')
                        <div class="ab-officer">
                            <x-profile-avatar :name="$org->name" :photo-url="$org->profilePhotoUrl()" :size="42" />
                            <div style="flex:1; min-width:0">
                                <div class="ab-of-name">{{ $org->name }}</div>
                                <div class="ab-of-pos">President · listed automatically from your account</div>
                            </div>
                        </div>
                    @endif

                    @if($org->officers->isEmpty() && $org->role !== 'student_org')
                        <div class="ab-empty">
                            <div style="font-size:1.8rem; margin-bottom:8px">👥</div>
                            No officers listed yet. Add them below so participants know who to reach.
                        </div>
                    @else
                        @if($org->officers->isEmpty())
                            <div class="ab-empty" style="padding:18px">
                                No additional officers listed yet. Add other officers below.
                            </div>
                        @endif
                        @foreach($org->officers as $officer)
                            <div class="ab-officer">
                                <x-profile-avatar :name="$officer->name" :photo-url="$officer->avatarUrl()" :size="42" />
                                <div style="flex:1; min-width:0">
                                    <div class="ab-of-name">{{ $officer->name }}</div>
                                    <div class="ab-of-pos">
                                        {{ $officer->position }}@if($officer->email) · {{ $officer->email }}@endif
                                    </div>
                                </div>
                                <div class="ab-of-actions">
                                    <form method="POST" action="{{ route('org.about.officers.move', $officer) }}" style="display:inline">
                                        @csrf<input type="hidden" name="direction" value="up">
                                        <button type="submit" class="ab-mini" title="Move up" @disabled($loop->first)>&uarr;</button>
                                    </form>
                                    <form method="POST" action="{{ route('org.about.officers.move', $officer) }}" style="display:inline">
                                        @csrf<input type="hidden" name="direction" value="down">
                                        <button type="submit" class="ab-mini" title="Move down" @disabled($loop->last)>&darr;</button>
                                    </form>
                                    <button type="button" class="ab-mini" title="Edit"
                                            onclick="abEditOfficer({{ $officer->id }})">&#9998;</button>
                                    <form method="POST" action="{{ route('org.about.officers.destroy', $officer) }}" style="display:inline"
                                          data-confirm
                                          data-confirm-title="Remove officer?"
                                          data-confirm-body="{name} will be removed from your officers list and will no longer appear on your public profile."
                                          data-confirm-name="{{ $officer->name }}"
                                          data-confirm-ok="Remove officer"
                                          data-confirm-cancel="Keep them">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="ab-mini" title="Remove" style="color:#b91c1c">&times;</button>
                                    </form>
                                    @if($org->role === 'student_org')
                                        <details style="position:relative">
                                            <summary class="ab-mini" title="Change officer photo" aria-label="Change photo for {{ $officer->name }}" style="display:flex; align-items:center; justify-content:center; list-style:none; cursor:pointer">Photo</summary>
                                            <div style="position:absolute; z-index:5; top:calc(100% + 6px); right:0; width:240px; padding:12px; border:1px solid #e2e8f0; border-radius:12px; background:#fff; box-shadow:0 12px 30px rgba(15,23,42,.14)">
                                                <form method="POST" action="{{ route('org.about.officers.avatar.update', $officer) }}" enctype="multipart/form-data">
                                                    @csrf
                                                    <label for="officer-avatar-{{ $officer->id }}" style="display:block; margin-bottom:7px; color:#334155; font-size:.78rem; font-weight:700">Upload or replace photo</label>
                                                    <input id="officer-avatar-{{ $officer->id }}" type="file" name="avatar" accept="image/jpeg,image/png,image/webp" required style="width:100%; font-size:.75rem">
                                                    <small style="display:block; margin-top:5px; color:#64748b">Use JPG, PNG, or WebP, at least 256 × 256 pixels, and no larger than 2 MB.</small>
                                                    @error('avatar')
                                                        <div style="margin-top:6px; color:#b91c1c; font-size:.75rem">{{ $message }}</div>
                                                    @enderror
                                                    <button type="submit" class="ab-btn ab-btn-primary" style="margin-top:9px; padding:8px 10px; font-size:.78rem">Save photo</button>
                                                </form>
                                                @if($officer->avatar_path)
                                                    <form method="POST" action="{{ route('org.about.officers.avatar.remove', $officer) }}" data-confirm
                                                          data-confirm-title="Remove officer photo?"
                                                          data-confirm-body="{name} will return to an initials avatar."
                                                          data-confirm-name="{{ $officer->name }}"
                                                          data-confirm-ok="Remove photo"
                                                          data-confirm-cancel="Keep photo">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="ab-btn" style="margin-top:8px; padding:8px 10px; color:#b91c1c; font-size:.78rem">Remove photo</button>
                                                    </form>
                                                @endif
                                                <div style="margin-top:8px; color:#64748b; font-size:.7rem">JPG, PNG, or WebP; maximum 2 MB.</div>
                                            </div>
                                        </details>
                                    @endif
                                </div>
                            </div>

                            <form method="POST" action="{{ route('org.about.officers.update', $officer) }}"
                                  id="ab-edit-{{ $officer->id }}" style="display:none; padding:14px 0; border-bottom:1px solid #f1f5f9">
                                @csrf
                                <div class="ab-grid2">
                                    <div class="ab-field" style="margin-bottom:0">
                                        <div class="ab-label"><label>Name</label></div>
                                        <input class="ab-input" name="name" value="{{ $officer->name }}" required maxlength="120">
                                    </div>
                                    <div class="ab-field" style="margin-bottom:0">
                                        <div class="ab-label"><label>Position</label></div>
                                        <input class="ab-input" name="position" value="{{ $officer->position }}" required maxlength="80">
                                    </div>
                                </div>
                                <div class="ab-field" style="margin-top:12px; margin-bottom:0">
                                    <div class="ab-label"><label>Email <span style="font-weight:400; color:#94a3b8">(optional)</span></label></div>
                                    <input class="ab-input" name="email" type="email" value="{{ $officer->email }}" maxlength="150">
                                </div>
                                <div style="display:flex; gap:10px; margin-top:14px">
                                    <button type="submit" class="ab-btn ab-btn-primary">Save officer</button>
                                    <button type="button" class="ab-btn" onclick="abEditOfficer({{ $officer->id }})">Cancel</button>
                                </div>
                            </form>
                        @endforeach
                    @endif
                </div>

                <div class="ab-card">
                    <div class="ab-card-head">
                        <span class="ab-icon">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
                        </span>
                        <div>
                            <h3 style="margin:0; font-size:1.02rem; color:#0f172a">Add an officer</h3>
                            <div style="margin-top:3px; color:#64748b; font-size:0.85rem">
                                The president is listed automatically. Add other officer positions here.
                            </div>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('org.about.officers.store') }}">
                        @csrf
                        <div class="ab-grid2">
                            <div class="ab-field" style="margin-bottom:0">
                                <div class="ab-label"><label for="of-name">Name</label></div>
                                <input class="ab-input" id="of-name" name="name" required maxlength="120" placeholder="e.g. Juan Dela Cruz">
                            </div>
                            <div class="ab-field" style="margin-bottom:0">
                                <div class="ab-label"><label for="of-pos">Position</label></div>
                                <input class="ab-input" id="of-pos" name="position" required maxlength="80" placeholder="e.g. Vice President">
                            </div>
                        </div>
                        <div class="ab-field" style="margin-top:14px">
                            <div class="ab-label"><label for="of-email">Email <span style="font-weight:400; color:#94a3b8">(optional)</span></label></div>
                            <input class="ab-input" id="of-email" name="email" type="email" maxlength="150">
                        </div>
                        <button type="submit" class="ab-btn ab-btn-primary" style="margin-top:16px">Add officer</button>
                    </form>
                </div>

            @else
                <form method="POST" action="{{ route('org.about.contact') }}"
                      data-confirm-navigation
                      data-confirm-title="Leave contact editing?"
                      data-confirm-body="You have unsaved contact changes. If you leave now, they will be lost."
                      data-confirm-tone="warning"
                      data-confirm-ok="Leave page"
                      data-confirm-cancel="Keep editing">
                    @csrf
                    <div class="ab-card">
                        <div class="ab-card-head">
                            <span class="ab-icon">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M3 6h18v12H3V6zm0 1l9 7 9-7" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                            </span>
                            <h3>Contact</h3>
                        </div>

                        <div class="ab-field">
                            <div class="ab-label"><label for="org_contact_email">Contact email</label></div>
                            <input class="ab-input" id="org_contact_email" name="org_contact_email" type="email" maxlength="150"
                                   value="{{ old('org_contact_email', $org->org_contact_email) }}" placeholder="e.g. sycomp@hnu.edu.ph">
                            <div class="ab-hint">Shown publicly so participants can reach your organization.</div>
                        </div>

                        <div class="ab-field">
                            <div class="ab-label"><label for="org_facebook">Facebook page</label></div>
                            <input class="ab-input" id="org_facebook" name="org_facebook" maxlength="150"
                                   value="{{ old('org_facebook', $org->org_facebook) }}" placeholder="e.g. fb.com/sycomphnu">
                        </div>
                    </div>

                    <div class="ab-bar">
                        <span class="ab-saved">Login email ({{ $org->email }}) stays private.</span>
                        <button type="submit" class="ab-btn ab-btn-primary">Save contact</button>
                    </div>
                </form>
            @endif
        </div>

        <div>
            <div class="ab-preview-label">Live preview</div>
            <div class="ab-preview">
                <div class="ab-pv-logo" id="pv-logo">
                    @if($logoUrl)<img src="{{ $logoUrl }}" alt="">@else<span style="font-size:1.4rem">🏢</span>@endif
                </div>
                <div class="ab-pv-name" id="pv-name">{{ $org->org_acronym ?: $org->organization_name ?: 'Your organization' }}</div>
                <div class="ab-pv-sub" id="pv-college">{{ $org->org_college ?: 'College or department' }}</div>
                <div class="ab-pv-desc" id="pv-desc">{{ $org->org_description ?: 'Your short description appears here.' }}</div>
                <div class="ab-pv-tags" id="pv-tags" @if(empty($advocacies)) style="display:none" @endif>
                    @foreach($org->advocacyLabels() as $label)
                        <span class="ab-pv-tag">{{ $label }}</span>
                    @endforeach
                </div>
            </div>

            @if(!empty($completeness['missing']))
                <div class="ab-todo">
                    <h4>💡 To reach 100%</h4>
                    <ul>
                        @foreach($completeness['missing'] as $item)<li>{{ $item }}</li>@endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>

    <script>
        function abEditOfficer(id) {
            const form = document.getElementById('ab-edit-' + id);
            if (form) form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }

        document.addEventListener('DOMContentLoaded', function () {
            // Live preview: mirror fields as they are typed. Uses textContent so
            // whatever is typed is shown as text, never parsed as markup.
            const bind = (inputId, targetId, fallback) => {
                const input = document.getElementById(inputId);
                const target = document.getElementById(targetId);
                if (!input || !target) return;
                input.addEventListener('input', () => {
                    target.textContent = input.value.trim() || fallback;
                });
            };
            bind('org_acronym', 'pv-name', {!! json_encode($org->organization_name ?: 'Your organization') !!});
            bind('org_college', 'pv-college', 'College or department');
            bind('org_description', 'pv-desc', 'Your short description appears here.');

            const desc = document.getElementById('org_description');
            const descCount = document.getElementById('ab-desc-count');
            if (desc && descCount) {
                const sync = () => { descCount.textContent = desc.value.length; };
                desc.addEventListener('input', sync);
                sync();
            }

            // Advocacy chips: keep the counter and preview tags in step with the selection.
            const chips = document.getElementById('ab-chips');
            const advCount = document.getElementById('ab-adv-count');
            const tagWrap = document.getElementById('pv-tags');
            if (chips) {
                const boxes = Array.from(chips.querySelectorAll('input[type=checkbox]'));
                const refresh = () => {
                    const picked = boxes.filter((b) => b.checked);
                    if (advCount) advCount.textContent = picked.length;

                    boxes.forEach((b) => {
                        const check = b.nextElementSibling.querySelector('.ab-check');
                        if (check) check.style.display = b.checked ? '' : 'none';
                    });

                    if (tagWrap) {
                        tagWrap.replaceChildren();
                        picked.forEach((b) => {
                            const tag = document.createElement('span');
                            tag.className = 'ab-pv-tag';
                            tag.textContent = b.nextElementSibling.textContent.trim();
                            tagWrap.appendChild(tag);
                        });
                        tagWrap.style.display = picked.length ? '' : 'none';
                    }
                };
                boxes.forEach((b) => b.addEventListener('change', refresh));
                refresh();
            }

            // Logo: preview the chosen file before it is uploaded.
            const logoInput = document.getElementById('ab-logo-input');
            if (logoInput) {
                logoInput.addEventListener('change', () => {
                    const file = logoInput.files && logoInput.files[0];
                    if (!file) return;
                    const nameEl = document.getElementById('ab-logo-name');
                    if (nameEl) nameEl.textContent = file.name;
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        ['ab-logo-box', 'pv-logo'].forEach((id) => {
                            const box = document.getElementById(id);
                            if (!box) return;
                            box.replaceChildren();
                            const img = document.createElement('img');
                            img.src = e.target.result;
                            img.alt = '';
                            box.appendChild(img);
                        });
                    };
                    reader.readAsDataURL(file);
                });
            }
        });
    </script>
@endsection
