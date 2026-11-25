@extends('layouts.app')

@section('title', 'Reset Password')

@push('styles')
    <style>
        .reset-password-card {
            padding: 28px 40px 26px;
        }

        @media (max-width: 480px) {
            .reset-password-card {
                padding: 24px 22px 22px;
            }
        }
    </style>
@endpush

@section('content')
    <div class="page-card reset-password-card">
        <a
            class="auth-back-button"
            href="{{ route('login') }}"
            data-tooltip="Back to Login"
            title="Back to Login"
            aria-label="Back to Login"
        >
            <x-ui-icon name="arrow-left" :size="22" />
        </a>
        <div class="section-title">Reset Password</div>

        @if(session('status'))
            <div class="alert-success">{{ session('status') }}</div>
        @endif

        @if($errors->any())
            <div class="alert-error">
                <ul style="margin:0; padding-left:18px;">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('password.reset.submit') }}">
            @csrf

            <label for="password">New Password</label>
            <input
                type="password"
                id="password"
                name="password"
                minlength="{{ \App\Support\PasswordPolicy::MIN_LENGTH }}"
                maxlength="{{ \App\Support\PasswordPolicy::MAX_LENGTH }}"
                autocomplete="new-password"
                data-password-policy-input
                required
            >
            <x-password-policy />

            <label for="password_confirmation">Confirm Password</label>
            <input
                type="password"
                id="password_confirmation"
                name="password_confirmation"
                minlength="{{ \App\Support\PasswordPolicy::MIN_LENGTH }}"
                maxlength="{{ \App\Support\PasswordPolicy::MAX_LENGTH }}"
                autocomplete="new-password"
                data-password-confirmation
                required
            >

            <button type="submit">Reset Password</button>

        </form>
    </div>
@endsection
