@extends('layouts.app')

@section('title', 'Forgot Password')

@push('styles')
    <style>
        .forgot-password-card {
            padding: 28px 40px 26px;
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

        }
    </style>
@endpush

@section('content')
    <div class="page-card forgot-password-card">
        <a
            class="auth-back-button"
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
