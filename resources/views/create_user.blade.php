@extends('layouts.admin')

@section('title', 'Create Account')
@section('subtitle', 'Create an account for your allowed role')

@section('content')
    <style>
        .cu-section { padding:28px; }
        .cu-section + .cu-section { border-top:1px solid #e5e7eb; }
        .cu-section-head { display:flex; align-items:center; gap:14px; margin-bottom:22px; }
        .cu-section-icon { width:42px; height:42px; border-radius:14px; display:flex; align-items:center; justify-content:center; background:#eef2ff; color:#4338ca; flex-shrink:0; }
        .cu-section-title { font-size:1.05rem; font-weight:700; color:#0f172a; margin:0; }
        .cu-section-sub { font-size:0.88rem; color:#64748b; margin-top:2px; }
        .cu-grid { display:grid; gap:18px; }
        .cu-grid-3 { grid-template-columns:repeat(3,minmax(0,1fr)); }
        .cu-grid-2 { grid-template-columns:repeat(2,minmax(0,1fr)); }
        @media (max-width:820px) { .cu-grid-3, .cu-grid-2 { grid-template-columns:1fr; } }
        .cu-field label { display:block; font-weight:700; font-size:0.9rem; color:#0f172a; margin-bottom:8px; }
        .cu-field input[type=text], .cu-field input[type=email], .cu-field input[type=password], .cu-field select, .cu-field textarea {
            width:100%; padding:13px 14px; border:1px solid #d1d5db; border-radius:12px; font:inherit; background:#f8fafc; transition:all 150ms ease;
        }
        .cu-field input:focus, .cu-field select:focus, .cu-field textarea:focus { outline:none; border-color:#3b82f6; background:#fff; box-shadow:0 0 0 4px rgba(59,130,246,0.1); }
        .cu-field .hint { font-size:0.82rem; color:#94a3b8; margin-top:6px; }
        .cu-role-card { display:block; cursor:pointer; }
        .cu-role-card input { position:absolute; opacity:0; pointer-events:none; }
        .cu-role-options { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:14px; }
        .cu-role-option { display:flex; align-items:center; gap:12px; padding:16px 18px; border-radius:14px; border:2px solid #e5e7eb; background:#f8fafc; transition:all 150ms ease; }
        .cu-role-option:hover { border-color:#c7d2fe; background:#eef2ff; }
        .cu-role-input:checked + .cu-role-option { border-color:#4338ca; background:#eef2ff; box-shadow:0 0 0 4px rgba(67,56,202,0.08); }
        .cu-role-dot { width:18px; height:18px; border-radius:50%; border:2px solid #cbd5e1; flex-shrink:0; position:relative; }
        .cu-role-input:checked + .cu-role-option .cu-role-dot { border-color:#4338ca; }
        .cu-role-input:checked + .cu-role-option .cu-role-dot::after { content:''; position:absolute; inset:3px; border-radius:50%; background:#4338ca; }
        .cu-logo-drop {
            display:flex; align-items:center; gap:16px; padding:16px; border:2px dashed #cbd5e1; border-radius:16px; background:#f8fbff; cursor:pointer; transition:all 150ms ease;
        }
        .cu-logo-drop:hover, .cu-logo-drop.active { border-color:#3b82f6; background:#eff6ff; }
        .cu-logo-preview { width:56px; height:56px; border-radius:14px; background:#dbeafe; color:#1d4ed8; display:flex; align-items:center; justify-content:center; font-size:1.4rem; flex-shrink:0; overflow:hidden; }
        .cu-logo-preview img { width:100%; height:100%; object-fit:cover; }
        .cu-logo-text strong { display:block; color:#0f172a; font-size:0.92rem; }
        .cu-logo-text span { color:#64748b; font-size:0.85rem; }
        .cu-actions { display:flex; justify-content:flex-end; gap:12px; padding:24px 28px; background:#f8fafc; border-top:1px solid #e5e7eb; border-radius:0 0 24px 24px; }
        .cu-btn-secondary { padding:13px 24px; border-radius:12px; background:#ffffff; color:#0f172a; border:1px solid #d1d5db; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; }
        .cu-btn-primary { padding:13px 26px; border-radius:12px; background:#0b6a4a; color:#ffffff; border:none; font-weight:700; cursor:pointer; }
        .cu-btn-primary:hover { background:#0f8a61; }
    </style>

    @if($errors->any())
        <div class="card" style="border-left:4px solid #dc2626; background:#fef2f2; color:#7f1d1d; padding:16px 20px; margin-bottom:20px">
            <div style="font-weight:700; margin-bottom:6px">Please fix the following:</div>
            <ul style="margin:0; padding-left:18px">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('users.store') }}" enctype="multipart/form-data" novalidate
          data-confirm-navigation
          data-confirm-title="Leave account creation?"
          data-confirm-body="You have unsaved changes. If you leave now, they will be lost."
          data-confirm-tone="warning"
          data-confirm-ok="Leave page"
          data-confirm-cancel="Keep creating">
        @csrf

        <div class="card" style="padding:0; overflow:hidden">
            <div class="cu-section">
                <div class="cu-section-head">
                    <div class="cu-section-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zm0 4a1 1 0 1 0 0 2 1 1 0 0 0 0-2zm1 9h-2v-6h2v6z" fill="currentColor"/></svg>
                    </div>
                    <div>
                        <h3 class="cu-section-title">A. Account role</h3>
                        <div class="cu-section-sub">Choose which role this account will have.</div>
                    </div>
                </div>

                <div class="cu-role-options">
                    @foreach($allowedRoles as $roleValue => $roleLabel)
                        <label class="cu-role-card">
                            <input class="cu-role-input" type="radio" name="role" value="{{ $roleValue }}" id="role-{{ $roleValue }}" {{ old('role') === $roleValue ? 'checked' : '' }} required>
                            <span class="cu-role-option">
                                <span class="cu-role-dot"></span>
                                <span>{{ $roleLabel }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div id="organization-fields" class="cu-section" style="display:none">
                <div class="cu-section-head">
                    <div class="cu-section-icon" style="background:#ecfdf5; color:#047857">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 4h7l3 3h6v13H4V4z" fill="currentColor"/></svg>
                    </div>
                    <div>
                        <h3 class="cu-section-title">B. Organization information</h3>
                        <div class="cu-section-sub">Shown on the org's dashboard and to CELP reviewers.</div>
                    </div>
                </div>

                <div class="cu-grid cu-grid-2">
                    <div class="cu-field">
                        <label for="organization_name">Student Organization Name</label>
                        <input type="text" id="organization_name" name="organization_name" value="{{ old('organization_name') }}">
                    </div>
                    <div class="cu-field">
                        <label for="org_logo">Logo</label>
                        <input id="org_logo" type="file" name="org_logo" accept="image/*" style="display:none">
                        <div id="cu-logo-drop" class="cu-logo-drop">
                            <div class="cu-logo-preview" id="cu-logo-preview">🏢</div>
                            <div class="cu-logo-text">
                                <strong id="cu-logo-name">Click to upload a logo</strong>
                                <span>PNG or JPG, up to 2MB</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Student organizations must be classified; colleges and
                     departments are not, so this hides for them. --}}
                <div id="classification-field" class="cu-field" style="margin-top:18px; display:none">
                    <label>Classification <span style="color:#b91c1c">*</span></label>
                    <div class="cu-role-options" style="margin-top:8px">
                        @foreach(\App\Models\User::ORG_CLASSIFICATIONS as $value => $label)
                            <label class="cu-role-card">
                                <input type="radio" name="org_classification" value="{{ $value }}"
                                       class="cu-role-input" @checked(old('org_classification') === $value)>
                                <span class="cu-role-option">
                                    <span class="cu-role-dot"></span>
                                    <span>{{ $label }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <div class="hint">Used to group activities by initiator type in reports.</div>
                </div>

                <div class="cu-field" style="margin-top:18px">
                    <label for="org_description">Description</label>
                    <textarea id="org_description" name="org_description" rows="3" maxlength="300" placeholder="What the organization does, who it's for">{{ old('org_description') }}</textarea>
                    <div class="hint">Max 300 characters.</div>
                </div>
            </div>

            {{-- Student / Employee / Alumni details. Each classification carries a
                 differently-named ID and its own extra fields. --}}
            <div id="participant-fields" class="cu-section" style="display:none">
                <div class="cu-section-head">
                    <div class="cu-section-icon" style="background:#eff6ff; color:#1d4ed8">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 3 2 8l10 5 10-5-10-5zM6 11v4c0 1.7 2.7 3 6 3s6-1.3 6-3v-4" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                    </div>
                    <div>
                        <h3 class="cu-section-title">B. Participant details</h3>
                        <div class="cu-section-sub">Which kind of participant this account belongs to.</div>
                    </div>
                </div>

                <div class="cu-role-options">
                    @foreach(\App\Models\User::PARTICIPANT_TYPES as $value => $label)
                        <label class="cu-role-card">
                            <input type="radio" name="participant_type" value="{{ $value }}"
                                   class="cu-role-input cu-ptype-input" @checked(old('participant_type') === $value)>
                            <span class="cu-role-option">
                                <span class="cu-role-dot"></span>
                                <span>{{ $label }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>

                <div id="ptype-details" style="display:none; margin-top:22px">
                    <div class="cu-grid cu-grid-2">
                        <div class="cu-field">
                            <label for="id_number" id="id_number_label">ID number</label>
                            <input type="text" id="id_number" name="id_number" value="{{ old('id_number') }}" maxlength="50" autocomplete="off">
                            <div class="hint">Required for attendance matching. Letters, numbers, and leading zeros are accepted.</div>
                        </div>
                        <div class="cu-field">
                            <label for="affiliation" id="affiliation_label">College / Department</label>
                            <input type="text" id="affiliation" name="affiliation" value="{{ old('affiliation') }}" maxlength="255">
                        </div>
                    </div>

                    <div class="cu-grid cu-grid-2" style="margin-top:18px">
                        <div class="cu-field" id="program-field">
                            <label for="program" id="program_label">Program / Course</label>
                            <input type="text" id="program" name="program" value="{{ old('program') }}" maxlength="160">
                        </div>
                        <div class="cu-field" id="year-field" style="display:none">
                            <label for="yp-year_graduated">Year graduated</label>
                            @include('partials.year_picker', [
                                'name' => 'year_graduated',
                                'value' => old('year_graduated'),
                                'min' => 1950,
                            ])
                        </div>
                        <div class="cu-field" id="employment-field" style="display:none">
                            <label>Employment type</label>
                            <div class="cu-role-options" style="grid-template-columns:repeat(2,minmax(0,1fr)); margin-top:8px">
                                @foreach(\App\Models\User::EMPLOYMENT_TYPES as $value => $label)
                                    <label class="cu-role-card">
                                        <input type="radio" name="employment_type" value="{{ $value }}"
                                               class="cu-role-input" @checked(old('employment_type') === $value)>
                                        <span class="cu-role-option">
                                            <span class="cu-role-dot"></span>
                                            <span>{{ $label }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="cu-section">
                <div class="cu-section-head">
                    <div class="cu-section-icon" style="background:#fef3c7; color:#92400e">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10zm0 2c-4.4 0-8 2.2-8 5v2h16v-2c0-2.8-3.6-5-8-5z" fill="currentColor"/></svg>
                    </div>
                    <div>
                        <h3 class="cu-section-title" id="cu-admin-title">C. Admin account</h3>
                        <div class="cu-section-sub">Login credentials for the person managing this account.</div>
                    </div>
                </div>

                <div class="cu-grid cu-grid-3">
                    <div class="cu-field">
                        <label for="firstname">First name</label>
                        <input type="text" id="firstname" name="firstname" value="{{ old('firstname') }}" required>
                    </div>
                    <div class="cu-field">
                        <label for="middlename">Middle name <span style="font-weight:400; color:#94a3b8">(optional)</span></label>
                        <input type="text" id="middlename" name="middlename" value="{{ old('middlename') }}">
                    </div>
                    <div class="cu-field">
                        <label for="lastname">Last name</label>
                        <input type="text" id="lastname" name="lastname" value="{{ old('lastname') }}" required>
                    </div>
                </div>

                <div class="cu-grid cu-grid-3" style="margin-top:18px">
                    <div class="cu-field">
                        <label for="suffix">Suffix <span style="font-weight:400; color:#94a3b8">(optional)</span></label>
                        <input type="text" id="suffix" name="suffix" value="{{ old('suffix') }}">
                    </div>
                    <div class="cu-field">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" value="{{ old('username') }}" required>
                    </div>
                    <div class="cu-field">
                        <label for="email">Email address</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" required
                               autocomplete="off" spellcheck="false">
                        @php $acctDomains = allowed_account_email_domains(); @endphp
                        @if($acctDomains)
                            <div class="hint">Must end in {{ collect($acctDomains)->map(fn($d) => '@' . $d)->implode(' or ') }}. The password is emailed here.</div>
                        @else
                            <div class="hint">Institutional or personal address (e.g. @hnu.edu.ph or @gmail.com). The password is emailed here, so check it carefully.</div>
                        @endif
                    </div>
                    {{-- Retyping is the only thing that catches a typo in an address
                         that is otherwise perfectly well-formed. --}}
                    <div class="cu-field">
                        <label for="email_confirmation">Confirm email address</label>
                        <input type="email" id="email_confirmation" name="email_confirmation"
                               value="{{ old('email_confirmation') }}" required
                               autocomplete="off" spellcheck="false" onpaste="return false;">
                        <div class="hint">Retype it — paste is disabled here on purpose.</div>
                    </div>
                </div>

            </div>

            <div class="cu-actions">
                <a href="{{ route(dashboard_route_for(auth()->user())) }}" class="cu-btn-secondary"
                   data-confirm
                   data-confirm-title="Cancel account creation?"
                   data-confirm-body="Any information you entered will be lost."
                   data-confirm-tone="warning"
                   data-confirm-ok="Leave page"
                   data-confirm-cancel="Keep creating">Cancel</a>
                <button type="submit" class="cu-btn-primary">Create account</button>
            </div>
        </div>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Selected by name, not by class: .cu-role-input now styles every
            // radio card on this form, including participant type and
            // classification, so it no longer identifies the role radios alone.
            const roleInputs = document.querySelectorAll('input[name="role"]');
            const orgFields = document.getElementById('organization-fields');
            const partFields = document.getElementById('participant-fields');
            const classField = document.getElementById('classification-field');
            const adminTitle = document.getElementById('cu-admin-title');
            const orgRoles = ['student_org', 'college_department'];

            // Field labels and visibility per participant classification.
            const PTYPE = {
                student:  { id: 'Student ID No.',  aff: 'College / Department',            program: 'Program / Course',   year: false, employment: false },
                employee: { id: 'Employee ID No.', aff: 'College / Department or Office',  program: null,                 year: false, employment: true  },
                alumni:   { id: 'Alumni ID No.',   aff: 'College / Department',            program: 'Program graduated',  year: true,  employment: false },
            };

            const show = (el, on) => { if (el) el.style.display = on ? 'block' : 'none'; };

            const updateSections = () => {
                const role = document.querySelector('input[name="role"]:checked')?.value;
                const isOrg = orgRoles.includes(role);
                const isParticipant = role === 'participant';

                show(orgFields, isOrg);
                show(partFields, isParticipant);
                // Only student organizations are classified co/extra-curricular.
                show(classField, role === 'student_org');

                // Section letters shift with whichever middle section is showing.
                adminTitle.textContent = (isOrg || isParticipant) ? 'C. Admin account' : 'B. Admin account';

                updateParticipantType();
            };

            const updateParticipantType = () => {
                const type = document.querySelector('.cu-ptype-input:checked')?.value;
                const spec = PTYPE[type];
                show(document.getElementById('ptype-details'), Boolean(spec));
                document.getElementById('id_number').required = Boolean(spec)
                    && document.querySelector('input[name="role"]:checked')?.value === 'participant';
                if (!spec) return;

                document.getElementById('id_number_label').textContent = spec.id;
                document.getElementById('affiliation_label').textContent = spec.aff;

                show(document.getElementById('program-field'), Boolean(spec.program));
                if (spec.program) document.getElementById('program_label').textContent = spec.program;

                show(document.getElementById('year-field'), spec.year);
                show(document.getElementById('employment-field'), spec.employment);
            };

            roleInputs.forEach((input) => input.addEventListener('change', updateSections));
            document.querySelectorAll('.cu-ptype-input')
                .forEach((input) => input.addEventListener('change', updateParticipantType));
            updateSections();

            const logoInput = document.getElementById('org_logo');
            const logoDrop = document.getElementById('cu-logo-drop');
            const logoPreview = document.getElementById('cu-logo-preview');
            const logoName = document.getElementById('cu-logo-name');

            logoDrop.addEventListener('click', () => logoInput.click());

            const showLogo = (file) => {
                logoName.textContent = file.name;
                const reader = new FileReader();
                reader.onload = (e) => { logoPreview.innerHTML = `<img src="${e.target.result}" alt="Logo preview">`; };
                reader.readAsDataURL(file);
            };

            logoInput.addEventListener('change', () => {
                if (logoInput.files.length) showLogo(logoInput.files[0]);
            });

            logoDrop.addEventListener('dragover', (e) => { e.preventDefault(); logoDrop.classList.add('active'); });
            logoDrop.addEventListener('dragleave', () => logoDrop.classList.remove('active'));
            logoDrop.addEventListener('drop', (e) => {
                e.preventDefault();
                logoDrop.classList.remove('active');
                if (e.dataTransfer.files.length) {
                    const dt = new DataTransfer();
                    dt.items.add(e.dataTransfer.files[0]);
                    logoInput.files = dt.files;
                    showLogo(logoInput.files[0]);
                }
            });
        });
    </script>
@endsection
