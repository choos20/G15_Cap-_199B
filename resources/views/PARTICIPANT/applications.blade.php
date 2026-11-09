@extends('layouts.admin')

@section('title', 'My Applications')
@section('subtitle', 'Enrollment log for activities you have applied to')

@section('content')
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
        <div style="display:grid; gap:14px">
            @foreach($applications as $application)
                @php [$bg, $color] = $statusColors[$application->status] ?? ['#f1f5f9', '#475569']; @endphp
                <div class="card" style="display:flex; justify-content:space-between; align-items:center; gap:16px; padding:20px 22px; flex-wrap:wrap">
                    <div style="min-width:0; flex:1">
                        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap">
                            <a href="{{ route('participant.activities.show', $application->announcement) }}" style="font-weight:700; color:#0f172a; text-decoration:none; font-size:1.02rem">{{ $application->announcement->title }}</a>
                            <span style="font-size:0.78rem; font-weight:700; padding:4px 10px; border-radius:999px; background:{{ $bg }}; color:{{ $color }}">{{ ucfirst($application->status) }}</span>
                        </div>
                        @if($application->announcement->creator)
                            <a href="{{ route('organizations.show', $application->announcement->creator) }}" style="display:inline-flex; align-items:center; gap:8px; margin-top:9px; color:#0b6a4a; font-size:0.87rem; font-weight:600; text-decoration:none">
                                <x-organization-avatar :organization="$application->announcement->creator" :size="30" />
                                <span>{{ $application->announcement->creator->organization_name ?? $application->announcement->creator->name }}</span>
                            </a>
                        @else
                            <div style="display:flex; align-items:center; gap:8px; margin-top:9px; color:#64748b; font-size:0.87rem">
                                <x-organization-avatar :organization="null" :size="30" />
                                <span>Organization unavailable</span>
                            </div>
                        @endif
                        <div style="font-size:0.88rem; color:#64748b; margin-top:6px">
                            Applied {{ $application->created_at->diffForHumans() }}
                            @if($application->announcement->event_date)
                                · Activity on {{ $application->announcement->event_date->format('M j, Y') }}
                            @endif
                        </div>
                        @if($application->attachments->isNotEmpty())
                            <div style="margin-top:8px; display:grid; gap:8px;">
                                @foreach($application->attachments as $doc)
                                    <div style="font-size:0.84rem; color:#334155">📄 {{ $doc->original_name }}</div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    @if(in_array($application->status, ['pending', 'approved']))
                        <form method="post" action="{{ route('participant.applications.withdraw', $application) }}"
                              data-confirm
                              data-confirm-title="Withdraw application?"
                              data-confirm-body="You'll be removed from {name}, and your slot will be released to other applicants."
                              data-confirm-name="{{ $application->announcement->title ?? 'this activity' }}"
                              data-confirm-note="You can apply again while slots remain."
                              data-confirm-ok="Withdraw"
                              data-confirm-cancel="Keep application">
                            @csrf
                            <button type="submit" style="background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; padding:9px 16px; border-radius:10px; font-weight:700; cursor:pointer;">Withdraw</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
@endsection
