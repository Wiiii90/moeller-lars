@extends('layouts.app')

@php
    $imageUrl = $media->originalUrl($artwork);
    $primaryMedia = $media->primaryMedia($artwork);
    $primaryAsset = $primaryMedia->getRelationValue('mediaAsset');
    $category = $artwork->getRelationValue('category');
    $sequence = $viewerArtworks->values();
    $currentIndex = $sequence->search(fn ($candidate) => $candidate->getKey() === $artwork->getKey());
    $previousArtwork = is_int($currentIndex) && $currentIndex > 0 ? $sequence->get($currentIndex - 1) : null;
    $nextArtwork = is_int($currentIndex) && $currentIndex < $sequence->count() - 1 ? $sequence->get($currentIndex + 1) : null;
@endphp

@section('title', $artwork->title.' — Lars Möller')
@section('meta_description', $artwork->description ?: $artwork->title.' — Lars Möller')
@section('canonical', app(\App\Domain\Content\CanonicalUrl::class)->forPath('/artworks/'.$artwork->slug))

@section('content')
    <article class="artwork-detail" data-artwork-viewer-sequence>
        <a
            class="artwork-detail__viewer-trigger"
            href="{{ $imageUrl }}"
            data-artwork-viewer-trigger
            data-viewer-key="{{ $artwork->slug }}"
            aria-label="Open {{ $artwork->title }} in image viewer"
        >
            <img class="artwork-image artwork-detail__image" src="{{ $imageUrl }}" alt="{{ $media->altText($artwork) }}">
        </a>

        <div class="artwork-detail__footer">
            <x-artwork-label :artwork="$artwork" :show-description="false" />

            <nav class="artwork-detail__actions" aria-label="Artwork navigation">
                @if ($previousArtwork !== null)
                    <a
                        class="artwork-context-button artwork-context-button--icon"
                        href="{{ route('artworks.show', $previousArtwork->slug) }}"
                        aria-label="Previous artwork"
                        title="Previous artwork"
                    >‹</a>
                @endif

                <a class="artwork-context-button artwork-context-button--category" href="{{ route('artworks.category', $category->slug) }}">
                    {{ $category->name }}
                </a>

                @if ($nextArtwork !== null)
                    <a
                        class="artwork-context-button artwork-context-button--icon"
                        href="{{ route('artworks.show', $nextArtwork->slug) }}"
                        aria-label="Next artwork"
                        title="Next artwork"
                    >›</a>
                @endif
            </nav>
        </div>

        @if ($artwork->description)
            <div class="artwork-detail__narrative">
                <p>{{ $artwork->description }}</p>
            </div>
        @endif

        @if ($primaryAsset->credit || $primaryAsset->copyright_notice)
            <div class="artwork-detail__credits">
                @if ($primaryAsset->credit)
                    <p class="artwork-credit">{{ $primaryAsset->credit }}</p>
                @endif
                @if ($primaryAsset->copyright_notice)
                    <p class="artwork-copyright">{{ $primaryAsset->copyright_notice }}</p>
                @endif
            </div>
        @endif

        <div class="artwork-viewer-sequence-data" hidden aria-hidden="true">
            @foreach ($sequence as $viewerArtwork)
                @php($viewerMediaUrl = $media->originalUrl($viewerArtwork))
                <span
                    data-artwork-viewer-item
                    data-viewer-key="{{ $viewerArtwork->slug }}"
                    data-viewer-src="{{ $viewerMediaUrl }}"
                    data-viewer-alt="{{ $media->altText($viewerArtwork) }}"
                    data-viewer-title="{{ $viewerArtwork->title }}"
                    data-viewer-page="{{ route('artworks.show', $viewerArtwork->slug) }}"
                ></span>
            @endforeach
        </div>
    </article>
@endsection
