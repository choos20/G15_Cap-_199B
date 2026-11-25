@extends('layouts.app')

@section('title', 'Forgot Password')

@push('styles')
    <style>
        .forgot-password-card {
            padding: 28px 40px 26px;
        }

        .forgot-password-back {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            margin: 0 0 18px;
            border: 1px solid rgba(209, 250, 229, 0.9);
            border-radius: 50%;
            background: #dff7ea;
            color: #10331e;
            text-decoration: none;
            box-shadow: 0 7px 18px rgba(8, 48, 27, 0.12);
            transition: transform 0.18s ease, background 0.18s ease, box-shadow 0.18s ease;
        }

        .forgot-password-back:hover {
            transform: translateY(-2px);
            background: #c8f1db;
            box-shadow: 0 10px 22px rgba(8, 48, 27, 0.18);
        }

        .forgot-password-back:focus-visible {
            outline: 3px solid rgba(217, 250, 227, 0.9);
            outline-offset: 3px;
        }

        .forgot-password-back::after {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            z-index: 3;
            padding: 6px 9px;
            border-radius: 7px;
            background: rgba(8, 32, 19, 0.92);
            color: #fff;
            content: attr(data-tooltip);
            font-size: 0.72rem;
            font-weight: 600;
            line-height: 1;
            opacity: 0;
            pointer-events: none;
            transform: translateY(-3px);
            white-space: nowrap;
            transition: opacity 0.16s ease, transform 0.16s ease;
        }

        .forgot-password-back:hover::after,
        .forgot-password-back:focus-visible::after {
            opacity: 1;
            transform: translateY(0);
        }

        .forgot-password-title {
            margin-bottom: 6px;
        }

        .forgot-password-form {
            margin-top: 2px;
        }

        .forgot-password-input-wrap {
            position: relative;
        }

        .forgot-password-input-icon {
            position: absolute;
            top: 50%;
            left: 14px;
            z-index: 1;
            color: #334155;
            pointer-events: none;
            transform: translateY(-50%);
        }

        .forgot-password-input-wrap input {
            padding-left: 46px;
        }

        .forgot-password-submit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .forgot-password-submit svg {
            flex: 0 0 auto;
        }

        @media (max-width: 480px) {
            .forgot-password-card {
                padding: 24px 22px 22px;
            }

            .forgot-password-back {
                margin-bottom: 16px;
            }
        }
    </style>
@endpush

@section('content')
    <div class="page-card forgot-password-card">
        <a
            class="forgot-password-back"
            href="{{ route('login') }}"
            data-tooltip="Back to Login"
            title="Back to Login"
            aria-label="Back to Login"
        >
            <x-ui-icon name="arrow-left" :size="22" />
        </a>

        <div class="section-title forgot-password-title">Forgot Password</div>
        <div class="section-subtitle">Enter your account email to receive a one-time OTP.</div>

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

        <form class="forgot-password-form" method="POST" action="{{ route('password.request.submit') }}">
            @csrf

            <label for="email">Email</label>
            <div class="forgot-password-input-wrap">
                <x-ui-icon class="forgot-password-input-icon" name="mail" :size="20" />
                <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="Enter your email address" autocomplete="email" required>
            </div>

            <button class="forgot-password-submit" type="submit">
                <x-ui-icon name="send" :size="21" />
                <span>Send OTP</span>
            </button>
        </form>
    </div>
@endsection
