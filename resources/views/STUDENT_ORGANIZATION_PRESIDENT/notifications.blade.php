@extends('layouts.admin')

@section('title','Notifications')
@section('subtitle','Track returned proposals and review comments from CELP and CSA reviewers')

@section('content')
    <div class="card" style="padding:24px; margin-bottom:20px">
        <h3 style="margin-top:0; margin-bottom:14px">Notifications</h3>
        <p style="margin:0 0 18px; color:#475569">You will see returned proposal alerts here whenever CELP Secretary, Coordinator, Head, or CSA send feedback.</p>

        @if($returnedProjects->isEmpty())
            <div style="padding:24px; border-radius:20px; background:#f8fafc; color:#334155">No returned proposals at the moment. Keep an eye on your dashboard for status updates.</div>
        @else
            <div style="display:grid; gap:16px">
                @foreach($returnedProjects as $project)
                    @php
                        $statusLabel = str_replace('_', ' ', $project->status);
                        $statusColor = match ($project->status) {
                            'returned_by_secretary' => '#fef3c7',
                            'returned_by_coordinator' => '#ede9fe',
                            'returned_by_celp_head' => '#fee2e2',
                            'returned_by_csa' => '#fee2e2',
                            default => '#f8fafc',
                        };
                        $statusText = match ($project->status) {
                            'returned_by_secretary' => 'Returned by CELP Secretary',
                            'returned_by_coordinator' => 'Returned by Coordinator',
                            'returned_by_celp_head' => 'Returned by CELP Head',
                            'returned_by_csa' => 'Returned by CSA',
                            default => ucfirst(str_replace('_', ' ', $project->status)),
                        };
                    @endphp
                    <div style="padding:20px; border-radius:24px; border:1px solid #e5e7eb; background:#ffffff; box-shadow:0 12px 30px rgba(15,23,42,0.06);">
                        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px;">
                            <div>
                                <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                                    <div style="width:12px; height:12px; border-radius:999px; background:#dc2626;"></div>
                                    <div style="font-size:0.95rem; font-weight:700; color:#0f172a">{{ $project->title }}</div>
                                </div>
                                <div style="font-size:0.9rem; color:#475569; margin-bottom:10px">{{ $statusText }} — returned on {{ $project->updated_at->format('M d, Y H:i') }}</div>
                                @if($project->review_comments)
                                    <div style="margin-bottom:12px; padding:16px; border-radius:18px; background:#f8fafc; border:1px solid #e2e8f0; color:#334155; white-space:pre-line">{{ $project->review_comments }}</div>
                                @endif
                                <div style="display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:999px; background:{{ $statusColor }}; color:#0f172a; font-weight:700; font-size:0.92rem">Returned proposal</div>
                            </div>
                            <div style="text-align:right; min-width:120px">
                                <div style="font-size:0.85rem; color:#64748b;">Current status</div>
                                <div style="margin-top:8px; font-weight:700; color:#0f172a">{{ ucwords(str_replace('_', ' ', $project->status)) }}</div>
                                <a href="{{ route('projects.edit', $project) }}" style="display:inline-block; margin-top:12px; padding:10px 14px; border-radius:14px; background:#0f172a; color:#ffffff; text-decoration:none; font-size:0.92rem;">Edit and resubmit</a>
                            </div>
                        </div>
                        <div style="margin-top:18px; font-size:0.95rem; color:#334155">Review the returned comments in the proposal details and update the activity before resubmission.</div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
