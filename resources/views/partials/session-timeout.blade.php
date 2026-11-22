@auth
<style>
    .idle-dialog { width:min(440px, calc(100% - 32px)); padding:26px; border:1px solid #d8eee5; border-radius:16px; color:#0f172a; box-shadow:0 20px 60px rgba(0,0,0,.25); font-family:inherit; }
    .idle-dialog::backdrop { background:rgba(4,31,27,.6); }
    .idle-dialog h2 { margin:0 0 10px; font-size:1.2rem; }
    .idle-dialog p { color:#526577; line-height:1.5; }
    .idle-countdown { display:block; margin:18px 0; text-align:center; color:#087c5b; font-size:2rem; font-weight:800; font-variant-numeric:tabular-nums; }
    .idle-actions { display:flex; flex-wrap:wrap; justify-content:flex-end; gap:10px; }
    .idle-actions button, .idle-actions a { padding:10px 14px; border:1px solid #b8d9cd; border-radius:9px; background:#fff; color:#065f46; font:inherit; font-weight:700; text-decoration:none; cursor:pointer; }
    .idle-actions button { background:#087c5b; border-color:#087c5b; color:#fff; }
</style>
<dialog class="idle-dialog" id="idle-dialog" aria-labelledby="idle-title" aria-describedby="idle-description">
    <h2 id="idle-title">Are you still there?</h2>
    <p id="idle-description">For your security, you will be signed out soon. Save your work or stay signed in to continue.</p>
    <span class="idle-countdown" id="idle-countdown" aria-live="off">02:00</span>
    <div class="idle-actions">
        <a href="{{ route('logout') }}">Sign out now</a>
        <button type="button" id="idle-stay">Stay signed in</button>
    </div>
</dialog>
<script>
(() => {
    const timeout = 15 * 60 * 1000;
    const warning = 2 * 60 * 1000;
    const storageKey = 'projectlink-activity-{{ hash('sha256', session()->getId()) }}';
    const activityUrl = @json(route('session.activity'));
    const expiredUrl = @json(route('login', ['reason' => 'inactive']));
    const token = @json(csrf_token());
    const dialog = document.getElementById('idle-dialog');
    const countdown = document.getElementById('idle-countdown');
    const stay = document.getElementById('idle-stay');
    let lastActivity = Date.now() - Math.max(0, {{ now()->timestamp - (int) session(\App\Http\Middleware\ExpireInactiveSession::SESSION_KEY, now()->timestamp) }} * 1000);
    let lastSent = 0;
    let pending = false;
    let redirecting = false;

    const expire = () => {
        if (redirecting) return;
        redirecting = true;
        window.location.assign(expiredUrl);
    };
    const tick = () => {
        const remaining = Math.max(0, timeout - (Date.now() - lastActivity));
        if (!remaining) { expire(); return; }
        if (remaining <= warning) {
            const seconds = Math.ceil(remaining / 1000);
            countdown.textContent = `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
            if (!dialog.open) dialog.showModal();
        } else if (dialog.open) {
            dialog.close();
        }
    };
    const recordActivity = async (force = false) => {
        if (Date.now() - lastActivity >= timeout) { expire(); return; }
        if (pending || (!force && (dialog.open || Date.now() - lastSent < 20000))) return;
        pending = true;
        lastSent = Date.now();
        try {
            const response = await fetch(activityUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (response.status === 401 || response.status === 419) { expire(); return; }
            if (!response.ok) return;
            await response.json();
            lastActivity = Date.now();
            try { localStorage.setItem(storageKey, String(lastActivity)); } catch (_) {}
            tick();
        } catch (_) {
            // The existing deadline remains in effect if the server cannot be reached.
        } finally { pending = false; }
    };
    dialog.addEventListener('cancel', event => event.preventDefault());
    stay.addEventListener('click', () => recordActivity(true));
    ['pointerdown', 'keydown', 'input', 'touchstart'].forEach(name => {
        document.addEventListener(name, () => recordActivity(), { passive:true });
    });
    window.addEventListener('storage', event => {
        if (event.key === storageKey && Number(event.newValue) > lastActivity) {
            lastActivity = Number(event.newValue);
            tick();
        }
    });
    document.addEventListener('visibilitychange', () => { if (!document.hidden) tick(); });
    try { localStorage.setItem(storageKey, String(lastActivity)); } catch (_) {}
    setInterval(tick, 1000);
    tick();
})();
</script>
@endauth
