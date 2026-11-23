@extends('layouts.admin')

@section('title', 'Colleges & programs')
@section('subtitle', 'Maintain the choices used on participant profiles and attendance reports')

@section('content')
    <style>
        .ac-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:20px; }
        .ac-card { min-width:0; padding:24px; border:1px solid #dfe9e3; border-radius:18px; background:#fff; }
        .ac-card h2 { margin:0 0 8px; font-size:1.1rem; color:#173e34; }
        .ac-note { margin:0 0 18px; color:#64776c; font-size:.88rem; line-height:1.6; }
        .ac-form { display:grid; gap:12px; }
        .ac-form label { display:grid; gap:6px; color:#334b40; font-size:.85rem; font-weight:700; }
        .ac-form input,.ac-form select { width:100%; min-width:0; padding:11px 12px; border:1px solid #cdded3; border-radius:10px; background:#fbfdfc; font:inherit; }
        .ac-form :focus-visible { outline:3px solid #b4dfc9; outline-offset:2px; }
        .ac-button { padding:11px 15px; border:0; border-radius:10px; background:#0b7656; color:#fff; font:inherit; font-weight:700; cursor:pointer; }
        .ac-entry { margin-top:18px; padding-top:18px; border-top:1px solid #e5eee8; }
        .ac-entry summary { color:#173e34; font-weight:750; cursor:pointer; overflow-wrap:anywhere; }
        .ac-entry .ac-form { margin-top:14px; }
        .ac-status { margin-left:6px; color:#71877b; font-size:.76rem; font-weight:500; }
        @media(max-width:850px) { .ac-grid { grid-template-columns:1fr; } }
    </style>
    <x-back-button href="{{ route('users.index') }}">Back to user accounts</x-back-button>
    @if(session('status'))
        <p class="card" role="status" style="color:#047857;padding:16px">{{ session('status') }}</p>
    @endif
    @if($errors->any())
        <div class="card" role="alert" style="color:#991b1b;padding:16px;margin-bottom:18px">
            <ul style="margin:0;padding-left:20px">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <p class="ac-note">Add your school's official colleges, departments or offices, then the programs each offers. Use short codes such as COECS and BSIT. Inactive entries stay on existing records but cannot be assigned to new participants.</p>
    <div class="ac-grid">
        <section class="ac-card">
            <h2>Colleges / departments / offices</h2>
            <p class="ac-note">Select an existing entry below to update its name or availability.</p>
            <form method="POST" action="{{ route('academic-catalog.store', 'departments') }}" class="ac-form">
                @csrf
                <label>Code<input name="code" maxlength="40" required placeholder="e.g. COECS" value="{{ old('code') }}"></label>
                <label>Official name<input name="name" maxlength="255" required value="{{ old('name') }}"></label>
                <button class="ac-button" type="submit">Add college / department / office</button>
            </form>
            @forelse($departments as $department)
                <details class="ac-entry">
                    <summary>{{ $department->code }} <span class="ac-status">{{ $department->is_active ? 'Active' : 'Inactive' }}</span></summary>
                    <form method="POST" action="{{ route('academic-catalog.update', ['departments', $department->id]) }}" class="ac-form">
                        @csrf @method('PUT')
                        <label>Official name<input name="name" value="{{ $department->name }}" maxlength="255" required></label>
                        <label>Availability<select name="is_active"><option value="1" @selected($department->is_active)>Active</option><option value="0" @selected(!$department->is_active)>Inactive</option></select></label>
                        <button class="ac-button" type="submit">Save {{ $department->code }}</button>
                    </form>
                </details>
            @empty
                <p class="ac-note" style="margin-top:18px">Add a college or department to start its program list.</p>
            @endforelse
        </section>
        <section class="ac-card">
            <h2>Programs</h2>
            <p class="ac-note">Each program belongs to a college or department. Codes and college assignments stay fixed to keep existing profiles consistent.</p>
            <form method="POST" action="{{ route('academic-catalog.store', 'programs') }}" class="ac-form">
                @csrf
                <label>College / department<select name="department_id" required><option value="">Choose college / department</option>@foreach($departments->where('is_active', true) as $department)<option value="{{ $department->id }}" @selected((string)old('department_id') === (string)$department->id)>{{ $department->code }}{{ $department->name !== $department->code ? ' — '.$department->name : '' }}</option>@endforeach</select></label>
                <label>Program code<input name="code" maxlength="40" required placeholder="e.g. BSIT" value="{{ old('code') }}"></label>
                <label>Official name<input name="name" maxlength="255" required value="{{ old('name') }}"></label>
                <button class="ac-button" type="submit">Add program</button>
            </form>
            @forelse($programs as $program)
                <details class="ac-entry">
                    <summary>{{ $program->code }} · {{ $program->department }} <span class="ac-status">{{ $program->is_active && $program->department_active ? 'Active' : 'Inactive' }}</span></summary>
                    <form method="POST" action="{{ route('academic-catalog.update', ['programs', $program->id]) }}" class="ac-form">
                        @csrf @method('PUT')
                        <label>Official name<input name="name" value="{{ $program->name }}" maxlength="255" required></label>
                        <label>Availability<select name="is_active"><option value="1" @selected($program->is_active)>Active</option><option value="0" @selected(!$program->is_active)>Inactive</option></select></label>
                        <button class="ac-button" type="submit">Save {{ $program->code }}</button>
                    </form>
                </details>
            @empty
                <p class="ac-note" style="margin-top:18px">No programs yet. Add the official offerings before assigning programs to participants.</p>
            @endforelse
        </section>
    </div>
@endsection
