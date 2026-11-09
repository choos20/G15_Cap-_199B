@extends('layouts.admin')

@section('title', 'Project Submissions')
@section('subtitle', 'Review submissions')

@section('content')
    @php
        $queueTitles = [
            'secretary' => 'Pending project submissions',
            'coordinator' => 'Projects endorsed by CELP Secretary',
            'celp_head' => 'Projects endorsed by CELP Coordinator',
            'csa_director' => 'Projects approved by CELP Head',
        ];
        $queueBlurbs = [
            'secretary' => 'Check baseline requirements before endorsing a proposal to the coordinator.',
            'coordinator' => 'Second-check proposals already cleared by the CELP Secretary.',
            'celp_head' => 'Give final CELP clearance before a proposal moves to CSA Director review.',
            'csa_director' => 'Give final compliance clearance so the activity can proceed.',
        ];
        $queueKey = is_string($for ?? null) ? $for : 'secretary';
    @endphp

    @if(session('status'))
        <div class="card" style="border-left:4px solid #16a34a; background:#ecfdf5; color:#134e4a; padding:16px 20px; margin-bottom:20px">
            {{ session('status') }}
        </div>
    @endif

    <div class="card" style="padding:24px; margin-bottom:20px">
        <div style="display:flex; align-items:center; gap:14px">
            <div style="width:48px; height:48px; border-radius:16px; display:flex; align-items:center; justify-content:center; background:#eff6ff; color:#1d4ed8; flex-shrink:0;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 4h7l3 3h6v13H4V4z" fill="currentColor"/></svg>
            </div>
            <div>
                <h3 style="margin:0">{{ $queueTitles[$queueKey] ?? 'Pending project submissions' }}</h3>
                <div style="color:#64748b; margin-top:4px">{{ $queueBlurbs[$queueKey] ?? '' }}</div>
            </div>
            <div style="margin-left:auto; background:#f1f5f9; color:#334155; font-weight:700; padding:8px 16px; border-radius:999px; font-size:0.9rem">
                {{ $projects->count() }} {{ \Illuminate\Support\Str::plural('submission', $projects->count()) }}
            </div>
        </div>
    </div>

    @if($projects->isEmpty())
        <div class="card" style="padding:40px; text-align:center; color:#64748b">
            <div style="font-size:2rem; margin-bottom:10px">📭</div>
            Nothing is waiting in this queue right now.
        </div>
    @else
        <div style="display:grid; gap:14px">
            @foreach($projects as $p)
                <div class="card" style="display:flex; justify-content:space-between; align-items:center; gap:18px; padding:20px 22px; flex-wrap:wrap">
                    <div style="flex:1; min-width:260px">
                        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap">
                            <div style="font-weight:700; font-size:1.05rem; color:#0f172a">{{ $p->title }}</div>
                            <span style="font-size:0.78rem; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; padding:4px 10px; border-radius:999px; background:{{ $p->attachments_count > 0 ? '#dcfce7' : '#fef3c7' }}; color:{{ $p->attachments_count > 0 ? '#166534' : '#92400e' }}">
                                {{ $p->attachments_count }} {{ \Illuminate\Support\Str::plural('file', $p->attachments_count) }}
                            </span>
                        </div>
                        @if($p->description)
                            <div style="color:#475569; margin-top:8px; max-width:640px; overflow:hidden; text-overflow:ellipsis; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;">{{ $p->description }}</div>
                        @endif
                        <div style="font-size:0.88rem; color:#64748b; margin-top:10px; display:flex; gap:14px; flex-wrap:wrap">
                            <span style="display:inline-flex; align-items:center; gap:8px">
                                <x-organization-avatar :organization="$p->creator" :size="28" />
                                {{ $p->creator->organization_name ?? $p->creator->name ?? '—' }}
                            </span>
                            <span>🕒 Submitted {{ $p->created_at->diffForHumans() }}</span>
                        </div>
                    </div>
                    <a href="{{ route('projects.show_review', $p->id) }}" style="flex-shrink:0; display:inline-flex; align-items:center; gap:6px; padding:12px 20px; border-radius:12px; background:#0b6a4a; color:#fff; text-decoration:none; font-weight:700">
                        Open
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </a>
                </div>
            @endforeach
        </div>
    @endif
@endsection
