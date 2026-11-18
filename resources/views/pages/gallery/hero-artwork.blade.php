@if ($heroCandidate)
    @php
        $navigableHero = $showNavigation && $heroGroup->count() > 1;
        $imageViewer = app(\App\Domain\Content\PublicImageViewer::class);
    @endphp
    <div data-artwork-viewer-sequence @if ($navigableHero) data-home-hero @endif>
        @foreach ($navigableHero ? $heroGroup : [$heroCandidate] as $candidate)
            @php
                $artwork = $candidate->artwork();
                $asset = $candidate->mediaAsset();
                $isCurrent = $candidate->key() === $heroCandidate->key();
                $assetPreview = $artwork instanceof \App\Models\Artwork
                    ? $media->thumbnailVariant($artwork)
                    : ($asset instanceof \App\Models\MediaAsset
                        ? \App\Domain\Media\MediaPreviewVariant::imageThumbnail($asset) : null);
                $previewWidth = (int) ($assetPreview?->getAttribute('width') ?? 0);
                $previewHeight = (int) ($assetPreview?->getAttribute('height') ?? 0);
                $fitHero = $previewWidth > 0 && $previewHeight > 0
                    && (($expandLandscape && $previewWidth > $previewHeight)
                        || ($expandPortrait && $previewHeight > $previewWidth));
            @endphp
            <div data-home-hero-slide class="{{ $fitHero ? 'home-hero-slide--expanded' : '' }}" @if (! $isCurrent) hidden @endif>
                @if ($artwork instanceof \App\Models\Artwork)
                    <x-artwork-card
                        :artwork="$artwork"
                        :media="$media"
                        :show-details="$showDetails"
                        :show-media-credits="$showMediaCredits"
                        :show-media-copyright="$showMediaCopyright"
                        :caption-layout="$captionLayout"
                        :allow-video-sound="$allowVideoSound"
                        :show-category-link="$showGalleryLink || $navigableHero"
                        :hero-navigation="$navigableHero"
                        :enable-viewer="$enableViewer"
                        :eager="$isCurrent"
                    />
                @elseif ($asset instanceof \App\Models\MediaAsset)
                    @php
                        $mime = (string) $asset->getAttribute('mime_type');
                        $isVideo = \App\Domain\Media\MediaTypePolicy::isVideo($mime);
                        $preview = $isVideo
                            ? \App\Domain\Media\MediaPreviewVariant::videoPoster($asset)
                            : \App\Domain\Media\MediaPreviewVariant::imageThumbnail($asset);
                        $previewUrl = $preview ? $media->variantUrl($preview) : null;
                        $alt = $media->altTextForAsset($asset);
                        $originalUrl = $media->originalUrlForAsset($asset);
                        $credit = $showMediaCredits ? trim((string) ($asset->getAttribute('credit') ?? '')) : '';
                        $copyright = $showMediaCopyright ? trim((string) ($asset->effectiveCopyrightNotice() ?? '')) : '';
                        $rights = implode(', ', array_filter([$credit, $copyright], static fn (string $value): bool => $value !== ''));
                        $rightsRight = $rights !== '' && $captionLayout === \App\Domain\Artwork\GalleryPresentation::CAPTION_RIGHTS_RIGHT;
                    @endphp
                    <article class="artwork-card" aria-label="{{ $alt }}">
                        <x-artwork-navigation :hero-navigation="$navigableHero" />
                        <div class="artwork-card__link artwork-card__link--static">
                            @if ($isVideo)
                                <video
                                    class="artwork-card__video"
                                    src="{{ $originalUrl }}"
                                    @if ($previewUrl) poster="{{ $previewUrl }}" @endif
                                    @if ($preview)
                                        width="{{ $preview->getAttribute('width') }}"
                                        height="{{ $preview->getAttribute('height') }}"
                                    @endif
                                    aria-label="{{ $alt }}"
                                    muted loop playsinline data-public-loop-video
                                    preload="{{ $isCurrent ? 'metadata' : 'none' }}"
                                ></video>
                            @else
                                <img class="artwork-image artwork-card__image"
                                    src="{{ $previewUrl }}" alt="{{ $alt }}"
                                    @if ($preview)
                                        width="{{ $preview->getAttribute('width') }}"
                                        height="{{ $preview->getAttribute('height') }}"
                                    @endif
                                    loading="{{ $isCurrent ? 'eager' : 'lazy' }}"
                                    decoding="async"
                                    @if ($enableViewer)
                                        {!! $imageViewer->attributes('home-media-'.$asset->getKey(), $originalUrl, $alt) !!}
                                    @endif
                                >
                            @endif
                        </div>
                        @if ($isVideo && $allowVideoSound)
                            <div class="public-video__controls">
                                <button type="button" class="public-video__sound"
                                    data-public-video-sound-toggle aria-label="Enable video sound"
                                    aria-pressed="false" title="Enable video sound">
                                    <span data-public-video-icon-muted><x-public-icon name="volume-muted" /></span>
                                    <span data-public-video-icon-playing hidden><x-public-icon name="volume-on" /></span>
                                </button>
                            </div>
                        @endif
                        @if ($showDetails || $rights !== '')
                            <div class="artwork-card__footer{{ $rights !== '' ? ' artwork-card__footer--with-rights' : '' }}{{ $rightsRight ? ' artwork-card__footer--rights-right' : '' }}">
                                @if ($rights !== '')
                                    <span class="artwork-card__rights">{{ $rights }}</span>
                                @endif
                                @if ($showDetails)
                                    <span class="artwork-caption-link">{{ $alt }}</span>
                                @endif
                            </div>
                        @endif
                    </article>
                @endif
            </div>
        @endforeach
    </div>
@endif
