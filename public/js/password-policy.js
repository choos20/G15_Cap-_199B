(() => {
    const unicodeUppercase = /\p{Lu}/u;
    const unicodeLowercase = /\p{Ll}/u;
    const unicodeNumber = /\p{N}/u;
    const unicodeSymbol = /[^\p{L}\p{N}\s]/u;

    document.querySelectorAll('[data-password-policy]').forEach((policy) => {
        const input = policy.parentElement?.querySelector('[data-password-policy-input]')
            || policy.previousElementSibling;
        const form = input?.form;
        const confirmation = form?.querySelector('[data-password-confirmation]');
        const counter = policy.querySelector('[data-password-counter]');
        const minLength = Number(policy.dataset.minLength || 16);
        const maxLength = Number(policy.dataset.maxLength || 64);

        if (!input) return;

        const length = (value) => Array.from(value).length;
        const checks = (value) => ({
            length: length(value) >= minLength && length(value) <= maxLength,
            uppercase: unicodeUppercase.test(value),
            lowercase: unicodeLowercase.test(value),
            number: unicodeNumber.test(value),
            symbol: unicodeSymbol.test(value),
            'edge-space': !/^\s|\s$/u.test(value),
        });

        const updateRule = (name, valid) => {
            const item = policy.querySelector(`[data-password-rule="${name}"]`);
            if (!item) return;
            item.dataset.state = valid ? 'valid' : 'invalid';
            const icon = item.querySelector('[data-password-rule-icon]');
            if (icon) icon.textContent = valid ? String.fromCharCode(0x2713) : String.fromCharCode(0x25cb);
        };

        const update = () => {
            const value = input.value;
            const result = checks(value);
            const valueLength = length(value);

            if (counter) counter.textContent = `${valueLength}/${maxLength}`;
            Object.entries(result).forEach(([name, valid]) => updateRule(name, valid));

            const complete = Object.values(result).every(Boolean);
            input.setCustomValidity(value === '' || complete ? '' : `Use ${minLength}-${maxLength} characters with uppercase, lowercase, a number, and a special character.`);
            syncConfirmation();
        };

        const syncConfirmation = () => {
            if (!confirmation) return;
            confirmation.setCustomValidity(
                confirmation.value !== '' && confirmation.value !== input.value
                    ? 'Password confirmation does not match.'
                    : ''
            );
        };

        input.addEventListener('input', update);
        input.addEventListener('change', update);
        confirmation?.addEventListener('input', syncConfirmation);
        confirmation?.addEventListener('change', syncConfirmation);
        update();
    });
})();
