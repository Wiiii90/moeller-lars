@props(['artwork', 'media', 'showCategoryLink' => false, 'eager' => false])

@php
    $thumbnail = $media->thumbnailVariant($artwork);
    $imageUrl = route('media.variant', $thumbnail);
    $originalUrl = $media->originalUrl($artwork);
    $altText = $media->altText($artwork);
    $category = $artwork->getRelationValue('category');
    $thumbnailWidth = (int) ($thumbnail->getAttribute('width') ?? 0);
    $thumbnailHeight = (int) ($thumbnail->getAttribute('height') ?? 0);
@endphp

<article class="artwork-card">
    <a
        class="artwork-card__link"
        href="{{ route('artworks.show', $artwork->slug) }}"
        data-artwork-viewer-item
        data-artwork-viewer-trigger
        data-viewer-key="{{ $artwork->slug }}"
        data-viewer-analytics-key="{{ $artwork->analytics_key }}"
        data-viewer-src="{{ $originalUrl }}"
        data-viewer-alt="{{ $altText }}"
        data-viewer-title="{{ $artwork->title }}"
        data-viewer-page="{{ route('artworks.show', $artwork->slug) }}"
        aria-label="Open {{ $artwork->title }} in image viewer"
    >
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
    </a>

    <div class="artwork-card__footer">
        <a
            class="artwork-label-trigger"
            href="{{ route('artworks.show', $artwork->slug) }}"
            aria-label="View details for {{ $artwork->title }}"
        >
            <x-artwork-label :artwork="$artwork" />
        </a>

        @if ($showCategoryLink && $category !== null)
            <nav class="artwork-card__actions" aria-label="Artwork navigation">
                <a class="artwork-context-button artwork-context-button--category" href="{{ route('artworks.category', $category->slug) }}">
                    {{ $category->name }} <span aria-hidden="true">→</span>
                </a>
            </nav>
        @endif
    </div>
</article>
