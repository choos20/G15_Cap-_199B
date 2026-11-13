@extends('layouts.admin')

@section('title', 'Edit Announcement')
@section('subtitle', 'Update your announcement details and attached files')

@section('content')
    @php
        $selectedCategory = old('category');
        if (! in_array($selectedCategory, ['Volunteer recruitment', 'General'], true)) {
            $selectedCategory = $announcement->category === 'Volunteer recruitment' ? 'Volunteer recruitment' : 'General';
        }
    @endphp
    <style>
        [data-category-fields][hidden] { display:none !important; }
        .ann-edit { max-width:940px; margin:0 auto; display:grid; gap:18px; }
        .ann-edit-head { display:flex; align-items:flex-start; gap:14px; padding:22px 26px; border-bottom:1px solid #e3ece8; background:linear-gradient(120deg,#f0faf6,#fbfdfc); }
        .ann-edit-head-icon { display:grid; place-items:center; flex:0 0 42px; height:42px; border-radius:14px; background:#d9f3e8; color:#08734e; }
        .ann-edit-eyebrow { margin:0 0 4px; color:#08734e; font-size:.72rem; font-weight:800; letter-spacing:.1em; text-transform:uppercase; }
        .ann-edit-title { margin:0; color:#173b30; font-size:1.2rem; font-weight:800; letter-spacing:-.02em; }
        .ann-edit-intro { margin:5px 0 0; color:#647a71; font-size:.87rem; line-height:1.5; }
        .ann-edit-card { padding:0 !important; overflow:hidden; border:1px solid #e3ece8; border-radius:18px; box-shadow:0 12px 35px rgba(15,55,43,.06); }
        .ann-edit-form-body { display:grid; gap:20px; padding:24px 26px; }
        .ann-edit-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
        .ann-edit-field label { display:block; margin-bottom:7px; color:#0f172a; font-weight:700; }
        .ann-edit-field input, .ann-edit-field select, .ann-edit-field textarea {
            width:100%; padding:12px 14px; border:1px solid #cbd5e1; border-radius:10px;
            background:#fff; color:#0f172a; font:inherit;
        }
        .ann-edit-field textarea { min-height:110px; resize:vertical; }
        .ann-edit-help { margin-top:5px; color:#64748b; font-size:.82rem; }
        .ann-edit-error { margin-top:6px; color:#b91c1c; font-size:.88rem; }
        .ann-edit-category-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; }
        .ann-edit-category { display:flex; align-items:flex-start; gap:11px; min-height:94px; padding:15px; border:1px solid #dfe9e4; border-radius:14px; background:#fff; cursor:pointer; transition:border-color .18s ease,background .18s ease,box-shadow .18s ease; }
        .ann-edit-category:hover { border-color:#8dcab0; background:#fbfefc; }
        .ann-edit-category:has(input:checked) { border-color:#15946a; background:#f0faf5; box-shadow:0 0 0 3px rgba(21,148,106,.1); }
        .ann-edit-category input { flex:0 0 auto; width:17px; height:17px; margin:2px 0 0; accent-color:#0b7656; }
        .ann-edit-category strong { display:block; color:#183b30; font-size:.9rem; }
        .ann-edit-category span span { display:block; margin-top:5px; color:#697d75; font-size:.81rem; line-height:1.45; }
        .ann-edit-hint { display:flex; gap:9px; padding:11px 13px; border-radius:10px; background:#f1f8f5; color:#49685b; font-size:.82rem; line-height:1.5; }
        .ann-edit-hint svg { flex:0 0 auto; color:#13835c; }
        .ann-edit-files { display:grid; gap:9px; margin-top:10px; }
        .ann-edit-file { display:flex; align-items:center; gap:12px; padding:11px 13px; border:1px solid #e2e8f0; border-radius:10px; background:#f8fafc; }
        .ann-edit-file-info { flex:1; min-width:0; overflow-wrap:anywhere; font-size:.9rem; font-weight:600; }
        .ann-edit-file-info span { display:block; margin-top:3px; color:#64748b; font-size:.78rem; font-weight:400; }
        .ann-edit-file a { color:#08734e; font-size:.84rem; font-weight:700; }
        .ann-edit-remove { padding:7px 10px; border:1px solid #fecaca; border-radius:8px; background:#fff; color:#b91c1c; font-weight:700; cursor:pointer; }
        .ann-edit-actions { display:flex; justify-content:flex-end; gap:10px; flex-wrap:wrap; margin-top:20px; }
        .ann-edit-actions a, .ann-edit-actions button { padding:11px 17px; border-radius:9px; font:inherit; font-weight:700; text-decoration:none; cursor:pointer; }
        .ann-edit-back { border:1px solid #cbd5e1; background:#fff; color:#334155; }
        .ann-edit-save { border:1px solid #0b7656; background:#0b7656; color:#fff; }
        @media(max-width:680px) { .ann-edit-grid, .ann-edit-category-grid { grid-template-columns:1fr; } .ann-edit-head { padding:19px; } .ann-edit-form-body { padding:20px; } }
    </style>

    <div class="ann-edit">
        <a href="{{ route('student_org.announcements.show', $announcement) }}" style="color:#2563eb; text-decoration:none; font-weight:700;">&larr; Back to announcement</a>

        @if(session('status'))
            <div class="card" style="border-left:4px solid #16a34a; background:#ecfdf5; color:#134e4a; padding:15px 18px">{{ session('status') }}</div>
        @endif

        @if($errors->any())
            <div class="card" role="alert" style="border-left:4px solid #dc2626; background:#fef2f2; color:#7f1d1d; padding:15px 18px">
                <strong>Please fix the following:</strong>
                <ul style="margin:7px 0 0; padding-left:20px">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div class="card ann-edit-card">
        <div class="ann-edit-head">
            <div class="ann-edit-head-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4L16.5 3.5Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <div>
                <div class="ann-edit-eyebrow">Announcement settings</div>
                <h2 class="ann-edit-title">Edit your announcement</h2>
                <p class="ann-edit-intro">Update the post and choose whether it is a volunteer opportunity or a general notice.</p>
                @if($announcement->category === 'Volunteer recruitment')
                    <p class="ann-edit-intro">This activity's recruitment is a single retained post. You can update its details and volunteer slots, but cannot move it to another activity or change it to a general announcement.</p>
                @endif
            </div>
        </div>
        <form id="announcement-edit-form" method="POST" action="{{ route('student_org.announcements.update', $announcement) }}" enctype="multipart/form-data"
              data-confirm-navigation
              data-confirm-title="Leave announcement editing?"
              data-confirm-body="You have unsaved changes. If you leave now, they will be lost."
              data-confirm-tone="warning"
              data-confirm-ok="Leave page"
              data-confirm-cancel="Keep editing">
            @csrf
            @method('PUT')

            <div class="ann-edit-form-body">
                <fieldset style="border:0; margin:0; padding:0">
                    <legend style="display:block; margin-bottom:9px; color:#203b32; font-size:.89rem; font-weight:750">Announcement type</legend>
                    <div class="ann-edit-category-grid">
                        <label class="ann-edit-category">
                            <input type="radio" name="category" value="Volunteer recruitment" required @checked($selectedCategory === 'Volunteer recruitment')>
                            <span><strong>Volunteer recruitment</strong><span>Invite participants to apply for an approved activity.</span></span>
                        </label>
                        <label class="ann-edit-category">
                            <input type="radio" name="category" value="General" required @checked($selectedCategory === 'General') @disabled($announcement->category === 'Volunteer recruitment')>
                            <span><strong>General announcement</strong><span>Share news and reminders, with an optional activity link.</span></span>
                        </label>
                    </div>
                    @error('category')<div class="ann-edit-error">{{ $message }}</div>@enderror
                </fieldset>

                <div class="ann-edit-hint" role="status" aria-live="polite">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M12 11v5m0-8h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    <span id="edit-category-hint"></span>
                </div>

                <div class="ann-edit-field" data-category-fields="linked-activity">
                    <label for="project_id">Link an approved activity <span style="font-weight:500; color:#74857d">(optional for general announcements)</span></label>
                    <select id="project_id" name="project_id" @disabled($announcement->category === 'Volunteer recruitment')>
                        <option value="">No linked activity</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" @selected((string) old('project_id', $announcement->project_id) === (string) $project->id)>
                                {{ $project->title }} — {{ ucfirst(str_replace('_', ' ', $project->status)) }}
                            </option>
                        @endforeach
                    </select>
                    @if($announcement->category === 'Volunteer recruitment')
                        <input type="hidden" name="project_id" value="{{ $announcement->project_id }}">
                    @endif
                    <div class="ann-edit-help" id="edit-project-help">Select the approved activity participants will apply to.</div>
                    @error('project_id')<div class="ann-edit-error">{{ $message }}</div>@enderror
                </div>

                <div class="ann-edit-grid">
                    <div class="ann-edit-field" style="grid-column:1/-1">
                        <label for="title">Title</label>
                        <input id="title" name="title" type="text" maxlength="255" required value="{{ old('title', $announcement->title) }}" placeholder="Give your announcement a clear title">
                        @error('title')<div class="ann-edit-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="ann-edit-field" style="grid-column:1/-1">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" maxlength="300" placeholder="Add the details participants need to know">{{ old('description', $announcement->description) }}</textarea>
                        <div class="ann-edit-help">Maximum 300 characters.</div>
                        @error('description')<div class="ann-edit-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="ann-edit-field" data-category-fields="recruitment">
                        <label for="slots_needed">Volunteers needed <span style="color:#b91c1c">*</span></label>
                        <input id="slots_needed" name="slots_needed" type="number" min="1" step="1" value="{{ old('slots_needed', $announcement->slots_needed) }}">
                        <div class="ann-edit-help">Enter how many volunteers your activity needs. This cannot be less than the volunteers already approved.</div>
                        @error('slots_needed')<div class="ann-edit-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="ann-edit-field" data-category-fields="recruitment">
                        <label for="application_deadline">Application deadline</label>
                        <input id="application_deadline" name="application_deadline" type="datetime-local"
                               min="{{ $announcement->application_deadline && $announcement->application_deadline->isPast() ? $announcement->application_deadline->timezone('Asia/Manila')->format('Y-m-d\TH:i') : now('Asia/Manila')->format('Y-m-d\TH:i') }}"
                               max="{{ now('Asia/Manila')->addYear()->startOfMinute()->format('Y-m-d\TH:i') }}"
                               value="{{ old('application_deadline', $announcement->application_deadline?->timezone('Asia/Manila')->format('Y-m-d\TH:i')) }}">
                        <div class="ann-edit-help">Choose the date and time applications close, or leave blank for no deadline.</div>
                        @error('application_deadline')<div class="ann-edit-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="ann-edit-field" data-category-fields="recruitment">
                        <label for="event_date">Event date &amp; time</label>
                        <input id="event_date" name="event_date" type="datetime-local"
                               min="{{ now('Asia/Manila')->format('Y-m-d\TH:i') }}"
                               value="{{ old('event_date', $announcement->event_date?->format('Y-m-d\TH:i')) }}">
                        <div class="ann-edit-help">
                            @if($announcement->event_date && $announcement->event_date->isPast())
                                This announcement already has a past event date. Leave it unchanged to save other edits, or select a future date.
                            @else
                                Optional. Must be now or in the future.
                            @endif
                        </div>
                        @error('event_date')<div class="ann-edit-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="ann-edit-field" data-category-fields="recruitment">
                        <label for="event_location">Event location</label>
                        <input id="event_location" name="event_location" type="text" maxlength="255" value="{{ old('event_location', $announcement->event_location) }}">
                        @error('event_location')<div class="ann-edit-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="ann-edit-field" data-category-fields="recruitment">
                        <label for="latitude">Map latitude <span style="font-weight:400; color:#64748b">(optional)</span></label>
                        <input id="latitude" name="latitude" type="number" step="any" min="-90" max="90" value="{{ old('latitude', $announcement->latitude) }}">
                        @error('latitude')<div class="ann-edit-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="ann-edit-field" data-category-fields="recruitment">
                        <label for="longitude">Map longitude <span style="font-weight:400; color:#64748b">(optional)</span></label>
                        <input id="longitude" name="longitude" type="number" step="any" min="-180" max="180" value="{{ old('longitude', $announcement->longitude) }}">
                        @error('longitude')<div class="ann-edit-error">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div style="height:1px; background:#e9efec"></div>
            </div>
        </form>

            <div class="ann-edit-field" style="margin-top:22px">
                <label>Current attachments ({{ $announcement->attachments->count() }}/5)</label>
                @if($announcement->attachments->isEmpty())
                    <div class="ann-edit-help">No files are currently attached.</div>
                @else
                    <div class="ann-edit-files">
                        @foreach($announcement->attachments as $attachment)
                            <div class="ann-edit-file">
                                <div class="ann-edit-file-info">
                                    {{ $attachment->original_name }}
                                    <span>{{ strtoupper(pathinfo($attachment->original_name, PATHINFO_EXTENSION)) }} · {{ number_format($attachment->size / 1024 / 1024, 2) }} MB</span>
                                </div>
                                <a href="{{ asset('storage/'.$attachment->file_path) }}" target="_blank" rel="noopener">View</a>
                                <form method="POST" action="{{ route('student_org.announcements.attachments.destroy', [$announcement, $attachment]) }}"
                                      data-confirm
                                      data-confirm-title="Remove attachment?"
                                      data-confirm-body="{name} will be removed from this announcement."
                                      data-confirm-name="{{ $attachment->original_name }}"
                                      data-confirm-ok="Remove file"
                                      data-confirm-cancel="Keep file">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="return_to" value="edit">
                                    <button class="ann-edit-remove" type="submit">Remove</button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="ann-edit-field" style="margin-top:22px">
                <label for="attachments">Add attachments</label>
                <input id="attachments" name="attachments[]" type="file" multiple accept="image/jpeg,image/png,application/pdf" form="announcement-edit-form">
                <div class="ann-edit-help">JPG, PNG or PDF · Maximum 5MB each · Up to {{ max(0, 5 - $announcement->attachments->count()) }} additional file(s).</div>
                @error('attachments')<div class="ann-edit-error">{{ $message }}</div>@enderror
                @foreach($errors->getMessages() as $field => $messages)
                    @if(str_starts_with($field, 'attachments.'))
                        @foreach($messages as $message)<div class="ann-edit-error">{{ $message }}</div>@endforeach
                    @endif
                @endforeach
                <div id="new-attachment-error" class="ann-edit-error" role="alert" style="display:none"></div>
                <div id="new-attachment-list" class="ann-edit-files" style="display:none"></div>
            </div>

            <div class="ann-edit-actions">
                <a class="ann-edit-back" href="{{ route('student_org.announcements.show', $announcement) }}">Cancel</a>
                <button class="ann-edit-save" type="submit" form="announcement-edit-form">Save changes</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const categoryInputs = document.querySelectorAll('input[name="category"]');
            const project = document.getElementById('project_id');
            const projectHelp = document.getElementById('edit-project-help');
            const categoryHint = document.getElementById('edit-category-hint');
            if (!categoryInputs.length || !project || !projectHelp || !categoryHint) return;

            const recruitmentFields = document.querySelectorAll('[data-category-fields="recruitment"]');
            const slotsNeeded = document.getElementById('slots_needed');
            const syncCategoryFields = () => {
                const isRecruitment = document.querySelector('input[name="category"]:checked')?.value === 'Volunteer recruitment';

                project.required = isRecruitment;
                slotsNeeded.required = isRecruitment;
                projectHelp.textContent = isRecruitment
                    ? 'Select the approved activity participants will apply to.'
                    : 'Optional: link this notice to an approved activity for context, or leave it unlinked for an organization-wide announcement.';
                categoryHint.textContent = isRecruitment
                    ? 'Participants can apply to volunteer through this announcement. Complete the recruitment details below.'
                    : 'This is an information-only post. It can be shared with everyone, with no volunteer application or map.';

                recruitmentFields.forEach((field) => {
                    field.hidden = !isRecruitment;
                    field.querySelectorAll('input, select, textarea, button').forEach((input) => {
                        input.disabled = !isRecruitment;
                    });
                });
            };

            categoryInputs.forEach((input) => input.addEventListener('change', syncCategoryFields));
            syncCategoryFields();
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const input = document.getElementById('attachments');
            const list = document.getElementById('new-attachment-list');
            const error = document.getElementById('new-attachment-error');
            const maxFiles = {{ max(0, 5 - $announcement->attachments->count()) }};
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
                    row.className = 'ann-edit-file';
                    const info = document.createElement('div');
                    info.className = 'ann-edit-file-info';
                    info.append(document.createTextNode(file.name));
                    const size = document.createElement('span');
                    size.textContent = `${(file.size / 1024 / 1024).toFixed(2)} MB`;
                    info.appendChild(size);
                    row.appendChild(info);

                    const preview = document.createElement('a');
                    preview.href = URL.createObjectURL(file);
                    preview.target = '_blank';
                    preview.rel = 'noopener';
                    preview.textContent = 'View';
                    previewUrls.push(preview.href);
                    row.appendChild(preview);

                    const remove = document.createElement('button');
                    remove.type = 'button';
                    remove.className = 'ann-edit-remove';
                    remove.textContent = 'Remove';
                    remove.addEventListener('click', () => {
                        selectedFiles.splice(index, 1);
                        syncInput();
                        render();
                    });
                    row.appendChild(remove);
                    list.appendChild(row);
                });
            };

            input.addEventListener('change', () => {
                const incoming = Array.from(input.files);
                if (selectedFiles.length + incoming.length > maxFiles) {
                    error.textContent = 'You can attach no more than 5 files total. Remove an existing attachment first.';
                    error.style.display = 'block';
                } else if (incoming.some((file) => file.size > 5 * 1024 * 1024)) {
                    error.textContent = 'Each attachment must be 5MB or smaller.';
                    error.style.display = 'block';
                } else {
                    selectedFiles.push(...incoming);
                    error.textContent = '';
                    error.style.display = 'none';
                }

                syncInput();
                render();
            });
        });
    </script>
@endsection
