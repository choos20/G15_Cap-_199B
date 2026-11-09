@extends('layouts.admin')

@section('title', 'Edit User')
@section('subtitle', 'Update organization information and remove logo if needed')

@section('content')
    <style>
        .cu-section { padding:28px; }
        .cu-section + .cu-section { border-top:1px solid #e5e7eb; }
        .cu-section-head { display:flex; align-items:center; gap:14px; margin-bottom:22px; }
        .cu-section-icon { width:42px; height:42px; border-radius:14px; display:flex; align-items:center; justify-content:center; background:#eef2ff; color:#4338ca; flex-shrink:0; }
        .cu-section-title { font-size:1.05rem; font-weight:700; color:#0f172a; margin:0; }
        .cu-section-sub { font-size:0.88rem; color:#64748b; margin-top:2px; }
        .cu-grid { display:grid; gap:18px; }
        .cu-grid-2 { grid-template-columns:repeat(2,minmax(0,1fr)); }
        @media (max-width:820px) { .cu-grid-2 { grid-template-columns:1fr; } }
        .cu-field label { display:block; font-weight:700; font-size:0.9rem; color:#0f172a; margin-bottom:8px; }
        .cu-field input[type=text], .cu-field input[type=email], .cu-field select, .cu-field textarea {
            width:100%; padding:13px 14px; border:1px solid #d1d5db; border-radius:12px; font:inherit; background:#f8fafc; transition:all 150ms ease;
        }
        .cu-field input:focus, .cu-field textarea:focus { outline:none; border-color:#3b82f6; background:#fff; box-shadow:0 0 0 4px rgba(59,130,246,0.1); }
        .cu-field input:disabled { background:#f1f5f9; color:#64748b; cursor:not-allowed; }
        .cu-field .hint { font-size:0.82rem; color:#94a3b8; margin-top:6px; }
        .cu-role-pill { display:inline-flex; padding:8px 16px; border-radius:999px; font-size:0.9rem; font-weight:700; background:#eef2ff; color:#4338ca; }
        .cu-logo-drop {
            display:flex; align-items:center; gap:16px; padding:16px; border:2px dashed #cbd5e1; border-radius:16px; background:#f8fbff; cursor:pointer; transition:all 150ms ease;
        }
        .cu-logo-drop:hover, .cu-logo-drop.active { border-color:#3b82f6; background:#eff6ff; }
        .cu-logo-preview { width:56px; height:56px; border-radius:14px; background:#dbeafe; color:#1d4ed8; display:flex; align-items:center; justify-content:center; font-size:1.4rem; flex-shrink:0; overflow:hidden; }
        .cu-logo-preview img { width:100%; height:100%; object-fit:cover; }
        .cu-logo-text strong { display:block; color:#0f172a; font-size:0.92rem; }
        .cu-logo-text span { color:#64748b; font-size:0.85rem; }
        .cu-remove-logo { margin-top:12px; background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; padding:9px 16px; border-radius:10px; font-weight:700; font-size:0.85rem; cursor:pointer; }
        .cu-actions { display:flex; justify-content:flex-end; gap:12px; padding:24px 28px; background:#f8fafc; border-top:1px solid #e5e7eb; border-radius:0 0 24px 24px; }
        .cu-btn-secondary { padding:13px 24px; border-radius:12px; background:#ffffff; color:#0f172a; border:1px solid #d1d5db; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; }
        .cu-btn-primary { padding:13px 26px; border-radius:12px; background:#0b6a4a; color:#ffffff; border:none; font-weight:700; cursor:pointer; }
        .cu-btn-primary:hover { background:#0f8a61; }
    </style>

    @if(session('status'))
        <div class="card" style="border-left:4px solid #16a34a; background:#ecfdf5; color:#134e4a; padding:16px 20px; margin-bottom:20px">
            {{ session('status') }}
        </div>
    @endif
    @if(session('email_change_warning'))
        <div class="card" style="border-left:4px solid #d97706; background:#fffbeb; color:#92400e; padding:16px 20px; margin-bottom:20px">
            {{ session('email_change_warning') }}
        </div>
    @endif

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

    <form method="POST" action="{{ route('users.update', $user) }}" enctype="multipart/form-data" novalidate>
        @csrf
        <input type="hidden" name="remove_org_logo" id="cu-remove-org-logo" value="0">

        <div class="card" style="padding:0; overflow:hidden; max-width:760px">
            <div class="cu-section">
                <div class="cu-section-head">
                    <div class="cu-section-icon" style="background:#fef3c7; color:#92400e">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10zm0 2c-4.4 0-8 2.2-8 5v2h16v-2c0-2.8-3.6-5-8-5z" fill="currentColor"/></svg>
                    </div>
                    <div>
                        <h3 class="cu-section-title">Account</h3>
                        <div class="cu-section-sub">CSA Directors can change student-organization names and emails; the CELP Head can change any account.</div>
                    </div>
                </div>

                <div class="cu-grid cu-grid-2">
                    <div class="cu-field">
                        <label @if($canChangeName) for="first_name" @endif>{{ $canChangeName ? 'First name' : 'Name' }}</label>
                        @if($canChangeName)
                            <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $user->first_name ?: $user->name) }}" autocomplete="given-name" required>
                        @else
                            <input type="text" value="{{ $user->name }}" disabled>
                        @endif
                    </div>
                    <div class="cu-field">
                        <label>Email</label>
                        @if($canChangeEmail)
                            <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" autocomplete="email" required>
                            <div class="hint">The new address must be verified at the account's next sign-in. The previous address will be notified.</div>
                        @else
                            <input type="email" value="{{ $user->email }}" disabled>
                        @endif
                    </div>
                    @if($user->role === 'participant')
                        <div class="cu-field">
                            <label for="id_number">{{ $user->idNumberLabel() }} <span style="color:#b91c1c">*</span></label>
                            <input type="text" id="id_number" name="id_number" value="{{ old('id_number', $user->id_number) }}" maxlength="50" autocomplete="off" required>
                            <div class="hint">Required for matching this participant to the attendance CSV roster. Keep leading zeros; letters and numbers are accepted.</div>
                        </div>
                    @endif
                    @if($canChangeEmail)
                        <div class="cu-field">
                            <label for="email_confirmation">Confirm new email</label>
                            <input type="email" id="email_confirmation" name="email_confirmation" value="{{ old('email_confirmation') }}" autocomplete="email" required>
                        </div>
                    @endif
                    @if($canChangeName)
                        <div class="cu-field">
                            <label for="middle_name">Middle name</label>
                            <input type="text" id="middle_name" name="middle_name" value="{{ old('middle_name', $user->middle_name) }}" autocomplete="additional-name">
                        </div>
                        <div class="cu-field">
                            <label for="last_name">Last name</label>
                            <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $user->last_name) }}" autocomplete="family-name">
                        </div>
                        <div class="cu-field">
                            <label for="suffix">Suffix</label>
                            <input type="text" id="suffix" name="suffix" value="{{ old('suffix', $user->suffix) }}" autocomplete="honorific-suffix">
                        </div>
                    @endif
                </div>

                <div class="cu-field" style="margin-top:18px">
                    <label>Role</label>
                    <div><span class="cu-role-pill">{{ ucwords(str_replace('_', ' ', $user->role)) }}</span></div>
                </div>
            </div>

            <div class="cu-section">
                <div class="cu-section-head">
                    <div class="cu-section-icon" style="background:#ecfdf5; color:#047857">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 4h7l3 3h6v13H4V4z" fill="currentColor"/></svg>
                    </div>
                    <div>
                        <h3 class="cu-section-title">Organization information</h3>
                        <div class="cu-section-sub">Shown on the org's dashboard and to CELP reviewers.</div>
                    </div>
                </div>

                <div class="cu-field">
                    <label for="organization_name">Organization / department name</label>
                    <input type="text" id="organization_name" name="organization_name" value="{{ old('organization_name', $user->organization_name) }}">
                </div>

                <div class="cu-field" style="margin-top:18px">
                    <label for="org_logo">Logo</label>
                    <input id="org_logo" type="file" name="org_logo" accept="image/*" style="display:none">
                    <div id="cu-logo-drop" class="cu-logo-drop">
                        <div class="cu-logo-preview" id="cu-logo-preview">
                            @if($user->orgLogoUrl())
                                <img src="{{ $user->orgLogoUrl() }}" alt="Current logo">
                            @else
                                🏢
                            @endif
                        </div>
                        <div class="cu-logo-text">
                            <strong id="cu-logo-name">{{ $user->org_logo ? 'Click to replace the logo' : 'Click to upload a logo' }}</strong>
                            <span>PNG or JPG, up to 2MB</span>
                        </div>
                    </div>
                    @if($user->org_logo)
                        <button type="button" class="cu-remove-logo" id="cu-remove-logo">
                            Remove current logo
                        </button>
                    @endif
                </div>

                <div class="cu-field" style="margin-top:18px">
                    <label for="org_description">Description</label>
                    <textarea id="org_description" name="org_description" rows="4" maxlength="300" placeholder="What the organization does, who it's for">{{ old('org_description', $user->org_description) }}</textarea>
                    <div class="hint">Max 300 characters.</div>
                </div>
            </div>

            <div class="cu-actions">
                <a href="{{ route('users.index') }}" class="cu-btn-secondary">Back to user list</a>
                <button type="submit" class="cu-btn-primary">Save changes</button>
            </div>
        </div>
    </form>

    {{-- Recovery path when the credential email never arrived. Nobody but the
         account holder knows the password, so this is the only way back in. --}}
    @if($user->id !== auth()->id())
        <div class="card" style="max-width:760px; margin-top:18px; padding:24px">
            <div style="display:flex; align-items:flex-start; gap:16px; flex-wrap:wrap">
                <div style="flex:1; min-width:260px">
                    <h3 style="margin:0 0 6px; font-size:1rem; color:#0f172a">Sign-in access</h3>
                    <div style="color:#64748b; font-size:0.9rem; line-height:1.6">
                        @if($user->must_change_password)
                            This account still has an unused temporary password and hasn't set its own yet.
                        @else
                            This account has set its own password.
                        @endif
                        Issue a new temporary password if the original email never arrived — it is
                        emailed to <strong>{{ $user->email }}</strong> and must be replaced at next sign-in.
                    </div>
                    @error('reset')
                        <div style="margin-top:10px; padding:10px 13px; border-radius:9px; background:#fef2f2;
                                    border:1px solid #fecaca; color:#b91c1c; font-size:0.86rem">{{ $message }}</div>
                    @enderror
                </div>

                <form method="POST" action="{{ route('users.password.reset', $user) }}" style="flex-shrink:0"
                      data-confirm
                      data-confirm-title="Issue a new temporary password?"
                      data-confirm-body="{name}'s current password stops working immediately, and a new one is emailed to them."
                      data-confirm-name="{{ $user->name }}"
                      data-confirm-note="They must set their own password at next sign-in."
                      data-confirm-ok="Send new password"
                      data-confirm-cancel="Cancel">
                    @csrf
                    <button type="submit" class="cu-btn-secondary" style="cursor:pointer">Email a new temporary password</button>
                </form>
            </div>
        </div>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const logoInput = document.getElementById('org_logo');
            const logoDrop = document.getElementById('cu-logo-drop');
            const logoPreview = document.getElementById('cu-logo-preview');
            const logoName = document.getElementById('cu-logo-name');
            const removeLogoInput = document.getElementById('cu-remove-org-logo');
            const removeLogoButton = document.getElementById('cu-remove-logo');
            const originalLogoName = logoName.textContent;
            const originalLogoPreview = logoPreview.innerHTML;

            logoDrop.addEventListener('click', () => logoInput.click());

            const showLogo = (file) => {
                removeLogoInput.value = '0';
                if (removeLogoButton) {
                    removeLogoButton.hidden = true;
                }
                logoName.textContent = file.name;
                const reader = new FileReader();
                reader.onload = (e) => { logoPreview.innerHTML = `<img src="${e.target.result}" alt="Logo preview">`; };
                reader.readAsDataURL(file);
            };

            if (removeLogoButton) {
                removeLogoButton.addEventListener('click', () => {
                    const removalPending = removeLogoInput.value === '1';
                    removeLogoInput.value = removalPending ? '0' : '1';
                    removeLogoButton.textContent = removalPending ? 'Remove current logo' : 'Undo logo removal';
                    logoName.textContent = removalPending ? originalLogoName : 'Logo will be removed when you save';
                    logoPreview.innerHTML = removalPending ? originalLogoPreview : '🏢';
                });
            }

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
