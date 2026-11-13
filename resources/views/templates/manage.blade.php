@extends('layouts.admin')

@section('title', 'Manage Templates')
@section('subtitle', 'Upload and manage proposal templates')

@section('content')
    @php
        $templateCount = $templates->count();
        $pdfCount = $templates->filter(fn ($template) => strtolower(pathinfo($template->original_name, PATHINFO_EXTENSION)) === 'pdf')->count();
        $wordCount = $templateCount - $pdfCount;
    @endphp

    <style>
        .tpl-manage { display:grid; gap:18px; }
        .tpl-summary { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; }
        .tpl-summary-card { display:flex; align-items:center; gap:13px; min-height:82px; padding:15px 17px; border:1px solid #e5eaf0; border-radius:13px; background:#fff; }
        .tpl-summary-icon { display:flex; align-items:center; justify-content:center; width:40px; height:40px; flex:0 0 40px; border-radius:11px; background:#edf7f2; color:#08734e; }
        .tpl-summary-card:nth-child(2) .tpl-summary-icon { background:#fff6e8; color:#d97706; }
        .tpl-summary-card:nth-child(3) .tpl-summary-icon { background:#eff5ff; color:#2563eb; }
        .tpl-summary-value { color:#102e2a; font-size:1.3rem; font-weight:800; line-height:1.1; }
        .tpl-summary-label { margin-top:4px; color:#64748b; font-size:0.8rem; }
        .tpl-upload-card { padding:20px; border:1px solid #e1e8e5; border-radius:15px; background:#fff; }
        .tpl-section-heading { display:flex; align-items:flex-start; gap:12px; margin-bottom:14px; }
        .tpl-section-heading-icon { display:flex; align-items:center; justify-content:center; width:36px; height:36px; flex:0 0 36px; border-radius:11px; background:#eaf7f0; color:#08734e; }
        .tpl-section-heading h3 { margin:0; color:#102e2a; font-size:1rem; }
        .tpl-section-heading p { margin:4px 0 0; color:#64748b; font-size:0.84rem; }
        .tpl-dropzone { position:relative; min-height:132px; border:1px dashed #93cdb2; border-radius:12px; background:#f8fcf9; display:flex; align-items:center; justify-content:center; padding:20px; text-align:center; transition:border-color 160ms ease,background 160ms ease; cursor:pointer; }
        .tpl-dropzone.active, .tpl-dropzone:hover { border-color:#0b7a58; background:#effaf4; }
        .tpl-dropzone:focus-visible { outline:3px solid rgba(11,118,86,0.2); outline-offset:2px; }
        .tpl-dropzone-inner { display:flex; flex-direction:column; align-items:center; gap:8px; max-width:480px; }
        .tpl-dropzone-icon { color:#0b8961; }
        .tpl-dropzone strong { display:block; margin-bottom:3px; color:#0f172a; font-size:0.98rem; }
        .tpl-dropzone-text { margin:0; color:#64748b; font-size:0.88rem; line-height:1.5; }
        .tpl-file-types { display:flex; gap:6px; margin-top:3px; }
        .tpl-file-type { padding:3px 8px; border-radius:999px; background:#eef2f4; color:#475569; font-size:0.7rem; font-weight:800; }
        .tpl-selected-file { display:none; align-items:center; gap:14px; margin-top:10px; padding:12px 14px; border:1px solid #e5e7eb; border-radius:11px; background:#f8fafc; }
        .tpl-selected-icon { display:flex; align-items:center; justify-content:center; width:42px; height:42px; flex:0 0 42px; border-radius:11px; background:#eff5ff; color:#2563eb; }
        .tpl-selected-info { flex:1; min-width:0; }
        .tpl-selected-info strong { display:block; overflow:hidden; color:#0f172a; font-size:0.92rem; text-overflow:ellipsis; white-space:nowrap; }
        .tpl-selected-info span { display:block; margin-top:2px; color:#64748b; font-size:0.82rem; }
        .tpl-selected-remove { padding:8px 12px; border:1px solid #cbd5e1; border-radius:9px; background:#fff; color:#0f172a; font-size:0.84rem; font-weight:600; cursor:pointer; }
        .tpl-selected-remove:hover { background:#f8fafc; }
        .tpl-upload-btn { width:100%; margin-top:9px; padding:12px 20px; border:1px solid #0b7656; border-radius:9px; background:#0b7656; color:#fff; font-weight:700; cursor:pointer; opacity:0.52; pointer-events:none; transition:opacity 150ms ease,background 150ms ease; }
        .tpl-upload-btn.enabled { opacity:1; pointer-events:auto; }
        .tpl-upload-btn.enabled:hover { background:#086247; }
        .tpl-existing { display:grid; gap:12px; }
        .tpl-existing-head { display:flex; align-items:center; justify-content:space-between; gap:12px; }
        .tpl-existing-head h3 { margin:0; color:#102e2a; font-size:1rem; }
        .tpl-existing-count { padding:5px 10px; border-radius:999px; background:#edf7f2; color:#08734e; font-size:0.77rem; font-weight:800; }
        .tpl-toolbar { display:flex; align-items:center; gap:9px; }
        .tpl-search-wrap { display:flex; align-items:center; gap:9px; flex:1; min-width:0; height:42px; padding:0 12px; border:1px solid #d9e1e6; border-radius:9px; background:#fff; color:#94a3b8; }
        .tpl-search-wrap:focus-within { border-color:#62b391; box-shadow:0 0 0 3px rgba(11,118,86,0.09); }
        .tpl-search { width:100%; min-width:0; border:0; outline:0; background:transparent; color:#0f172a; font:inherit; font-size:0.87rem; }
        .tpl-filter-select { height:42px; padding:0 30px 0 12px; border:1px solid #d9e1e6; border-radius:9px; background:#fff; color:#334155; font:inherit; font-size:0.84rem; cursor:pointer; }
        .tpl-row { display:flex; align-items:center; justify-content:space-between; gap:14px; padding:14px 16px; border:1px solid #e1e7eb; border-radius:11px; background:#fff; flex-wrap:wrap; }
        .tpl-row[hidden], .tpl-empty[hidden] { display:none; }
        .tpl-row-main { display:flex; align-items:center; gap:12px; flex:1; min-width:240px; }
        .tpl-row-icon { display:flex; align-items:center; justify-content:center; width:40px; height:40px; flex:0 0 40px; border-radius:10px; background:#eff5ff; color:#2563eb; }
        .tpl-row-icon.is-word { background:#edf7f2; color:#08734e; }
        .tpl-row-title { color:#172f2a; font-size:0.9rem; font-weight:700; line-height:1.4; overflow-wrap:anywhere; }
        .tpl-row-meta { margin-top:3px; color:#71817c; font-size:0.78rem; line-height:1.4; }
        .tpl-row-tags { display:flex; gap:6px; margin-top:6px; }
        .tpl-file-badge, .tpl-source-badge { display:inline-flex; align-items:center; padding:3px 8px; border-radius:999px; font-size:0.7rem; font-weight:800; letter-spacing:0.02em; }
        .tpl-file-badge { background:#f1f5f9; color:#475569; }
        .tpl-source-celp { background:#eaf3ff; color:#2456a6; }
        .tpl-source-csa { background:#e7f8ef; color:#08734e; }
        .tpl-source-unknown { background:#f1f5f9; color:#64748b; }
        .tpl-actions { display:flex; align-items:center; gap:8px; }
        .tpl-download-btn, .tpl-replace-btn, .tpl-remove-btn { display:inline-flex; align-items:center; justify-content:center; padding:8px 11px; border-radius:8px; font:inherit; font-size:0.8rem; font-weight:700; text-decoration:none; cursor:pointer; }
        .tpl-download-btn { border:1px solid #cce8d8; background:#eef8f2; color:#08734e; }
        .tpl-download-btn:hover { background:#e1f2e8; }
        .tpl-replace-btn { border:1px solid #dce4e8; background:#fff; color:#334155; }
        .tpl-replace-btn:hover { border-color:#cbd5e1; background:#f8fafc; }
        .tpl-remove-btn { border:1px solid #fecaca; background:#fff; color:#b91c1c; }
        .tpl-remove-btn:hover { background:#fef2f2; }
        .tpl-empty { padding:34px 20px; border:1px dashed #d5e4dc; border-radius:12px; background:#f9fcfa; color:#64748b; text-align:center; }
        @media(max-width:700px) {
            .tpl-summary { gap:8px; }
            .tpl-summary-card { align-items:flex-start; flex-direction:column; gap:9px; padding:13px; }
            .tpl-summary-icon { width:34px; height:34px; flex-basis:34px; }
            .tpl-toolbar { align-items:stretch; flex-direction:column; }
            .tpl-filter-select { width:100%; }
        }
        @media(max-width:480px) {
            .tpl-summary { grid-template-columns:1fr; }
            .tpl-summary-card { align-items:center; flex-direction:row; }
            .tpl-upload-card { padding:15px; }
            .tpl-row { align-items:flex-start; }
            .tpl-row-main { min-width:0; }
            .tpl-actions { width:100%; }
            .tpl-download-btn, .tpl-replace-btn, .tpl-remove-btn { flex:1; }
        }
    </style>

    @if(session('status'))
        <div class="card" style="border-left:4px solid #16a34a; background:#ecfdf5; color:#134e4a; padding:16px 20px; margin-bottom:20px">
            {{ session('status') }}
        </div>
    @endif

    <div class="tpl-manage">
        <section class="tpl-summary" aria-label="Template summary">
            <div class="tpl-summary-card">
                <span class="tpl-summary-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none"><rect x="5" y="5" width="13" height="15" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M8 3h9a2 2 0 0 1 2 2v13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></span>
                <div><div class="tpl-summary-value">{{ $templateCount }}</div><div class="tpl-summary-label">Total templates</div></div>
            </div>
            <div class="tpl-summary-card">
                <span class="tpl-summary-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M7 3h8l4 4v14H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M14 3v5h5M9 13h6m-6 3h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></span>
                <div><div class="tpl-summary-value">{{ $pdfCount }}</div><div class="tpl-summary-label">PDF files</div></div>
            </div>
            <div class="tpl-summary-card">
                <span class="tpl-summary-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M7 3h8l4 4v14H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M14 3v5h5M9 13h6m-6 3h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></span>
                <div><div class="tpl-summary-value">{{ $wordCount }}</div><div class="tpl-summary-label">Word documents</div></div>
            </div>
        </section>

        <section class="tpl-upload-card" aria-labelledby="tpl-upload-title">
            <div class="tpl-section-heading">
                <span class="tpl-section-heading-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M12 16V4m0 0L7 9m5-5 5 5M5 15v4a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                <div><h3 id="tpl-upload-title">Upload a template</h3><p>PDF, DOC, or DOCX · Maximum 10 MB</p></div>
            </div>
            <form method="post" action="{{ route('templates.store') }}" enctype="multipart/form-data">
                @csrf
                <input id="tpl-file-input" type="file" name="template" accept=".pdf,.doc,.docx" style="display:none">
                <div id="tpl-dropzone" class="tpl-dropzone" tabindex="0" role="button" aria-label="Choose or drop a template file">
                    <div class="tpl-dropzone-inner">
                        <svg class="tpl-dropzone-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 16V4m0 0L7 9m5-5 5 5M5 15v4a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-4" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <div><strong>Drag and drop your file here</strong><p class="tpl-dropzone-text">or click to browse your computer</p></div>
                        <div class="tpl-file-types"><span class="tpl-file-type">PDF</span><span class="tpl-file-type">DOC</span><span class="tpl-file-type">DOCX</span></div>
                    </div>
                </div>
                <div id="tpl-selected-file" class="tpl-selected-file">
                    <div class="tpl-selected-icon" id="tpl-selected-icon" aria-hidden="true">DOC</div>
                    <div class="tpl-selected-info"><strong id="tpl-selected-name"></strong><span id="tpl-selected-size"></span></div>
                    <button type="button" class="tpl-selected-remove" id="tpl-selected-remove">Remove</button>
                </div>
                @error('template')<div role="alert" style="color:#b91c1c; font-size:0.88rem; margin-top:10px">{{ $message }}</div>@enderror
                <button type="submit" id="tpl-upload-btn" class="tpl-upload-btn">Upload template</button>
            </form>
        </section>

        <section class="tpl-existing" aria-labelledby="tpl-existing-title">
            <div class="tpl-existing-head">
                <h3 id="tpl-existing-title">Existing templates</h3>
                <span class="tpl-existing-count">{{ $templateCount }} {{ \Illuminate\Support\Str::plural('template', $templateCount) }}</span>
            </div>
            <div class="tpl-toolbar">
                <label class="tpl-search-wrap" for="tpl-search">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.8"/><path d="m16 16 4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    <input class="tpl-search" id="tpl-search" type="search" placeholder="Search templates..." autocomplete="off">
                </label>
                <select class="tpl-filter-select" id="tpl-source-filter" aria-label="Filter templates by source">
                    <option value="all">All offices</option>
                    <option value="celp">CELP</option>
                    <option value="csa">CSA</option>
                </select>
            </div>

            @if($templates->isEmpty())
                <div class="tpl-empty"><div style="font-size:1.8rem; margin-bottom:9px">📁</div>No templates uploaded yet.</div>
            @else
                <div style="display:grid; gap:10px">
                    @foreach($templates as $t)
                        @php
                            $ext = strtoupper(pathinfo($t->original_name, PATHINFO_EXTENSION));
                            $sourceClass = $t->source ? 'tpl-source-' . $t->source : 'tpl-source-unknown';
                            $sourceValue = $t->source ?: 'unknown';
                            $searchValue = \Illuminate\Support\Str::lower($t->original_name . ' ' . ($t->uploader->name ?? ''));
                        @endphp
                        <div class="tpl-row" data-template-row data-source="{{ $sourceValue }}" data-search="{{ $searchValue }}">
                            <div class="tpl-row-main">
                                <div class="tpl-row-icon {{ $ext === 'PDF' ? '' : 'is-word' }}" aria-hidden="true">
                                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none"><path d="M7 3h8l4 4v14H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M14 3v5h5M9 13h6m-6 3h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                </div>
                                <div style="min-width:0">
                                    <div class="tpl-row-title">{{ $t->original_name }}</div>
                                    <div class="tpl-row-meta">Uploaded by {{ $t->uploader->name ?? '—' }} · {{ $t->created_at->diffForHumans() }}</div>
                                    <div class="tpl-row-tags">
                                        <span class="tpl-source-badge {{ $sourceClass }}">{{ $t->sourceLabel() }}</span>
                                        <span class="tpl-file-badge">{{ $ext }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="tpl-actions">
                                <a href="{{ route('templates.download', $t->id) }}" class="tpl-download-btn">Download</a>
                                @if($t->canBeManagedBy(auth()->user()))
                                    <form method="POST" action="{{ route('templates.replace', $t->id) }}" enctype="multipart/form-data" style="margin:0" id="tpl-replace-{{ $t->id }}">
                                        @csrf
                                        <input type="file" name="template" accept=".pdf,.doc,.docx" style="display:none" onchange="this.form.submit()">
                                        <button type="button" class="tpl-replace-btn" onclick="document.querySelector('#tpl-replace-{{ $t->id }} input[type=file]').click()">Replace</button>
                                    </form>
                                    <form method="POST" action="{{ route('templates.destroy', $t->id) }}" style="margin:0" data-confirm
                                          data-confirm-title="Remove template?"
                                          data-confirm-body="{name} will be deleted and organizations will no longer be able to download it."
                                          data-confirm-name="{{ $t->original_name }}"
                                          data-confirm-note="This cannot be undone."
                                          data-confirm-ok="Remove template"
                                          data-confirm-cancel="Keep it">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="tpl-remove-btn">Remove</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="tpl-empty" id="tpl-no-results" hidden>No templates match your search or filter. Try changing your search.</div>
            @endif
        </section>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const fileInput = document.getElementById('tpl-file-input');
            const dropzone = document.getElementById('tpl-dropzone');
            const selectedFile = document.getElementById('tpl-selected-file');
            const selectedName = document.getElementById('tpl-selected-name');
            const selectedSize = document.getElementById('tpl-selected-size');
            const selectedIcon = document.getElementById('tpl-selected-icon');
            const removeBtn = document.getElementById('tpl-selected-remove');
            const uploadBtn = document.getElementById('tpl-upload-btn');
            const searchInput = document.getElementById('tpl-search');
            const sourceFilter = document.getElementById('tpl-source-filter');
            const templateRows = Array.from(document.querySelectorAll('[data-template-row]'));
            const noResults = document.getElementById('tpl-no-results');

            const filterRows = () => {
                const term = searchInput.value.trim().toLowerCase();
                const source = sourceFilter.value;
                let visibleCount = 0;

                templateRows.forEach((row) => {
                    const matchesSearch = row.dataset.search.includes(term);
                    const matchesSource = source === 'all' || row.dataset.source === source;
                    row.hidden = !matchesSearch || !matchesSource;
                    if (!row.hidden) visibleCount++;
                });

                if (noResults) noResults.hidden = visibleCount > 0;
            };

            searchInput.addEventListener('input', filterRows);
            sourceFilter.addEventListener('change', filterRows);

            const iconFor = (name) => name.split('.').pop().toLowerCase() === 'pdf' ? 'PDF' : 'DOC';

            const showFile = (file) => {
                selectedIcon.textContent = iconFor(file.name);
                selectedName.textContent = file.name;
                selectedSize.textContent = `${(file.size / 1024 / 1024).toFixed(2)} MB`;
                selectedFile.style.display = 'flex';
                dropzone.style.display = 'none';
                uploadBtn.classList.add('enabled');
            };

            const clearFile = () => {
                const dt = new DataTransfer();
                fileInput.files = dt.files;
                selectedFile.style.display = 'none';
                dropzone.style.display = 'flex';
                uploadBtn.classList.remove('enabled');
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
            dropzone.addEventListener('drop', (event) => {
                event.preventDefault();
                dropzone.classList.remove('active');
                if (event.dataTransfer.files.length) {
                    const dt = new DataTransfer();
                    dt.items.add(event.dataTransfer.files[0]);
                    fileInput.files = dt.files;
                    showFile(fileInput.files[0]);
                }
            });

            fileInput.addEventListener('change', () => {
                if (fileInput.files.length) showFile(fileInput.files[0]);
            });

            removeBtn.addEventListener('click', clearFile);

            fileInput.closest('form').addEventListener('submit', () => {
                if (uploadBtn.classList.contains('enabled')) {
                    uploadBtn.textContent = 'Uploading...';
                    uploadBtn.style.pointerEvents = 'none';
                }
            });
        });
    </script>
@endsection
