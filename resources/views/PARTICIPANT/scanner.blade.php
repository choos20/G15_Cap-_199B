@extends('layouts.admin')

@section('title', 'Attendance Scanner')
@section('subtitle', 'Scan the organizer’s QR code on-site to check in or check out')

@section('content')
    <style>
        .qs-layout { display:grid; grid-template-columns:minmax(0,1.2fr) minmax(300px,0.8fr); gap:20px; align-items:start; }
        .qs-card { min-width:0; padding:24px; }
        .qs-guide { display:flex; flex-direction:column; gap:18px; }
        .qs-guide-card { padding:24px; }
        .qs-guide-title { margin:0; color:#0f172a; font-size:1.1rem; }
        .qs-steps { list-style:none; margin:20px 0 0; padding:0; display:flex; flex-direction:column; gap:18px; }
        .qs-step { display:flex; align-items:flex-start; gap:13px; }
        .qs-step-number { display:flex; align-items:center; justify-content:center; width:32px; height:32px; flex:0 0 32px; border-radius:11px; background:#ecfdf5; color:#08734e; font-weight:800; }
        .qs-step strong { display:block; color:#0f172a; font-size:0.94rem; }
        .qs-step p, .qs-guide-copy { margin:5px 0 0; color:#64748b; font-size:0.9rem; line-height:1.55; }
        .qs-help { padding:20px 22px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:14px; }
        .qs-help h4 { margin:0; color:#0f172a; font-size:0.98rem; }
        .qs-help ul { margin:12px 0 0; padding-left:19px; color:#475569; font-size:0.88rem; line-height:1.65; }
        .qs-browse-link { display:inline-flex; align-items:center; gap:6px; margin-top:18px; color:#08734e; font-size:0.9rem; font-weight:700; text-decoration:none; }
        .qs-browse-link:hover { text-decoration:underline; }
        .qs-video-frame {
            position:relative; width:100%; height:clamp(260px,38vh,340px); border-radius:24px; overflow:hidden;
            background:#0f172a; border:1px solid #e2e8f0;
        }
        .qs-video-frame video { width:100%; height:100%; object-fit:contain; background:#0f172a; }
        .qs-reticle {
            position:absolute; inset:12%; border:3px solid rgba(255,255,255,0.7); border-radius:20px;
            box-shadow:0 0 0 999px rgba(15,23,42,0.35); pointer-events:none;
        }
        .qs-idle { display:flex; align-items:center; justify-content:center; height:100%; color:#94a3b8; text-align:center; padding:24px; }
        .qs-status { margin-top:16px; padding:14px 16px; border-radius:14px; background:#f8fafc; border:1px solid #e2e8f0; color:#334155; font-size:0.92rem; }
        .qs-status.error { background:#fef2f2; border-color:#fecaca; color:#991b1b; }
        .qs-status.success { background:#ecfdf4; border-color:#d1fae5; color:#164e2f; }
        .qs-btn { margin-top:16px; padding:13px 22px; border-radius:12px; background:#0b6a4a; color:#fff; border:none; font-weight:700; cursor:pointer; }
        .qs-btn:disabled { opacity:0.6; cursor:progress; }
        .qs-divider { display:flex; align-items:center; gap:12px; margin:26px 0; color:#94a3b8; font-size:0.85rem; }
        .qs-divider::before, .qs-divider::after { content:''; flex:1; height:1px; background:#e2e8f0; }
        .qs-fallback input { width:100%; padding:13px 14px; border:1px solid #d1d5db; border-radius:12px; font:inherit; }
        .qs-fallback button { margin-top:10px; padding:12px 20px; border-radius:12px; background:#f8fafc; color:#0f172a; border:1px solid #cbd5e1; font-weight:700; cursor:pointer; }
        @media (max-width:960px) {
            .qs-layout { grid-template-columns:minmax(0,1fr) minmax(260px,0.8fr); }
        }
        @media (max-width:760px) {
            .qs-layout { grid-template-columns:minmax(0,1fr); }
        }
        @media (max-width:480px) {
            .qs-card, .qs-guide-card { padding:18px; }
            .qs-video-frame { border-radius:18px; }
            .qs-divider { gap:8px; font-size:0.78rem; }
        }
    </style>

    @if($errors->any())
        <div class="card" style="border-left:4px solid #dc2626; background:#fef2f2; color:#7f1d1d; padding:16px 20px; margin-bottom:20px">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="qs-layout">
        <section class="card qs-card" aria-labelledby="qs-scanner-title">
            <h3 id="qs-scanner-title" style="margin-top:0">Scan a session QR code</h3>
            <p style="color:#64748b; margin-bottom:18px">Point your camera at the QR code the organizer is displaying. Once it opens the attendance page, choose <strong>Check in</strong> when you arrive or <strong>Check out</strong> when you leave.</p>

            <div class="qs-video-frame" id="qs-frame">
                <div class="qs-idle" id="qs-idle">Your camera is off. Allow camera access when prompted to scan the QR code.</div>
                <video id="qs-video" playsinline muted disablepictureinpicture disableremoteplayback style="display:none"></video>
                <div class="qs-reticle" id="qs-reticle" style="display:none"><i></i><i></i><i></i><i></i><span></span></div>
            </div>
            <canvas id="qs-canvas" style="display:none"></canvas>


            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <button type="button" class="qs-btn" id="qs-start">Start Scanning</button>
                <button type="button" class="qs-btn qs-secondary" id="qs-stop" style="display:none">Stop</button><button type="button" class="qs-btn" id="qs-switch" style="display:none; background:#f8fafc; color:#0f172a; border:1px solid #cbd5e1;">Switch camera</button>
            </div>
            <div class="qs-status" id="qs-status" role="status" aria-live="polite">Start scanning when ready. Your browser will ask for camera access.</div><div id="qs-camera-list" style="margin-top:10px; font-size:0.85rem; color:#94a3b8;"></div>

            <div class="qs-divider">or paste the link / code instead</div>

            <form method="GET" action="{{ route('participant.scanner.resolve') }}" class="qs-fallback">
                <label for="qs-code">Attendance link or code</label><input id="qs-code" type="text" name="code" placeholder="Paste the scan link or code here" required>
                <button type="submit">Continue</button>
            </form>
        </section>

        <aside class="qs-guide" aria-label="Check-in guidance">
            <details class="card qs-guide-card"><summary class="qs-guide-title">How to scan</summary>

                <p class="qs-guide-copy">Use the same activity QR code to check in when you arrive and check out when you leave.</p>
                <ol class="qs-steps">
                    <li class="qs-step">
                        <span class="qs-step-number" aria-hidden="true">1</span>
                        <div><strong>Open the organizer's QR code</strong><p>Ask the organizer to display the attendance code for your session.</p></div>
                    </li>
                    <li class="qs-step">
                        <span class="qs-step-number" aria-hidden="true">2</span>
                        <div><strong>Scan the code</strong><p>Select “Start Scanning” and hold your camera steady over the QR code.</p></div>
                    </li>
                    <li class="qs-step">
                        <span class="qs-step-number" aria-hidden="true">3</span>
                        <div><strong>Choose your attendance action</strong><p>Choose check-in when you arrive or check-out when you leave, then allow location access if prompted.</p></div>
                    </li>
                </ol></details>

            <section class="qs-help" aria-labelledby="qs-help-title">
                <h4 id="qs-help-title">Having trouble?</h4>
                <ul>
                    <li>Allow camera access when your browser asks.</li>
                    <li>Make sure the QR code is clear and well lit.</li>
                    <li>Use the paste field if your camera is unavailable.</li>
                    <li>Check-in may ask for your location to confirm you're on-site.</li>
                </ul>
                <a class="qs-browse-link" href="{{ route('participant.activities') }}">Browse activities</a>
            </section>
        </aside>
    </div>

    <style>
    .qs-layout{max-width:1120px;margin:auto}.qs-card>p{font-size:.9rem;line-height:1.5}.qs-reticle{inset:auto;top:50%;left:50%;width:210px;height:210px;transform:translate(-50%,-50%);border:0;border-radius:16px}.qs-reticle i{position:absolute;width:32px;height:32px;border:4px solid #34d399}.qs-reticle i:nth-child(1){top:0;left:0;border-right:0;border-bottom:0;border-radius:12px 0 0}.qs-reticle i:nth-child(2){top:0;right:0;border-left:0;border-bottom:0;border-radius:0 12px 0 0}.qs-reticle i:nth-child(3){bottom:0;left:0;border-right:0;border-top:0;border-radius:0 0 0 12px}.qs-reticle i:nth-child(4){bottom:0;right:0;border-left:0;border-top:0;border-radius:0 0 12px}.qs-reticle span{position:absolute;top:10px;left:8px;right:8px;height:2px;background:#6ee7b7;box-shadow:0 0 14px #34d399;animation:qs-sweep 2.5s ease-in-out infinite alternate}@keyframes qs-sweep{to{transform:translateY(188px)}}#qs-start{width:100%;font-size:1rem;box-shadow:0 6px 16px #08734e22}.qs-secondary{background:#f8fafc;color:#334155;border:1px solid #cbd5e1}.qs-status.busy:before{content:'';display:inline-block;width:12px;height:12px;border:2px solid #a7f3d0;border-top-color:#047857;border-radius:50%;margin-right:8px;animation:qs-spin .8s linear infinite}@keyframes qs-spin{to{transform:rotate(360deg)}}.qs-guide summary{cursor:pointer;font-weight:700}.qs-idle{box-sizing:border-box;max-width:320px;margin:auto;line-height:1.6}.qs-fallback label{display:block;font-size:.85rem;margin-bottom:8px}.qs-fallback input{box-sizing:border-box}@media(prefers-reduced-motion:reduce){.qs-reticle span,.qs-status.busy:before{animation:none}}@media(max-width:760px){.qs-card{padding:18px}.qs-guide{gap:12px}.qs-guide-card{padding:18px}}
    </style>
    <script src="https://unpkg.com/jsqr@1.4.0/dist/jsQR.js" crossorigin="anonymous"></script>
    <script src="{{ asset('js/attendance-scanner.js') }}?v=2" data-resolve="{{ route('participant.scanner.resolve') }}" defer></script>
@endsection