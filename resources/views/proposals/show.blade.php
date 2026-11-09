@extends('layouts.admin')

@section('title', $proposal->title)
@section('subtitle', 'Proposal details')

@section('content')
    <div class="card">
        <h3 style="margin-top:0">{{ $proposal->title }}</h3>
        <p>{{ $proposal->description }}</p>
        <p><strong>Applicant:</strong> {{ $proposal->applicant->name }}</p>
        <p><strong>Status:</strong> {{ $proposal->status }}</p>

        <form method="POST" action="{{ route('proposals.coordinator_review', $proposal) }}">
            @csrf
            <label for="comments">Comments</label>
            <textarea id="comments" name="comments" style="width:100%; min-height:100px">{{ old('comments') }}</textarea>

            <div style="display:flex; gap:8px; margin-top:12px">
                <button name="action" value="endorse" style="padding:10px 12px; background:#0fbf91; color:#fff; border:none; border-radius:8px">Endorse</button>
                <button name="action" value="return" style="padding:10px 12px; background:#f97373; color:#fff; border:none; border-radius:8px">Return for revision</button>
            </div>
        </form>
    </div>
@endsection
