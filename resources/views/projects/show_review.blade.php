@extends('layouts.admin')

@section('title', 'Review Project')
@section('subtitle', 'Check details and update status')

@section('content')
    <div class="card" style="max-width:900px; padding:28px">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap">
            <div>
                <h3 style="margin:0; font-size:1.4rem; color:#0f172a">{{ $project->title }}</h3>
                <div style="display:flex; align-items:center; gap:9px; color:#64748b; margin-top:8px; flex-wrap:wrap">
                    <x-organization-avatar :organization="$project->creator" :size="30" />
                    <span>{{ $project->creator->organization_name ?? $project->creator->name ?? '—' }}</span>
                    <span>· 🕒 Submitted {{ $project->created_at->diffForHumans() }}</span>
                </div>
            </div>
            <span style="font-size:0.85rem; font-weight:700; padding:6px 14px; border-radius:999px; background:#eff6ff; color:#1d4ed8; white-space:nowrap">
                {{ ucwords(str_replace('_', ' ', $project->status)) }}
            </span>
        </div>

        @if($project->description)
            <div style="margin-top:20px; padding:18px; border-radius:14px; background:#f8fafc; border:1px solid #e2e8f0; color:#334155; line-height:1.7; white-space:pre-line;">{{ $project->description }}</div>
        @endif

        <div style="margin-top:22px">
            <h4 style="margin:0 0 12px; color:#0f172a">Attachments</h4>
            @if($project->attachments->isEmpty())
                <div style="padding:16px 18px; border-radius:14px; background:#fffbeb; border:1px solid #fde68a; color:#92400e;">No files were attached to this submission.</div>
            @else
                <div style="display:grid; gap:10px">
                    @foreach($project->attachments as $attachment)
                        <a href="{{ asset('storage/'.$attachment->file_path) }}" target="_blank" rel="noopener" style="display:flex; align-items:center; gap:14px; padding:14px 16px; border-radius:14px; border:1px solid #e5e7eb; background:#ffffff; text-decoration:none; color:inherit">
                            <div style="width:42px; height:42px; border-radius:12px; background:#eef2ff; color:#4338ca; display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0;">📄</div>
                            <div style="flex:1; min-width:0">
                                <div style="font-weight:600; color:#0f172a; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $attachment->file_name }}</div>
                                <div style="font-size:0.85rem; color:#64748b;">{{ strtoupper(pathinfo($attachment->file_name, PATHINFO_EXTENSION)) }} · {{ number_format($attachment->file_size / 1024 / 1024, 2) }} MB</div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        <div style="margin-top:26px; border-top:1px solid #e5e7eb; padding-top:22px">
            @if(auth()->user()->role === 'celp_secretary')
                <form method="post" action="{{ route('projects.secretary_review.update', $project->id) }}">
                    @csrf
                    <div style="margin-bottom:14px">
                        <label style="display:block; font-weight:700; margin-bottom:8px">Comments (optional)</label>
                        <textarea name="comments" rows="4" style="width:100%; padding:14px; border:1px solid #d1d5db; border-radius:12px;"></textarea>
                    </div>
                    <div style="display:flex; gap:12px">
                        <button name="action" value="endorse" style="padding:12px 22px; border-radius:12px; background:#0b6a4a; color:#fff; border:none; font-weight:700; cursor:pointer;">Endorse</button>
                        <button name="action" value="return" style="padding:12px 22px; border-radius:12px; background:#fff7ed; color:#9a3412; border:1px solid #fed7aa; font-weight:700; cursor:pointer;">Return to applicant</button>
                    </div>
                </form>
            @elseif(auth()->user()->role === 'coordinator')
                <form method="post" action="{{ route('projects.coordinator_review.update', $project->id) }}">
                    @csrf
                    <div style="margin-bottom:14px">
                        <label style="display:block; font-weight:700; margin-bottom:8px">Comments (optional)</label>
                        <textarea name="comments" rows="4" style="width:100%; padding:14px; border:1px solid #d1d5db; border-radius:12px;"></textarea>
                    </div>
                    <div style="display:flex; gap:12px">
                        <button name="action" value="approve" style="padding:12px 22px; border-radius:12px; background:#0b6a4a; color:#fff; border:none; font-weight:700; cursor:pointer;">Approve</button>
                        <button name="action" value="return" style="padding:12px 22px; border-radius:12px; background:#fff7ed; color:#9a3412; border:1px solid #fed7aa; font-weight:700; cursor:pointer;">Return to applicant</button>
                    </div>
                </form>
            @elseif(auth()->user()->role === 'celp_head')
                <form method="post" action="{{ route('projects.celp_head_review.update', $project->id) }}">
                    @csrf
                    <div style="margin-bottom:14px">
                        <label style="display:block; font-weight:700; margin-bottom:8px">Comments (optional)</label>
                        <textarea name="comments" rows="4" style="width:100%; padding:14px; border:1px solid #d1d5db; border-radius:12px;"></textarea>
                    </div>
                    <div style="display:flex; gap:12px">
                        <button name="action" value="approve" style="padding:12px 22px; border-radius:12px; background:#0b6a4a; color:#fff; border:none; font-weight:700; cursor:pointer;">Approve</button>
                        <button name="action" value="return" style="padding:12px 22px; border-radius:12px; background:#fff7ed; color:#9a3412; border:1px solid #fed7aa; font-weight:700; cursor:pointer;">Return to applicant</button>
                    </div>
                </form>
            @elseif(auth()->user()->role === 'csa_director')
                <form method="post" action="{{ route('projects.csa_review.update', $project->id) }}" style="margin-bottom:14px">
                    @csrf
                    <button name="action" value="clear" style="padding:12px 22px; border-radius:12px; background:#0b6a4a; color:#fff; border:none; font-weight:700; cursor:pointer;">Clear for compliance</button>
                </form>
                <form method="post" action="{{ route('projects.csa_review.update', $project->id) }}">
                    @csrf
                    <div style="margin-bottom:14px">
                        <label for="csa-return-comments" style="display:block; font-weight:700; margin-bottom:8px">Required return reason</label>
                        <textarea id="csa-return-comments" name="comments" rows="4" required maxlength="2000"
                                  style="width:100%; padding:14px; border:1px solid {{ $errors->has('comments') ? '#dc2626' : '#d1d5db' }}; border-radius:12px;">{{ old('comments') }}</textarea>
                        @error('comments')
                            <div style="margin-top:6px; color:#b91c1c; font-size:0.88rem">{{ $message }}</div>
                        @enderror
                    </div>
                    <button name="action" value="return" style="padding:12px 22px; border-radius:12px; background:#fff7ed; color:#9a3412; border:1px solid #fed7aa; font-weight:700; cursor:pointer;">Return to applicant</button>
                </form>
            @else
                <div style="color:#64748b">You are not authorized to review this submission.</div>
            @endif
        </div>
    </div>
@endsection
