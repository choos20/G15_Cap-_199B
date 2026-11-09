{{--
    Year picker — a grid of years in a popover, paged by decade.

    A full calendar is the wrong control here: graduation is recorded as a year,
    so month and day would be noise the user has to dismiss. This keeps the
    calendar's affordances (paging arrows, Clear, a "this year" shortcut) while
    only ever asking for the one value that matters.

    Usage:
        @include('partials.year_picker', [
            'name'  => 'year_graduated',
            'value' => old('year_graduated'),
            'min'   => 1950,              // optional
        ])

    Submits through a hidden input, so server-side rules are unchanged.
--}}
@php
    $ypMax = $max ?? now()->year;
    $ypMin = $min ?? ($ypMax - 80);
    $ypValue = $value ?: null;
    $ypId = 'yp-' . $name;
@endphp

<div class="yp" data-yp data-yp-min="{{ $ypMin }}" data-yp-max="{{ $ypMax }}">
    <button type="button" class="yp-trigger" data-yp-trigger
            aria-haspopup="dialog" aria-expanded="false" id="{{ $ypId }}">
        <span data-yp-label class="{{ $ypValue ? '' : 'yp-placeholder' }}">
            {{ $ypValue ?: 'Select year' }}
        </span>
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </button>

    <input type="hidden" name="{{ $name }}" value="{{ $ypValue }}" data-yp-value>

    <div class="yp-pop" data-yp-pop hidden role="dialog" aria-label="Choose a year">
        <div class="yp-head">
            <button type="button" class="yp-nav" data-yp-prev aria-label="Earlier years">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <span class="yp-range" data-yp-range></span>
            <button type="button" class="yp-nav" data-yp-next aria-label="Later years">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
        </div>
        <div class="yp-grid" data-yp-grid></div>
        <div class="yp-foot">
            <button type="button" class="yp-link" data-yp-clear>Clear</button>
            <button type="button" class="yp-link" data-yp-now>This year</button>
        </div>
    </div>
</div>

@once
    <style>
        .yp { position:relative; }
        .yp-trigger { width:100%; display:flex; align-items:center; justify-content:space-between; gap:10px;
                      padding:13px 14px; border:1px solid #d1d5db; border-radius:12px; background:#f8fafc;
                      font:inherit; color:#0f172a; cursor:pointer; text-align:left; transition:all 150ms ease; }
        .yp-trigger:hover { border-color:#cbd5e1; }
        .yp-trigger[aria-expanded="true"] { border-color:#3b82f6; background:#fff; box-shadow:0 0 0 4px rgba(59,130,246,0.1); }
        .yp-placeholder { color:#94a3b8; }

        .yp-pop { position:absolute; z-index:60; top:calc(100% + 8px); left:0; width:270px; padding:12px;
                  background:#fff; border:1px solid #e5e7eb; border-radius:16px;
                  box-shadow:0 18px 40px rgba(15,23,42,0.16); }
        .yp-pop[hidden] { display:none; }
        .yp-head { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:10px; }
        .yp-range { font-weight:700; color:#0f172a; font-size:0.9rem; font-variant-numeric:tabular-nums; }
        .yp-nav { width:30px; height:30px; display:flex; align-items:center; justify-content:center;
                  border:1px solid #e2e8f0; background:#fff; border-radius:9px; color:#334155; cursor:pointer; }
        .yp-nav:hover:not(:disabled) { background:#f8fafc; border-color:#cbd5e1; }
        .yp-nav:disabled { opacity:0.35; cursor:default; }

        .yp-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:6px; }
        .yp-year { padding:9px 0; border:1px solid transparent; border-radius:9px; background:#f8fafc;
                   font:inherit; font-size:0.86rem; font-variant-numeric:tabular-nums; color:#0f172a;
                   cursor:pointer; transition:all 120ms ease; }
        .yp-year:hover:not(:disabled) { background:#eef2ff; border-color:#c7d2fe; }
        .yp-year[aria-pressed="true"] { background:#0b6a4a; border-color:#0b6a4a; color:#fff; font-weight:700; }
        .yp-year:disabled { opacity:0.3; cursor:default; background:transparent; }

        .yp-foot { display:flex; align-items:center; justify-content:space-between; margin-top:10px;
                   padding-top:10px; border-top:1px solid #f1f5f9; }
        .yp-link { background:none; border:none; padding:0; font:inherit; font-size:0.84rem; font-weight:700;
                   color:#2563eb; cursor:pointer; }
        .yp-link:hover { text-decoration:underline; }
    </style>

    <script>
        (() => {
            const PAGE = 16; // 4 x 4 grid

            const init = (root) => {
                const min = Number(root.dataset.ypMin);
                const max = Number(root.dataset.ypMax);
                const trigger = root.querySelector('[data-yp-trigger]');
                const pop = root.querySelector('[data-yp-pop]');
                const grid = root.querySelector('[data-yp-grid]');
                const rangeEl = root.querySelector('[data-yp-range]');
                const label = root.querySelector('[data-yp-label]');
                const hidden = root.querySelector('[data-yp-value]');
                const prev = root.querySelector('[data-yp-prev]');
                const next = root.querySelector('[data-yp-next]');

                // Page containing the current value, else the most recent years.
                const pageStartFor = (year) => max - PAGE + 1 - Math.floor((max - year) / PAGE) * PAGE;
                let start = pageStartFor(Number(hidden.value) || max);

                const render = () => {
                    const end = start + PAGE - 1;
                    rangeEl.textContent = `${start} – ${end}`;
                    prev.disabled = start <= min;
                    next.disabled = end >= max;

                    grid.replaceChildren();
                    for (let y = start; y <= end; y++) {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'yp-year';
                        btn.textContent = y;
                        btn.disabled = y < min || y > max;
                        btn.setAttribute('aria-pressed', String(Number(hidden.value) === y));
                        btn.addEventListener('click', () => pick(y));
                        grid.appendChild(btn);
                    }
                };

                const pick = (year) => {
                    hidden.value = year;
                    label.textContent = year;
                    label.classList.remove('yp-placeholder');
                    close();
                };

                const clear = () => {
                    hidden.value = '';
                    label.textContent = 'Select year';
                    label.classList.add('yp-placeholder');
                    close();
                };

                const open = () => {
                    start = pageStartFor(Number(hidden.value) || max);
                    render();
                    pop.hidden = false;
                    trigger.setAttribute('aria-expanded', 'true');
                };
                const close = () => {
                    pop.hidden = true;
                    trigger.setAttribute('aria-expanded', 'false');
                };

                trigger.addEventListener('click', () => pop.hidden ? open() : close());
                prev.addEventListener('click', () => { start = Math.max(min, start - PAGE); render(); });
                next.addEventListener('click', () => { start = Math.min(max - PAGE + 1, start + PAGE); render(); });
                root.querySelector('[data-yp-clear]').addEventListener('click', clear);
                root.querySelector('[data-yp-now]').addEventListener('click', () => pick(max));

                document.addEventListener('click', (e) => { if (!root.contains(e.target)) close(); });
                document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !pop.hidden) close(); });

                render();
            };

            document.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('[data-yp]').forEach(init);
            });
        })();
    </script>
@endonce
