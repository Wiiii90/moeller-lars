@if ($artwork)
    @php($navigableHero = $showNavigation && $heroGroup->count() > 1)
    <div data-artwork-viewer-sequence @if ($navigableHero) data-home-hero @endif>
        @foreach ($navigableHero ? $heroGroup : [$artwork] as $heroArtwork)
            <div data-home-hero-slide @if (! $heroArtwork->is($artwork)) hidden @endif>
                <x-artwork-card
                    :artwork="$heroArtwork"
                    :media="$media"
                    :show-details="$showDetails"
                    :show-category-link="$showGalleryLink"
                    :hero-navigation="$navigableHero"
                    :folio-position="$navigableHero ? $loop->iteration : null"
                    :folio-total="$navigableHero ? $heroGroup->count() : null"
                    :enable-viewer="$enableViewer"
                    :eager="$heroArtwork->is($artwork)"
                />
            </div>
        @endforeach
    </div>
@endif
