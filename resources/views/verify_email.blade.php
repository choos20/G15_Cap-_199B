@extends('layouts.app')

@section('title', 'Verify Email')

@section('content')
    <div class="page-card">
        <div class="section-title">Verify your email</div>
        <div class="section-subtitle">Enter the 6-digit code we sent to {{ session('verify_email') }}.</div>

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

        <form method="POST" action="{{ route('verify_email.submit') }}">
            @csrf

            <label for="otp">OTP Code</label>
            <input
                type="text"
                id="otp"
                name="otp"
                value="{{ old('otp') }}"
                required
                maxlength="6"
                inputmode="numeric"
                autocomplete="one-time-code"
            >

            <div class="otp-timer" id="otp-timer" data-seconds-left="{{ $secondsLeft }}">
                <span id="otp-timer-text">Code expires in</span>
                <strong id="otp-timer-clock">--:--</strong>
            </div>

            <div class="section-subtitle" style="margin:10px 0 0; font-size:0.78rem;">
                {{ $attemptsLeft }} of {{ $maxAttempts }} attempts remaining.
            </div>

            <button type="submit" id="otp-submit">Verify Email</button>
        </form>

        <form method="POST" action="{{ route('verify_email.resend') }}" class="otp-resend" id="otp-resend-form">
            @csrf
            <div class="otp-resend__prompt">Didn't receive the code?</div>
            <button type="submit" class="otp-resend__button" id="otp-resend-button" aria-label="Resend verification code">
                <span class="otp-resend__icon" aria-hidden="true">↻</span>
                <span class="otp-resend__label">Resend code</span>
            </button>
            <div class="otp-resend__hint">We'll send a fresh verification code to your email.</div>
        </form>

        <div class="links">
            <p><x-back-button href="{{ route('login') }}">Back to Login</x-back-button></p>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .otp-resend {
            margin: 20px 0 0;
            padding-top: 18px;
            border-top: 1px solid rgba(255, 255, 255, 0.18);
            text-align: center;
        }

        .otp-resend__prompt {
            margin-bottom: 10px;
            color: rgba(238, 247, 239, 0.86);
            font-size: 0.82rem;
        }

        .otp-resend__button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: auto;
            min-width: 154px;
            min-height: 44px;
            margin: 0;
            padding: 10px 18px;
            border: 1px solid rgba(145, 238, 195, 0.72);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.1);
            color: #effff4;
            font-size: 0.88rem;
            font-weight: 700;
            letter-spacing: 0.01em;
            box-shadow: 0 8px 20px rgba(5, 35, 20, 0.14);
            transition: background 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
        }

        .otp-resend__button:hover {
            filter: none;
            background: rgba(145, 238, 195, 0.2);
            border-color: #a8f3c8;
            box-shadow: 0 10px 24px rgba(5, 35, 20, 0.2);
            transform: translateY(-1px);
        }

        .otp-resend__button:focus-visible {
            outline: 3px solid rgba(168, 243, 200, 0.7);
            outline-offset: 3px;
        }

        .otp-resend__button:active {
            transform: translateY(0);
        }

        .otp-resend__button[disabled] {
            opacity: 0.68;
            cursor: wait;
            box-shadow: none;
        }

        .otp-resend__icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            font-size: 1.05rem;
            line-height: 1;
        }

        .otp-resend__hint {
            margin-top: 9px;
            color: rgba(238, 247, 239, 0.62);
            font-size: 0.72rem;
            line-height: 1.45;
        }

        @media (max-width: 480px) {
            .otp-resend {
                margin-top: 18px;
                padding-top: 16px;
            }

            .otp-resend__button {
                width: 100%;
                max-width: 280px;
            }

            .otp-resend__hint {
                max-width: 250px;
                margin-right: auto;
                margin-left: auto;
            }
        }
    </style>
@endpush

@push('scripts')
    <script>
        (function () {
            var box = document.getElementById('otp-timer');
            var text = document.getElementById('otp-timer-text');
            var clock = document.getElementById('otp-timer-clock');
            var submit = document.getElementById('otp-submit');
            var remaining = parseInt(box.dataset.secondsLeft, 10) || 0;

            function expire() {
                box.classList.add('is-expired');
                text.textContent = 'Code expired — resend a new one.';
                clock.textContent = '00:00';
                submit.disabled = true;
            }

            function render() {
                if (remaining <= 0) {
                    expire();
                    return;
                }

                var m = Math.floor(remaining / 60);
                var s = remaining % 60;
                clock.textContent = ('0' + m).slice(-2) + ':' + ('0' + s).slice(-2);
                remaining -= 1;
                setTimeout(render, 1000);
            }

            render();
        })();

        (function () {
            var form = document.getElementById('otp-resend-form');
            var button = document.getElementById('otp-resend-button');
            var label = button ? button.querySelector('.otp-resend__label') : null;

            if (!form || !button || !label) {
                return;
            }

            form.addEventListener('submit', function () {
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
                label.textContent = 'Sending...';
            });
        })();
    </script>
@endpush
