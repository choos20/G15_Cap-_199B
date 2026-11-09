{{--
    App-wide confirmation dialog.

    Replaces the browser's native confirm(), which renders as an OS alert titled
    with the host ("127.0.0.1:8000 says") and can't be styled.

    Usage — put the attributes on the form (or on a button inside one):

        <form method="POST" action="..."
              data-confirm
              data-confirm-title="Withdraw application?"
              data-confirm-body="You'll be removed from {name}, and your slot will be released."
              data-confirm-name="{{ $activity->title }}"
              data-confirm-note="You can apply again while slots remain."
              data-confirm-ok="Withdraw"
              data-confirm-cancel="Keep application">

    {name} in the body is replaced by data-confirm-name, rendered bold. Only
    title/ok/cancel are required to have sensible defaults; everything else is
    optional. data-confirm-tone="warning" switches the accent from red to amber.
--}}
<div id="cfm" class="cfm-overlay" role="dialog" aria-modal="true" aria-labelledby="cfm-title" hidden>
    <div class="cfm-box">
        <div class="cfm-heading">
            <div class="cfm-icon" id="cfm-icon" aria-hidden="true">
                <svg width="21" height="21" viewBox="0 0 24 24" fill="none"><path d="M12 9v4.5m0 3.2v.01M10.3 3.9 2.4 18a2 2 0 0 0 1.7 3h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <h3 class="cfm-title" id="cfm-title"></h3>
        </div>
        <p class="cfm-body" id="cfm-body"></p>
        <p class="cfm-note" id="cfm-note" hidden></p>
        <div class="cfm-actions">
            <button type="button" class="cfm-cancel" id="cfm-cancel"></button>
            <button type="button" class="cfm-ok" id="cfm-ok"></button>
        </div>
    </div>
</div>

