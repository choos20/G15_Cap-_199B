@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="page-card">
        <div class="brand">
            <div class="brand-name">Dashboard</div>
            <div class="brand-sub">Welcome, {{ auth()->user()->name }}</div>
        </div>

        @if(session('status'))
            <div class="alert-success">{{ session('status') }}</div>
        @endif

        <div>
            <p><strong>Your role:</strong> {{ str_replace('_', ' ', ucfirst(auth()->user()->role)) }}</p>
        </div>

        <div class="links">
            @if(auth()->user()->role === 'csa_director' || auth()->user()->role === 'celp_head')
                <p><a href="{{ route('users.create') }}">Create a new account</a></p>
            @endif
            <p><a href="{{ route('logout') }}">Logout</a></p>
        </div>
    </div>
@endsection
