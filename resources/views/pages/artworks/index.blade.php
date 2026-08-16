@extends('layouts.app')

@section('title', $category->name.' — Lars Möller')

@section('content')
    <h2 class="category-heading">{{ $category->name }}</h2>
    <div class="artwork-list">
        @forelse ($artworks as $artwork)
            <x-artwork-card :artwork="$artwork" :media="$media" />
        @empty
            <p class="public-empty-state">No artwork is currently available.</p>
        @endforelse
    </div>
@endsection
