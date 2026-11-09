@extends('layouts.admin')

@section('title', $title ?? 'Placeholder')
@section('subtitle', $subtitle ?? '')

@section('content')
    <div class="card">
        <h3>{{ $title ?? 'Placeholder' }}</h3>
        <p>{{ $description ?? 'This page is not implemented yet.' }}</p>
    </div>
@endsection
