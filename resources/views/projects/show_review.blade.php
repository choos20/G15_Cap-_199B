@extends('layouts.admin')

@section('title', 'Review Project')
@section('subtitle', 'Check details and update status')

@section('content')
    <link rel="stylesheet" href="{{ asset('css/project-review.css') }}">

    <div class="review-page">
        <div class="review-card">
            <div class="review-header">
                <div>
                    <div class="review-title-row">
                        <span class="review-project-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none"><path d="M5 4h14v16H5zM8 8h8M8 12h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        <div>
                            <h3>{{ $project->title }}</h3>
                            <div class="review-meta">
                                <x-organization-avatar :organization="$project->creator" :size="28" />
                                <span>{{ $project->creator->organization_name ?? $project->creator->name ?? 'Unknown organization' }}</span>
                                <span class="review-meta-separator">|</span>
                                <x-ui-icon name="clock" :size="15" />
                                <span>Submitted {{ $project->created_at->diffForHumans() }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <span class="review-status">
                    <x-ui-icon name="send" :size="15" />
                    {{ \App\Models\Project::statusLabel($project->status) }}
                </span>
            </div>

            @if($project->description)
                <div class="review-description">{{ $project->description }}</div>
            @endif

            <div class="review-section">
                <h4 class="review-section-heading">
                    <x-ui-icon name="paperclip" :size="18" />
                    <span>Attachments ({{ $project->attachments->count() }})</span>
                </h4>

                @if($project->attachments->isEmpty())
                    <div class="review-empty">No files were attached to this submission.</div>
                @else
                    <div class="review-attachments">
                        @foreach($project->attachments as $attachment)
                            <div class="review-attachment">
                                <div class="review-attachment-icon">
                                    <x-document-type-icon :file-name="$attachment->file_name" :size="42" />
                                </div>
                                <div class="review-attachment-info">
                                    <div class="review-attachment-name" title="{{ $attachment->file_name }}">{{ $attachment->file_name }}</div>
                                    <div class="review-attachment-meta">{{ strtoupper(pathinfo($attachment->file_name, PATHINFO_EXTENSION)) }} · {{ number_format($attachment->file_size / 1024 / 1024, 2) }} MB</div>
                                </div>
                                <div class="review-attachment-actions">
                                    <a href="{{ route('projects.attachments.preview', $attachment) }}" class="review-attachment-action review-attachment-action--preview">
                                        <x-ui-icon name="eye" :size="15" />
                                        <span>Preview</span>
                                    </a>
                                    <a href="{{ route('projects.attachments.download', $attachment) }}" class="review-attachment-action review-attachment-action--download">
                                        <x-ui-icon name="download" :size="15" />
                                        <span>Download</span>
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="review-decision">
                @if(auth()->user()->role === 'celp_secretary')
                    <form method="post" action="{{ route('projects.secretary_review.update', $project->id) }}">
                        @csrf
                        <label class="review-label" for="review-comments">Comments (optional)</label>
                        <textarea id="review-comments" class="review-textarea" name="comments" rows="4" placeholder="Add a comment, feedback, or instruction (optional)..."></textarea>
                        <div class="review-actions" style="margin-top:14px">
                            <button name="action" value="endorse" class="review-button review-button--primary"><x-ui-icon name="check-circle" :size="16" /> Endorse</button>
                            <button name="action" value="return" class="review-button review-button--return"><x-ui-icon name="arrow-left" :size="16" /> Return to applicant</button>
                        </div>
                    </form>
                @elseif(auth()->user()->role === 'coordinator')
                    <form method="post" action="{{ route('projects.coordinator_review.update', $project->id) }}">
                        @csrf
                        <label class="review-label" for="review-comments">Comments (optional)</label>
                        <textarea id="review-comments" class="review-textarea" name="comments" rows="4" placeholder="Add a comment, feedback, or instruction (optional)..."></textarea>
                        <div class="review-actions" style="margin-top:14px">
                            <button name="action" value="approve" class="review-button review-button--primary"><x-ui-icon name="check-circle" :size="16" /> Approve</button>
                            <button name="action" value="return" class="review-button review-button--return"><x-ui-icon name="arrow-left" :size="16" /> Return to applicant</button>
                        </div>
                    </form>
                @elseif(auth()->user()->role === 'celp_head')
                    <form method="post" action="{{ route('projects.celp_head_review.update', $project->id) }}">
                        @csrf
                        <label class="review-label" for="review-comments">Comments (optional)</label>
                        <textarea id="review-comments" class="review-textarea" name="comments" rows="4" placeholder="Add a comment, feedback, or instruction (optional)..."></textarea>
                        <div class="review-actions" style="margin-top:14px">
                            <button name="action" value="approve" class="review-button review-button--primary"><x-ui-icon name="check-circle" :size="16" /> Approve</button>
                            <button name="action" value="return" class="review-button review-button--return"><x-ui-icon name="arrow-left" :size="16" /> Return to applicant</button>
                        </div>
                    </form>
                @elseif(auth()->user()->role === 'csa_director')
                    <form method="post" action="{{ route('projects.csa_review.update', $project->id) }}" style="margin-bottom:14px">
                        @csrf
                        <button name="action" value="clear" class="review-button review-button--primary"><x-ui-icon name="check-circle" :size="16" /> Clear for compliance</button>
                    </form>
                    <form method="post" action="{{ route('projects.csa_review.update', $project->id) }}">
                        @csrf
                        <label class="review-label" for="csa-return-comments">Required return reason</label>
                        <textarea id="csa-return-comments" class="review-textarea" name="comments" rows="4" required maxlength="2000" placeholder="Explain what changes are needed before returning this proposal.">{{ old('comments') }}</textarea>
                        @error('comments')
                            <div class="review-error">{{ $message }}</div>
                        @enderror
                        <div class="review-actions" style="margin-top:14px">
                            <button name="action" value="return" class="review-button review-button--return"><x-ui-icon name="arrow-left" :size="16" /> Return to applicant</button>
                        </div>
                    </form>
                @else
                    <div style="color:#52665d">You are not authorized to review this submission.</div>
                @endif
            </div>
        </div>
    </div>
@endsection
