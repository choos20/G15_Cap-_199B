@extends('layouts.app')

@section('title', 'ProjectLink | Login')

@section('content')
    <style>
        .password-field { position: relative; }
        .input-with-icon { position: relative; }
        .input-with-icon .field-icon {
            position: absolute;
            z-index: 1;
            top: 50%;
            left: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #2f8d5d;
            pointer-events: none;
            transform: translateY(-50%);
        }
        .input-with-icon .field-icon svg { width: 19px; height: 19px; }
        .input-with-icon input { padding-left: 44px; }
        .password-field input {
            padding-right: 50px;
            appearance: none;
            -webkit-appearance: none;
            -ms-appearance: none;
        }
        .password-field input::-ms-reveal,
        .password-field input::-ms-clear,
        .password-field input::-webkit-credentials-auto-fill-button {
            display: none !important;
            visibility: hidden !important;
            width: 0 !important;
            height: 0 !important;
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
        .input-with-icon.has-error input {
            border: 2px solid #ff4354;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(255, 67, 84, .18), 0 7px 18px rgba(115, 19, 35, .12);
            padding-left: 60px;
        }
        .input-with-icon.has-error .field-icon {
            left: 8px;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: #ffe1e5;
            color: #d92d3d;
        }
        .field-error {
            display: flex;
            align-items: center;
            gap: 9px;
            margin-top: 9px;
            padding: 11px 13px;
            border: 1px solid #ffb8bd;
            border-radius: 10px;
            background: #ffe8ea;
            color: #b42318;
            font-size: .84rem;
            font-weight: 700;
            line-height: 1.35;
            box-shadow: 0 5px 14px rgba(126, 23, 39, .08);
        }
        .field-error::before {
            display: inline-flex;
            width: 22px;
            height: 22px;
            align-items: center;
            justify-content: center;
            flex: 0 0 22px;
            border: 2px solid currentColor;
            border-radius: 50%;
            content: '!';
            font-size: .78rem;
            font-weight: 800;
        }
        .login-captcha {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 18px 0 2px;
        }
        .login-captcha .cf-turnstile {
            display: flex;
            width: 100%;
            min-height: 65px;
            align-items: center;
            justify-content: center;
        }
        .login-captcha .cf-turnstile > div,
        .login-captcha .cf-turnstile iframe {
            max-width: 100%;
        }
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

        @if(request('reason') === 'inactive')
            <div class="alert-success">Your session ended due to inactivity. Please sign in again to continue.</div>
        @endif

        @if (session('status'))
            <div class="alert-success">{{ session('status') }}</div>
        @endif

        @if ($errors->has('login'))
            <div class="alert-error">{{ $errors->first('login') }}</div>
        @endif

        <form method="POST" action="{{ route('login.submit') }}" novalidate>
            @csrf

            <label for="user">Username or Email</label>
            <div class="input-with-icon {{ $errors->has('user') ? 'has-error' : '' }}">
                <span class="field-icon" aria-hidden="true"><x-ui-icon name="user" :size="19" /></span>
                <input
                    type="text"
                    id="user"
                    name="user"
                    value="{{ old('user') }}"
                    class="{{ $errors->has('user') ? 'is-invalid' : '' }}"
                    aria-invalid="{{ $errors->has('user') ? 'true' : 'false' }}"
                    @if($errors->has('user')) aria-describedby="user-error" @endif
                    required
                    autofocus
                >
            </div>
            @error('user')
                <div class="field-error" id="user-error">{{ $message }}</div>
            @enderror

            <label for="password">Password</label>
            <div class="password-field input-with-icon {{ $errors->has('password') ? 'has-error' : '' }}">
                <span class="field-icon" aria-hidden="true"><x-ui-icon name="lock" :size="19" /></span>
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="{{ $errors->has('password') ? 'is-invalid' : '' }}"
                    aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                    @if($errors->has('password')) aria-describedby="password-error" @endif
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
                <div class="field-error" id="password-error">{{ $message }}</div>
            @enderror

            @if(session('captcha_required'))
                @if(config('services.turnstile.site_key'))
                    <div class="login-captcha" aria-label="Security verification">
                        <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" data-action="login" data-size="flexible" data-theme="light" data-appearance="always"></div>
                    </div>
                    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
                @else
                    <div class="alert-error">The security check is temporarily unavailable. Please contact an administrator.</div>
                @endif
            @endif

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

            const updateIcon = () => {
                const isVisible = password.type === 'text';
                toggle.innerHTML = isVisible
                    ? '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.8"/><path d="m3 3 18 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>'
                    : '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.8"/></svg>';
            };

            const updateToggle = () => {
                if (password.value.length === 0) {
                    password.type = 'password';
                    toggle.setAttribute('aria-label', 'Show password');
                    toggle.setAttribute('aria-pressed', 'false');
                    toggle.hidden = true;
                } else {
                    toggle.hidden = false;
                }
                updateIcon();
            };

            password.addEventListener('input', updateToggle);
            toggle.addEventListener('mousedown', (event) => event.preventDefault());
            toggle.addEventListener('click', () => {
                const isVisible = password.type === 'text';
                password.type = isVisible ? 'password' : 'text';
                toggle.setAttribute('aria-label', isVisible ? 'Show password' : 'Hide password');
                toggle.setAttribute('aria-pressed', isVisible ? 'false' : 'true');
                updateIcon();
            });
            updateToggle();
        })();
    </script>

@endsection
