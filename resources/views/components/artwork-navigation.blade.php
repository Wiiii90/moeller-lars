@props([
    'category' => null,
    'showGalleryLink' => false,
    'heroNavigation' => false,
    'previousArtwork' => null,
    'nextArtwork' => null,
    'folioPosition' => null,
    'folioTotal' => null,
])

@php
    $hasSteps = $heroNavigation || $previousArtwork !== null || $nextArtwork !== null;
    $hasGalleryLink = $showGalleryLink && $category !== null;
    $preview = app(\App\Domain\Content\SitePreviewContext::class);
@endphp

@if ($hasGalleryLink || $hasSteps)
    <nav class="artwork-folio" aria-label="Artwork browsing">
        @if ($hasGalleryLink)
            <a class="artwork-folio__gallery"
               href="{{ $preview->url(route('site.section', ['section' => $category->slug])) }}"
               aria-label="Back to {{ $category->name }} gallery"
               title="{{ $category->name }} gallery">Gallery</a>
        @endif

        @if ($hasSteps)
            <div class="artwork-folio__browse">
                @if ($folioPosition !== null && $folioTotal !== null && $folioTotal > 1)
                    <span class="artwork-folio__position"
                          aria-label="Artwork {{ $folioPosition }} of {{ $folioTotal }}">{{ $folioPosition }} / {{ $folioTotal }}</span>
                @endif

                @if ($heroNavigation)
                    <button class="artwork-folio__step" type="button"
                            data-home-hero-direction="previous"
                            aria-label="Previous Hero artwork" title="Previous artwork">
                        <x-public-icon name="arrow-left" />
                    </button>
                    <button class="artwork-folio__step" type="button"
                            data-home-hero-direction="next"
                            aria-label="Next Hero artwork" title="Next artwork">
                        <x-public-icon name="arrow-right" />
                    </button>
                @else
                    @if ($previousArtwork !== null)
                        <a class="artwork-folio__step"
                           href="{{ $preview->url(route('artworks.show', $previousArtwork->slug)) }}"
                           aria-label="Previous artwork" title="Previous artwork">
                            <x-public-icon name="arrow-left" />
                        </a>
                    @endif
                    @if ($nextArtwork !== null)
                        <a class="artwork-folio__step"
                           href="{{ $preview->url(route('artworks.show', $nextArtwork->slug)) }}"
                           aria-label="Next artwork" title="Next artwork">
                            <x-public-icon name="arrow-right" />
                        </a>
                    @endif
                @endif
            </div>
        @endif
    </nav>
@endif
