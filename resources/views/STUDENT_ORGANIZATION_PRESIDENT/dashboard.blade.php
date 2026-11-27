@extends('layouts.admin')

@section('title', auth()->user()->role === 'college_department' ? 'College / Department Dashboard' : 'Student Organization Dashboard')
@section('subtitle','Manage your organization activities and approvals')

@section('content')
    @if(session('proposal_success'))
        @include('partials.proposal-success-modal', ['success' => session('proposal_success')])
    @endif

    @php
        $organizationWorkflowLinks = [
            'secretary' => route('student_org.proposals', ['status' => 'submitted']),
            'coordinator' => route('student_org.proposals', ['status' => 'endorsed_by_secretary']),
            'celp_head' => route('student_org.proposals', ['status' => 'endorsed_by_coordinator']),
            'csa' => route('student_org.proposals', ['status' => 'approved']),
            'approved' => route('student_org.proposals', ['status' => 'csa_cleared']),
            'returned' => route('student_org.proposals', ['status_group' => 'returned']),
        ];
        $statusMeta = $workflowStages;
        $statusSum = $workflowTotal;
    @endphp

    <style>
        .org-dashboard{display:grid;gap:18px}
        .org-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
        .org-stat{display:flex;align-items:center;gap:13px;min-width:0;padding:17px;border:1px solid #e5eaf0;border-radius:14px;background:#fff}
        .org-stat-icon{display:flex;align-items:center;justify-content:center;width:44px;height:44px;flex:0 0 44px;border:1px solid #dce9e3;border-radius:13px;background:linear-gradient(145deg,#f4fbf7,#e8f5ee);color:#08734e}
        .org-stat-icon.is-review{border-color:#f2e0bd;background:linear-gradient(145deg,#fffaf0,#fff2d9);color:#a16207}
        .org-stat-icon.is-returned{border-color:#f2d6d6;background:linear-gradient(145deg,#fff7f7,#feecec);color:#b42318}
        .org-stat-icon.is-approved{border-color:#d3e9dc;background:linear-gradient(145deg,#f2fbf5,#e5f5eb);color:#08734e}
        .org-stat-icon svg{width:22px;height:22px}
        .org-stat-label{color:#52665d;font-size:.8rem;font-weight:700;line-height:1.35}
        .org-stat-value{margin-top:4px;color:#102e2a;font-size:1.55rem;font-weight:800;line-height:1.1}
        .org-chart-layout{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(280px,.95fr);gap:18px;align-items:stretch}
        .org-chart-panel{min-width:0;padding:20px;border:1px solid #e8edf0;border-radius:15px;background:#fff;box-shadow:0 5px 16px rgba(11,42,42,.04)}
        .org-chart-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:8px}
        .org-chart-title{display:flex;align-items:center;gap:9px;margin:0;color:#123d33;font-size:1rem}
        .org-chart-icon{display:flex;align-items:center;justify-content:center;width:34px;height:34px;flex:0 0 34px;border-radius:11px;background:#edf6f3;color:#087f67}
        .org-chart-period{color:#52665d;font-size:.72rem;white-space:nowrap}
        .org-status-dot{width:9px;height:9px;flex:0 0 9px;border-radius:50%}
        .org-status-count{color:#183b32;font-size:.82rem;font-weight:800;font-variant-numeric:tabular-nums}
        .org-status-percent{min-width:30px;color:#7b8d84;font-size:.67rem;text-align:right}
        .org-content-grid{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(290px,.85fr);gap:18px;align-items:start}
        .org-stack{display:grid;gap:18px}
        .org-panel{min-width:0;padding:20px;border:1px solid #e8edf0;border-radius:15px;background:#fff;box-shadow:0 5px 16px rgba(11,42,42,.04)}
        .org-panel-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:7px}
        .org-panel-head h3{margin:0;color:#123d33;font-size:1rem}
        .org-panel-link{color:#08734e;font-size:.82rem;font-weight:700;text-decoration:none;white-space:nowrap}
        .org-panel-link:hover,.org-template-link:hover{text-decoration:underline}
        .org-panel-description{margin:0 0 16px;color:#52665d;font-size:.84rem;line-height:1.5}
        .org-pipeline{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:9px}
        .org-pipeline-step{min-width:0;padding:13px;border:1px solid #e6edf0;border-radius:11px;background:#fbfdfc}
        .org-pipeline-label{min-height:34px;color:#64756e;font-size:.76rem;font-weight:700;line-height:1.4}
        .org-pipeline-count{margin-top:5px;color:#123d33;font-size:1.35rem;font-weight:800}
        .org-pipeline-note{margin-top:14px;padding:11px 13px;border-radius:10px;background:#fff8f4;color:#854d0e;font-size:.82rem;line-height:1.5}
        .org-actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}
        .org-action{display:flex;align-items:center;gap:10px;min-height:66px;padding:11px;border:1px solid #e6edf0;border-radius:11px;color:#203b34;text-decoration:none;font-size:.82rem;font-weight:700;line-height:1.35;transition:background 160ms ease,border-color 160ms ease,transform 160ms ease}
        .org-action:hover{transform:translateY(-1px);border-color:#b9ddca;background:#f7fcf8}
        .org-action-icon{display:flex;align-items:center;justify-content:center;width:36px;height:36px;flex:0 0 36px;border-radius:11px;background:#e8f6ee;color:#08734e}
        .org-action-icon svg{width:19px;height:19px}
        .org-templates{display:grid;gap:11px;margin:0;padding:0;list-style:none}
        .org-template{min-width:0}
        .org-template-link{display:block;overflow:hidden;color:#176b50;font-size:.84rem;font-weight:700;text-decoration:none;text-overflow:ellipsis;white-space:nowrap}
        .org-template-meta{margin-top:4px;color:#87938e;font-size:.74rem}
        .org-empty{padding:14px;border:1px dashed #dbe7e1;border-radius:10px;background:#f8fbf9;color:#64756e;font-size:.84rem;line-height:1.5}
        .org-latest{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:18px;align-items:center;padding:18px;border:1px solid #e6edf0;border-radius:12px;background:#fbfdfc}
        .org-latest-title{color:#17362e;font-size:1.08rem;font-weight:800;overflow-wrap:anywhere}
        .org-latest-meta{margin-top:7px;color:#52665d;font-size:.82rem;line-height:1.5}
        .org-status{display:inline-flex;align-items:center;margin-top:10px;padding:5px 9px;border:1px solid #dce9e3;border-radius:999px;background:#f0f8f3;color:#176b50;font-size:.75rem;font-weight:750}
        .org-next-step{margin-top:12px;color:#435850;font-size:.86rem;line-height:1.55}
        .org-latest-action{display:inline-flex;align-items:center;justify-content:center;padding:9px 12px;border-radius:9px;background:#0b7656;color:#fff;font-size:.81rem;font-weight:700;text-decoration:none;white-space:nowrap}
        .org-latest-action:hover{background:#086247}
        @media(max-width:1050px){.org-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.org-chart-layout,.org-content-grid{grid-template-columns:minmax(0,1fr)}}
        @media(max-width:620px){.org-stats,.org-pipeline{grid-template-columns:repeat(2,minmax(0,1fr))}.org-panel,.org-chart-panel{padding:16px}.org-status-overview{grid-template-columns:115px minmax(0,1fr);gap:8px}.org-donut{width:115px;height:115px}.org-status-legend-row{grid-template-columns:minmax(0,1fr) auto}.org-status-percent{display:none}.org-latest{grid-template-columns:minmax(0,1fr)}.org-latest-action{justify-self:start}}
        @media(max-width:380px){.org-stats,.org-actions,.org-pipeline{grid-template-columns:minmax(0,1fr)}}
    </style>

    <div class="org-dashboard">
        @if(session('status'))
            <div class="org-panel" style="border-left:4px solid #16a34a;background:#ecfdf5;color:#134e4a">
                <strong style="display:block;margin-bottom:5px">ProjectLink update</strong>
                <div>{{ session('status') }}</div>
            </div>
        @endif

        <x-dashboard-welcome />

        <section class="org-stats" aria-label="Proposal summary">
            <article class="org-stat">
                <div class="org-stat-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M5 4h14v16H5zM8 8h8M8 12h8M8 16h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
                <div><div class="org-stat-label">Total proposals</div><div class="org-stat-value" data-count-up="{{ $totalProposals }}" aria-label="{{ $totalProposals }}">{{ $totalProposals }}</div></div>
            </article>
            <article class="org-stat">
                <div class="org-stat-icon is-review" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M12 7v5l3 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
                <div><div class="org-stat-label">In review</div><div class="org-stat-value" data-count-up="{{ $projectsInReview }}" aria-label="{{ $projectsInReview }}">{{ $projectsInReview }}</div></div>
            </article>
            <article class="org-stat">
                <div class="org-stat-icon is-returned" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M9 14 4 9l5-5M4 9h9a7 7 0 1 1 0 14h-2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
                <div><div class="org-stat-label">Returned for updates</div><div class="org-stat-value" data-count-up="{{ $projectsReturned }}" aria-label="{{ $projectsReturned }}">{{ $projectsReturned }}</div></div>
            </article>
            <article class="org-stat">
                <div class="org-stat-icon is-approved" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="m8 12 2.5 2.5L16 9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
                <div><div class="org-stat-label">Fully approved</div><div class="org-stat-value" data-count-up="{{ $approvedActivities }}" aria-label="{{ $approvedActivities }}">{{ $approvedActivities }}</div></div>
            </article>
        </section>

        <div class="org-chart-layout">
            <x-analytics.columns title="Monthly proposal activity" description="Monitor your organization’s proposal activity" :data="$projectTrend" :series="[['key'=>'approved','label'=>'Approved','color'=>'#078d70'],['key'=>'pending','label'=>'In review','color'=>'#c98208'],['key'=>'returned','label'=>'Returned','color'=>'#d94e74']]" :record-url="route('student_org.proposals')" />

            <x-analytics.radial title="Proposal status breakdown" description="Your organization’s current proposal statuses" :items="$workflowStages" total-label="Proposals" :links="$organizationWorkflowLinks" />
        </div>

        <div class="org-content-grid">
            <div class="org-stack">
                <section class="org-panel">
                    <div class="org-panel-head">
                        <h3>Proposal pipeline</h3>
                        <a class="org-panel-link" href="{{ route('student_org.proposals') }}">View my proposals</a>
                    </div>
                    <p class="org-panel-description">See how many proposals are currently waiting at each review stage.</p>
                    <x-analytics.pipeline :stages="collect($workflowStages)->take(4)->all()" :total="$projectsInReview" :links="$organizationWorkflowLinks" />
                    @if($projectsReturned > 0)
                        <div class="org-pipeline-note">{{ $projectsReturned }} {{ \Illuminate\Support\Str::plural('proposal', $projectsReturned) }} {{ $projectsReturned === 1 ? 'needs' : 'need' }} updates. Check My proposals for reviewer feedback.</div>
                    @endif
                </section>

                <section class="org-panel">
                    <div class="org-panel-head"><h3>Latest proposal</h3></div>
                    @if($latestProject)
                        <div class="org-latest">
                            <div>
                                <div class="org-latest-title">{{ $latestProject->title ?? 'Untitled proposal' }}</div>
                                <div class="org-latest-meta">Updated {{ $latestProject->updated_at->diffForHumans() }} · {{ $latestProject->updated_at->format('M j, Y') }}</div>
                                <span class="org-status">{{ $latestProjectStatusLabel }}</span>
                                <div class="org-next-step"><strong>Next step:</strong> {{ $latestProjectNextStep }}</div>
                            </div>
                            <a class="org-latest-action" href="{{ route('student_org.proposals') }}">Open proposals</a>
                        </div>
                    @else
                        <div class="org-empty">You have not submitted a proposal yet. Create one to start tracking its CELP and CSA review progress.</div>
                        <a class="org-latest-action" style="margin-top:12px" href="{{ route('projects.create') }}">Create proposal</a>
                    @endif
                </section>
            </div>

            <aside class="org-stack">
                <section class="org-panel">
                    <div class="org-panel-head"><h3>Quick actions</h3></div>
                    <div class="org-actions">
                        <a class="org-action" href="{{ route('projects.create') }}"><span class="org-action-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>Create proposal</a>
                        <a class="org-action" href="{{ route('student_org.proposals') }}"><span class="org-action-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M5 5h14v14H5zM8 9h8M8 13h8M8 16h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>My proposals</a>
                        <a class="org-action" href="{{ route('templates.index') }}"><span class="org-action-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M6 3h9l5 5v13H6V3zm8 1v5h5M9 13h6m-6 4h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Forms &amp; requirements</a>
                        <a class="org-action" href="{{ route('templates.index') }}"><span class="org-action-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M6 4h12v16H6zM9 8h6M9 12h6M9 16h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Browse forms</a>
                    </div>
                </section>

                <section class="org-panel">
                    <div class="org-panel-head">
                        <h3>Recent forms</h3>
                        <a class="org-panel-link" href="{{ route('templates.index') }}">View all</a>
                    </div>
                    @if($templates->isEmpty())
                        <div class="org-empty">No forms are currently available.</div>
                    @else
                        <ul class="org-templates">
                            @foreach($templates as $template)
                                <li class="org-template">
                                    <a class="org-template-link" href="{{ route('templates.download', $template->id) }}">{{ $template->original_name }}</a>
                                    <div class="org-template-meta">
                                        {{ $template->uploader ? ($template->uploader->role === 'celp_secretary' ? 'CELP Secretary' : ($template->uploader->role === 'celp_head' ? 'CELP Head' : $template->uploader->name)) : 'ProjectLink' }}
                                        · {{ $template->created_at->format('M j, Y') }}
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </aside>
        </div>
    </div>
@endsection
