(() => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const start = (root) => {
        root.classList.add('is-animated');
        root.querySelectorAll('[data-rating-value]').forEach((element) => {
            const target = Number(element.dataset.ratingValue);
            if (!Number.isFinite(target)) return;
            const started = performance.now();
            const tick = (now) => {
                const progress = Math.min((now - started) / 700, 1);
                element.textContent = (target * progress).toFixed(2);
                if (progress < 1) requestAnimationFrame(tick);
            };
            requestAnimationFrame(tick);
        });
    };
    const roots = document.querySelectorAll('[data-evaluation-ratings]');
    if (!('IntersectionObserver' in window)) { roots.forEach(start); return; }
    const observer = new IntersectionObserver((entries) => entries.forEach((entry) => {
        if (entry.isIntersecting) { observer.unobserve(entry.target); start(entry.target); }
    }), { threshold: 0.1 });
    roots.forEach((root) => observer.observe(root));
})();
