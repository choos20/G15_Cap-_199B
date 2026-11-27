@props(['name', 'extension', 'previewKind', 'contentUrl', 'downloadUrl', 'backUrl', 'backLabel'])

@once
    @vite('resources/js/document-viewer.js')
    <link rel="stylesheet" href="{{ asset('css/document-viewer.css') }}?v={{ substr(md5_file(public_path('css/document-viewer.css')), 0, 12) }}">
@endonce

<div class="tpl-viewer" data-document-viewer data-preview-kind="{{ $previewKind }}" data-content-url="{{ $contentUrl }}" aria-busy="true">
    <div class="tpl-viewer-toolbar">
        <a href="{{ $backUrl }}" class="tpl-viewer-back">
            <x-ui-icon name="arrow-left" :size="17" />
            <span>{{ $backLabel }}</span>
        </a>
        <div class="tpl-viewer-title" title="{{ $name }}">{{ $name }}</div>
        @if($previewKind !== 'unsupported')
            <div class="tpl-viewer-tools" aria-label="Document zoom controls">
                <button type="button" class="tpl-viewer-control" data-preview-zoom-out aria-label="Zoom out" disabled>&minus;</button>
                <span class="tpl-viewer-zoom-value" data-preview-zoom-value>100%</span>
                <button type="button" class="tpl-viewer-control" data-preview-zoom-in aria-label="Zoom in" disabled>+</button>
                <button type="button" class="tpl-viewer-control" data-preview-zoom-reset aria-label="Reset zoom" disabled>Reset</button>
            </div>
        @endif
        <a href="{{ $downloadUrl }}" class="tpl-viewer-download">
            <x-ui-icon name="download" :size="17" />
            <span>Download original file</span>
        </a>
    </div>
    <div class="tpl-viewer-meta" aria-label="Document information">
        <span class="tpl-viewer-badge">{{ $extension }}</span>
        {{ $slot }}
    </div>
    <div class="tpl-viewer-viewport">
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
                        <iframe class="tpl-viewer-native" data-native-preview title="{{ $name }}" hidden></iframe>
                    @elseif($previewKind === 'image')
                        <img class="tpl-viewer-image" data-native-preview alt="{{ $name }}" hidden>
                    @elseif($previewKind === 'spreadsheet')
                        <div class="tpl-viewer-spreadsheet" data-spreadsheet-body></div>
                    @endif
                </div>
            </div>
        </div>
        <div class="tpl-viewer-error" data-preview-error role="alert" hidden>
            <h2>Preview unavailable</h2>
            <p data-preview-error-message>We could not render this document in the browser.</p>
            <a href="{{ $downloadUrl }}" class="tpl-viewer-download">Download original file</a>
        </div>
    </div>
    <noscript>
        <div class="tpl-viewer-noscript">JavaScript is required for the preview. You can still download the original file.</div>
    </noscript>
</div>
