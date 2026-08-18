@extends('layouts.app')

@section('title', $category->name.' — Lars Möller')
@section('meta_description', $category->description ?: $category->name.' — Lars Möller')
@section('canonical', app(\App\Domain\Content\CanonicalUrl::class)->forPath('/'.$category->slug))

@section('content')
    <div class="artwork-list" data-artwork-viewer-sequence>
        @forelse ($artworks as $artwork)
            <x-artwork-card :artwork="$artwork" :media="$media" />
        @empty
            <p class="public-empty-state">No artwork is currently available.</p>
        @endforelse
    </div>
@endsection
