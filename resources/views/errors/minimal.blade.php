@extends('layouts.app')

@section('title', $exception->getStatusCode())

@section('content')
    <div class="container py-5 text-center">

        <h1 class="display-4">
            {{ $exception->getStatusCode() }}
        </h1>

        <p class="lead">
            {{ $exception->getMessage() ?: 'An error occurred.' }}
        </p>

        <a href="/" class="btn btn-primary mt-3">
            Return home
        </a>

    </div>
@endsection
