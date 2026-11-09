@extends('layouts.admin')

@section('title', 'Second-level Proposal Review')
@section('subtitle', 'Proposals endorsed by Secretary awaiting your review')

@section('content')
    <div class="card">
        <h3 style="margin-top:0">Proposal Queue</h3>
        @if($proposals->isEmpty())
            <p>No proposals waiting for review.</p>
        @else
            <ul style="padding-left:18px">
                @foreach($proposals as $p)
                    <li style="margin-bottom:8px">
                        <a href="{{ route('proposals.show', $p) }}">{{ $p->title }}</a>
                        <div style="font-size:0.9rem; color:#6b7280">Submitted by: {{ $p->applicant->name }}</div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
