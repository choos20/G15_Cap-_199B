const modal = document.querySelector('[data-proposal-success]');

if (modal) {
    const dialog = modal.querySelector('[role="dialog"]');
    const primaryAction = modal.querySelector('[data-success-primary]');
    const previousBodyOverflow = document.body.style.overflow;
    const lastFocusedElement = document.activeElement instanceof HTMLElement
        ? document.activeElement
        : null;

    const getFocusableElements = () => Array.from(modal.querySelectorAll(
        'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])'
    ));

    const close = () => {
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = previousBodyOverflow;
        if (lastFocusedElement && lastFocusedElement !== document.body) {
            lastFocusedElement.focus({ preventScroll: true });
        }
    };

    modal.querySelectorAll('[data-success-close]').forEach((element) => {
        element.addEventListener('click', close);
    });

    document.addEventListener('keydown', (event) => {
        if (modal.hidden) return;

        if (event.key === 'Escape') {
            event.preventDefault();
            close();
            return;
        }

        if (event.key !== 'Tab') return;

        const focusable = getFocusableElements();
        if (!focusable.length) {
            event.preventDefault();
            dialog?.focus();
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    document.body.style.overflow = 'hidden';
    requestAnimationFrame(() => primaryAction?.focus({ preventScroll: true }));
}
