@extends('layouts.admin')

@section('title', $announcement->title)
@section('subtitle', 'Announcement details')

@section('content')
    <style>
        .ann-back { display:inline-flex; align-items:center; gap:8px; color:#2563eb; text-decoration:none; font-weight:700; margin-bottom:18px; }
        .ann-header { display:flex; justify-content:space-between; gap:18px; flex-wrap:wrap; align-items:flex-start; margin-bottom:8px; }
        .ann-badge { display:inline-flex; align-items:center; padding:6px 12px; border-radius:999px; font-size:0.85rem; font-weight:700; }
        .ann-badge-published { background:#dcfce7; color:#166534; }
        .ann-badge-draft { background:#f1f5f9; color:#475569; }
        .ann-meta { display:flex; flex-wrap:wrap; gap:10px; color:#64748b; font-size:0.92rem; margin-top:10px; }
        .ann-meta span { display:inline-flex; align-items:center; gap:6px; }
        .ann-section-title { font-size:1.05rem; font-weight:700; color:#0f172a; margin:0 0 14px; }
        .ann-image-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(180px, 1fr)); gap:16px; }
        .ann-image-card { border-radius:16px; overflow:hidden; border:1px solid #e5e7eb; background:#f8fafc; cursor:pointer; transition:transform 150ms ease, box-shadow 150ms ease; }
        .ann-image-card:hover { transform:translateY(-2px); box-shadow:0 12px 28px rgba(15,23,42,0.1); }
        .ann-image-card img { width:100%; height:150px; object-fit:cover; display:block; }
        .ann-image-caption { padding:10px 12px; font-size:0.85rem; color:#334155; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .ann-file-list { display:grid; gap:12px; }
        .ann-file-card { display:flex; align-items:center; gap:14px; padding:14px 16px; border-radius:16px; border:1px solid #e5e7eb; background:#ffffff; text-decoration:none; color:inherit; transition:box-shadow 150ms ease, border-color 150ms ease; }
        .ann-file-card:hover { box-shadow:0 10px 24px rgba(15,23,42,0.08); border-color:#c7d2fe; }
        .ann-file-icon { width:44px; height:44px; border-radius:12px; background:#eef2ff; color:#4338ca; display:flex; align-items:center; justify-content:center; font-size:1.2rem; flex-shrink:0; }
        .ann-file-info strong { display:block; color:#0f172a; }
        .ann-file-info span { font-size:0.85rem; color:#64748b; }
        .ann-lightbox { display:none; position:fixed; inset:0; background:rgba(15,23,42,0.85); z-index:1000; align-items:center; justify-content:center; padding:40px; }
        .ann-lightbox.active { display:flex; }
        .ann-lightbox img { max-width:100%; max-height:100%; border-radius:12px; box-shadow:0 30px 60px rgba(0,0,0,0.4); }
        .ann-lightbox-close { position:absolute; top:24px; right:32px; color:#fff; font-size:2rem; background:none; border:none; cursor:pointer; line-height:1; }
    </style>

    <a href="{{ route('student_org.announcements') }}" class="ann-back">&larr; Back to announcements</a>

    @if(session('status'))
        <div class="card" style="border-left:4px solid #16a34a; background:#ecfdf5; color:#134e4a; padding:16px 20px; margin-bottom:20px">
            {{ session('status') }}
        </div>
    @endif

    <div class="card" style="padding:28px; margin-bottom:20px">
        <div class="ann-header">
            <div>
                <h2 style="margin:0 0 8px; font-size:1.5rem; color:#0f172a;">{{ $announcement->title }}</h2>
                <span class="ann-badge {{ $announcement->status === 'published' ? 'ann-badge-published' : 'ann-badge-draft' }}">{{ ucfirst($announcement->status) }}</span>
            </div>
            <div style="display:flex; gap:10px;">
                <a href="{{ route('student_org.announcements') }}" style="padding:10px 16px; border-radius:10px; background:#f8fafc; color:#0f172a; border:1px solid #e2e8f0; text-decoration:none; font-weight:600;">Close</a>
                <a href="{{ route('student_org.announcements.edit', $announcement) }}" style="padding:10px 16px; border-radius:10px; background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; text-decoration:none; font-weight:600;">Edit</a>
                <form action="{{ route('student_org.announcements.status', $announcement) }}" method="POST"
                      @if($announcement->status === 'published')
                          data-confirm
                          data-confirm-title="Unpublish announcement?"
                          data-confirm-body="{name} will be hidden from participants and volunteer applications."
                          data-confirm-name="{{ $announcement->title }}"
                          data-confirm-ok="Unpublish"
                          data-confirm-cancel="Keep published"
                      @endif>
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="{{ $announcement->status === 'published' ? 'draft' : 'published' }}">
                    <button type="submit" style="padding:10px 16px; border-radius:10px; background:{{ $announcement->status === 'published' ? '#fff7ed' : '#ecfdf5' }}; color:{{ $announcement->status === 'published' ? '#b45309' : '#08734e' }}; border:1px solid {{ $announcement->status === 'published' ? '#fed7aa' : '#a7f3d0' }}; font-weight:600; cursor:pointer;">
                        {{ $announcement->status === 'published' ? 'Unpublish' : 'Publish' }}
                    </button>
                </form>
                <form action="{{ route('student_org.announcements.destroy', $announcement) }}" method="POST" data-confirm
                      data-confirm-title="Remove announcement?"
                      data-confirm-body="{name} will be deleted and will no longer be visible to volunteers."
                      data-confirm-name="{{ $announcement->title }}"
                      data-confirm-note="This cannot be undone."
                      data-confirm-ok="Remove announcement"
                      data-confirm-cancel="Keep it">
                    @csrf
                    @method('DELETE')
                    <button type="submit" style="padding:10px 16px; border-radius:10px; background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; font-weight:600; cursor:pointer;">Remove</button>
                </form>
            </div>
        </div>

        <div class="ann-meta">
            <span>📁 {{ $announcement->category }}</span>
            @if($announcement->project)
                <span>🔗 {{ $announcement->project->title }}</span>
            @endif
            @if($announcement->slots_needed)
                <span>👥 {{ $announcement->slots_needed }} slot{{ $announcement->slots_needed === 1 ? '' : 's' }} needed</span>
            @endif
            @if($announcement->application_deadline)
                <span>⏰ Deadline: {{ $announcement->application_deadline->timezone('Asia/Manila')->format('M j, Y g:i A') }}</span>
            @endif
            <span>🕒 Posted {{ $announcement->created_at->diffForHumans() }}</span>
        </div>

        @if($announcement->slots_needed)
            @php $visibilityIssue = $announcement->participantVisibilityIssue(); @endphp
            @if($visibilityIssue)
                <div style="margin-top:16px; padding:12px 16px; border-radius:12px; background:#fffbeb; border:1px solid #fde68a; color:#92400e; font-size:0.9rem; font-weight:600;">
                    ⚠️ {{ $visibilityIssue }}
                </div>
            @else
                <div style="margin-top:16px; padding:12px 16px; border-radius:12px; background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; font-size:0.9rem; font-weight:600;">
                    ✅ Visible to volunteers in the activity feed.
                </div>
            @endif
        @endif

        @if($announcement->description)
            <p style="margin-top:18px; color:#334155; line-height:1.7; white-space:pre-line;">{{ $announcement->description }}</p>
        @endif
    </div>

    @php
        $imageAttachments = $announcement->attachments->filter(fn($a) => str_starts_with($a->mime_type, 'image/'));
        $otherAttachments = $announcement->attachments->reject(fn($a) => str_starts_with($a->mime_type, 'image/'));
    @endphp

    @if($announcement->attachments->isEmpty())
        <div class="card" style="padding:24px; color:#64748b;">No files were attached to this announcement.</div>
    @else
        @if($imageAttachments->isNotEmpty())
            <div class="card" style="padding:24px; margin-bottom:20px">
                <h3 class="ann-section-title">Images ({{ $imageAttachments->count() }})</h3>
                <div class="ann-image-grid">
                    @foreach($imageAttachments as $attachment)
                        <div>
                            <div class="ann-image-card" data-lightbox-src="{{ asset('storage/'.$attachment->file_path) }}">
                                <img src="{{ asset('storage/'.$attachment->file_path) }}" alt="{{ $attachment->original_name }}" loading="lazy">
                                <div class="ann-image-caption">{{ $attachment->original_name }}</div>
                            </div>
                            <form method="POST" action="{{ route('student_org.announcements.attachments.destroy', [$announcement, $attachment]) }}"
                                  style="margin-top:8px; text-align:right" data-confirm
                                  data-confirm-title="Remove attachment?"
                                  data-confirm-body="{name} will be removed from this announcement."
                                  data-confirm-name="{{ $attachment->original_name }}"
                                  data-confirm-ok="Remove file"
                                  data-confirm-cancel="Keep file">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="ann-upload-action ann-upload-remove">Remove file</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if($otherAttachments->isNotEmpty())
            <div class="card" style="padding:24px;">
                <h3 class="ann-section-title">Files ({{ $otherAttachments->count() }})</h3>
                <div class="ann-file-list">
                    @foreach($otherAttachments as $attachment)
                        <div>
                            <a class="ann-file-card" href="{{ asset('storage/'.$attachment->file_path) }}" target="_blank" rel="noopener">
                                <div class="ann-file-icon">📄</div>
                                <div class="ann-file-info">
                                    <strong>{{ $attachment->original_name }}</strong>
                                    <span>{{ strtoupper(pathinfo($attachment->original_name, PATHINFO_EXTENSION)) }} · {{ number_format($attachment->size / 1024 / 1024, 2) }} MB</span>
                                </div>
                            </a>
                            <form method="POST" action="{{ route('student_org.announcements.attachments.destroy', [$announcement, $attachment]) }}"
                                  style="margin-top:8px; text-align:right" data-confirm
                                  data-confirm-title="Remove attachment?"
                                  data-confirm-body="{name} will be removed from this announcement."
                                  data-confirm-name="{{ $attachment->original_name }}"
                                  data-confirm-ok="Remove file"
                                  data-confirm-cancel="Keep file">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="ann-upload-action ann-upload-remove">Remove file</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif

    <div id="ann-lightbox" class="ann-lightbox">
        <button type="button" class="ann-lightbox-close" aria-label="Close">&times;</button>
        <img id="ann-lightbox-img" src="" alt="">
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const lightbox = document.getElementById('ann-lightbox');
            const lightboxImg = document.getElementById('ann-lightbox-img');
            const closeBtn = lightbox.querySelector('.ann-lightbox-close');

            document.querySelectorAll('.ann-image-card').forEach((card) => {
                card.addEventListener('click', () => {
                    lightboxImg.src = card.dataset.lightboxSrc;
                    lightbox.classList.add('active');
                });
            });

            const close = () => {
                lightbox.classList.remove('active');
                lightboxImg.src = '';
            };

            closeBtn.addEventListener('click', close);
            lightbox.addEventListener('click', (event) => {
                if (event.target === lightbox) close();
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') close();
            });
        });
    </script>
@endsection
