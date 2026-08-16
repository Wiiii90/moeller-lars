@extends('layouts.app')

@section('title', 'Lars Möller')

@section('content')
    @if ($artwork)
        <div class="home-artwork">
            <x-artwork-card :artwork="$artwork" :media="$media" />
        </div>
    @else
        <p class="missing-media public-empty-state">No artwork is currently available.</p>
    @endif
@endsection
