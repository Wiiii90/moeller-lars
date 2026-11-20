@props(['artwork', 'media', 'showCategoryLink' => false, 'showDetails' => null, 'heroNavigation' => false, 'enableViewer' => null, 'eager' => false, 'priority' => null, 'allowVideoSound' => false, 'showMediaCredits' => null, 'showMediaCopyright' => null, 'captionLayout' => null, 'galleryIndex' => false, 'galleryShift' => 0, 'galleryGap' => 0, 'heroFitted' => false])

@php
    $viewerItem = app(\App\Domain\Artwork\PublicArtworkViewerItem::class)->forArtwork($artwork, $media, $enableViewer);
    $originalUrl = $viewerItem['src'];
    $altText = $viewerItem['alt'];
    $kind = $viewerItem['kind'];
    $mime = $viewerItem['mime'];
    $isVideo = $kind === 'video';
    $galleryVideoPlayer = $galleryIndex && $isVideo;
    $thumbnail = $isVideo ? null : $media->thumbnailVariant($artwork);
    $imageUrl = $thumbnail ? $media->variantUrl($thumbnail) : null;
    $posterVariant = $isVideo
        ? \App\Domain\Media\MediaPreviewVariant::videoPoster($media->primaryAsset($artwork))
        : null;
    $posterUrl = $posterVariant ? $media->variantUrl($posterVariant) : null;
    $category = $artwork->getRelationValue('category');
    $thumbnailWidth = (int) ($thumbnail?->getAttribute('width') ?? 0);
    $thumbnailHeight = (int) ($thumbnail?->getAttribute('height') ?? 0);
    // Use the canonical media preview dimensions for either artwork kind.
    $galleryMediaWidth = $isVideo ? (int) ($posterVariant?->getAttribute('width') ?? 0) : $thumbnailWidth;
    $galleryMediaHeight = $isVideo ? (int) ($posterVariant?->getAttribute('height') ?? 0) : $thumbnailHeight;
    $galleryMediaAspect = $galleryIndex && $galleryMediaWidth > 0 && $galleryMediaHeight > 0
        ? $galleryMediaWidth / $galleryMediaHeight
        : null;
    $preview = app(\App\Domain\Content\SitePreviewContext::class);
    $detailUrl = $preview->url(route('artworks.show', $artwork->slug));
    $presentation = \App\Domain\Artwork\GalleryPresentation::class;
    $viewerActive = $enableViewer === null ? $presentation::viewerEnabled($category) : (bool) $enableViewer;
    // Hero keeps its independent viewer policy. Gallery cyclic navigation
    // never leaks into the Gallery Hero viewer sequence.
    $viewerNavigation = $viewerItem['navigation'];
    $viewerCyclic = $viewerItem['cyclic'];
    $detailPageEnabled = $presentation::artworkPagesEnabled($category);
    $configuration = \App\Domain\Artwork\ArtworkInformation::effective($category, $artwork);
    if ($showDetails !== null || $showMediaCredits !== null || $showMediaCopyright !== null) {
        // Hero's explicit controls own Hero presentation independently of
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
    // Hero has its own explicit hero surface policy. Only its presentation
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

@if ($galleryMediaAspect !== null)
    {{-- Gallery alone consumes the existing nonce-authorized stylesheet slot;
         no inline style attribute, viewport expansion or new script path. --}}
    @push('public-component-styles')
        <style nonce="{{ request()->attributes->get(\App\Http\Middleware\SecurityHeaders::STYLE_NONCE_ATTRIBUTE) }}">
            .artwork-card--gallery-dimensioned[data-gallery-artwork="{{ (int) $artwork->getKey() }}"] {
                --gallery-media-aspect: {{ round($galleryMediaAspect, 8) }};
            }
        </style>
    @endpush
@endif

<article class="artwork-card{{ $galleryIndex ? ' artwork-card--gallery-index artwork-card--gallery-shift-'.(max(-2, min(2, (int) $galleryShift)) + 2).' artwork-card--gallery-gap-'.max(0, min(4, (int) $galleryGap)) : '' }}{{ $galleryMediaAspect !== null ? ' artwork-card--gallery-dimensioned' : '' }}"
    @if ($galleryMediaAspect !== null) data-gallery-artwork="{{ (int) $artwork->getKey() }}" @endif
    @if ($galleryVideoPlayer && $viewerActive)
        {{-- The Gallery video itself is interactive media, never a Viewer
             trigger. Keep its canonical Viewer item on the card instead. --}}
        data-artwork-viewer-item
        data-viewer-key="{{ $viewerItem['key'] }}"
        data-viewer-analytics-key="{{ $viewerItem['analyticsKey'] }}"
        data-viewer-kind="{{ $viewerItem['kind'] }}"
        data-viewer-mime="{{ $viewerItem['mime'] }}"
        data-viewer-src="{{ $viewerItem['src'] }}"
        data-viewer-alt="{{ $viewerItem['alt'] }}"
        data-viewer-title="{{ $viewerItem['title'] }}"
        data-viewer-page="{{ $viewerItem['page'] ?? '' }}"
        data-viewer-navigation="{{ $viewerNavigation ? '1' : '0' }}"
        data-viewer-cyclic="{{ $viewerCyclic ? '1' : '0' }}"
    @endif>
    <x-artwork-navigation
        :category="$category"
        :show-gallery-link="$showCategoryLink"
        :hero-navigation="$heroNavigation"
    />

    @if ($galleryIndex || $heroFitted)
        {{-- The shared image-and-caption composition keeps artwork information
             immediately below its actual media, including on the Gallery Hero. --}}
        <div class="artwork-card__composition{{ $isVideo ? ' artwork-card__composition--video' : '' }}{{ $heroFitted ? ' artwork-card__composition--gallery-hero' : '' }}">
    @endif

    @if ($viewerActive && ! $galleryVideoPlayer)
        <a
            class="artwork-card__link"
            href="{{ $detailPageEnabled ? $detailUrl : $originalUrl }}"
            data-artwork-viewer-item
            data-artwork-viewer-trigger
            data-viewer-key="{{ $viewerItem['key'] }}"
            data-viewer-analytics-key="{{ $viewerItem['analyticsKey'] }}"
            data-viewer-kind="{{ $viewerItem['kind'] }}"
            data-viewer-mime="{{ $viewerItem['mime'] }}"
            data-viewer-src="{{ $viewerItem['src'] }}"
            data-viewer-alt="{{ $viewerItem['alt'] }}"
            data-viewer-title="{{ $viewerItem['title'] }}"
            data-viewer-page="{{ $viewerItem['page'] ?? '' }}"
            data-viewer-navigation="{{ $viewerNavigation ? '1' : '0' }}"
            data-viewer-cyclic="{{ $viewerCyclic ? '1' : '0' }}"
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
                @if ($galleryVideoPlayer) controls @endif
                data-public-loop-video
                preload="{{ $heroNavigation && ! $eager ? 'none' : 'metadata' }}"
            ></video>
        @else
            <img
                class="artwork-image artwork-card__image"
                data-gallery-image-state="pending"
                src="{{ $imageUrl }}"
                alt="{{ $altText }}"
                @if ($thumbnailWidth > 0 && $thumbnailHeight > 0)
                    width="{{ $thumbnailWidth }}"
                    height="{{ $thumbnailHeight }}"
                @endif
                loading="{{ $eager ? 'eager' : 'lazy' }}"
                decoding="async"
                fetchpriority="{{ ($priority ?? $eager) ? 'high' : 'auto' }}"
            >
        @endif
    @if ($viewerActive && ! $galleryVideoPlayer)
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

    @if ($galleryIndex || $heroFitted)
        </div>
    @endif
</article>
