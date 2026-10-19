@props(['artwork', 'media', 'showCategoryLink' => false, 'showDetails' => true, 'heroNavigation' => false, 'enableViewer' => true, 'eager' => false, 'priority' => null, 'galleryBuffered' => false, 'allowVideoSound' => false, 'showMediaCredits' => false, 'showMediaCopyright' => false, 'captionLayout' => 'rights_left'])

@php
    $originalUrl = $media->originalUrl($artwork);
    $altText = $media->altText($artwork);
    $kind = $media->kind($artwork);
    $mime = $media->mimeType($artwork);
    $isVideo = $kind === 'video';
    $thumbnail = $isVideo ? null : $media->thumbnailVariant($artwork);
    $imageUrl = $thumbnail ? $media->variantUrl($thumbnail) : null;
    $posterVariant = $isVideo
        ? \App\Domain\Media\MediaPreviewVariant::videoPoster($media->primaryAsset($artwork))
        : null;
    $posterUrl = $posterVariant ? $media->variantUrl($posterVariant) : null;
    $category = $artwork->getRelationValue('category');
    $thumbnailWidth = (int) ($thumbnail?->getAttribute('width') ?? 0);
    $thumbnailHeight = (int) ($thumbnail?->getAttribute('height') ?? 0);
    $preview = app(\App\Domain\Content\SitePreviewContext::class);
    $detailUrl = $preview->url(route('artworks.show', $artwork->slug));
    $asset = ($showMediaCredits || $showMediaCopyright) ? $media->primaryAsset($artwork) : null;
    $credit = $showMediaCredits && $asset !== null ? trim((string) ($asset->getAttribute('credit') ?? '')) : '';
    $copyright = $showMediaCopyright && $asset !== null ? trim((string) ($asset->effectiveCopyrightNotice() ?? '')) : '';
    $mediaRights = implode(' · ', array_filter([$credit, $copyright], static fn (string $value): bool => $value !== ''));
    $rightsRight = $captionLayout === 'rights_right';
@endphp

<article class="artwork-card">
    <x-artwork-navigation
        :category="$category"
        :show-gallery-link="$showCategoryLink"
        :hero-navigation="$heroNavigation"
    />

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
                @if ($posterUrl) poster="{{ $posterUrl }}" @endif
                @if ($posterVariant)
                    width="{{ $posterVariant->getAttribute('width') }}"
                    height="{{ $posterVariant->getAttribute('height') }}"
                    style="--public-video-ratio: {{ $posterVariant->getAttribute('width') }} / {{ $posterVariant->getAttribute('height') }}"
                @endif
                aria-label="{{ $altText }}"
                muted
                loop
                playsinline
                data-public-loop-video
                preload="{{ $heroNavigation && ! $eager ? 'none' : 'metadata' }}"
            ></video>
        @else
            <img
                class="artwork-image artwork-card__image"
                @if ($galleryBuffered)
                    data-gallery-src="{{ $imageUrl }}"
                    hidden
                @else
                    src="{{ $imageUrl }}"
                @endif
                alt="{{ $altText }}"
                @if ($thumbnailWidth > 0 && $thumbnailHeight > 0)
                    width="{{ $thumbnailWidth }}"
                    height="{{ $thumbnailHeight }}"
                @endif
                loading="{{ $eager ? 'eager' : 'lazy' }}"
                decoding="async"
                fetchpriority="{{ $galleryBuffered ? 'low' : (($priority ?? $eager) ? 'high' : 'auto') }}"
            >
            @if ($galleryBuffered)
                {{-- Browser-native fallback without JavaScript; otherwise the
                     primary image receives its URL near the viewport. --}}
                <noscript>
                    <img
                        class="artwork-image artwork-card__image"
                        src="{{ $imageUrl }}"
                        alt="{{ $altText }}"
                        @if ($thumbnailWidth > 0 && $thumbnailHeight > 0)
                            width="{{ $thumbnailWidth }}"
                            height="{{ $thumbnailHeight }}"
                        @endif
                        loading="lazy"
                        decoding="async"
                    >
                </noscript>
            @endif
        @endif
    @if ($enableViewer)
        </a>
    @else
        </div>
    @endif

    @if ($isVideo && $allowVideoSound)
        <div class="public-video__controls">
            <button type="button" class="public-video__sound" data-public-video-sound-toggle aria-label="Enable video sound" aria-pressed="false" title="Enable video sound">
                <span data-public-video-icon-muted><x-public-icon name="volume-muted" /></span>
                <span data-public-video-icon-playing hidden><x-public-icon name="volume-on" /></span>
            </button>
        </div>
    @endif

    @if ($showDetails || $mediaRights !== '')
        <div class="artwork-card__footer{{ $mediaRights !== '' ? ' artwork-card__footer--with-rights' : '' }}{{ $rightsRight ? ' artwork-card__footer--rights-right' : '' }}">
            @if ($mediaRights !== '')
                <span class="artwork-card__rights">{{ $mediaRights }}</span>
            @endif
            @if ($showDetails)
            <a
                class="artwork-caption-link"
                href="{{ $detailUrl }}"
                aria-label="View details for {{ $artwork->title }}"
            >
                <x-artwork-caption :artwork="$artwork" />
            </a>
            @endif
        </div>
    @endif
</article>
