@extends('layouts.app')

@php
    $kind = $media->kind($artwork);
    $mime = $media->mimeType($artwork);
    $isVideo = $kind === 'video';
    $thumbnail = $isVideo ? null : $media->thumbnailVariant($artwork);
    $imageUrl = $thumbnail ? $media->variantUrl($thumbnail) : null;
    $posterVariant = $isVideo
        ? \App\Domain\Media\MediaPreviewVariant::videoPoster($media->primaryAsset($artwork))
        : null;
    $posterUrl = $posterVariant ? $media->variantUrl($posterVariant) : null;
    $originalUrl = $media->originalUrl($artwork);
    $thumbnailWidth = (int) ($thumbnail?->getAttribute('width') ?? 0);
    $thumbnailHeight = (int) ($thumbnail?->getAttribute('height') ?? 0);
    $primaryMedia = $media->primaryMedia($artwork);
    $primaryAsset = $primaryMedia->getRelationValue('mediaAsset');
    $category = $artwork->getRelationValue('category');
    $presentation = \App\Domain\Artwork\GalleryPresentation::class;
    $edgeMode = $presentation::edgeMode($category);
    $sequence = $viewerArtworks->values();
    $currentIndex = $sequence->search(fn ($candidate) => $candidate->getKey() === $artwork->getKey());
    $previousArtwork = is_int($currentIndex) && $currentIndex > 0 ? $sequence->get($currentIndex - 1) : null;
    $nextArtwork = is_int($currentIndex) && $currentIndex < $sequence->count() - 1 ? $sequence->get($currentIndex + 1) : null;
    if ($edgeMode === $presentation::EDGE_CYCLIC && $sequence->count() > 1 && is_int($currentIndex)) {
        $previousArtwork ??= $sequence->last();
        $nextArtwork ??= $sequence->first();
    }
    $preview = app(\App\Domain\Content\SitePreviewContext::class);
@endphp

{{-- Orientation comes from the authoritative public thumbnail dimensions,
     before image decoding, without any client-side layout measurement. --}}
@section('content_class', ! $isVideo && $thumbnailWidth > $thumbnailHeight && $presentation::enabled($category)
    ? 'site-content--landscape-artwork'
    : '')

@section('title', $artwork->title.' — Lars Möller')
@section('meta_description', $artwork->description ?: $artwork->title.' — Lars Möller')
@section('canonical', app(\App\Domain\Content\CanonicalUrl::class)->forPath('/artworks/'.$artwork->slug))

@section('content')
    <article class="artwork-detail" data-artwork-viewer-sequence
        data-matomo-event-on-load="artwork_detail_view"
        data-matomo-event-category="Artwork"
        data-matomo-event-name="{{ $artwork->analytics_key }}"
    >
        <x-artwork-navigation
            :category="$category"
            :show-gallery-link="true"
            :previous-artwork="$previousArtwork"
            :next-artwork="$nextArtwork"
            :empty-step-mode="$edgeMode"
        />
        <div class="artwork-detail__media">
            <a
                class="artwork-detail__viewer-trigger"
                href="{{ $originalUrl }}"
                data-artwork-viewer-trigger
                data-viewer-key="{{ $artwork->slug }}"
                data-viewer-analytics-key="{{ $artwork->analytics_key }}"
                data-viewer-kind="{{ $kind }}"
                data-viewer-mime="{{ $mime }}"
                data-viewer-src="{{ $originalUrl }}"
                data-viewer-alt="{{ $media->altText($artwork) }}"
                data-viewer-title="{{ $artwork->title }}"
                data-viewer-page="{{ $preview->url(route('artworks.show', $artwork->slug)) }}"
                aria-label="Open {{ $artwork->title }} in media viewer"
            >
                @if ($isVideo)
                    <video
                        class="artwork-detail__video"
                        src="{{ $originalUrl }}"
                        @if ($posterUrl) poster="{{ $posterUrl }}" @endif
                        @if ($posterVariant)
                            width="{{ $posterVariant->getAttribute('width') }}"
                            height="{{ $posterVariant->getAttribute('height') }}"
                        @endif
                        aria-label="{{ $media->altText($artwork) }}"
                        muted
                        loop
                        playsinline
                        data-public-loop-video
                        preload="metadata"
                    ></video>
                @else
                    <img
                        class="artwork-image artwork-detail__image"
                        src="{{ $imageUrl }}"
                        alt="{{ $media->altText($artwork) }}"
                        @if ($thumbnailWidth > 0 && $thumbnailHeight > 0)
                            width="{{ $thumbnailWidth }}"
                            height="{{ $thumbnailHeight }}"
                        @endif
                        loading="eager"
                        decoding="async"
                        fetchpriority="high"
                    >
                @endif
            </a>

            @if ($isVideo && $presentation::allowVideoSound($category))
                <div class="public-video__controls">
            <button type="button" class="public-video__sound" data-public-video-sound-toggle aria-label="Enable video sound" aria-pressed="false" title="Enable video sound">
                <span data-public-video-icon-muted><x-public-icon name="volume-muted" /></span>
                <span data-public-video-icon-playing hidden><x-public-icon name="volume-on" /></span>
            </button>
        </div>
            @endif

            <div class="artwork-detail__footer">
                <x-artwork-caption :artwork="$artwork" />
            </div>
        </div>

        @if ($artwork->description)
            <div class="artwork-detail__narrative">
                <p>{{ $artwork->description }}</p>
            </div>
        @endif

        @if ($primaryAsset->credit)
            <div class="artwork-detail__credits">
                <p class="artwork-credit">{{ $primaryAsset->credit }}</p>
            </div>
        @endif

        <div class="artwork-viewer-sequence-data" hidden aria-hidden="true">
            @foreach ($sequence as $viewerArtwork)
                @php
                    $viewerMediaUrl = $media->originalUrl($viewerArtwork);
                    $viewerKind = $media->kind($viewerArtwork);
                    $viewerMime = $media->mimeType($viewerArtwork);
                @endphp
                <span
                    data-artwork-viewer-item
                    data-viewer-key="{{ $viewerArtwork->slug }}"
                    data-viewer-analytics-key="{{ $viewerArtwork->analytics_key }}"
                    data-viewer-kind="{{ $viewerKind }}"
                    data-viewer-mime="{{ $viewerMime }}"
                    data-viewer-src="{{ $viewerMediaUrl }}"
                    data-viewer-alt="{{ $media->altText($viewerArtwork) }}"
                    data-viewer-title="{{ $viewerArtwork->title }}"
                    data-viewer-page="{{ $preview->url(route('artworks.show', $viewerArtwork->slug)) }}"
                ></span>
            @endforeach
        </div>
    </article>
@endsection
