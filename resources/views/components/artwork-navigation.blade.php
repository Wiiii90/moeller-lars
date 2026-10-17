@props([
    'category' => null,
    'showGalleryLink' => false,
    'heroNavigation' => false,
    'previousArtwork' => null,
    'nextArtwork' => null,
    'emptyStepMode' => 'hidden',
])

@php
    $hasSteps = $heroNavigation || $previousArtwork !== null || $nextArtwork !== null;
    $hasGalleryLink = $showGalleryLink && $category !== null;
    $preview = app(\App\Domain\Content\SitePreviewContext::class);
@endphp

@if ($hasGalleryLink || $hasSteps)
    <nav class="artwork-folio" aria-label="Artwork browsing">
        <span class="artwork-folio__leading">
            @if ($heroNavigation)
                <button class="artwork-folio__step" type="button"
                        data-home-hero-direction="previous"
                        aria-label="Previous Hero artwork" title="Previous artwork">
                    <x-public-icon name="arrow-left" />
                </button>
            @elseif ($previousArtwork !== null)
                <a class="artwork-folio__step"
                   href="{{ $preview->url(route('artworks.show', $previousArtwork->slug)) }}"
                   aria-label="Previous artwork" title="Previous artwork">
                    <x-public-icon name="arrow-left" />
                </a>
            @elseif (in_array($emptyStepMode, ['disabled', 'cyclic'], true))
                <button class="artwork-folio__step" type="button" disabled aria-label="No previous artwork">
                    <x-public-icon name="arrow-left" />
                </button>
            @endif
        </span>

        @if ($hasGalleryLink)
            <a class="artwork-folio__gallery"
               href="{{ $preview->url(route('site.section', ['section' => $category->slug])) }}"
               aria-label="Back to {{ $category->name }} gallery"
               title="{{ $category->name }} gallery">{{ $category->name }} Gallery</a>
        @else
            <span class="artwork-folio__center" aria-hidden="true"></span>
        @endif

        <span class="artwork-folio__trailing">
            @if ($heroNavigation)
                <button class="artwork-folio__step" type="button"
                        data-home-hero-direction="next"
                        aria-label="Next Hero artwork" title="Next artwork">
                    <x-public-icon name="arrow-right" />
                </button>
            @elseif ($nextArtwork !== null)
                <a class="artwork-folio__step"
                   href="{{ $preview->url(route('artworks.show', $nextArtwork->slug)) }}"
                   aria-label="Next artwork" title="Next artwork">
                    <x-public-icon name="arrow-right" />
                </a>
            @elseif (in_array($emptyStepMode, ['disabled', 'cyclic'], true))
                <button class="artwork-folio__step" type="button" disabled aria-label="No next artwork">
                    <x-public-icon name="arrow-right" />
                </button>
            @endif
        </span>
    </nav>
@endif
