@extends('layouts.admin')

@section('title', 'Document Preview')
@section('subtitle', $template->original_name)

@section('content')
    @php
        $isPreviewable = $previewKind !== 'unsupported';
        $uploaderName = $template->uploader->name ?? 'ProjectLink';
        $officeName = $template->sourceLabel();
    @endphp

    @vite('resources/js/document-viewer.js')

    <style>
        [hidden] { display: none !important; }
        .tpl-viewer { min-width: 0; }
        .tpl-viewer-toolbar { position: sticky; top: 0; z-index: 10; display: flex; align-items: center; gap: 14px;
            min-height: 66px; padding: 10px 14px; border: 1px solid #dce7e2; border-radius: 15px;
            background: rgba(255,255,255,.96); box-shadow: 0 8px 24px rgba(15,23,42,.08); backdrop-filter: blur(12px); }
        .tpl-viewer-back, .tpl-viewer-download, .tpl-viewer-control { display: inline-flex; align-items: center; justify-content: center;
            gap: 8px; min-height: 40px; border-radius: 10px; padding: 0 13px; font: inherit; font-size: .86rem;
            font-weight: 750; text-decoration: none; cursor: pointer; white-space: nowrap; }
        .tpl-viewer-back { border: 1px solid #d7e5df; background: #fff; color: #12634c; }
        .tpl-viewer-back:hover { background: #f1faf5; border-color: #9bd8bd; }
        .tpl-viewer-download { border: 1px solid #087c5b; background: #087c5b; color: #fff; }
        .tpl-viewer-download:hover { border-color: #066348; background: #066348; color: #fff; }
        .tpl-viewer-title { min-width: 0; flex: 1 1 auto; color: #102e26; font-size: .98rem; font-weight: 800;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .tpl-viewer-tools { display: flex; align-items: center; gap: 6px; flex: 0 0 auto; }
        .tpl-viewer-control { min-width: 38px; padding: 0 9px; border: 1px solid #d7e5df; background: #fff; color: #12634c; }
        .tpl-viewer-control:hover:not(:disabled) { background: #f1faf5; border-color: #9bd8bd; }
        .tpl-viewer-control:disabled { cursor: default; opacity: .55; }
        .tpl-viewer-zoom-value { min-width: 47px; color: #52665d; font-size: .78rem; font-weight: 750; text-align: center; }
        .tpl-viewer-meta { display: flex; flex-wrap: wrap; gap: 7px 18px; align-items: center; margin: 14px 2px;
            color: #52665d; font-size: .84rem; }
        .tpl-viewer-meta strong { color: #243d34; }
        .tpl-viewer-meta-item { display: inline-flex; align-items: center; gap: 5px; }
        .tpl-viewer-badge { display: inline-flex; align-items: center; padding: 4px 9px; border-radius: 999px;
            background: #e7f8ef; color: #08734e; font-size: .74rem; font-weight: 800; letter-spacing: .03em; }
        .tpl-viewer-viewport { min-height: calc(100vh - 260px); overflow: auto; padding: 22px 16px 38px; border: 1px solid #dfe8e4;
            border-radius: 17px; background: #eef2f1; box-shadow: inset 0 1px 2px rgba(15,23,42,.03); }
        .tpl-viewer-stage { min-height: 530px; }
        .tpl-viewer-loading { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 530px;
            gap: 12px; color: #52665d; font-size: .94rem; font-weight: 700; text-align: center; }
        .tpl-viewer-spinner { width: 34px; height: 34px; border: 3px solid #cfe4da; border-top-color: #087c5b; border-radius: 50%; animation: tpl-spin .8s linear infinite; }
        @keyframes tpl-spin { to { transform: rotate(360deg); } }
        .tpl-viewer-zoom-target { --preview-zoom: 1; width: min(100%, 980px); min-height: 530px; margin: 0 auto; transform-origin: top center; }
        .tpl-viewer-paper { width: 100%; min-height: 530px; overflow: hidden; border-radius: 7px; background: #fff;
            box-shadow: 0 12px 32px rgba(15,23,42,.13); }
        .tpl-viewer-native { display: block; width: 100%; min-height: 710px; border: 0; background: #fff; }
        .tpl-viewer-image { display: block; max-width: 100%; max-height: 78vh; margin: 0 auto; object-fit: contain; }
        .tpl-viewer-docx-body { min-height: 530px; }
        .tpl-viewer-docx-body .docx-wrapper { min-height: 530px; padding: 28px 14px !important; background: #e6ece9 !important; }
        .tpl-viewer-docx-body .docx { margin: 0 auto 18px; box-shadow: 0 10px 28px rgba(15,23,42,.15); }
        .tpl-viewer-error { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 400px;
            gap: 12px; padding: 28px; color: #52665d; text-align: center; }
        .tpl-viewer-error h2 { margin: 0; color: #243d34; font-size: 1.1rem; }
        .tpl-viewer-error p { max-width: 560px; margin: 0; line-height: 1.6; }
        .tpl-viewer-error .tpl-viewer-download { margin-top: 5px; }
        .tpl-viewer-noscript { margin-top: 16px; padding: 14px 16px; border: 1px solid #f3d49a; border-radius: 10px;
            background: #fffbeb; color: #854d0e; }
        @media (max-width: 700px) {
            .tpl-viewer-toolbar { align-items: stretch; flex-wrap: wrap; padding: 10px; }
            .tpl-viewer-back { order: 1; }
            .tpl-viewer-title { order: 3; flex-basis: 100%; }
            .tpl-viewer-tools { order: 2; margin-left: auto; }
            .tpl-viewer-viewport { padding: 12px 8px 28px; }
            .tpl-viewer-meta { gap: 6px 12px; font-size: .78rem; }
            .tpl-viewer-native { min-height: 610px; }
            .tpl-viewer-docx-body .docx-wrapper { padding: 14px 5px !important; }
        }
    </style>

    <div class="tpl-viewer"
         data-document-viewer
         data-preview-kind="{{ $previewKind }}"
         data-content-url="{{ $contentUrl }}">
        <div class="tpl-viewer-toolbar">
            <a href="{{ route('templates.index') }}" class="tpl-viewer-back">
                <x-ui-icon name="arrow-left" :size="17" />
                <span>Back to Forms</span>
            </a>

            <div class="tpl-viewer-title" title="{{ $template->original_name }}">{{ $template->original_name }}</div>

            @if($isPreviewable)
                <div class="tpl-viewer-tools" aria-label="Document zoom controls">
                    <button type="button" class="tpl-viewer-control" data-preview-zoom-out aria-label="Zoom out">−</button>
                    <span class="tpl-viewer-zoom-value" data-preview-zoom-value>100%</span>
                    <button type="button" class="tpl-viewer-control" data-preview-zoom-in aria-label="Zoom in">+</button>
                    <button type="button" class="tpl-viewer-control" data-preview-zoom-reset aria-label="Reset zoom">Reset</button>
                </div>
            @endif

            <a href="{{ route('templates.download', $template) }}" class="tpl-viewer-download">
                <x-ui-icon name="download" :size="17" />
                <span>Download</span>
            </a>
        </div>

        <div class="tpl-viewer-meta" aria-label="Document information">
            <span class="tpl-viewer-badge">{{ $extension }}</span>
            <span class="tpl-viewer-meta-item"><strong>Uploaded by:</strong> {{ $uploaderName }}</span>
            <span class="tpl-viewer-meta-item"><strong>Responsible office:</strong> {{ $officeName }}</span>
        </div>

        <main class="tpl-viewer-viewport">
            <div class="tpl-viewer-loading" data-preview-loading role="status" aria-live="polite">
                <span class="tpl-viewer-spinner" aria-hidden="true"></span>
                <span>Loading document preview...</span>
            </div>

            <div class="tpl-viewer-stage" data-preview-stage hidden>
                <div class="tpl-viewer-zoom-target" data-preview-zoom-target>
                    <div class="tpl-viewer-paper">
                        @if($previewKind === 'docx')
                            <div data-docx-styles aria-hidden="true"></div>
                            <div class="tpl-viewer-docx-body" data-docx-body></div>
                        @elseif($previewKind === 'pdf')
                            <iframe class="tpl-viewer-native" data-native-preview title="{{ $template->original_name }}" hidden></iframe>
                        @elseif($previewKind === 'image')
                            <img class="tpl-viewer-image" data-native-preview alt="{{ $template->original_name }}" hidden>
                        @endif
                    </div>
                </div>
            </div>

            <div class="tpl-viewer-error" data-preview-error role="alert" hidden>
                <h2>Preview unavailable</h2>
                <p data-preview-error-message>We could not render this document in the browser.</p>
                <a href="{{ route('templates.download', $template) }}" class="tpl-viewer-download">
                    <x-ui-icon name="download" :size="17" />
                    <span>Download file</span>
                </a>
            </div>
        </main>

        <noscript>
            <div class="tpl-viewer-noscript">JavaScript is required to render this document preview. You can still use the Download button above.</div>
        </noscript>
    </div>
@endsection
