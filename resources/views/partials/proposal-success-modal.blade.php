@php
    $statusLabel = $success['status_label'] ?? 'Pending review';
@endphp

@vite('resources/js/proposal-success-modal.js')
<link rel="stylesheet" href="{{ asset('css/proposal-success-modal.css') }}">

<div class="proposal-success-modal" data-proposal-success aria-hidden="false">
    <div class="proposal-success-modal__backdrop" data-success-close></div>

    <section class="proposal-success-dialog" role="dialog" aria-modal="true"
             aria-labelledby="proposal-success-title" aria-describedby="proposal-success-message"
             tabindex="-1">
        <button type="button" class="proposal-success-close" data-success-close aria-label="Close confirmation">&times;</button>

        <div class="proposal-success-mark" aria-hidden="true">
            <svg viewBox="0 0 72 72" focusable="false">
                <circle class="proposal-success-mark__circle" cx="36" cy="36" r="30"/>
                <path class="proposal-success-mark__check" d="m21 36 10 10 21-22"/>
            </svg>
        </div>

        <h2 id="proposal-success-title">Submission successful!</h2>
        <p id="proposal-success-message" class="proposal-success-dialog__message">
            Your project proposal has been successfully submitted. The CELP Secretary will be notified.
        </p>

        <div class="proposal-success-status" aria-label="Proposal status">
            <span>Proposal status</span>
            <strong><span class="proposal-success-status__dot" aria-hidden="true"></span>{{ $statusLabel }}</strong>
        </div>

        <div class="proposal-success-actions">
            <a class="proposal-success-primary" data-success-primary href="{{ route('student_org.proposals') }}">View my proposals</a>
            <a class="proposal-success-secondary" href="{{ route('projects.create') }}">Submit another proposal</a>
        </div>
    </section>
</div>
