@props(['user' => null])

@php
    $welcomeUser = $user ?? auth()->user();
    [$workspace, $welcomeMessage] = match ($welcomeUser?->role) {
        'celp_head' => ['CELP Head workspace', 'Oversee community extension projects, review proposals, and keep your team moving forward.'],
        'celp_secretary' => ['CELP Secretary workspace', 'Review incoming proposals, check requirements, and keep activity records organized.'],
        'coordinator' => ['CELP Coordinator workspace', 'Review endorsed proposals, coordinate activities, and track community project progress.'],
        'csa_director' => ['CSA Director workspace', 'Monitor organization activities, review compliance, and support your project initiators.'],
        'student_org' => ['Student Organization workspace', 'Bring your organization’s ideas to life. Manage proposals, activities, and volunteer participation.'],
        'college_department' => ['College / Department workspace', 'Plan community extension activities, track approvals, and manage your college or department’s projects.'],
        'participant' => ['ProjectLink community', 'Discover community activities and keep your participation on track.'],
        default => ['ProjectLink workspace', 'Stay connected with your community and keep track of your activities.'],
    };
    $welcomeName = trim((string) $welcomeUser?->first_name) ?: (trim((string) $welcomeUser?->name) ?: 'there');
    $welcomeOrganization = in_array($welcomeUser?->role, ['student_org', 'college_department'], true)
        ? ($welcomeUser?->organization_name ?: 'Holy Name University')
        : 'Holy Name University';
@endphp

@once
    <style>
        .dw-banner{position:relative;isolation:isolate;display:flex;align-items:center;justify-content:space-between;gap:24px;min-width:0;min-height:124px;box-sizing:border-box;padding:22px 26px;overflow:hidden;border:1px solid #d5e9dd;border-radius:16px;background:linear-gradient(105deg,#fbfdfb 0%,#f1faf5 58%,#e4f3eb 100%);box-shadow:0 3px 12px rgba(20,83,70,.035)}
        .dw-banner::before,.dw-banner::after{position:absolute;z-index:-1;content:'';pointer-events:none;border-radius:50%;transform:rotate(-20deg)}
        .dw-banner::before{width:390px;height:210px;right:-100px;bottom:-160px;background:linear-gradient(100deg,rgba(125,211,166,.22),rgba(25,125,87,.14))}
        .dw-banner::after{width:400px;height:200px;right:90px;bottom:-176px;border:1px solid rgba(35,143,95,.13);background:rgba(160,221,184,.1)}
        .dw-copy{min-width:0;max-width:710px}.dw-workspace{margin:0 0 7px;color:#08734e;font-size:.66rem;font-weight:700;letter-spacing:.1em;line-height:1.4;text-transform:uppercase}
        .dw-title{margin:0;color:#143d33;font-size:clamp(1.15rem,2vw,1.45rem);font-weight:700;letter-spacing:-.025em;line-height:1.35;overflow-wrap:anywhere}
        .dw-message{max-width:650px;margin:7px 0 0;color:#506c61;font-size:.83rem;line-height:1.6}
        .dw-meta{flex:0 0 auto;max-width:235px;padding-left:18px;border-left:1px solid #cce2d4;color:#5a7568;font-size:.72rem;line-height:1.6;text-align:right;overflow-wrap:anywhere}
        .dw-meta time{display:block;color:#2c5946;font-weight:600}.dw-meta span{display:block;margin-top:3px}
        @media(max-width:1000px){.dw-banner{padding:20px 22px;gap:18px}.dw-meta{max-width:180px;font-size:.68rem}.dw-message{font-size:.8rem}}
        @media(max-width:700px){.dw-banner{min-height:116px;padding:19px 20px;gap:0}.dw-copy{max-width:100%}.dw-meta{display:none}.dw-workspace{font-size:.63rem}.dw-title{font-size:1.22rem}.dw-message{font-size:.79rem}.dw-banner::before{right:-210px;bottom:-170px}.dw-banner::after{right:-40px}}
        @media(max-width:380px){.dw-banner{padding:17px;border-radius:14px}.dw-title{font-size:1.12rem}.dw-message{font-size:.76rem}}
    </style>
@endonce

<section {{ $attributes->class(['dw-banner']) }} aria-label="Welcome">
    <div class="dw-copy">
        <p class="dw-workspace">{{ $workspace }}</p>
        <h2 class="dw-title">Welcome back, {{ $welcomeName }}.</h2>
        <p class="dw-message">{{ $welcomeMessage }}</p>
    </div>
    <div class="dw-meta">
        <time datetime="{{ now()->toDateString() }}">{{ now()->format('D, M j, Y') }}</time>
        <span>{{ $welcomeOrganization }}</span>
    </div>
</section>
