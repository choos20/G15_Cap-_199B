@extends('layouts.admin')

@section('title', 'Edit Returned Proposal')
@section('subtitle', 'Review reviewer comments, update your proposal, and resubmit')

@section('content')
    <style>
        .upload-page {
            min-height:calc(100vh - 100px);
            background:linear-gradient(180deg, #f8fafc 0%, #eef2ff 40%, #ffffff 100%);
            padding:40px 20px 60px;
        }
        .upload-card {
            max-width:840px;
            margin:auto;
            background:#ffffff;
            border:1px solid #e2e8f0;
            border-radius:32px;
            box-shadow:0 28px 64px rgba(15, 23, 42, 0.08);
            padding:36px;
        }
        .upload-card h3 {
            margin:0 0 8px;
            font-size:1.9rem;
            letter-spacing:-0.02em;
            color:#0f172a;
        }
        .upload-card .intro {
            margin:0 0 24px;
            color:#475569;
            font-size:1rem;
            line-height:1.75;
            max-width:700px;
        }
        .upload-field {
            margin-bottom:22px;
        }
        .upload-field label {
            display:block;
            margin-bottom:10px;
            font-weight:700;
            color:#0f172a;
        }
        .text-input,
        .textarea-field {
            width:100%;
            padding:16px 18px;
            border-radius:16px;
            border:1px solid #d1d5db;
            background:#f8fafc;
            color:#0f172a;
            font-size:1rem;
            transition:all 180ms ease;
        }
        .text-input:focus,
        .textarea-field:focus,
        .dropzone:focus {
            border-color:#3b82f6;
            outline:none;
            box-shadow:0 0 0 4px rgba(59,130,246,0.12);
        }
        .textarea-field { min-height:150px; resize:vertical; }
        .field-helper { margin-top:8px; color:#64748b; font-size:0.9rem; }
        .dropzone {
            position:relative;
            min-height:180px;
            border:2px dashed #cbd5e1;
            border-radius:22px;
            background:#f8fbff;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:28px;
            text-align:center;
            transition:all 180ms ease;
            cursor:pointer;
        }
        .dropzone.active,
        .dropzone:hover {
            border-color:#3b82f6;
            background:#eff6ff;
        }
        .dropzone-inner {
            display:flex;
            flex-direction:column;
            align-items:center;
            gap:16px;
            max-width:540px;
        }
        .dropzone-icon {
            width:56px;
            height:56px;
            border-radius:18px;
            background:#dbeafe;
            color:#1d4ed8;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:1.45rem;
            box-shadow:0 10px 30px rgba(59,130,246,0.12);
        }
        .dropzone strong { display:block; font-size:1rem; color:#0f172a; margin-bottom:6px; }
        .dropzone-text { margin:0; color:#475569; font-size:0.96rem; line-height:1.7; }
        .hidden-input { display:none; }
        .file-note { font-size:0.9rem; color:#475569; margin-top:16px; line-height:1.6; }
        .attachment-list { margin-top:20px; display:grid; gap:14px; }
        .attachment-item {
            display:grid;
            grid-template-columns:auto 1fr auto;
            gap:14px;
            align-items:center;
            padding:16px 18px;
            border-radius:20px;
            background:#ffffff;
            border:1px solid #e5e7eb;
            box-shadow:0 12px 28px rgba(15, 23, 42, 0.06);
        }
        .attachment-icon {
            width:50px;
            height:50px;
            border-radius:16px;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:1.3rem;
            color:#1e3a8a;
            flex-shrink:0;
            background:#eff6ff;
        }
        .attachment-info strong {
            display:block;
            font-size:1rem;
            color:#0f172a;
            overflow:hidden;
            text-overflow:ellipsis;
            white-space:nowrap;
        }
        .attachment-info span { display:block; color:#64748b; margin-top:4px; font-size:0.92rem; }
        .attachment-remove {
            border:1px solid #cbd5e1;
            background:#ffffff;
            color:#0f172a;
            border-radius:14px;
            padding:10px 16px;
            cursor:pointer;
            font-weight:600;
            transition:all 150ms ease;
        }
        .attachment-remove:hover { transform:translateY(-1px); background:#f8fafc; }
        .attachment-meta {
            display:flex;
            justify-content:space-between;
            align-items:center;
            flex-wrap:wrap;
            gap:12px;
            color:#475569;
            font-size:0.95rem;
            margin-top:12px;
        }
        .upload-actions {
            display:flex;
            flex-wrap:wrap;
            gap:14px;
            align-items:center;
            justify-content:flex-end;
            margin-top:28px;
        }
        .button-secondary,
        .button-primary {
            display:inline-flex;
            align-items:center;
            justify-content:center;
            padding:14px 22px;
            border-radius:14px;
            font-weight:700;
            min-width:160px;
            border:none;
            transition:all 180ms ease;
            text-decoration:none;
        }
        .button-secondary {
            border:1px solid #cbd5e1;
            background:#f8fafc;
            color:#0f172a;
        }
        .button-secondary:hover { background:#eef2ff; }
        .button-primary {
            background:#0fbf91;
            color:#ffffff;
            box-shadow:0 12px 30px rgba(15, 89, 72, 0.16);
        }
        .button-primary:hover { transform:translateY(-1px); background:#10b981; }
        .error-text { color:#b91c1c; margin-top:10px; font-size:0.93rem; }
        .return-comments {
            margin-bottom:24px;
            padding:18px;
            border-radius:20px;
            background:#fef3c7;
            color:#92400e;
            border:1px solid #fcd34d;
        }
        @media (max-width:720px) {
            .upload-card { padding:28px; }
            .attachment-item { grid-template-columns:1fr auto; }
            .attachment-icon { width:44px; height:44px; }
            .upload-actions { justify-content:stretch; }
            .button-secondary, .button-primary { width:100%; min-width:0; }
        }
    </style>

    <div class="upload-page">
        <div class="upload-card">
            <h3>Edit returned proposal</h3>
            <p class="intro">Review the comments from your reviewer, update the activity, and resubmit the proposal when ready.</p>

            @if($project->review_comments)
                <div class="return-comments">
                    <strong>Reviewer comments</strong>
                    <p style="margin:10px 0 0; white-space:pre-line">{{ $project->review_comments }}</p>
                </div>
            @endif

            <form method="post" action="{{ route('projects.update', $project) }}" enctype="multipart/form-data"
                  data-confirm-navigation
                  data-confirm-title="Leave proposal editing?"
                  data-confirm-body="You have unsaved changes. If you leave now, they will be lost."
                  data-confirm-tone="warning"
                  data-confirm-ok="Leave page"
                  data-confirm-cancel="Keep editing">
                @csrf

                <div class="upload-field">
                    <label for="title">Title</label>
                    <input id="title" name="title" class="text-input" maxlength="120" value="{{ old('title', $project->title) }}" placeholder="Enter proposal title">
                    <div class="field-helper">Maximum 120 characters.</div>
                    @error('title')<div class="error-text">{{ $message }}</div>@enderror
                </div>

                <div class="upload-field">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="textarea-field" maxlength="300" rows="4" placeholder="Write a short description for your project">{{ old('description', $project->description) }}</textarea>
                    <div class="field-helper">Maximum 300 characters.</div>
                    @error('description')<div class="error-text">{{ $message }}</div>@enderror
                </div>

                <div class="upload-field">
                    <label for="attachments">Add or remove files</label>
                    <input id="attachments" class="hidden-input" type="file" name="attachments[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.webp,.zip">
                    <div id="dropzone" class="dropzone" tabindex="0">
                        <div class="dropzone-inner">
                            <div class="dropzone-icon">⬆</div>
                            <div>
                                <strong>Drag and drop files here</strong>
                                <p class="dropzone-text">or click to browse. Upload up to 5 files total.</p>
                            </div>
                        </div>
                    </div>
                    <div class="attachment-meta">
                        <span id="attachment-count">No new files selected.</span>
                        <span>Max 5 files total, including existing attachments.</span>
                    </div>
                    <div id="attachment-error" class="error-text" style="display:none"></div>
                    <div id="attachment-list" class="attachment-list" style="display:none"></div>
                    <div class="file-note">You can add new files and remove existing attachments before resubmitting.</div>
                    @error('attachments')<div class="error-text">{{ $message }}</div>@enderror
                    @error('attachments.*')<div class="error-text">{{ $message }}</div>@enderror
                </div>

                @if($project->attachments->isNotEmpty())
                    <div class="upload-field">
                        <label>Existing attachments</label>
                        <div class="attachment-list" style="display:grid">
                            @foreach($project->attachments as $attachment)
                                <label class="attachment-item" style="padding:16px 18px;">
                                    <div class="attachment-icon">📎</div>
                                    <div class="attachment-info">
                                        <strong>{{ $attachment->file_name }}</strong>
                                        <span>{{ strtoupper(pathinfo($attachment->file_name, PATHINFO_EXTENSION)) }} · {{ number_format($attachment->file_size / 1024 / 1024, 2) }} MB</span>
                                    </div>
                                    <input type="checkbox" name="remove_attachments[]" value="{{ $attachment->id }}" style="margin-left:auto"> Remove
                                </label>
                            @endforeach
                        </div>
                        @error('remove_attachments')<div class="error-text">{{ $message }}</div>@enderror
                    </div>
                @endif

                <div class="upload-actions">
                    <a href="{{ route('student_org.notifications') }}" class="button-secondary">Back to Notifications</a>
                    <button type="submit" class="button-primary">Resubmit Proposal</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const maxFiles = 5;
            const fileInput = document.getElementById('attachments');
            const dropzone = document.getElementById('dropzone');
            const attachmentCount = document.getElementById('attachment-count');
            const attachmentList = document.getElementById('attachment-list');
            const attachmentError = document.getElementById('attachment-error');
            const selectedFiles = [];

            const iconMap = {
                pdf: '📄',
                doc: '📝',
                docx: '📝',
                xls: '📊',
                xlsx: '📊',
                png: '🖼️',
                jpg: '🖼️',
                jpeg: '🖼️',
                webp: '🖼️',
                zip: '🗜️',
            };

            const getFileIcon = (name) => {
                const ext = name.split('.').pop().toLowerCase();
                return iconMap[ext] || '📎';
            };

            const updateInputFiles = (files) => {
                const dt = new DataTransfer();
                files.forEach((file) => dt.items.add(file));
                fileInput.files = dt.files;
            };

            const clearError = () => {
                attachmentError.style.display = 'none';
                attachmentError.textContent = '';
            };

            const showError = (message) => {
                attachmentError.style.display = 'block';
                attachmentError.textContent = message;
            };

            const renderFileList = (files) => {
                if (!files.length) {
                    attachmentList.style.display = 'none';
                    attachmentCount.textContent = 'No new files selected.';
                    return;
                }

                attachmentList.innerHTML = '';
                attachmentList.style.display = 'grid';
                attachmentCount.textContent = `${files.length} new file${files.length === 1 ? '' : 's'} selected.`;
                clearError();

                files.forEach((file, index) => {
                    const item = document.createElement('div');
                    item.className = 'attachment-item';

                    const icon = document.createElement('div');
                    icon.className = 'attachment-icon';
                    icon.textContent = getFileIcon(file.name);

                    const info = document.createElement('div');
                    info.className = 'attachment-info';
                    const nameEl = document.createElement('strong');
                    nameEl.textContent = file.name;
                    const metaEl = document.createElement('span');
                    metaEl.textContent = `${(file.size / 1024 / 1024).toFixed(2)} MB`;
                    info.appendChild(nameEl);
                    info.appendChild(metaEl);

                    const removeButton = document.createElement('button');
                    removeButton.type = 'button';
                    removeButton.className = 'attachment-remove';
                    removeButton.textContent = 'Remove';
                    removeButton.addEventListener('click', () => {
                        selectedFiles.splice(index, 1);
                        updateInputFiles(selectedFiles);
                        renderFileList(selectedFiles);
                    });

                    item.appendChild(icon);
                    item.appendChild(info);
                    item.appendChild(removeButton);
                    attachmentList.appendChild(item);
                });
            };

            const handleFiles = (incomingFiles) => {
                clearError();
                const fileArray = Array.from(incomingFiles);
                const duplicateNames = [];
                let overflow = false;

                fileArray.forEach((file) => {
                    const duplicate = selectedFiles.some((existing) =>
                        existing.name === file.name && existing.size === file.size && existing.lastModified === file.lastModified
                    );
                    if (duplicate) {
                        duplicateNames.push(file.name);
                        return;
                    }
                    if (selectedFiles.length < maxFiles) {
                        selectedFiles.push(file);
                    } else {
                        overflow = true;
                    }
                });

                if (overflow) {
                    showError('Only 5 new files are allowed. Remove one before adding another.');
                }
                if (duplicateNames.length) {
                    showError(`Duplicate files skipped: ${duplicateNames.join(', ')}`);
                }

                updateInputFiles(selectedFiles);
                renderFileList(selectedFiles);
            };

            const onDrop = (event) => {
                event.preventDefault();
                event.stopPropagation();
                dropzone.classList.remove('active');
                const files = event.dataTransfer.files;
                if (files.length) {
                    handleFiles(files);
                }
            };

            dropzone.addEventListener('click', () => fileInput.click());
            dropzone.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    fileInput.click();
                }
            });
            dropzone.addEventListener('dragenter', (event) => {
                event.preventDefault();
                dropzone.classList.add('active');
            });
            dropzone.addEventListener('dragover', (event) => {
                event.preventDefault();
                dropzone.classList.add('active');
            });
            dropzone.addEventListener('dragleave', () => {
                dropzone.classList.remove('active');
            });
            dropzone.addEventListener('drop', onDrop);

            fileInput.addEventListener('change', () => {
                if (fileInput.files.length) {
                    handleFiles(fileInput.files);
                }
            });

            const form = fileInput.closest('form');
            const submitButton = form.querySelector('button[type="submit"]');
            form.addEventListener('submit', () => {
                submitButton.disabled = true;
                submitButton.textContent = 'Submitting...';
            });
        });
    </script>
@endsection
