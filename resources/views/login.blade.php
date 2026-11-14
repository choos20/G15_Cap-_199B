@extends('layouts.app')

@section('title', 'ProjectLink | Login')

@section('content')
    <style>
        .password-field { position: relative; }
        .password-field input { padding-right: 50px; }
        .password-field input::-ms-reveal,
        .password-field input::-ms-clear,
        .password-field input::-webkit-credentials-auto-fill-button {
            display: none !important;
        }
        .password-toggle {
            position: absolute;
            z-index: 2;
            top: 50%;
            right: 8px;
            width: 34px;
            height: 34px;
            margin: 0;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #315d47;
            background: transparent;
            border: 0;
            border-radius: 9px;
            transform: translateY(-50%);
        }
        .password-toggle[hidden] { display: none; }
        .password-toggle:hover { background: rgba(44, 165, 111, .12); color: #146c43; transform: translateY(-50%); }
        .password-toggle:focus-visible { outline: 2px solid #8be4a2; outline-offset: 2px; }
        .password-toggle svg { width: 19px; height: 19px; }
    </style>

    <div class="page-card">
        <div class="brand">
            <img src="{{ asset('images/Logo_nobg.png') }}" alt="ProjectLink Logo">
            <div class="brand-name">ProjectLink</div>
            <div class="brand-sub">Community Extension Project Monitoring &amp; <br>
            Volunteer Management System</div>
        </div>

        <div class="section-title">Sign in to your account</div>
        <div class="section-subtitle">Use your registered email and password to continue.</div>

        @if (session('status'))
            <div class="alert-success">{{ session('status') }}</div>
        @endif

        @if ($errors->has('login'))
            <div class="alert-error">{{ $errors->first('login') }}</div>
        @endif

        <form method="POST" action="{{ route('login.submit') }}" novalidate>
            @csrf

            <label for="user">Username or Email</label>
            <input
                type="text"
                id="user"
                name="user"
                value="{{ old('user') }}"
                class="{{ $errors->has('user') ? 'is-invalid' : '' }}"
                required
                autofocus
            >
            @error('user')
                <div class="field-error">{{ $message }}</div>
            @enderror

            <label for="password">Password</label>
            <div class="password-field">
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="{{ $errors->has('password') ? 'is-invalid' : '' }}"
                    required
                >
                <button type="button" class="password-toggle" id="password-toggle" aria-label="Show password" aria-pressed="false" hidden>
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                        <circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.8"/>
                    </svg>
                </button>
            </div>
            @error('password')
                <div class="field-error">{{ $message }}</div>
            @enderror

            <button type="submit">Login</button>
        </form>

        <div class="links">
            <p><a href="{{ route('password.request') }}">Forgot Password?</a></p>
            <p style="opacity:0.85">Need an account? Contact the CELP Office.</p>
        </div>
    </div>

    <script>
        (() => {
            const password = document.getElementById('password');
            const toggle = document.getElementById('password-toggle');
            if (!password || !toggle) return;

            const updateToggle = () => {
                toggle.hidden = password.value.length === 0;
            };

            password.addEventListener('input', updateToggle);
            toggle.addEventListener('click', () => {
                const isVisible = password.type === 'text';
                password.type = isVisible ? 'password' : 'text';
                toggle.setAttribute('aria-label', isVisible ? 'Show password' : 'Hide password');
                toggle.setAttribute('aria-pressed', isVisible ? 'false' : 'true');
            });
            updateToggle();
        })();
    </script>

@endsection
