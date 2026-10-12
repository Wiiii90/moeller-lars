@props(['artwork', 'media', 'showCategoryLink' => false, 'showDetails' => true, 'heroNavigation' => false, 'enableViewer' => true, 'eager' => false])

@php
    $originalUrl = $media->originalUrl($artwork);
    $altText = $media->altText($artwork);
    $kind = $media->kind($artwork);
    $mime = $media->mimeType($artwork);
    $isVideo = $kind === 'video';
    $thumbnail = $isVideo ? null : $media->thumbnailVariant($artwork);
    $imageUrl = $thumbnail ? $media->variantUrl($thumbnail) : null;
    $category = $artwork->getRelationValue('category');
    $thumbnailWidth = (int) ($thumbnail?->getAttribute('width') ?? 0);
    $thumbnailHeight = (int) ($thumbnail?->getAttribute('height') ?? 0);
    $preview = app(\App\Domain\Content\SitePreviewContext::class);
    $detailUrl = $preview->url(route('artworks.show', $artwork->slug));
@endphp

<article class="artwork-card">
    @if ($enableViewer)
        <a
            class="artwork-card__link"
            href="{{ $detailUrl }}"
            data-artwork-viewer-item
            data-artwork-viewer-trigger
            data-viewer-key="{{ $artwork->slug }}"
            data-viewer-analytics-key="{{ $artwork->analytics_key }}"
            data-viewer-kind="{{ $kind }}"
            data-viewer-mime="{{ $mime }}"
            data-viewer-src="{{ $originalUrl }}"
            data-viewer-alt="{{ $altText }}"
            data-viewer-title="{{ $artwork->title }}"
            data-viewer-page="{{ $detailUrl }}"
            aria-label="Open {{ $artwork->title }} in media viewer"
        >
    @else
        <div class="artwork-card__link artwork-card__link--static">
    @endif
        @if ($isVideo)
            <video
                class="artwork-card__video"
                src="{{ $originalUrl }}"
                aria-label="{{ $altText }}"
                muted
                playsinline
                preload="{{ $heroNavigation && ! $eager ? 'none' : 'metadata' }}"
            ></video>
        @else
            <img
                class="artwork-image artwork-card__image"
                src="{{ $imageUrl }}"
                alt="{{ $altText }}"
                @if ($thumbnailWidth > 0 && $thumbnailHeight > 0)
                    width="{{ $thumbnailWidth }}"
                    height="{{ $thumbnailHeight }}"
                @endif
                loading="{{ $eager ? 'eager' : 'lazy' }}"
                decoding="async"
                fetchpriority="{{ $eager ? 'high' : 'auto' }}"
            >
        @endif
    @if ($enableViewer)
        </a>
    @else
        </div>
    @endif

    @if ($showDetails || ($showCategoryLink && $category !== null) || $heroNavigation)
        <div class="artwork-card__footer">
            @if ($showDetails)
                <a
                    class="artwork-label-trigger"
                    href="{{ $detailUrl }}"
                    aria-label="View details for {{ $artwork->title }}"
                >
                    <x-artwork-label :artwork="$artwork" />
                </a>
            @endif

            @if ($heroNavigation || ($showCategoryLink && $category !== null))
                <nav class="artwork-card__actions" aria-label="Artwork navigation">
                    @if ($showCategoryLink && $category !== null)
                        <a class="artwork-context-button artwork-context-button--category" href="{{ $preview->url(route('site.section', ['section' => $category->slug])) }}" aria-label="Open {{ $category->name }} gallery" title="{{ $category->name }} gallery">
                            <span class="artwork-context-button__label">{{ $category->name }} gallery</span>
                        </a>
                    @endif

                    @if ($heroNavigation)
                        <button class="artwork-context-button artwork-context-button--step artwork-context-button--previous" type="button" data-home-hero-direction="previous" aria-label="Previous Hero artwork" title="Previous artwork">
                            <x-public-icon name="chevron-left" /><span>Previous</span>
                        </button>
                        <button class="artwork-context-button artwork-context-button--step artwork-context-button--next" type="button" data-home-hero-direction="next" aria-label="Next Hero artwork" title="Next artwork">
                            <span>Next</span><x-public-icon name="chevron-right" />
                        </button>
                    @endif
                </nav>
            @endif
        </div>
    @endif
</article>