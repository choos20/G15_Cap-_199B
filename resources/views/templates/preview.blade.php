@extends('layouts.admin')

@section('title', 'Document Preview')
@section('subtitle', 'Document details')

@section('content')
    <style>
        .tpl-preview-card { max-width:720px; margin:0 auto; padding:32px; text-align:center; }
        .tpl-preview-icon { display:flex; justify-content:center; margin-bottom:18px; }
        .tpl-preview-card h2 { margin:0; color:#0f172a; font-size:1.35rem; }
        .tpl-preview-card p { max-width:560px; margin:10px auto 0; color:#475569; line-height:1.65; }
        .tpl-preview-meta { display:flex; flex-wrap:wrap; justify-content:center; gap:8px 14px; margin-top:18px; color:#334155; font-size:.92rem; }
        .tpl-preview-actions { display:flex; justify-content:center; flex-wrap:wrap; gap:10px; margin-top:24px; }
        .tpl-preview-actions a { display:inline-flex; align-items:center; justify-content:center; gap:8px; min-width:128px; min-height:42px; padding:0 14px; border-radius:10px; font:inherit; font-size:.9rem; font-weight:700; line-height:1; text-decoration:none; }
        .tpl-preview-actions .tpl-preview-btn { border:1px solid #087c5b; background:#fff; color:#087c5b; }
        .tpl-preview-actions .tpl-download-btn { border:1px solid #087c5b; background:#087c5b; color:#fff; }
        .tpl-preview-actions .tpl-preview-btn:hover { background:#ecfdf5; }
        .tpl-preview-actions .tpl-download-btn:hover { background:#066348; border-color:#066348; }
    </style>

    <div class="card tpl-preview-card">
        <div class="tpl-preview-icon">
            <x-document-type-icon :file-name="$template->original_name" :size="76" />
        </div>
        <h2>{{ $template->original_name }}</h2>
        <p>
            This {{ $extension }} document cannot be rendered directly in the browser.
            Download it to open it in Microsoft Word or a compatible document editor.
        </p>
        <div class="tpl-preview-meta">
            <span>{{ $extension }} document</span>
            <span>Uploaded by {{ $template->uploader->name ?? 'ProjectLink' }}</span>
        </div>
        <div class="tpl-preview-actions">
            <a href="{{ route('templates.download', $template) }}" class="tpl-download-btn">
                <x-ui-icon name="download" :size="18" />
                <span>Download</span>
            </a>
            <a href="{{ route('templates.index') }}" class="tpl-preview-btn">
                <span>Back to forms</span>
            </a>
        </div>
    </div>
@endsection
