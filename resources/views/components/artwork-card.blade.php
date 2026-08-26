@props(['artwork', 'media', 'showCategoryLink' => false, 'showDetails' => true, 'eager' => false])

@php
    $originalUrl = $media->originalUrl($artwork);
    $altText = $media->altText($artwork);
    $kind = $media->kind($artwork);
    $mime = $media->mimeType($artwork);
    $isVideo = $kind === 'video';
    $thumbnail = $isVideo ? null : $media->thumbnailVariant($artwork);
    $imageUrl = $thumbnail ? route('media.variant', $thumbnail) : null;
    $category = $artwork->getRelationValue('category');
    $thumbnailWidth = (int) ($thumbnail?->getAttribute('width') ?? 0);
    $thumbnailHeight = (int) ($thumbnail?->getAttribute('height') ?? 0);
    $preview = app(\App\Domain\Content\SitePreviewContext::class);
    $detailUrl = $preview->url(route('artworks.show', $artwork->slug));
@endphp

<article class="artwork-card">
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
        @if ($isVideo)
            <video
                class="artwork-card__video"
                src="{{ $originalUrl }}"
                aria-label="{{ $altText }}"
                muted
                playsinline
                preload="metadata"
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
    </a>

    @if ($showDetails || ($showCategoryLink && $category !== null))
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

            @if ($showCategoryLink && $category !== null)
                <nav class="artwork-card__actions" aria-label="Artwork navigation">
                    <a class="artwork-context-button artwork-context-button--category" href="{{ $preview->url(route('site.section', ['section' => $category->slug])) }}">
                        {{ $category->name }} <span aria-hidden="true">→</span>
                    </a>
                </nav>
            @endif
        </div>
    @endif
</article>