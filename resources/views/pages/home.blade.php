@extends('layouts.app')

@section('title', 'Lars Möller')
@section('meta_description', 'Official website of artist Lars Möller')
@section('canonical', app(\App\Domain\Content\CanonicalUrl::class)->forPath('/'))

@section('content')
    @if ($artwork)
        <div class="home-artwork" data-artwork-viewer-sequence>
            <x-artwork-card :artwork="$artwork" :media="$media" :show-category-link="true" />
        </div>
    @else
        <p class="missing-media public-empty-state">No artwork is currently available.</p>
    @endif
@endsection
