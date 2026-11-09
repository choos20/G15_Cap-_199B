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
            position:relative; width:100%; aspect-ratio:4/3; border-radius:24px; overflow:hidden;
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
                <div class="qs-idle" id="qs-idle">Camera preview will appear here.</div>
                <video id="qs-video" playsinline muted disablepictureinpicture disableremoteplayback style="display:none"></video>
                <div class="qs-reticle" id="qs-reticle" style="display:none"></div>
            </div>
            <canvas id="qs-canvas" style="display:none"></canvas>

            <div class="qs-status" id="qs-status" role="status" aria-live="polite">Tap "Start scanner" to allow camera access.</div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <button type="button" class="qs-btn" id="qs-start">Start scanner</button>
                <button type="button" class="qs-btn" id="qs-switch" style="display:none; background:#f8fafc; color:#0f172a; border:1px solid #cbd5e1;">Switch camera</button>
            </div>
            <div id="qs-camera-list" style="margin-top:10px; font-size:0.85rem; color:#94a3b8;"></div>

            <div class="qs-divider">or paste the link / code instead</div>

            <form method="GET" action="{{ route('participant.scanner.resolve') }}" class="qs-fallback">
                <input type="text" name="code" placeholder="Paste the scan link or code here" required>
                <button type="submit">Continue</button>
            </form>
        </section>

        <aside class="qs-guide" aria-label="Check-in guidance">
            <section class="card qs-guide-card">
                <h3 class="qs-guide-title">Before you scan</h3>
                <p class="qs-guide-copy">Use the same activity QR code to check in when you arrive and check out when you leave.</p>
                <ol class="qs-steps">
                    <li class="qs-step">
                        <span class="qs-step-number" aria-hidden="true">1</span>
                        <div><strong>Open the organizer's QR code</strong><p>Ask the organizer to display the attendance code for your session.</p></div>
                    </li>
                    <li class="qs-step">
                        <span class="qs-step-number" aria-hidden="true">2</span>
                        <div><strong>Scan the code</strong><p>Select “Start scanner” and hold your camera steady over the QR code.</p></div>
                    </li>
                    <li class="qs-step">
                        <span class="qs-step-number" aria-hidden="true">3</span>
                        <div><strong>Choose your attendance action</strong><p>Choose check-in when you arrive or check-out when you leave, then allow location access if prompted.</p></div>
                    </li>
                </ol>
            </section>

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

    <script src="https://unpkg.com/jsqr@1.4.0/dist/jsQR.js" integrity="sha256-vEDIoVGWI2sjFNsIVvcsoLSZgM1UE7jIUqc0n1/uCFk=" crossorigin=""></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const startBtn = document.getElementById('qs-start');
            const switchBtn = document.getElementById('qs-switch');
            const cameraListEl = document.getElementById('qs-camera-list');
            const statusEl = document.getElementById('qs-status');
            const idleEl = document.getElementById('qs-idle');
            const video = document.getElementById('qs-video');
            const reticle = document.getElementById('qs-reticle');
            const canvas = document.getElementById('qs-canvas');
            const ctx = canvas.getContext('2d', { willReadFrequently: true });

            let stream = null;
            let rafId = null;
            let scanning = false;
            let cameras = [];
            let cameraIndex = 0;

            // Chrome can auto-pop an active camera stream into a floating
            // Picture-in-Picture window when the tab loses focus. The video
            // and its attributes opt out of that, but force it back inline
            // if it ever slips through anyway.
            video.addEventListener('enterpictureinpicture', () => {
                if (document.pictureInPictureElement) {
                    document.exitPictureInPicture().catch(() => {});
                }
            });

            const setStatus = (text, kind) => {
                statusEl.textContent = text;
                statusEl.className = 'qs-status' + (kind ? ' ' + kind : '');
            };

            const tokenPattern = /([a-zA-Z0-9]{40})/;

            const stopScanning = () => {
                if (rafId) cancelAnimationFrame(rafId);
                if (stream) stream.getTracks().forEach((track) => track.stop());
                rafId = null;
                stream = null;
                scanning = false;
                idleEl.style.display = 'flex';
                video.style.display = 'none';
                reticle.style.display = 'none';
                switchBtn.style.display = 'none';
                cameraListEl.textContent = '';
                startBtn.textContent = 'Start scanner';
                startBtn.disabled = false;
            };

            const tick = () => {
                if (video.readyState === video.HAVE_ENOUGH_DATA) {
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                    const code = window.jsQR ? jsQR(imageData.data, imageData.width, imageData.height) : null;

                    if (code && code.data) {
                        const match = code.data.match(tokenPattern);
                        if (match) {
                            setStatus('QR code recognized. Opening check-in...', 'success');
                            stopScanning();
                            window.location.href = '{{ route("participant.scanner.resolve") }}?code=' + encodeURIComponent(match[1]);
                            return;
                        }
                    }
                }
                if (scanning) rafId = requestAnimationFrame(tick);
            };

            // Labels are only populated by the browser after permission has
            // been granted once, so this is called (and re-called) after a
            // successful getUserMedia to build the switchable camera list.
            const refreshCameraList = async () => {
                if (!navigator.mediaDevices.enumerateDevices) return;
                const devices = await navigator.mediaDevices.enumerateDevices();
                cameras = devices.filter((d) => d.kind === 'videoinput');
                switchBtn.style.display = cameras.length > 1 ? 'inline-flex' : 'none';
                cameraListEl.textContent = cameras.length
                    ? cameras.length + ' camera' + (cameras.length === 1 ? '' : 's') + ' found: ' + cameras.map((c) => c.label || 'unnamed camera').join(', ')
                    : '';
            };

            const openStream = async (constraints) => {
                if (stream) stream.getTracks().forEach((track) => track.stop());
                stream = await navigator.mediaDevices.getUserMedia(constraints);

                video.srcObject = stream;
                video.muted = true;
                if ('disablePictureInPicture' in video) video.disablePictureInPicture = true;
                await video.play();

                // Wait for real frame dimensions before scanning — drawing a
                // 0x0 video to the canvas would look like a black box.
                if (!video.videoWidth) {
                    await new Promise((resolve) => video.addEventListener('loadedmetadata', resolve, { once: true }));
                }

                const track = stream.getVideoTracks()[0];
                const label = track ? (track.label || 'camera') : 'camera';

                idleEl.style.display = 'none';
                video.style.display = 'block';
                reticle.style.display = 'block';
                setStatus('Using ' + label + ' (' + video.videoWidth + '×' + video.videoHeight + '). Point it at the QR code.');

                await refreshCameraList();

                // Keep cameraIndex pointed at whichever device is actually active.
                if (track && cameras.length) {
                    const settings = track.getSettings ? track.getSettings() : {};
                    const activeIndex = cameras.findIndex((c) => c.deviceId === settings.deviceId);
                    if (activeIndex !== -1) cameraIndex = activeIndex;
                }
            };

            startBtn.addEventListener('click', async () => {
                if (scanning) {
                    stopScanning();
                    setStatus('Scanner stopped.');
                    return;
                }

                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    setStatus('Camera access is not supported on this browser. Use the paste option below instead.', 'error');
                    return;
                }

                startBtn.disabled = true;
                setStatus('Requesting camera access...');

                // Prefer the rear camera (phones), but most laptops only have
                // a front webcam — fall back to "any camera" instead of failing.
                try {
                    try {
                        await openStream({ video: { facingMode: { ideal: 'environment' } }, audio: false });
                    } catch (firstError) {
                        await openStream({ video: true, audio: false });
                    }
                } catch (error) {
                    setStatus('Could not access any camera (' + (error.name || 'error') + ': ' + (error.message || 'unknown') + '). Check your browser/OS camera permissions, or use the paste option below.', 'error');
                    startBtn.disabled = false;
                    return;
                }

                startBtn.textContent = 'Stop scanner';
                startBtn.disabled = false;
                scanning = true;
                rafId = requestAnimationFrame(tick);
            });

            switchBtn.addEventListener('click', async () => {
                if (!cameras.length) return;
                switchBtn.disabled = true;
                cameraIndex = (cameraIndex + 1) % cameras.length;
                const nextDeviceId = cameras[cameraIndex].deviceId;

                try {
                    await openStream({ video: { deviceId: { exact: nextDeviceId } }, audio: false });
                    if (!scanning) {
                        scanning = true;
                        rafId = requestAnimationFrame(tick);
                    }
                } catch (error) {
                    setStatus('Could not switch to that camera (' + (error.name || 'error') + '). Try again or use the paste option below.', 'error');
                }
                switchBtn.disabled = false;
            });

            document.addEventListener('visibilitychange', () => {
                if (document.hidden) stopScanning();
            });
        });
    </script>
@endsection
