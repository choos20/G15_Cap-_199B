@props([
    'minLength' => \App\Support\PasswordPolicy::MIN_LENGTH,
    'maxLength' => \App\Support\PasswordPolicy::MAX_LENGTH,
])

<div class="password-policy" data-password-policy data-min-length="{{ $minLength }}" data-max-length="{{ $maxLength }}">
    <div class="password-policy-heading">
        <span>Use a strong password</span>
        <span class="password-policy-counter" data-password-counter aria-live="polite">0/{{ $maxLength }}</span>
    </div>
    <p class="password-policy-copy">Use {{ $minLength }}&ndash;{{ $maxLength }} characters with uppercase, lowercase, a number, and a special character.</p>
    <ul class="password-policy-list" aria-label="Password requirements">
        <li data-password-rule="length"><span class="password-policy-rule-icon" data-password-rule-icon aria-hidden="true">&#9675;</span><span>{{ $minLength }}&ndash;{{ $maxLength }} characters</span></li>
        <li data-password-rule="uppercase"><span class="password-policy-rule-icon" data-password-rule-icon aria-hidden="true">&#9675;</span><span>At least one uppercase letter</span></li>
        <li data-password-rule="lowercase"><span class="password-policy-rule-icon" data-password-rule-icon aria-hidden="true">&#9675;</span><span>At least one lowercase letter</span></li>
        <li data-password-rule="number"><span class="password-policy-rule-icon" data-password-rule-icon aria-hidden="true">&#9675;</span><span>At least one number</span></li>
        <li data-password-rule="symbol"><span class="password-policy-rule-icon" data-password-rule-icon aria-hidden="true">&#9675;</span><span>At least one special character</span></li>
        <li data-password-rule="edge-space"><span class="password-policy-rule-icon" data-password-rule-icon aria-hidden="true">&#9675;</span><span>No leading or trailing spaces</span></li>
    </ul>
</div>
