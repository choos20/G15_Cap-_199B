@extends('layouts.admin')
@section('title', 'Attachment Preview')
@section('subtitle', $attachment->file_name)
@section('content')
    <x-document-viewer :name="$attachment->file_name" :extension="$extension" :preview-kind="$previewKind"
        :content-url="$contentUrl" :download-url="$downloadUrl"
        :back-url="route('projects.show_review', $project)" back-label="Back to Review">
        <span class="tpl-viewer-meta-item"><strong>Uploaded by:</strong> {{ $project->creator->name ?? 'ProjectLink' }}</span>
        <span class="tpl-viewer-meta-item"><strong>Organization:</strong> {{ $project->creator->organization_name ?? $project->creator->name ?? 'Project proposal' }}</span>
        <span class="tpl-viewer-meta-item"><strong>Project:</strong> {{ $project->title }}</span>
    </x-document-viewer>
@endsection