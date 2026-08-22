<x-filament-panels::page>
    <div class="artist-workspace artist-home-workspace">
        <header class="artist-workspace__head">
            <div>
                <p class="artist-workspace__kicker">Homepage</p>
                <h2>Home</h2>
            </div>
            <nav class="artist-gallery-tools" aria-label="Homepage actions">
                <a class="artist-action" href="{{ $previewUrl }}" target="_blank" rel="noopener">Preview site</a>
                <a class="artist-action" href="{{ \App\Filament\Pages\SitePages::getUrl() }}">Pages</a>
            </nav>
        </header>

        <section class="artist-home-current" aria-labelledby="home-current-heading">
            <div class="artist-home-section-head">
                <div>
                    <p class="artist-workspace__kicker">Current presentation</p>
                    <h3 id="home-current-heading">Homepage artwork</h3>
                </div>
            </div>

            @if ($selectionIssue)
                <div class="artist-home-alert">
                    <strong>Homepage selection needs attention</strong>
                    <p>{{ $selectionIssue }}</p>
                    <p>Open one of the newest eligible artworks below and use “Feature on home when newest year is shared” to resolve the tie.</p>
                </div>
            @elseif ($currentArtwork)
                <article class="artist-home-feature">
                    <div class="artist-home-feature__image">
                        @if ($currentArtwork['thumbnail_url'])
                            <img src="{{ $currentArtwork['thumbnail_url'] }}" alt="" loading="eager" decoding="async">
                        @else
                            <span>No image</span>
                        @endif
                    </div>
                    <div class="artist-home-feature__copy">
                        <span class="artist-workspace__kicker">Live selection</span>
                        <strong>{{ $currentArtwork['title'] }}</strong>
                        <span>
                            {{ $currentArtwork['gallery'] }}
                            @if ($currentArtwork['year']) · {{ $currentArtwork['year'] }}@endif
                            @if ($currentArtwork['date']) · {{ $currentArtwork['date'] }}@endif
                        </span>
                        <div class="artist-home-feature__actions">
                            <a class="artist-action is-primary" href="{{ $currentArtwork['edit_url'] }}">Edit artwork</a>
                            @if ($currentArtwork['gallery_url'])
                                <a class="artist-action" href="{{ $currentArtwork['gallery_url'] }}">Open Gallery</a>
                            @endif
                        </div>
                    </div>
                </article>
            @else
                <p class="artist-home-empty">No published artwork from a homepage-enabled Gallery is currently available.</p>
            @endif
        </section>

        <section class="artist-home-sources" aria-labelledby="home-sources-heading">
            <div class="artist-home-section-head">
                <div>
                    <p class="artist-workspace__kicker">Sources</p>
                    <h3 id="home-sources-heading">Homepage Galleries</h3>
                </div>
                <p>Home automatically uses the newest published artwork from enabled, published Galleries.</p>
            </div>

            <div class="artist-home-source-list">
                @foreach ($galleries as $gallery)
                    <article class="artist-home-source-row" wire:key="home-gallery-{{ $gallery['id'] }}">
                        <div class="artist-home-source-row__identity">
                            <strong>{{ $gallery['name'] }}</strong>
                            <span>{{ ucfirst($gallery['state']) }} · {{ $gallery['published_artworks'] }} published artwork{{ $gallery['published_artworks'] === 1 ? '' : 's' }}</span>
                        </div>
                        <div class="artist-home-source-row__meta">
                            <span>{{ $gallery['newest_year'] ?: 'No dated artwork' }}</span>
                            <button
                                type="button"
                                class="artist-placement-toggle {{ $gallery['eligible'] ? 'is-on' : '' }}"
                                wire:click="toggleGalleryEligibility({{ $gallery['id'] }})"
                                aria-pressed="{{ $gallery['eligible'] ? 'true' : 'false' }}"
                            >{{ $gallery['eligible'] ? 'Used on Home' : 'Not used on Home' }}</button>
                        </div>
                        <div class="artist-home-source-row__actions">
                            <a class="artist-action" href="{{ $gallery['workspace_url'] }}">Open Gallery</a>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        @if ($newestEligibleArtworks !== [])
            <section class="artist-home-candidates" aria-labelledby="home-candidates-heading">
                <div class="artist-home-section-head">
                    <div>
                        <p class="artist-workspace__kicker">Newest eligible year</p>
                        <h3 id="home-candidates-heading">Selection candidates</h3>
                    </div>
                </div>

                <div class="artist-home-candidate-list">
                    @foreach ($newestEligibleArtworks as $artwork)
                        <article class="artist-home-candidate-row" wire:key="home-candidate-{{ $artwork['id'] }}">
                            <div class="artist-home-candidate-row__image">
                                @if ($artwork['thumbnail_url'])
                                    <img src="{{ $artwork['thumbnail_url'] }}" alt="" loading="lazy" decoding="async">
                                @endif
                            </div>
                            <div class="artist-home-candidate-row__identity">
                                <strong>{{ $artwork['title'] }}</strong>
                                <span>
                                    {{ $artwork['gallery'] }}
                                    @if ($artwork['date']) · {{ $artwork['date'] }}@elseif ($artwork['year']) · {{ $artwork['year'] }}@endif
                                    @if ($artwork['featured']) · explicit tie-breaker@endif
                                </span>
                            </div>
                            <a class="artist-action" href="{{ $artwork['edit_url'] }}">Edit</a>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
