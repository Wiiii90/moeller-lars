@extends('layouts.app')

@section('title', $category->name.' — Lars Möller')
@section('meta_description', $category->description ?: $category->name.' — Lars Möller')
@section('canonical', app(\App\Domain\Content\CanonicalUrl::class)->forPath('/'.$category->slug))

@section('content')
    <x-public.page-heading :section="$section" />
    <div class="artwork-list" data-artwork-viewer-sequence data-gallery-image-buffer>
        @foreach ($artworks as $artwork)
            <x-artwork-card :artwork="$artwork" :media="$media" :eager="$loop->index < 2" :priority="$loop->first" :gallery-buffered="$loop->index >= 2" />
        @endforeach
    </div>
@endsection
