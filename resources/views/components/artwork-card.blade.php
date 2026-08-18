@props(['artwork', 'media', 'showCategoryLink' => false])

@php
    $imageUrl = $media->thumbnailUrl($artwork);
    $originalUrl = $media->originalUrl($artwork);
    $category = $artwork->getRelationValue('category');
@endphp

<article class="artwork-card">
    <a
        class="artwork-card__link"
        href="{{ route('artworks.show', $artwork->slug) }}"
        data-artwork-viewer-item
        data-artwork-viewer-trigger
        data-viewer-key="{{ $artwork->slug }}"
        data-viewer-src="{{ $originalUrl }}"
        data-viewer-alt="{{ $media->altText($artwork) }}"
        data-viewer-title="{{ $artwork->title }}"
        data-viewer-page="{{ route('artworks.show', $artwork->slug) }}"
        aria-label="Open {{ $artwork->title }} in image viewer"
    >
        <img class="artwork-image artwork-card__image" src="{{ $imageUrl }}" alt="{{ $media->altText($artwork) }}">
    </a>

    <div class="artwork-card__footer">
        <a
            class="artwork-label-trigger"
            href="{{ route('artworks.show', $artwork->slug) }}"
            data-artwork-viewer-trigger
            data-viewer-key="{{ $artwork->slug }}"
            aria-label="Open {{ $artwork->title }} in image viewer"
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
