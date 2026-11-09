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
            @endphp
            <div data-home-hero-slide @if (! $isCurrent) hidden @endif>
                @if ($artwork instanceof \App\Models\Artwork)
                    <x-artwork-card
                        :artwork="$artwork"
                        :media="$media"
                        :show-details="$showDetails"
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
                        @if ($showDetails)
                            <div class="artwork-card__footer">
                                <span class="artwork-caption-link">{{ $alt }}</span>
                            </div>
                        @endif
                    </article>
                @endif
            </div>
        @endforeach
    </div>
@endif
