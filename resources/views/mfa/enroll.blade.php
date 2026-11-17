@extends('layouts.app')

@section('title', 'Set up MFA')

@section('content')
    <div class="page-card mfa-page-card">
        <div class="mfa-eyebrow">High-privilege account security</div>
        <div class="section-title">Set up two-factor authentication</div>
        <div class="section-subtitle">
            Protect your {{ role_label($user->role) }} account before entering the ProjectLink dashboard.
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

        <div class="mfa-step">
            <h3>1. Scan this QR code in SafeAuth</h3>
            <div class="mfa-qr-status"><span class="mfa-qr-status-dot" aria-hidden="true"></span>Ready to scan</div>
            <div class="mfa-qr-frame">
                <span class="mfa-qr-corner mfa-qr-corner--tl" aria-hidden="true"></span>
                <span class="mfa-qr-corner mfa-qr-corner--tr" aria-hidden="true"></span>
                <span class="mfa-qr-corner mfa-qr-corner--bl" aria-hidden="true"></span>
                <span class="mfa-qr-corner mfa-qr-corner--br" aria-hidden="true"></span>
                @if($qrCode)
                    <img src="{{ $qrCode }}" alt="ProjectLink MFA setup QR code">
                @else
                    <p style="color:#334155; text-align:center;">QR code unavailable. Use the manual setup key below.</p>
                @endif
            </div>
            <p>Open SafeAuth, choose to add a TOTP account, and scan this code. You may use another authenticator app that supports standard TOTP.</p>

            <details class="mfa-manual-key">
                <summary>Can’t scan? Show the manual setup key</summary>
                <code>{{ $manualKey }}</code>
            </details>
        </div>

        <div class="mfa-step">
            <h3>2. Confirm the six-digit code</h3>
            <p>Enter the current six-digit code shown by SafeAuth. The code refreshes about every 30 seconds.</p>

            <form method="POST" action="{{ route('mfa.enroll.verify') }}">
                @csrf
                <label for="mfa-enrollment-code">Authenticator code</label>
                <input
                    class="mfa-code-input"
                    type="text"
                    id="mfa-enrollment-code"
                    name="code"
                    required
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    maxlength="6"
                    pattern="[0-9]{6}"
                    placeholder="000000"
                    aria-describedby="mfa-enrollment-help"
                >
                <div id="mfa-enrollment-help" class="mfa-help">Your dashboard will unlock after this code is verified.</div>
                <button type="submit" class="mfa-primary-button">Confirm and continue</button>
            </form>
        </div>

        <a class="mfa-logout" href="{{ route('logout') }}">Sign out</a>
    </div>
@endsection

@push('styles')
    @include('mfa._styles')
@endpush
