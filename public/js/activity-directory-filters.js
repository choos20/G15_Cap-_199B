document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-activity-filters]');
    if (!form) return;
    const from = form.querySelector('[name="from"]');
    const to = form.querySelector('[name="to"]');
    const error = form.querySelector('#ad-date-error');
    const presets = Array.from(form.querySelectorAll('[data-date-preset]'));
    const rangeMessage = 'The To date must be on or after the From date.';

    function updateDates(edited = false) {
        const invalid = Boolean(from.value && to.value && from.value > to.value);
        from.max = to.value;
        to.min = from.value;
        to.setCustomValidity(invalid ? rangeMessage : '');
        error.hidden = !invalid && (error.dataset.serverError !== '1' || edited);
        if (invalid) error.textContent = rangeMessage;
        [from, to].forEach(input => input.setAttribute('aria-describedby', error.hidden ? 'ad-date-hint' : 'ad-date-hint ad-date-error'));
        if (edited || invalid) [from, to].forEach(input => input.setAttribute('aria-invalid', String(invalid)));
        presets.forEach(button => button.setAttribute('aria-pressed', String(from.value === button.dataset.from && to.value === button.dataset.to)));
    }

    [from, to].forEach(input => input.addEventListener('input', () => updateDates(true)));
    [from, to].forEach(input => input.addEventListener('change', () => updateDates(true)));
    presets.forEach(button => button.addEventListener('click', () => {
        from.value = button.dataset.from;
        to.value = button.dataset.to;
        updateDates(true);
    }));
    form.querySelector('[data-date-clear]').addEventListener('click', () => {
        from.value = '';
        to.value = '';
        updateDates(true);
        from.focus();
    });
    updateDates();
});
