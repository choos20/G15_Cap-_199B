@extends('layouts.app')

@section('title', 'Verify OTP')

@section('content')
    <div class="page-card">
        <a class="back-link" href="{{ route('password.request') }}">&larr; Back</a>
        <div class="section-title">Verify OTP</div>
        <div class="section-subtitle">Enter the 6-digit code sent to your email.</div>

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

        <form method="POST" action="{{ route('password.verify.submit') }}">
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

            <button type="submit" id="otp-submit">Verify OTP</button>
        </form>

        <div class="links">
            <p><a href="{{ route('password.request') }}">Request a new OTP</a></p>
        </div>
    </div>
@endsection

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
                text.textContent = 'Code expired — request a new OTP.';
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
        })();ve
        
    </script>
@endpush
