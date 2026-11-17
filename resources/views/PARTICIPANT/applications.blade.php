@extends('layouts.admin')

@section('title', 'My Applications')
@section('subtitle', 'Enrollment log for activities you have applied to')

@section('content')
    <style>
        .application-list{display:grid; gap:14px}
        .application-card{display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:center; gap:16px; padding:20px 22px}
        .application-main{min-width:0; width:100%}
        .application-title-row{display:flex; align-items:center; gap:10px; flex-wrap:wrap; min-width:0}
        .application-title{min-width:0; max-width:100%; color:#0f172a; font-size:1.02rem; font-weight:700; line-height:1.35; overflow-wrap:break-word; word-break:normal; text-decoration:none}
        .application-status{flex:0 0 auto; white-space:nowrap}
        .application-organization{display:inline-flex; align-items:center; gap:8px; max-width:100%; margin-top:9px; color:#0b6a4a; font-size:.87rem; font-weight:600; text-decoration:none}
        .application-organization span{min-width:0; overflow-wrap:break-word; word-break:normal}
        .application-meta{font-size:.88rem; color:#64748b; margin-top:6px; overflow-wrap:break-word; word-break:normal}
        .application-attachments{margin-top:8px; display:grid; gap:8px}
        .application-attachments > div{overflow-wrap:break-word; word-break:normal}
        .application-attachment{font-size:.84rem; color:#334155; overflow-wrap:anywhere; word-break:normal}
        .application-action{min-width:0; max-width:280px; color:#92400e; font-size:.88rem; font-weight:600; overflow-wrap:break-word; word-break:normal}
        .application-action form{margin:0}
        .application-withdraw{background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; padding:9px 16px; border-radius:10px; font:inherit; font-weight:700; cursor:pointer; white-space:nowrap}
        .application-withdrawal-unavailable{display:flex; align-items:flex-start; gap:9px; width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid #fecaca; border-radius:12px; background:#fef2f2; color:#991b1b; font-size:.82rem; line-height:1.35; font-weight:700}
        .application-withdrawal-unavailable__icon{display:inline-flex; align-items:center; justify-content:center; flex:0 0 20px; width:20px; height:20px; margin-top:1px; border-radius:999px; background:#fee2e2; color:#b91c1c; font-size:.78rem; font-weight:800}
        .application-withdrawal-unavailable__copy{min-width:0}
        .application-withdrawal-unavailable__title{display:block}
        .application-withdrawal-unavailable__reason{display:block; margin-top:2px; color:#b91c1c; font-size:.78rem; font-weight:600}
        @media (max-width:760px){
            .application-card{grid-template-columns:minmax(0,1fr); align-items:stretch; gap:14px; padding:16px}
            .application-action{width:100%; max-width:none}
            .application-action form{width:100%}
            .application-withdraw{width:100%; min-height:44px}
            .application-withdrawal-unavailable{padding:11px 12px}
        }
        @media (max-width:360px){
            .application-card{padding:14px}
            .application-title-row{align-items:flex-start; gap:8px}
        }
    </style>
    @php
        $statusColors = [
            'pending' => ['#fffbeb', '#92400e'],
            'approved' => ['#ecfdf5', '#065f46'],
            'declined' => ['#fef2f2', '#991b1b'],
            'withdrawn' => ['#f1f5f9', '#475569'],
        ];
    @endphp

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

    @if($applications->isEmpty())
        <div class="card" style="padding:40px; text-align:center; color:#64748b">
            <div style="font-size:2rem; margin-bottom:10px">📋</div>
            You haven't applied to any activities yet.
            <div style="margin-top:14px"><a href="{{ route('participant.activities') }}" style="color:#2563eb; text-decoration:none; font-weight:700;">Browse open activities</a></div>
        </div>
    @else
        <div class="application-list">
            @foreach($applications as $application)
                @php [$bg, $color] = $statusColors[$application->status] ?? ['#f1f5f9', '#475569']; @endphp
                @php
                    $projectId = $application->announcement?->project_id;
                    $hasAttendance = $projectId && $attendedProjectIds->contains((int) $projectId);
                @endphp
                <div class="card application-card">
                    <div class="application-main">
                        <div class="application-title-row">
                            <a class="application-title" href="{{ route('participant.activities.show', $application->announcement) }}">{{ $application->announcement->title }}</a>
                            <span class="application-status" style="font-size:0.78rem; font-weight:700; padding:4px 10px; border-radius:999px; background:{{ $bg }}; color:{{ $color }}">{{ ucfirst($application->status) }}</span>
                        </div>
                        @if($application->announcement->creator)
                            <a class="application-organization" href="{{ route('organizations.show', $application->announcement->creator) }}">
                                <x-organization-avatar :organization="$application->announcement->creator" :size="30" />
                                <span>{{ $application->announcement->creator->organization_name ?? $application->announcement->creator->name }}</span>
                            </a>
                        @else
                            <div class="application-organization" style="color:#64748b">
                                <x-organization-avatar :organization="null" :size="30" />
                                <span>Organization unavailable</span>
                            </div>
                        @endif
                        <div class="application-meta">
                            Applied {{ $application->created_at->diffForHumans() }}
                            @if($application->announcement->event_date)
                                · Activity on {{ $application->announcement->event_date->format('M j, Y') }}
                            @endif
                        </div>
                        @if($application->attachments->isNotEmpty())
                            <div class="application-attachments">
                                @foreach($application->attachments as $doc)
                                    <div style="font-size:0.84rem; color:#334155">📄 {{ $doc->original_name }}</div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    @if(in_array($application->status, ['pending', 'approved']) && $hasAttendance)
                        <div class="application-action">
                            <div class="application-withdrawal-unavailable" role="status">
                                <span class="application-withdrawal-unavailable__icon" aria-hidden="true">!</span>
                                <span class="application-withdrawal-unavailable__copy">
                                    <span class="application-withdrawal-unavailable__title">Withdrawal unavailable</span>
                                    <span class="application-withdrawal-unavailable__reason">Attendance has already been recorded.</span>
                                </span>
                            </div>
                        </div>
                    @elseif(in_array($application->status, ['pending', 'approved']))
                        <div class="application-action">
                            <form method="post" action="{{ route('participant.applications.withdraw', $application) }}"
                              data-confirm
                              data-confirm-title="Withdraw application?"
                              data-confirm-body="You'll be removed from {name}, and your slot will be released to other applicants."
                              data-confirm-name="{{ $application->announcement->title ?? 'this activity' }}"
                              data-confirm-note="You can apply again while slots remain."
                              data-confirm-ok="Withdraw"
                              data-confirm-cancel="Keep application">
                            @csrf
                            <button class="application-withdraw" type="submit">Withdraw</button>
                            </form>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
@endsection
