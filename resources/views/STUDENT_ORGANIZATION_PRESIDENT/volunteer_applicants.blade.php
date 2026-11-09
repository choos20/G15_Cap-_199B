@extends('layouts.admin')

@section('title', $announcement->title)
@section('subtitle', 'Review volunteer applications')

@section('content')
    @php
        $statusColors = [
            'pending' => ['#fffbeb', '#92400e'],
            'approved' => ['#ecfdf5', '#065f46'],
            'declined' => ['#fef2f2', '#991b1b'],
            'withdrawn' => ['#f1f5f9', '#475569'],
        ];
        $remaining = $announcement->remainingSlots();
    @endphp

    <a href="{{ route('student_org.volunteers') }}" style="display:inline-flex; align-items:center; gap:8px; color:#2563eb; text-decoration:none; font-weight:700; margin-bottom:18px;">&larr; Back to volunteer management</a>

    @if(session('status'))
        <div class="card" style="border-left:4px solid #16a34a; background:#ecfdf5; color:#134e4a; padding:16px 20px; margin-bottom:20px">
            {{ session('status') }}
        </div>
    @endif

    @if($errors->any())
        <div class="card" style="border-left:4px solid #dc2626; background:#fef2f2; color:#7f1d1d; padding:16px 20px; margin-bottom:20px">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="card" style="padding:24px; margin-bottom:20px">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap">
            <div>
                <h3 style="margin:0">{{ $announcement->title }}</h3>
                <div style="color:#64748b; margin-top:6px">{{ $announcement->project->title ?? '—' }}</div>
            </div>
            <span style="font-size:0.9rem; font-weight:700; padding:8px 16px; border-radius:999px; background:{{ $remaining > 0 ? '#ecfdf5' : '#fef2f2' }}; color:{{ $remaining > 0 ? '#065f46' : '#b91c1c' }}">{{ $remaining }} of {{ $announcement->slots_needed }} slots remaining</span>
        </div>
    </div>

    @if($applications->isEmpty())
        <div class="card" style="padding:40px; text-align:center; color:#64748b">
            No applications yet.
        </div>
    @else
        <div style="display:grid; gap:12px">
            @foreach($applications as $application)
                @php [$bg, $color] = $statusColors[$application->status] ?? ['#f1f5f9', '#475569']; @endphp
                <div class="card" style="padding:18px 20px">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap">
                        <div>
                            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap">
                                <div style="font-weight:700; color:#0f172a">{{ $application->applicant->name ?? 'Unknown' }}</div>
                                <span style="font-size:0.78rem; font-weight:700; padding:4px 10px; border-radius:999px; background:{{ $bg }}; color:{{ $color }}">{{ ucfirst($application->status) }}</span>
                            </div>
                            <div style="font-size:0.85rem; color:#64748b; margin-top:4px">
                                {{ $application->applicant->email ?? '' }} · Applied {{ $application->created_at->diffForHumans() }}
                                @if($application->decision_notes)
                                    · {{ $application->decision_notes }}
                                @endif
                            </div>
                        </div>
                        @if($application->status === 'pending')
                            <div style="display:flex; gap:10px; flex-shrink:0">
                                <form method="post" action="{{ route('student_org.volunteers.approve', $application) }}">
                                    @csrf
                                    <button type="submit" style="background:#0b6a4a; color:#fff; border:none; padding:9px 16px; border-radius:10px; font-weight:700; cursor:pointer;">Approve</button>
                                </form>
                                <form method="post" action="{{ route('student_org.volunteers.decline', $application) }}" data-confirm
                                      data-confirm-title="Decline application?"
                                      data-confirm-body="{name} will be notified that their application was not accepted, and their slot will be released."
                                      data-confirm-name="{{ $application->user->name ?? 'This applicant' }}"
                                      data-confirm-note="They can apply again while slots remain."
                                      data-confirm-ok="Decline"
                                      data-confirm-cancel="Go back">
                                    @csrf
                                    <button type="submit" style="background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; padding:9px 16px; border-radius:10px; font-weight:700; cursor:pointer;">Decline</button>
                                </form>
                            </div>
                        @endif
                    </div>

                    <div style="margin-top:14px; padding-top:14px; border-top:1px solid #f1f5f9;">
                        <div style="font-size:0.78rem; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.04em; margin-bottom:10px;">Supporting documents</div>
                        @if($application->attachments->isEmpty())
                            <div style="color:#b91c1c; font-size:0.88rem;">⚠️ No documents submitted.</div>
                        @else
                            <div style="display:flex; flex-wrap:wrap; gap:10px;">
                                @foreach($application->attachments as $doc)
                                    <a href="{{ route('volunteer_applications.documents.download', $doc) }}" style="display:flex; align-items:center; gap:8px; padding:9px 14px; border-radius:10px; border:1px solid #e5e7eb; background:#f8fafc; text-decoration:none; color:#0f172a; font-size:0.88rem; font-weight:600;">
                                        📄 {{ $doc->original_name }}
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
