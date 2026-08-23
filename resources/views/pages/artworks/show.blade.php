@extends('layouts.app')

@php
    $kind = $media->kind($artwork);
    $mime = $media->mimeType($artwork);
    $isVideo = $kind === 'video';
    $thumbnail = $isVideo ? null : $media->thumbnailVariant($artwork);
    $imageUrl = $thumbnail ? route('media.variant', $thumbnail) : null;
    $originalUrl = $media->originalUrl($artwork);
    $thumbnailWidth = (int) ($thumbnail?->getAttribute('width') ?? 0);
    $thumbnailHeight = (int) ($thumbnail?->getAttribute('height') ?? 0);
    $primaryMedia = $media->primaryMedia($artwork);
    $primaryAsset = $primaryMedia->getRelationValue('mediaAsset');
    $category = $artwork->getRelationValue('category');
    $sequence = $viewerArtworks->values();
    $currentIndex = $sequence->search(fn ($candidate) => $candidate->getKey() === $artwork->getKey());
    $previousArtwork = is_int($currentIndex) && $currentIndex > 0 ? $sequence->get($currentIndex - 1) : null;
    $nextArtwork = is_int($currentIndex) && $currentIndex < $sequence->count() - 1 ? $sequence->get($currentIndex + 1) : null;
    $preview = app(\App\Domain\Content\SitePreviewContext::class);
@endphp

@section('title', $artwork->title.' — Lars Möller')
@section('meta_description', $artwork->description ?: $artwork->title.' — Lars Möller')
@section('canonical', app(\App\Domain\Content\CanonicalUrl::class)->forPath('/artworks/'.$artwork->slug))

@section('content')
    <article class="artwork-detail" data-artwork-viewer-sequence
        data-matomo-event-on-load="artwork_detail_view"
        data-matomo-event-category="Artwork"
        data-matomo-event-name="{{ $artwork->analytics_key }}"
    >
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
                    aria-label="{{ $media->altText($artwork) }}"
                    muted
                    playsinline
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

        <div class="artwork-detail__footer">
            <x-artwork-label :artwork="$artwork" :show-description="false" />

            <nav class="artwork-detail__actions" aria-label="Artwork navigation">
                @if ($previousArtwork !== null)
                    <a
                        class="artwork-context-button artwork-context-button--icon"
                        href="{{ $preview->url(route('artworks.show', $previousArtwork->slug)) }}"
                        aria-label="Previous artwork"
                        title="Previous artwork"
                    >‹</a>
                @endif

                <a class="artwork-context-button artwork-context-button--category" href="{{ $preview->url(route('site.section', ['section' => $category->slug])) }}">
                    {{ $category->name }}
                </a>

                @if ($nextArtwork !== null)
                    <a
                        class="artwork-context-button artwork-context-button--icon"
                        href="{{ $preview->url(route('artworks.show', $nextArtwork->slug)) }}"
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
