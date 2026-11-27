@extends('layouts.admin')
@section('title', 'Document Preview')
@section('subtitle', $template->original_name)
@section('content')
    <x-document-viewer :name="$template->original_name" :extension="$extension" :preview-kind="$previewKind"
        :content-url="$contentUrl" :download-url="route('templates.download', $template)"
        :back-url="auth()->user()->canManageTemplates() ? route('templates.manage') : route('templates.index')" back-label="Back to Forms">
        <span class="tpl-viewer-meta-item"><strong>Uploaded by:</strong> {{ $template->uploader->name ?? 'ProjectLink' }}</span>
        <span class="tpl-viewer-meta-item"><strong>Responsible office:</strong> {{ $template->sourceLabel() }}</span>
    </x-document-viewer>
@endsection