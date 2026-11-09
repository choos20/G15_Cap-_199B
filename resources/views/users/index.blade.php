@extends('layouts.admin')

@section('title', 'User Accounts')
@section('subtitle', auth()->user()->role === 'csa_director' ? 'Manage your organization accounts' : 'Manage system accounts')

@section('content')
    @php
        // Declaration order drives the filter menu: CELP staff by seniority, then CSA,
        // then the two project initiators (student org + college/department) together,
        // then participants.
        $roleLabels = [
            'celp_head' => 'CELP Head',
            'coordinator' => 'Coordinator',
            'celp_secretary' => 'CELP Secretary',
            'csa_director' => 'CSA Director',
            'student_org' => 'Student Organization',
            'college_department' => 'College / Department',
            'participant' => 'Participant',
        ];
        $roleColors = [
            'student_org' => ['#eff6ff', '#1d4ed8'],
            'college_department' => ['#eef2ff', '#4338ca'],
            'celp_secretary' => ['#fff7ed', '#9a3412'],
            'coordinator' => ['#f0fdf4', '#166534'],
            'csa_director' => ['#fdf2f8', '#9d174d'],
            'celp_head' => ['#f1f5f9', '#334155'],
        ];
    @endphp

    <style>
        .ua-table { width:100%; border-collapse:collapse; }
        .ua-table th { text-align:left; padding:12px 16px; font-size:0.8rem; text-transform:uppercase; letter-spacing:0.04em; color:#94a3b8; font-weight:700; border-bottom:1px solid #e5e7eb; }
        .ua-table td { padding:14px 16px; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
        .ua-table tr:last-child td { border-bottom:none; }
        .ua-table tr:hover td { background:#f8fafc; }
        .ua-avatar { width:38px; height:38px; border-radius:50%; background:#0b6a4a; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:0.85rem; flex-shrink:0; }
        .ua-name-cell { display:flex; align-items:center; gap:12px; }
        .ua-pill { display:inline-flex; padding:5px 12px; border-radius:999px; font-size:0.8rem; font-weight:700; white-space:nowrap; }
        .ua-status-active { background:#dcfce7; color:#166534; }
        .ua-status-inactive { background:#fef2f2; color:#b91c1c; }
        .ua-action-link { color:#2563eb; text-decoration:none; font-weight:700; font-size:0.9rem; }
        .ua-action-btn { background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; padding:7px 14px; border-radius:10px; font-weight:700; font-size:0.85rem; cursor:pointer; margin-left:10px; }
        .ua-action-btn-activate { background:#ecfdf5; color:#047857; border:1px solid #a7f3d0; }
        .ua-actions { display:flex; align-items:center; gap:8px; }
        .ua-actions form { margin:0; }
        .ua-actions .ua-action-btn { min-height:38px; margin-left:0; }
        .ua-action-edit { display:inline-flex; align-items:center; justify-content:center; min-height:38px; padding:7px 14px; border:1px solid #bfdbfe; border-radius:10px; background:#eff6ff; color:#1d4ed8; text-decoration:none; font-size:0.85rem; font-weight:700; }
        .ua-action-edit:hover { background:#dbeafe; }
        .ua-actions :focus-visible { outline:3px solid rgba(37,99,235,0.32); outline-offset:2px; }
        .ua-logo-thumb { width:34px; height:34px; border-radius:8px; object-fit:cover; }

        .ua-modal-overlay {
            display:none; position:fixed; inset:0; background:rgba(15,23,42,0.55); z-index:1000;
            align-items:center; justify-content:center; padding:20px;
        }
        .ua-modal-overlay.active { display:flex; }
        .ua-modal { background:#fff; border-radius:20px; padding:28px; max-width:420px; width:100%; box-shadow:0 30px 60px rgba(15,23,42,0.25); }
        .ua-modal-icon { width:44px; height:44px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.2rem; margin-bottom:16px; }
        .ua-modal-title { margin:0 0 8px; font-size:1.15rem; font-weight:700; color:#0f172a; line-height:1.4; }
        .ua-modal-desc { margin:0; color:#64748b; font-size:0.92rem; line-height:1.6; }
        .ua-modal-warning { display:flex; align-items:center; gap:8px; margin-top:14px; padding:10px 14px; border-radius:10px; background:#fffbeb; color:#92400e; font-size:0.85rem; font-weight:600; }
        .ua-modal-actions { display:flex; justify-content:flex-end; gap:10px; margin-top:22px; }
        .ua-modal-cancel { padding:11px 20px; border-radius:10px; background:#fff; color:#0f172a; border:1px solid #d1d5db; font-weight:700; cursor:pointer; }
        .ua-modal-confirm { padding:11px 20px; border-radius:10px; border:none; font-weight:700; cursor:pointer; color:#fff; }

        /* Role filter — scopes the table below it. Options are built from the roles
           actually present, so a CSA Director never sees roles it cannot manage. */
        .uf-wrap { position:relative; }
        .uf-btn { display:inline-flex; align-items:center; gap:8px; padding:8px 14px; border-radius:999px;
                  background:#fff; color:#334155; border:1px solid #e2e8f0; font:inherit; font-size:0.9rem;
                  font-weight:700; cursor:pointer; transition:border-color 150ms ease, background 150ms ease; }
        .uf-btn:hover { border-color:#cbd5e1; background:#f8fafc; }
        .uf-btn.is-on { border-color:#0b6a4a; color:#0b6a4a; background:#f0fdf7; }
        .uf-badge { display:inline-flex; align-items:center; justify-content:center; min-width:19px; height:19px;
                    padding:0 5px; border-radius:999px; background:#0b6a4a; color:#fff; font-size:0.72rem; }
        /* An author display rule outranks the UA stylesheet's [hidden], so restore it. */
        .uf-badge[hidden] { display:none; }
        .uf-panel { position:absolute; top:calc(100% + 8px); left:0; z-index:40; min-width:262px; padding:8px;
                    background:#fff; border:1px solid #e5e7eb; border-radius:14px;
                    box-shadow:0 18px 40px rgba(15,23,42,0.14); display:none; }
        .uf-panel.active { display:block; }
        .uf-panel-head { display:flex; align-items:center; justify-content:space-between; gap:12px;
                         padding:8px 10px 10px; border-bottom:1px solid #f1f5f9; margin-bottom:6px; }
        .uf-panel-title { font-size:0.78rem; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.04em; }
        .uf-clear { background:none; border:none; padding:0; color:#2563eb; font:inherit; font-size:0.82rem; font-weight:700; cursor:pointer; }
        .uf-clear:hover { text-decoration:underline; }
        .uf-clear[disabled] { color:#cbd5e1; cursor:default; text-decoration:none; }
        .uf-opt { display:flex; align-items:center; gap:10px; padding:9px 10px; border-radius:10px; cursor:pointer; }
        .uf-opt:hover { background:#f8fafc; }
        .uf-opt input { width:16px; height:16px; accent-color:#0b6a4a; cursor:pointer; flex-shrink:0; margin:0; }
        .uf-opt-count { margin-left:auto; color:#94a3b8; font-size:0.82rem; font-variant-numeric:tabular-nums; }
        .ua-no-match td { padding:34px; text-align:center; color:#64748b; }
        .ua-no-match[hidden] { display:none; }
        @media(max-width:760px) {
            .ua-table-wrap { overflow:visible !important; background:transparent; box-shadow:none; }
            .ua-table, .ua-table tbody { display:block; width:100%; }
            .ua-table thead { display:none; }
            .ua-table tr.ua-row {
                display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:0 14px;
                margin-bottom:12px; padding:13px 15px; overflow:hidden;
                border:1px solid #e5eaf0; border-radius:14px; background:#fff;
                box-shadow:0 4px 12px rgba(15,23,42,0.04);
            }
            .ua-table tr.ua-row:hover td { background:transparent; }
            .ua-table tr.ua-row td {
                display:flex; flex-direction:column; align-items:flex-start; justify-content:center;
                min-width:0; padding:9px 0; border-bottom:1px solid #f1f5f9;
                overflow-wrap:anywhere; font-size:0.88rem;
            }
            .ua-table tr.ua-row td::before {
                content:attr(data-label); margin-bottom:5px; color:#94a3b8; font-size:0.67rem;
                font-weight:800; letter-spacing:0.06em; text-transform:uppercase;
            }
            .ua-table tr.ua-row td:first-child { grid-column:1 / -1; }
            .ua-table tr.ua-row td:first-child::before { content:none; }
            .ua-table tr.ua-row td:nth-child(3), .ua-table tr.ua-row td:nth-child(5) { align-items:flex-start; }
            .ua-table tr.ua-row td:last-child { grid-column:1 / -1; border-bottom:0; }
            .ua-name-cell { width:100%; }
            .ua-actions { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); width:100%; gap:10px; }
            .ua-actions > a, .ua-actions > form, .ua-actions > form > button { width:100%; }
            .ua-action-edit, .ua-actions .ua-action-btn { min-height:44px; padding:10px 12px; margin:0; text-align:center; font-size:0.9rem; }
            .ua-no-match { display:block; }
            .ua-no-match td { display:block; padding:24px 12px; }
        }
        @media(max-width:420px) {
            .ua-table tr.ua-row { grid-template-columns:minmax(0,1fr); }
            .ua-table tr.ua-row td:first-child, .ua-table tr.ua-row td:last-child { grid-column:1; }
            .ua-actions { grid-template-columns:minmax(0,1fr); }
        }
    </style>

    @if(session('status'))
        <div class="card" style="border-left:4px solid #16a34a; background:#ecfdf5; color:#134e4a; padding:16px 20px; margin-bottom:20px">
            {{ session('status') }}
        </div>
    @endif

    @php
        // Only offer roles that exist in this list, ordered by the label map above so
        // the menu reads the same way every time.
        $roleCounts = $users->groupBy('role')->map->count();
        $filterRoles = collect($roleLabels)->keys()
            ->filter(fn($r) => $roleCounts->has($r))
            ->merge($roleCounts->keys())
            ->unique()
            ->values();
    @endphp

    <div style="display:flex; align-items:center; gap:14px; margin-bottom:18px">
        <span id="ua-count" style="background:#f1f5f9; color:#334155; font-weight:700; padding:8px 16px; border-radius:999px; font-size:0.9rem">{{ $users->count() }} {{ \Illuminate\Support\Str::plural('account', $users->count()) }}</span>

        @if($filterRoles->count() > 1)
            <div class="uf-wrap">
                <button type="button" class="uf-btn" id="uf-btn" aria-haspopup="true" aria-expanded="false" aria-controls="uf-panel">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 5h18l-7 8v6l-4 2v-8L3 5z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                    Filter
                    <span class="uf-badge" id="uf-badge" hidden></span>
                </button>
                <div class="uf-panel" id="uf-panel">
                    <div class="uf-panel-head">
                        <span class="uf-panel-title">Filter by role</span>
                        <button type="button" class="uf-clear" id="uf-clear" disabled>Clear</button>
                    </div>
                    @foreach($filterRoles as $role)
                        @php [$optBg, $optColor] = $roleColors[$role] ?? ['#f1f5f9', '#334155']; @endphp
                        <label class="uf-opt">
                            <input type="checkbox" class="uf-check" value="{{ $role }}">
                            <span class="ua-pill" style="background:{{ $optBg }}; color:{{ $optColor }}">{{ $roleLabels[$role] ?? ucfirst($role) }}</span>
                            <span class="uf-opt-count">{{ $roleCounts[$role] }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endif

        <div style="margin-left:auto">
            @if(in_array(auth()->user()->role, ['csa_director', 'celp_head']))
                <a href="{{ route('users.create') }}" style="display:inline-flex; align-items:center; gap:8px; background:#0b6a4a; color:#fff; padding:12px 20px; border-radius:12px; text-decoration:none; font-weight:700;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    Create account
                </a>
            @endif
        </div>
    </div>

    <div class="card ua-table-wrap" style="padding:0; overflow:auto">
        @if($users->isEmpty())
            <div style="padding:40px; text-align:center; color:#64748b">
                <div style="font-size:2rem; margin-bottom:10px">👤</div>
                No accounts yet.
            </div>
        @else
            <table class="ua-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Organization</th>
                        <th>Status</th>
                        <th>Created by</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $u)
                        @php
                            [$roleBg, $roleColor] = $roleColors[$u->role] ?? ['#f1f5f9', '#334155'];
                        @endphp
                        <tr class="ua-row" data-role="{{ $u->role }}">
                            <td data-label="Name">
                                <div class="ua-name-cell">
                                    @if($u->orgLogoUrl())
                                        <img src="{{ $u->orgLogoUrl() }}" alt="" class="ua-logo-thumb">
                                    @else
                                        <x-profile-avatar :name="$u->name" :photo-url="$u->profilePhotoUrl()" :size="38" />
                                    @endif
                                    <span style="font-weight:600; color:#0f172a">{{ $u->name }}</span>
                                </div>
                            </td>
                            <td data-label="Email" style="color:#475569">{{ $u->email }}</td>
                            <td data-label="Role"><span class="ua-pill" style="background:{{ $roleBg }}; color:{{ $roleColor }}">{{ $roleLabels[$u->role] ?? ucfirst($u->role) }}</span></td>
                            <td data-label="Organization" style="color:#475569">{{ $u->organization_name ?? '—' }}</td>
                            <td data-label="Status">
                                @if(array_key_exists('is_active', $u->getAttributes()))
                                    <span class="ua-pill {{ $u->is_active ? 'ua-status-active' : 'ua-status-inactive' }}">{{ $u->is_active ? 'Active' : 'Deactivated' }}</span>
                                @else
                                    <span class="ua-pill ua-status-active">Active</span>
                                @endif
                            </td>
                            <td data-label="Created by" style="color:#475569">{{ optional($u->creator)->name ?? 'System' }}</td>
                            <td data-label="Actions">
                                @php
                                    $auth = auth()->user();
                                    $canEdit = can_edit_account($auth, $u);
                                    $canSetStatus = can_manage_account_status($auth, $u);
                                @endphp

                                <div class="ua-actions">
                                    @if($canEdit)
                                        <a href="{{ route('users.edit', $u) }}" class="ua-action-edit">Edit</a>
                                    @endif

                                    @if($canSetStatus)
                                        @if($u->is_active)
                                            <form method="POST" action="{{ route('users.deactivate', $u) }}">
                                                @csrf
                                                <button type="button" class="ua-action-btn" data-confirm-action="deactivate" data-confirm-name="{{ $u->organization_name ?? $u->name }}">Deactivate</button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('users.activate', $u) }}">
                                                @csrf
                                                <button type="button" class="ua-action-btn ua-action-btn-activate" data-confirm-action="activate" data-confirm-name="{{ $u->organization_name ?? $u->name }}">Reactivate</button>
                                            </form>
                                        @endif
                                    @endif

                                    @unless($canEdit || $canSetStatus)
                                        <span style="color:#cbd5e1">—</span>
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    <tr class="ua-no-match" id="ua-no-match" hidden>
                        <td colspan="7">No accounts match this filter.</td>
                    </tr>
                </tbody>
            </table>
        @endif
    </div>

    <div id="ua-confirm-modal" class="ua-modal-overlay">
        <div class="ua-modal">
            <div class="ua-modal-icon" id="ua-modal-icon"></div>
            <h3 class="ua-modal-title" id="ua-modal-title"></h3>
            <p class="ua-modal-desc" id="ua-modal-desc"></p>
            <div class="ua-modal-warning" id="ua-modal-warning" style="display:none">
                ⚠️ This is a dangerous action — it can be undone, but the account loses access until reactivated.
            </div>
            <div class="ua-modal-actions">
                <button type="button" class="ua-modal-cancel" id="ua-modal-cancel">Cancel</button>
                <button type="button" class="ua-modal-confirm" id="ua-modal-confirm"></button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const filterBtn = document.getElementById('uf-btn');

            if (filterBtn) {
                const panel = document.getElementById('uf-panel');
                const badge = document.getElementById('uf-badge');
                const clearBtn = document.getElementById('uf-clear');
                const checks = Array.from(document.querySelectorAll('.uf-check'));
                const rows = Array.from(document.querySelectorAll('.ua-row'));
                const noMatch = document.getElementById('ua-no-match');
                const countChip = document.getElementById('ua-count');

                const closePanel = () => {
                    panel.classList.remove('active');
                    filterBtn.setAttribute('aria-expanded', 'false');
                };

                const apply = () => {
                    const picked = checks.filter((c) => c.checked).map((c) => c.value);
                    let shown = 0;

                    rows.forEach((row) => {
                        const match = picked.length === 0 || picked.includes(row.dataset.role);
                        row.hidden = !match;
                        if (match) shown += 1;
                    });

                    noMatch.hidden = shown > 0;
                    countChip.textContent = `${shown} ${shown === 1 ? 'account' : 'accounts'}`;

                    filterBtn.classList.toggle('is-on', picked.length > 0);
                    badge.hidden = picked.length === 0;
                    badge.textContent = String(picked.length);
                    clearBtn.disabled = picked.length === 0;
                };

                filterBtn.addEventListener('click', (event) => {
                    event.stopPropagation();
                    const open = !panel.classList.contains('active');
                    panel.classList.toggle('active', open);
                    filterBtn.setAttribute('aria-expanded', String(open));
                });

                panel.addEventListener('click', (event) => event.stopPropagation());
                checks.forEach((c) => c.addEventListener('change', apply));

                clearBtn.addEventListener('click', () => {
                    checks.forEach((c) => { c.checked = false; });
                    apply();
                });

                document.addEventListener('click', closePanel);
                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && panel.classList.contains('active')) {
                        closePanel();
                        filterBtn.focus();
                    }
                });
            }

            const modal = document.getElementById('ua-confirm-modal');
            const iconEl = document.getElementById('ua-modal-icon');
            const titleEl = document.getElementById('ua-modal-title');
            const descEl = document.getElementById('ua-modal-desc');
            const warningEl = document.getElementById('ua-modal-warning');
            const confirmBtn = document.getElementById('ua-modal-confirm');
            const cancelBtn = document.getElementById('ua-modal-cancel');
            let pendingForm = null;

            const copy = {
                deactivate: {
                    icon: '⚠️',
                    iconBg: '#fef2f2',
                    iconColor: '#b91c1c',
                    title: (name) => `Deactivate ${name}?`,
                    desc: (name) => `${name} will immediately lose access and won't be able to log in until the account is reactivated.`,
                    showWarning: true,
                    confirmLabel: 'Deactivate account',
                    confirmBg: '#dc2626',
                },
                activate: {
                    icon: '↻',
                    iconBg: '#eff6ff',
                    iconColor: '#1d4ed8',
                    title: (name) => `Reactivate ${name}?`,
                    desc: (name) => `${name} will regain access and can log in again right away.`,
                    showWarning: false,
                    confirmLabel: 'Reactivate account',
                    confirmBg: '#0f172a',
                },
            };

            const closeModal = () => {
                modal.classList.remove('active');
                pendingForm = null;
            };

            document.querySelectorAll('[data-confirm-action]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const c = copy[btn.dataset.confirmAction];
                    const name = btn.dataset.confirmName;

                    iconEl.textContent = c.icon;
                    iconEl.style.background = c.iconBg;
                    iconEl.style.color = c.iconColor;
                    titleEl.textContent = c.title(name);
                    descEl.textContent = c.desc(name);
                    warningEl.style.display = c.showWarning ? 'flex' : 'none';
                    confirmBtn.textContent = c.confirmLabel;
                    confirmBtn.style.background = c.confirmBg;

                    pendingForm = btn.closest('form');
                    modal.classList.add('active');
                });
            });

            cancelBtn.addEventListener('click', closeModal);
            modal.addEventListener('click', (event) => {
                if (event.target === modal) closeModal();
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && modal.classList.contains('active')) closeModal();
            });
            confirmBtn.addEventListener('click', () => {
                if (pendingForm) pendingForm.submit();
            });
        });
    </script>
@endsection
