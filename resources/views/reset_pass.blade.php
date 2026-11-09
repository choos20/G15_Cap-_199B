@extends('layouts.app')

@section('title', 'Reset Password')

@section('content')
    <div class="page-card">
        <a class="back-link" href="{{ route('login') }}">&larr; Back</a>
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
            <input type="password" id="password" name="password" required>

            <label for="password_confirmation">Confirm Password</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required>

            <button type="submit">Reset Password</button>
    

        </form>
    </div>
@endsection
