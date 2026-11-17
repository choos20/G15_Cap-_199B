@extends('layouts.app')

@section('title', 'Verify MFA')

@section('content')
    <div class="page-card mfa-page-card">
        <div class="mfa-eyebrow">Secure sign-in</div>
        <div class="section-title">Verify your identity</div>
        <div class="section-subtitle">
            Enter the six-digit code from SafeAuth to continue to your {{ role_label($user->role) }} dashboard.
        </div>

        @if($errors->any())
            <div class="alert-error">
                <ul style="margin:0; padding-left:18px;">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('mfa.challenge.verify') }}">
            @csrf
            <label for="mfa-challenge-code">Authenticator code</label>
            <input
                class="mfa-code-input"
                type="text"
                id="mfa-challenge-code"
                name="code"
                required
                inputmode="numeric"
                autocomplete="one-time-code"
                maxlength="6"
                pattern="[0-9]{6}"
                placeholder="000000"
            >
            <button type="submit" class="mfa-primary-button">Verify and continue</button>
        </form>

        <div class="mfa-divider"><span>or</span></div>

        <form method="POST" action="{{ route('mfa.recovery.verify') }}">
            @csrf
            <label for="mfa-recovery-code">Use a recovery code</label>
            <input
                type="text"
                id="mfa-recovery-code"
                name="recovery_code"
                required
                autocomplete="off"
                maxlength="32"
                placeholder="XXXX-XXXX-XXXX"
            >
            <button type="submit" class="mfa-secondary-button">Use recovery code</button>
        </form>

        <p class="mfa-help">Recovery codes are single-use. Contact the system administrator if you no longer have access to SafeAuth or your recovery codes.</p>
        <a class="mfa-logout" href="{{ route('logout') }}">Sign out</a>
    </div>
@endsection

@push('styles')
    @include('mfa._styles')
@endpush
