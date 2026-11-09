@extends('layouts.admin')

@section('title','Change Password')
@section('subtitle','Update your account password')

@section('content')
    @if(session('status'))<div style="margin-bottom:12px; padding:10px; background:#e6fff6; border-radius:8px">{{ session('status') }}</div>@endif

    {{-- Shown while ForcePasswordChange is holding this account here. --}}
    @if(auth()->user()->must_change_password)
        <div style="max-width:480px; margin-bottom:16px; padding:14px 16px; background:#fffbeb;
                    border:1px solid #fde68a; border-radius:12px; color:#92400e; line-height:1.6">
            <strong>Set your own password to continue.</strong><br>
            <span style="font-size:0.9rem">
                You're signed in with the temporary password that was emailed to you. Enter it as your
                current password below, then choose one only you know. The rest of the system stays
                locked until you do.
            </span>
        </div>
    @endif

    <div class="card" style="max-width:480px">
        <form method="post" action="{{ route('account.password.update') }}">
            @csrf
            <div style="margin-bottom:12px">
                <label>Current password</label>
                <input type="password" name="current_password" style="width:100%; padding:8px; border-radius:6px">
                @error('current_password')<div style="color:#c53030">{{ $message }}</div>@enderror
            </div>
            <div style="margin-bottom:12px">
                <label>New password</label>
                <input type="password" name="password" style="width:100%; padding:8px; border-radius:6px">
                @error('password')<div style="color:#c53030">{{ $message }}</div>@enderror
            </div>
            <div style="margin-bottom:12px">
                <label>Confirm new password</label>
                <input type="password" name="password_confirmation" style="width:100%; padding:8px; border-radius:6px">
            </div>
            <div><button style="padding:8px 12px; background:#0fbf91; border:none; border-radius:8px">Update password</button></div>
        </form>
    </div>
@endsection
