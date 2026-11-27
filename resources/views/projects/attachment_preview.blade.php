@extends('layouts.admin')

@section('title', 'Attachment Preview')
@section('subtitle', $attachment->file_name)

@section('content')
    @php
        $isPreviewable = $previewKind !== 'unsupported';
        $uploaderName = $project->creator->name ?? 'ProjectLink';
        $organizationName = $project->creator->organization_name ?? $project->creator->name ?? 'Project proposal';
    @endphp

    @vite('resources/js/document-viewer.js')

    <style>
        [hidden] { display:none !important; }
        .project-viewer { min-width:0; }
        .project-viewer-toolbar { position:sticky; top:0; z-index:10; display:flex; align-items:center; gap:14px;
            min-height:66px; padding:10px 14px; border:1px solid #dce7e2; border-radius:15px;
            background:rgba(255,255,255,.96); box-shadow:0 8px 24px rgba(15,23,42,.08); backdrop-filter:blur(12px); }
        .project-viewer-back, .project-viewer-download, .project-viewer-control { display:inline-flex; align-items:center; justify-content:center;
            gap:8px; min-height:40px; border-radius:10px; padding:0 13px; font:inherit; font-size:.86rem;
            font-weight:750; text-decoration:none; cursor:pointer; white-space:nowrap; }
        .project-viewer-back { border:1px solid #d7e5df; background:#fff; color:#12634c; }
        .project-viewer-back:hover { background:#f1faf5; border-color:#9bd8bd; }
        .project-viewer-download { border:1px solid #087c5b; background:#087c5b; color:#fff; }
        .project-viewer-download:hover { border-color:#066348; background:#066348; color:#fff; }
        .project-viewer-title { min-width:0; flex:1 1 auto; color:#102e26; font-size:.98rem; font-weight:800;
            overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .project-viewer-tools { display:flex; align-items:center; gap:6px; flex:0 0 auto; }
        .project-viewer-control { min-width:38px; padding:0 9px; border:1px solid #d7e5df; background:#fff; color:#12634c; }
        .project-viewer-control:hover:not(:disabled) { background:#f1faf5; border-color:#9bd8bd; }
        .project-viewer-control:disabled { cursor:default; opacity:.55; }
        .project-viewer-zoom-value { min-width:47px; color:#52665d; font-size:.78rem; font-weight:750; text-align:center; }
        .project-viewer-meta { display:flex; flex-wrap:wrap; gap:7px 18px; align-items:center; margin:14px 2px;
            color:#52665d; font-size:.84rem; }
        .project-viewer-meta strong { color:#243d34; }
        .project-viewer-meta-item { display:inline-flex; align-items:center; gap:5px; }
        .project-viewer-badge { display:inline-flex; align-items:center; padding:4px 9px; border-radius:999px;
            background:#e7f8ef; color:#08734e; font-size:.74rem; font-weight:800; letter-spacing:.03em; }
        .project-viewer-viewport { min-height:calc(100vh - 260px); overflow:auto; padding:22px 16px 38px; border:1px solid #dfe8e4;
            border-radius:17px; background:#eef2f1; box-shadow:inset 0 1px 2px rgba(15,23,42,.03); }
        .project-viewer-stage { min-height:530px; }
        .project-viewer-loading { display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:530px;
            gap:12px; color:#52665d; font-size:.94rem; font-weight:700; text-align:center; }
        .project-viewer-spinner { width:34px; height:34px; border:3px solid #cfe4da; border-top-color:#087c5b; border-radius:50%; animation:project-viewer-spin .8s linear infinite; }
        @keyframes project-viewer-spin { to { transform:rotate(360deg); } }
        .project-viewer-zoom-target { width:min(100%,980px); min-height:530px; margin:0 auto; transform-origin:top center; }
        .project-viewer-paper { width:100%; min-height:530px; overflow:hidden; border-radius:7px; background:#fff; box-shadow:0 12px 32px rgba(15,23,42,.13); }
        .project-viewer-native { display:block; width:100%; min-height:710px; border:0; background:#fff; }
        .project-viewer-image { display:block; max-width:100%; max-height:78vh; margin:0 auto; object-fit:contain; }
        .project-viewer-docx-body { min-height:530px; }
        .project-viewer-docx-body .docx-wrapper { min-height:530px; padding:28px 14px !important; background:#e6ece9 !important; }
        .project-viewer-docx-body .docx { margin:0 auto 18px; box-shadow:0 10px 28px rgba(15,23,42,.15); }
        .project-viewer-error { display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:400px;
            gap:12px; padding:28px; color:#52665d; text-align:center; }
        .project-viewer-error h2 { margin:0; color:#243d34; font-size:1.1rem; }
        .project-viewer-error p { max-width:560px; margin:0; line-height:1.6; }
        .project-viewer-error .project-viewer-download { margin-top:5px; }
        .tpl-viewer-spreadsheet { min-height:530px; padding:18px; background:#fff; }
        .tpl-viewer-sheet-tabs { display:flex; gap:7px; overflow-x:auto; padding-bottom:12px; border-bottom:1px solid #dfe8e4; }
        .tpl-viewer-sheet-tab { min-height:34px; padding:0 12px; border:1px solid #d7e5df; border-radius:9px; background:#f8fbf9; color:#52665d; cursor:pointer; font:inherit; font-size:.78rem; font-weight:750; white-space:nowrap; }
        .tpl-viewer-sheet-tab[aria-selected="true"] { border-color:#087c5b; background:#e7f8ef; color:#08734e; }
        .tpl-viewer-sheet-stage { overflow:auto; margin-top:14px; }
        .tpl-viewer-spreadsheet-table { min-width:100%; border-collapse:separate; border-spacing:0; color:#243d34; font-size:.78rem; }
        .tpl-viewer-spreadsheet-table th, .tpl-viewer-spreadsheet-table td { min-width:110px; max-width:280px; padding:9px 11px; border-right:1px solid #e5ece8; border-bottom:1px solid #e5ece8; text-align:left; vertical-align:top; white-space:pre-wrap; overflow-wrap:anywhere; }
        .tpl-viewer-spreadsheet-table tr:first-child th { position:sticky; top:0; z-index:1; background:#eaf8f0; color:#08734e; font-weight:800; }
        .tpl-viewer-spreadsheet-table tr:first-child th:first-child { z-index:2; }
        .tpl-viewer-spreadsheet-row-number { min-width:45px !important; width:45px; background:#f5faf7; color:#7b8d84; font-variant-numeric:tabular-nums; text-align:right !important; }
        .tpl-viewer-sheet-note, .tpl-viewer-sheet-empty { margin:12px 0 0; color:#64756e; font-size:.76rem; line-height:1.5; }
        @media (max-width:700px) {
            .project-viewer-toolbar { align-items:stretch; flex-wrap:wrap; padding:10px; }
            .project-viewer-back { order:1; }
            .project-viewer-title { order:3; flex-basis:100%; }
            .project-viewer-tools { order:2; margin-left:auto; }
            .project-viewer-viewport { padding:12px 8px 28px; }
            .project-viewer-meta { gap:6px 12px; font-size:.78rem; }
            .project-viewer-native { min-height:610px; }
            .project-viewer-docx-body .docx-wrapper { padding:14px 5px !important; }
            .tpl-viewer-spreadsheet { padding:10px; }
        }
    </style>

    <div class="project-viewer"
         data-document-viewer
         data-preview-kind="{{ $previewKind }}"
         data-content-url="{{ $contentUrl }}">
        <div class="project-viewer-toolbar">
            <a href="{{ route('projects.show_review', $project) }}" class="project-viewer-back">
                <x-ui-icon name="arrow-left" :size="17" />
                <span>Back to Review</span>
            </a>

            <div class="project-viewer-title" title="{{ $attachment->file_name }}">{{ $attachment->file_name }}</div>

            @if($isPreviewable)
                <div class="project-viewer-tools" aria-label="Document zoom controls">
                    <button type="button" class="project-viewer-control" data-preview-zoom-out aria-label="Zoom out">−</button>
                    <span class="project-viewer-zoom-value" data-preview-zoom-value>100%</span>
                    <button type="button" class="project-viewer-control" data-preview-zoom-in aria-label="Zoom in">+</button>
                    <button type="button" class="project-viewer-control" data-preview-zoom-reset aria-label="Reset zoom">Reset</button>
                </div>
            @endif

            <a href="{{ $downloadUrl }}" class="project-viewer-download">
                <x-ui-icon name="download" :size="17" />
                <span>Download</span>
            </a>
        </div>

        <div class="project-viewer-meta" aria-label="Attachment information">
            <span class="project-viewer-badge">{{ $extension }}</span>
            <span class="project-viewer-meta-item"><strong>Uploaded by:</strong> {{ $uploaderName }}</span>
            <span class="project-viewer-meta-item"><strong>Organization:</strong> {{ $organizationName }}</span>
            <span class="project-viewer-meta-item"><strong>Project:</strong> {{ $project->title }}</span>
        </div>

        <main class="project-viewer-viewport">
            <div class="project-viewer-loading" data-preview-loading role="status" aria-live="polite">
                <span class="project-viewer-spinner" aria-hidden="true"></span>
                <span>Loading document preview...</span>
            </div>

            <div class="project-viewer-stage" data-preview-stage hidden>
                <div class="project-viewer-zoom-target" data-preview-zoom-target>
                    <div class="project-viewer-paper">
                        @if($previewKind === 'docx')
                            <div data-docx-styles aria-hidden="true"></div>
                            <div class="project-viewer-docx-body" data-docx-body></div>
                        @elseif($previewKind === 'pdf')
                            <iframe class="project-viewer-native" data-native-preview title="{{ $attachment->file_name }}" hidden></iframe>
                        @elseif($previewKind === 'image')
                            <img class="project-viewer-image" data-native-preview alt="{{ $attachment->file_name }}" hidden>
                        @elseif($previewKind === 'spreadsheet')
                            <div class="tpl-viewer-spreadsheet" data-spreadsheet-body></div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="project-viewer-error" data-preview-error role="alert" hidden>
                <h2>Preview unavailable</h2>
                <p data-preview-error-message>We could not render this file in the browser.</p>
                <a href="{{ $downloadUrl }}" class="project-viewer-download">
                    <x-ui-icon name="download" :size="17" />
                    <span>Download file</span>
                </a>
            </div>
        </main>
    </div>
@endsection
