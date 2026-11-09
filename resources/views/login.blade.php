@extends('layouts.app')

@section('title', 'ProjectLink | Login')

@section('content')
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
            <input
                type="password"
                id="password"
                name="password"
                class="{{ $errors->has('password') ? 'is-invalid' : '' }}"
                required
            >
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
@endsection
