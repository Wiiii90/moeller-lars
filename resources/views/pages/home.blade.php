@extends('layouts.app')

@section('title', 'Lars Möller')

@section('content')
    @if ($artwork)
        <x-artwork-card :artwork="$artwork" :media="$media" />
    @else
        <p class="missing-media">No artwork is currently available.</p>
    @endif
@endsection
