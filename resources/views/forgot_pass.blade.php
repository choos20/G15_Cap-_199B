@extends('layouts.app')

@section('title', 'Forgot Password')

@section('content')
    <div class="page-card">
        <div class="section-title">Forgot Password</div>
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

        <form method="POST" action="{{ route('password.request.submit') }}">
            @csrf

            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required>

            <button type="submit">Send OTP</button>
        </form>

        <div class="links">
            <p><a href="{{ route('login') }}">Back to Login</a></p>
        </div>
    </div>
@endsection
