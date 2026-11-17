(() => {
    const currentScript = document.currentScript;
    const resolveUrl = currentScript?.dataset.resolve;
    const el = (id) => document.getElementById(`qs-${id}`);
    const start = el('start');
    const stop = el('stop');
    const swap = el('switch');
    const video = el('video');
    const videoFrame = el('frame');
    const detectedOverlay = el('detected');
    const canvas = el('canvas');
    const ctx = canvas.getContext('2d', { willReadFrequently: true });

    let stream;
    let animationFrame;
    let generation = 0;
    let cameras = [];
    let activeDevice;
    let busy = false;
    let lastFrame = 0;

    const setStatus = (text, kind = '') => {
        el('status').textContent = text;
        el('status').className = `qs-status ${kind}`;
    };

    const resetDetectionState = () => {
        videoFrame?.classList.remove('is-detected');
        detectedOverlay?.setAttribute('aria-hidden', 'true');
    };

    const shutdown = ({ keepDetection = false } = {}) => {
        generation += 1;
        cancelAnimationFrame(animationFrame);
        stream?.getTracks().forEach((track) => track.stop());
        stream = null;
        video.srcObject = null;
        video.style.display = 'none';
        el('reticle').style.display = 'none';
        stop.style.display = 'none';
        swap.style.display = 'none';
        el('idle').style.display = 'flex';
        start.disabled = false;
        start.textContent = 'Start Scanning';
        busy = false;
        if (!keepDetection) resetDetectionState();
    };

    const tick = (time) => {
        if (!stream) return;

        if (video.readyState >= 2 && time - lastFrame > 130) {
            lastFrame = time;
            const scale = Math.min(1, 800 / video.videoWidth);
            canvas.width = Math.round(video.videoWidth * scale);
            canvas.height = Math.round(video.videoHeight * scale);
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            const pixels = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const code = window.jsQR(pixels.data, pixels.width, pixels.height);

            if (code?.data) {
                shutdown({ keepDetection: true });
                videoFrame?.classList.add('is-detected');
                detectedOverlay?.setAttribute('aria-hidden', 'false');
                setStatus('QR code captured - validating attendance...', 'success');
                start.disabled = true;
                navigator.vibrate?.(70);

                const target = new URL(resolveUrl, location.origin);
                target.searchParams.set('code', code.data);
                window.setTimeout(() => location.assign(target.href), 450);
                return;
            }
        }

        animationFrame = requestAnimationFrame(tick);
    };

    const open = async (deviceId) => {
        if (busy) return;

        shutdown();
        busy = true;
        const request = generation;
        start.disabled = true;
        stop.style.display = 'inline-flex';
        setStatus('Allow camera access in your browser to start scanning.', 'busy');

        try {
            if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
                throw new Error('Camera access requires HTTPS and a supported browser. You can paste the code below.');
            }
            if (!window.jsQR) {
                throw new Error('The QR reader could not load. Refresh this page or use the paste option below.');
            }

            const next = await navigator.mediaDevices.getUserMedia({
                video: deviceId ? { deviceId: { exact: deviceId } } : { facingMode: { ideal: 'environment' } },
                audio: false,
            });

            if (request !== generation) {
                next.getTracks().forEach((track) => track.stop());
                return;
            }

            stream = next;
            video.srcObject = stream;
            video.style.display = 'block';
            el('idle').style.display = 'none';
            await video.play();
            if (request !== generation) return;

            activeDevice = stream.getVideoTracks()[0]?.getSettings().deviceId;
            try {
                cameras = (await navigator.mediaDevices.enumerateDevices()).filter((device) => device.kind === 'videoinput');
            } catch {
                cameras = [];
            }

            if (request !== generation) return;
            el('reticle').style.display = 'block';
            swap.style.display = cameras.length > 1 ? 'inline-flex' : 'none';
            start.textContent = 'Scanning...';
            busy = false;
            setStatus('Position the QR code inside the green frame.');
            animationFrame = requestAnimationFrame(tick);
        } catch (error) {
            if (request !== generation) return;

            shutdown();
            const messages = {
                NotAllowedError: 'Camera access was denied. Allow it in browser settings, then try again.',
                NotFoundError: 'No camera found. Connect a camera or paste the code.',
                NotReadableError: 'The camera may be in use. Close other camera apps and try again.',
            };
            setStatus(messages[error.name] || error.message || 'Could not start the camera.', 'error');
        }
    };

    start.addEventListener('click', () => open());
    stop.addEventListener('click', () => {
        shutdown();
        setStatus('Camera stopped. Start scanning whenever you are ready.');
    });
    swap.addEventListener('click', () => {
        const index = cameras.findIndex((camera) => camera.deviceId === activeDevice);
        open(cameras[(index + 1) % cameras.length]?.deviceId);
    });
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            shutdown();
            setStatus('Camera paused. Start scanning to continue.');
        }
    });
    window.addEventListener('pagehide', () => shutdown());
})();
