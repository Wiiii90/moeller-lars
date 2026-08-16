@php
    $year = $artwork->work_date?->format('Y');
    $imageUrl = $media->thumbnailUrl($artwork);
    $originalUrl = $media->originalUrl($artwork);
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
    >
        <img class="artwork-image artwork-card__image" src="{{ $imageUrl }}" alt="{{ $media->altText($artwork) }}">
        <div class="artwork-card__metadata">
            <p>{{ $artwork->title }}@if ($year), {{ $year }}@endif</p>
            @if ($artwork->medium || $artwork->dimensions)
                <p>{{ $artwork->medium }}@if ($artwork->medium && $artwork->dimensions), @endif{{ $artwork->dimensions }}</p>
            @endif
            @if ($artwork->description)
                <p>{{ $artwork->description }}</p>
            @endif
        </div>
    </a>
</article>
