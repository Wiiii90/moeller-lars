@extends('layouts.app')

@section('title', $category->name.' — Lars Möller')
@section('meta_description', $category->description ?: $category->name.' — Lars Möller')
@section('canonical', app(\App\Domain\Content\CanonicalUrl::class)->forPath('/'.$category->slug))

@section('content')
    @php($divider = \App\Domain\Artwork\GalleryPresentation::divider($category))
    <x-public.page-heading :section="$section" />
    <div class="artwork-list{{ $divider !== 'none' ? ' artwork-list--divided' : '' }}" data-artwork-viewer-sequence data-gallery-image-buffer>
        @foreach ($artworks as $artwork)
            <x-artwork-card :artwork="$artwork" :media="$media" :eager="$loop->index < 2" :priority="$loop->first" :gallery-buffered="$loop->index >= 2" :allow-video-sound="\App\Domain\Artwork\GalleryPresentation::allowVideoSound($category)"
                :show-details="\App\Domain\Artwork\GalleryPresentation::showArtworkInformation($category)"
                :show-media-credits="\App\Domain\Artwork\GalleryPresentation::showMediaCredits($category)"
                :show-media-copyright="\App\Domain\Artwork\GalleryPresentation::showMediaCopyright($category)"
                :caption-layout="\App\Domain\Artwork\GalleryPresentation::captionLayout($category)" />
            @if ($divider !== 'none' && ! $loop->last)
                {{-- Same canonical stroke as Custom Page / Home dividers.
                     Exactly one separator between adjacent visible artworks. --}}
                <div class="custom-page__divider custom-page__divider--{{ $divider }} artwork-list__divider" aria-hidden="true"></div>
            @endif
        @endforeach
    </div>
@endsection