<style>
    .cfm-overlay { position:fixed; inset:0; z-index:1200; background:rgba(15,23,42,0.62);
                   backdrop-filter:blur(4px); -webkit-backdrop-filter:blur(4px);
                   display:flex; align-items:center; justify-content:center; padding:20px;
                   animation:cfm-fade-in .16s ease-out; }
    .cfm-overlay[hidden] { display:none; }
    .cfm-box { background:#fff; border:1px solid rgba(226,232,240,.9); border-radius:20px;
               padding:28px; max-width:440px; width:100%;
               box-shadow:0 24px 70px rgba(15,23,42,.28), 0 4px 12px rgba(15,23,42,.08);
               animation:cfm-rise-in .2s cubic-bezier(.2,.8,.2,1); }
    .cfm-heading { display:flex; align-items:center; gap:14px; margin-bottom:16px; }
    .cfm-icon { width:46px; height:46px; flex:0 0 46px; border-radius:15px;
                display:flex; align-items:center; justify-content:center;
                background:#fff7ed; color:#c2410c; box-shadow:inset 0 0 0 1px #ffedd5; }
    .cfm-icon.is-warning { background:#fffbeb; color:#b45309; }
    .cfm-icon.is-warning { box-shadow:inset 0 0 0 1px #fef3c7; }
    .cfm-title { margin:0; font-size:1.18rem; font-weight:750; letter-spacing:-.02em;
                 color:#0f172a; line-height:1.35; }
    .cfm-body { margin:0; color:#64748b; font-size:.95rem; line-height:1.65; }
    .cfm-body strong { color:#0f172a; font-weight:700; }
    .cfm-note { margin:10px 0 0; padding:10px 12px; border-radius:10px;
                background:#f8fafc; color:#64748b; font-size:.9rem; line-height:1.55; }
    .cfm-actions { display:flex; justify-content:flex-end; gap:10px; margin-top:24px;
                   padding-top:18px; border-top:1px solid #f1f5f9; }
    .cfm-cancel, .cfm-ok { min-height:44px; padding:10px 18px; border-radius:10px;
                           font:inherit; font-weight:700; font-size:.9rem; cursor:pointer;
                           transition:background-color .15s ease, border-color .15s ease,
                                      box-shadow .15s ease, transform .15s ease; }
    .cfm-cancel { background:#fff; color:#334155; border:1px solid #cbd5e1; }
    .cfm-cancel:hover { background:#f8fafc; border-color:#94a3b8; }
    .cfm-ok { background:#c2410c; color:#fff; border:1px solid #c2410c; }
    .cfm-ok:hover { background:#9a3412; border-color:#9a3412; }
    .cfm-ok.is-warning { background:#b45309; border-color:#b45309; }
    .cfm-ok.is-warning:hover { background:#92400e; border-color:#92400e; }
    .cfm-cancel:focus-visible, .cfm-ok:focus-visible {
        outline:3px solid rgba(59,130,246,.35); outline-offset:2px;
    }
    .cfm-cancel:active, .cfm-ok:active { transform:translateY(1px); }
    @keyframes cfm-fade-in { from { opacity:0; } to { opacity:1; } }
    @keyframes cfm-rise-in { from { opacity:0; transform:translateY(8px) scale(.985); }
                             to { opacity:1; transform:translateY(0) scale(1); } }
    @media (max-width:480px) {
        .cfm-overlay { padding:16px; }
        .cfm-box { padding:22px; border-radius:18px; }
        .cfm-heading { gap:12px; }
        .cfm-actions { gap:8px; }
        .cfm-cancel, .cfm-ok { flex:1; padding-inline:12px; }
    }
    @media (prefers-reduced-motion:reduce) {
        .cfm-overlay, .cfm-box { animation:none; }
        .cfm-cancel, .cfm-ok { transition:none; }
    }
</style>

<script>
    (() => {
        const overlay = document.getElementById('cfm');
        if (!overlay) return;

        const iconEl = document.getElementById('cfm-icon');
        const titleEl = document.getElementById('cfm-title');
        const bodyEl = document.getElementById('cfm-body');
        const noteEl = document.getElementById('cfm-note');
        const okBtn = document.getElementById('cfm-ok');
        const cancelBtn = document.getElementById('cfm-cancel');

        let pendingForm = null;
        let pendingHref = null;
        let lastFocused = null;
        let allowUnload = false;

        /**
         * Build the body text, bolding the {name} placeholder. Names come from
         * user data, so they are inserted as text nodes — never as HTML.
         */
        const renderBody = (template, name) => {
            bodyEl.replaceChildren();
            const parts = String(template).split('{name}');
            parts.forEach((part, i) => {
                if (part) bodyEl.appendChild(document.createTextNode(part));
                if (i < parts.length - 1 && name) {
                    const strong = document.createElement('strong');
                    strong.textContent = name;
                    bodyEl.appendChild(strong);
                }
            });
        };

        const close = () => {
            overlay.hidden = true;
            pendingForm = null;
            pendingHref = null;
            if (lastFocused) lastFocused.focus();
        };

        const open = (form, data, href = null) => {
            pendingForm = form;
            pendingHref = href;
            lastFocused = document.activeElement;

            const warning = data.confirmTone === 'warning';
            iconEl.classList.toggle('is-warning', warning);
            okBtn.classList.toggle('is-warning', warning);

            titleEl.textContent = data.confirmTitle || 'Are you sure?';
            renderBody(data.confirmBody || 'This action cannot be undone.', data.confirmName);

            if (data.confirmNote) {
                noteEl.textContent = data.confirmNote;
                noteEl.hidden = false;
            } else {
                noteEl.hidden = true;
            }

            okBtn.textContent = data.confirmOk || 'Confirm';
            cancelBtn.textContent = data.confirmCancel || 'Cancel';

            overlay.hidden = false;
            okBtn.focus();
        };

        const isDirty = (form) => Array.from(form.elements).some((control) => {
            if (control.hasAttribute('data-confirm-ignore')) return false;

            if (control instanceof HTMLInputElement) {
                if (control.type === 'file') return control.files.length > 0;
                if (control.type === 'checkbox' || control.type === 'radio') {
                    return control.checked !== control.defaultChecked;
                }
                return control.value !== control.defaultValue;
            }

            if (control instanceof HTMLTextAreaElement) {
                return control.value !== control.defaultValue;
            }

            if (control instanceof HTMLSelectElement) {
                const defaultOptions = Array.from(control.options).filter((option) => option.defaultSelected);
                if (!control.multiple && defaultOptions.length === 0 && control.options.length > 0) {
                    defaultOptions.push(control.options[0]);
                }

                return Array.from(control.options).some((option) =>
                    option.selected !== defaultOptions.includes(option)
                );
            }

            return false;
        });

        // Controls opt in individually; otherwise the whole form does.
        document.addEventListener('click', (event) => {
            if (!(event.target instanceof Element) || event.defaultPrevented) return;

            const trigger = event.target.closest('[data-confirm]');
            if (trigger?.tagName === 'A') {
                event.preventDefault();
                open(null, trigger.dataset, trigger.href);
                return;
            }

            if (trigger?.tagName === 'BUTTON' && trigger.form) {
                event.preventDefault();
                open(trigger.form, trigger.dataset);
                return;
            }

            const link = event.target.closest('a[href]');
            const form = document.querySelector('form[data-confirm-navigation]');
            if (!link || !form || !isDirty(form)
                || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey
                || link.hasAttribute('download') || (link.target && link.target !== '_self')) {
                return;
            }

            const destination = new URL(link.href, window.location.href);
            if (destination.origin === window.location.origin
                && destination.pathname === window.location.pathname
                && destination.search === window.location.search) {
                return;
            }

            event.preventDefault();
            open(null, form.dataset, link.href);
        });

        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (form.matches('form[data-confirm]') && form !== pendingForm) {
                event.preventDefault();
                open(form, form.dataset);
                return;
            }

            if (form.matches('form[data-confirm-navigation]')) {
                allowUnload = true;
            }
        });

        window.addEventListener('beforeunload', (event) => {
            const form = document.querySelector('form[data-confirm-navigation]');
            if (allowUnload || !form || !isDirty(form)) return;

            event.preventDefault();
            event.returnValue = '';
        });

        okBtn.addEventListener('click', () => {
            const form = pendingForm;
            const href = pendingHref;
            overlay.hidden = true;
            pendingForm = null;
            pendingHref = null;
            if (href) {
                allowUnload = true;
                window.location.assign(href);
            } else if (form) {
                form.submit();   // .submit() bypasses the submit listener
            }
        });

        cancelBtn.addEventListener('click', close);
        overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !overlay.hidden) close();
        });
    })();
</script>
