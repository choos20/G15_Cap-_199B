@extends('layouts.app')

@section('title', 'Save recovery codes')

@section('content')
    <div class="page-card mfa-page-card">
        <div class="mfa-eyebrow">MFA setup complete</div>
        <div class="section-title">Save your recovery codes</div>
        <div class="section-subtitle">Keep these codes somewhere secure. Each code can be used once if you cannot access SafeAuth.</div>

        <div class="mfa-recovery-warning">
            These codes are shown only once. Save or print them now. Do not share them with anyone.
        </div>

        <div class="mfa-recovery-list" id="mfa-recovery-list">
            @foreach($recoveryCodes as $code)
                <code class="mfa-recovery-code">{{ $code }}</code>
            @endforeach
        </div>

        <button
            type="button"
            class="mfa-secondary-button"
            id="copy-recovery-codes"
            data-codes="{{ implode("\n", $recoveryCodes) }}"
        >Copy recovery codes</button>

        <a class="mfa-primary-button" href="{{ route(dashboard_route_for($user)) }}">Continue to dashboard</a>
    </div>
@endsection

@push('styles')
    @include('mfa._styles')
@endpush

@push('scripts')
    <script>
        (function () {
            var button = document.getElementById('copy-recovery-codes');
            if (!button) {
                return;
            }

            button.addEventListener('click', function () {
                var codes = button.dataset.codes || '';
                if (!navigator.clipboard || !codes) {
                    return;
                }

                navigator.clipboard.writeText(codes).then(function () {
                    var original = button.textContent;
                    button.textContent = 'Copied';
                    window.setTimeout(function () {
                        button.textContent = original;
                    }, 1800);
                });
            });
        })();
    </script>
@endpush
