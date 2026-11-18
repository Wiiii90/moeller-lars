@props(['artwork', 'media', 'showCategoryLink' => false, 'showDetails' => null, 'heroNavigation' => false, 'enableViewer' => null, 'eager' => false, 'priority' => null, 'galleryBuffered' => false, 'allowVideoSound' => false, 'showMediaCredits' => null, 'showMediaCopyright' => null, 'captionLayout' => null])

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
    $presentation = \App\Domain\Artwork\GalleryPresentation::class;
    $viewerActive = $enableViewer === null ? $presentation::viewerEnabled($category) : (bool) $enableViewer;
    $detailPageEnabled = $presentation::artworkPagesEnabled($category);
    $configuration = \App\Domain\Artwork\ArtworkInformation::effective($category, $artwork);
    if ($showDetails !== null || $showMediaCredits !== null || $showMediaCopyright !== null) {
        // Home's explicit controls own Home presentation independently of
        // the Gallery-specific selection. Avoid applying two hide policies.
        $configuration['enabled'] = (bool) ($showDetails ?? false)
            || (bool) ($showMediaCredits ?? false)
            || (bool) ($showMediaCopyright ?? false);
        if ($showDetails !== null) {
            foreach (['title', 'date', 'dimensions', 'material'] as $field) {
                $configuration['visible'][$field] = (bool) $showDetails;
            }
        }
    }
    // Home has its own explicit hero surface policy. Only its presentation
    // choices override Gallery defaults; media metadata is still canonical.
    if ($showMediaCredits !== null) {
        $configuration['visible']['credits'] = (bool) $showMediaCredits;
    }
    if ($showMediaCopyright !== null) {
        $configuration['visible']['copyright'] = (bool) $showMediaCopyright;
    }
    if ($captionLayout !== null) {
        $configuration['arrangement'] = \App\Domain\Artwork\ArtworkInformation::defaultArrangement($captionLayout === 'rights_right');
    }
    $asset = ($configuration['visible']['credits'] || $configuration['visible']['copyright'])
        ? $media->primaryAsset($artwork) : null;
    $informationRows = \App\Domain\Artwork\ArtworkInformation::rows($artwork, $asset, $configuration);
    $titleLinked = $detailPageEnabled && $configuration['enabled'] && $configuration['visible']['title'];
@endphp

<article class="artwork-card">
    <x-artwork-navigation
        :category="$category"
        :show-gallery-link="$showCategoryLink"
        :hero-navigation="$heroNavigation"
    />

    @if ($viewerActive)
        <a
            class="artwork-card__link"
            href="{{ $detailPageEnabled ? $detailUrl : $originalUrl }}"
            data-artwork-viewer-item
            data-artwork-viewer-trigger
            data-viewer-key="{{ $artwork->slug }}"
            data-viewer-analytics-key="{{ $artwork->analytics_key }}"
            data-viewer-kind="{{ $kind }}"
            data-viewer-mime="{{ $mime }}"
            data-viewer-src="{{ $originalUrl }}"
            data-viewer-alt="{{ $altText }}"
            data-viewer-title="{{ $artwork->title }}"
            data-viewer-page="{{ $detailPageEnabled ? $detailUrl : '' }}"
            data-viewer-zoom="{{ $presentation::viewerZoomEnabled($category) ? '1' : '0' }}"
            data-viewer-navigation="{{ $presentation::viewerNavigationEnabled($category) ? '1' : '0' }}"
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
                data-gallery-image-state="{{ $galleryBuffered ? 'deferred' : 'pending' }}"
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
    @if ($viewerActive)
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

    @if ($informationRows !== [])
        <div class="artwork-card__footer">
            <x-artwork-caption :artwork="$artwork" :rows="$informationRows"
                :detail-url="$titleLinked ? $detailUrl : null" />
        </div>
    @endif
</article>
