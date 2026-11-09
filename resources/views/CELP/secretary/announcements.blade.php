@extends('layouts.admin')

@section('title','Announcements')
@section('subtitle','Post general notices to all student organizations')

@section('content')
    <style>
        .ann-dropzone {
            position:relative;
            min-height:140px;
            border:2px dashed #cbd5e1;
            border-radius:18px;
            background:#f8fbff;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:24px;
            text-align:center;
            transition:all 180ms ease;
            cursor:pointer;
        }
        .ann-dropzone.active, .ann-dropzone:hover { border-color:#3b82f6; background:#eff6ff; }
        .ann-dropzone-inner { display:flex; flex-direction:column; align-items:center; gap:12px; max-width:480px; }
        .ann-dropzone-icon {
            width:48px; height:48px; border-radius:14px; background:#dbeafe; color:#1d4ed8;
            display:flex; align-items:center; justify-content:center; font-size:1.3rem;
        }
        .ann-dropzone strong { display:block; font-size:0.98rem; color:#0f172a; margin-bottom:4px; }
        .ann-dropzone-text { margin:0; color:#475569; font-size:0.92rem; line-height:1.6; }
        .ann-attachment-meta { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; color:#475569; font-size:0.92rem; margin-top:10px; }
        .ann-attachment-list { margin-top:16px; display:grid; gap:12px; }
        .ann-attachment-item {
            display:grid; grid-template-columns:auto 1fr auto; gap:14px; align-items:center;
            padding:14px 16px; border-radius:16px; background:#ffffff; border:1px solid #e5e7eb;
        }
        .ann-attachment-icon { width:42px; height:42px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.15rem; color:#1e3a8a; flex-shrink:0; background:#eff6ff; }
        .ann-attachment-info strong { display:block; font-size:0.95rem; color:#0f172a; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .ann-attachment-info span { display:block; color:#64748b; margin-top:2px; font-size:0.86rem; }
        .ann-attachment-remove { border:1px solid #cbd5e1; background:#ffffff; color:#0f172a; border-radius:12px; padding:8px 14px; cursor:pointer; font-weight:600; font-size:0.9rem; }
        .ann-attachment-remove:hover { background:#f8fafc; }
    </style>

    @if(session('status'))
        <div class="card" style="border-left:4px solid #16a34a; background:#ecfdf5; color:#134e4a; padding:18px 22px; margin-bottom:20px">
            <div style="font-weight:700; margin-bottom:6px">Success</div>
            <div>{{ session('status') }}</div>
        </div>
    @endif

    <div class="card" style="padding:24px;">
        <h3 style="margin-top:0; margin-bottom:6px">Create announcement</h3>
        <p style="margin:0 0 18px; color:#64748b">Published notices appear on every student organization's Announcements page.</p>
        <form action="{{ route('celp.secretary.announcements.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div style="display:grid; gap:18px;">
                <div>
                    <label style="display:block; font-weight:700; margin-bottom:8px">Category</label>
                    <select name="category" required style="width:100%; padding:14px; border:1px solid #d1d5db; border-radius:12px;">
                        <option value="General">General</option>
                        <option value="Deadline reminder">Deadline reminder</option>
                        <option value="Policy update">Policy update</option>
                        <option value="Template update">Template update</option>
                    </select>
                    @error('category')<div style="color:#b91c1c; font-size:0.92rem; margin-top:6px">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label style="display:block; font-weight:700; margin-bottom:8px">Title</label>
                    <input type="text" name="title" required maxlength="255" value="{{ old('title') }}" style="width:100%; padding:14px; border:1px solid #d1d5db; border-radius:12px;" />
                    @error('title')<div style="color:#b91c1c; font-size:0.92rem; margin-top:6px">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label style="display:block; font-weight:700; margin-bottom:8px">Description</label>
                    <textarea name="description" rows="5" maxlength="1000" style="width:100%; padding:14px; border:1px solid #d1d5db; border-radius:12px;">{{ old('description') }}</textarea>
                    @error('description')<div style="color:#b91c1c; font-size:0.92rem; margin-top:6px">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label style="display:block; font-weight:700; margin-bottom:8px">Attachments</label>
                    <input id="ann-file-input" class="hidden-input" type="file" name="attachments[]" multiple accept="image/jpeg,image/png,application/pdf" style="display:none">
                    <div id="ann-dropzone" class="ann-dropzone" tabindex="0">
                        <div class="ann-dropzone-inner">
                            <div class="ann-dropzone-icon">⬆</div>
                            <div>
                                <strong>Drag and drop files here</strong>
                                <p class="ann-dropzone-text">or click to browse. You can add files across multiple clicks — up to 5 total.</p>
                            </div>
                        </div>
                    </div>
                    <div class="ann-attachment-meta">
                        <span id="ann-attachment-count">No files selected.</span>
                        <span>JPG, PNG, PDF only. Max 5 files, 5MB each.</span>
                    </div>
                    <div id="ann-attachment-error" style="display:none; color:#b91c1c; font-size:0.92rem; margin-top:6px"></div>
                    <div id="ann-attachment-list" class="ann-attachment-list" style="display:none"></div>
                    @error('attachments')<div style="color:#b91c1c; font-size:0.92rem; margin-top:6px">{{ $message }}</div>@enderror
                    @error('attachments.*')<div style="color:#b91c1c; font-size:0.92rem; margin-top:6px">{{ $message }}</div>@enderror
                </div>

                <div style="display:flex; gap:12px; align-items:center; justify-content:flex-end;">
                    <button type="submit" name="status" value="draft" style="padding:14px 22px; border-radius:12px; background:#f8fafc; color:#0b2a2a; border:1px solid #e2e8f0; font-weight:700; cursor:pointer;">Save draft</button>
                    <button type="submit" name="status" value="published" style="padding:14px 22px; border-radius:12px; background:#16a34a; color:#ffffff; border:none; font-weight:700; cursor:pointer;">Publish</button>
                </div>
            </div>
        </form>
    </div>

    <div class="card" style="margin-top:24px;">
        <h3 style="margin-top:0; margin-bottom:18px">Your announcements</h3>
        @if($announcements->isEmpty())
            <div style="color:#64748b">No announcements created yet.</div>
        @else
            <div style="display:grid; gap:14px;">
                @foreach($announcements as $announcement)
                    <div style="padding:18px; border-radius:16px; border:1px solid #e5e7eb; background:#ffffff; display:flex; justify-content:space-between; gap:16px; align-items:center; flex-wrap:wrap;">
                        <a href="{{ route('celp.secretary.announcements.show', $announcement) }}" style="text-decoration:none; color:inherit; flex:1; min-width:220px;">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div style="font-size:1rem; font-weight:700; color:#0f172a;">{{ $announcement->title }}</div>
                                <span style="font-size:0.78rem; font-weight:700; padding:4px 10px; border-radius:999px; background:{{ $announcement->status === 'published' ? '#dcfce7' : '#f1f5f9' }}; color:{{ $announcement->status === 'published' ? '#166534' : '#475569' }}">{{ ucfirst($announcement->status) }}</span>
                            </div>
                            <div style="font-size:0.9rem; color:#64748b; margin-top:6px;">{{ $announcement->category }} · {{ $announcement->created_at->diffForHumans() }}</div>
                        </a>
                        <div style="display:flex; gap:10px; align-items:center;">
                            <span style="font-size:0.85rem; color:#475569;">{{ $announcement->attachments->count() }} files</span>
                            <a href="{{ route('celp.secretary.announcements.show', $announcement) }}" style="color:#2563eb; text-decoration:none; font-weight:700;">View</a>
                            <form action="{{ route('celp.secretary.announcements.destroy', $announcement) }}" method="POST" data-confirm
                                  data-confirm-title="Remove announcement?"
                                  data-confirm-body="{name} will be deleted and will no longer be visible to anyone."
                                  data-confirm-name="{{ $announcement->title }}"
                                  data-confirm-note="This cannot be undone."
                                  data-confirm-ok="Remove announcement"
                                  data-confirm-cancel="Keep it">
                                @csrf
                                @method('DELETE')
                                <button type="submit" style="color:#b91c1c; background:none; border:none; padding:0; font-weight:700; cursor:pointer; font-size:0.95rem; text-decoration:underline;">Remove</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const maxFiles = 5;
            const fileInput = document.getElementById('ann-file-input');
            const dropzone = document.getElementById('ann-dropzone');
            const attachmentCount = document.getElementById('ann-attachment-count');
            const attachmentList = document.getElementById('ann-attachment-list');
            const attachmentError = document.getElementById('ann-attachment-error');
            const selectedFiles = [];

            const iconMap = { pdf: '📄', png: '🖼️', jpg: '🖼️', jpeg: '🖼️' };
            const getFileIcon = (name) => iconMap[name.split('.').pop().toLowerCase()] || '📎';

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
                    attachmentCount.textContent = 'No files selected.';
                    return;
                }

                attachmentList.innerHTML = '';
                attachmentList.style.display = 'grid';
                attachmentCount.textContent = `${files.length} file${files.length === 1 ? '' : 's'} selected.`;
                clearError();

                files.forEach((file, index) => {
                    const item = document.createElement('div');
                    item.className = 'ann-attachment-item';

                    const icon = document.createElement('div');
                    icon.className = 'ann-attachment-icon';
                    icon.textContent = getFileIcon(file.name);

                    const info = document.createElement('div');
                    info.className = 'ann-attachment-info';
                    const nameEl = document.createElement('strong');
                    nameEl.textContent = file.name;
                    const metaEl = document.createElement('span');
                    metaEl.textContent = `${(file.size / 1024 / 1024).toFixed(2)} MB`;
                    info.appendChild(nameEl);
                    info.appendChild(metaEl);

                    const removeButton = document.createElement('button');
                    removeButton.type = 'button';
                    removeButton.className = 'ann-attachment-remove';
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
                    showError('Only 5 files are allowed. Remove one before adding another.');
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
                if (files.length) handleFiles(files);
            };

            dropzone.addEventListener('click', () => fileInput.click());
            dropzone.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    fileInput.click();
                }
            });
            dropzone.addEventListener('dragenter', (event) => { event.preventDefault(); dropzone.classList.add('active'); });
            dropzone.addEventListener('dragover', (event) => { event.preventDefault(); dropzone.classList.add('active'); });
            dropzone.addEventListener('dragleave', () => dropzone.classList.remove('active'));
            dropzone.addEventListener('drop', onDrop);

            fileInput.addEventListener('change', () => {
                if (fileInput.files.length) handleFiles(fileInput.files);
            });
        });
    </script>
@endsection
