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

        <section class="artist-dashboard__section" aria-labelledby="home-current-heading">
            <div class="artist-dashboard__section-head">
                <span id="home-current-heading">Current homepage artwork</span>
                <span>Automatic newest selection</span>
            </div>

            @if ($selectionIssue)
                <div class="artist-dashboard__notice">
                    <span>
                        <strong>Homepage selection needs attention</strong><br>
                        {{ $selectionIssue }} Open one of the newest eligible artworks below and use “Feature on home when newest year is shared” to resolve the tie.
                    </span>
                </div>
            @elseif ($currentArtwork)
                <div class="artist-section-list">
                    <article class="artist-section">
                        <div class="artist-section__identity">
                            <span class="artist-section__type">Live selection</span>
                            <strong>{{ $currentArtwork['title'] }}</strong>
                            <span class="artist-section__path">
                                {{ $currentArtwork['gallery'] }}
                                @if ($currentArtwork['year']) · {{ $currentArtwork['year'] }}@endif
                                @if ($currentArtwork['date']) · {{ $currentArtwork['date'] }}@endif
                            </span>
                        </div>
                        <div class="artist-section__state">
                            <span class="is-published">Published</span>
                            @if ($currentArtwork['featured'])
                                <span class="is-visible">Explicit tie-breaker</span>
                            @endif
                        </div>
                        <div class="artist-section__count">
                            @if ($currentArtwork['thumbnail_url'])
                                <img src="{{ $currentArtwork['thumbnail_url'] }}" alt="" width="76" height="58" loading="eager" decoding="async">
                            @else
                                <span>No image</span>
                            @endif
                        </div>
                        <div class="artist-section__actions">
                            <a class="artist-action is-primary" href="{{ $currentArtwork['edit_url'] }}">Edit artwork</a>
                            @if ($currentArtwork['gallery_url'])
                                <a class="artist-action" href="{{ $currentArtwork['gallery_url'] }}">Open Gallery</a>
                            @endif
                        </div>
                    </article>
                </div>
            @else
                <p class="artist-dashboard__quiet">No published artwork from a homepage-enabled Gallery is currently available.</p>
            @endif
        </section>

        <section class="artist-dashboard__section" aria-labelledby="home-sources-heading">
            <div class="artist-dashboard__section-head">
                <span id="home-sources-heading">Homepage Galleries</span>
                <span>Sources for automatic selection</span>
            </div>

            <div class="artist-section-list">
                @foreach ($galleries as $gallery)
                    <article class="artist-section" wire:key="home-gallery-{{ $gallery['id'] }}">
                        <div class="artist-section__identity">
                            <span class="artist-section__type">Gallery</span>
                            <strong>{{ $gallery['name'] }}</strong>
                            <span class="artist-section__path">{{ ucfirst($gallery['state']) }}</span>
                        </div>
                        <div class="artist-section__state">
                            <button
                                type="button"
                                class="artist-placement-toggle {{ $gallery['eligible'] ? 'is-on' : '' }}"
                                wire:click="toggleGalleryEligibility({{ $gallery['id'] }})"
                                aria-pressed="{{ $gallery['eligible'] ? 'true' : 'false' }}"
                            >{{ $gallery['eligible'] ? 'Used on Home' : 'Not used on Home' }}</button>
                        </div>
                        <div class="artist-section__count">
                            <strong>{{ $gallery['published_artworks'] }}</strong>
                            <span>published · newest {{ $gallery['newest_year'] ?: '—' }}</span>
                        </div>
                        <div class="artist-section__actions">
                            <a class="artist-action" href="{{ $gallery['workspace_url'] }}">Open Gallery</a>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        @if ($newestEligibleArtworks !== [])
            <section class="artist-dashboard__section" aria-labelledby="home-candidates-heading">
                <div class="artist-dashboard__section-head">
                    <span id="home-candidates-heading">Newest eligible artworks</span>
                    <span>Only the newest eligible year participates</span>
                </div>

                <div class="artist-section-list">
                    @foreach ($newestEligibleArtworks as $artwork)
                        <article class="artist-section" wire:key="home-candidate-{{ $artwork['id'] }}">
                            <div class="artist-section__identity">
                                <span class="artist-section__type">Artwork</span>
                                <strong>{{ $artwork['title'] }}</strong>
                                <span class="artist-section__path">
                                    {{ $artwork['gallery'] }}
                                    @if ($artwork['date']) · {{ $artwork['date'] }}@elseif ($artwork['year']) · {{ $artwork['year'] }}@endif
                                </span>
                            </div>
                            <div class="artist-section__state">
                                <span class="is-published">Published</span>
                                @if ($artwork['featured'])
                                    <span class="is-visible">Explicit tie-breaker</span>
                                @endif
                            </div>
                            <div class="artist-section__count">
                                @if ($artwork['thumbnail_url'])
                                    <img src="{{ $artwork['thumbnail_url'] }}" alt="" width="64" height="48" loading="lazy" decoding="async">
                                @else
                                    <span>No image</span>
                                @endif
                            </div>
                            <div class="artist-section__actions">
                                <a class="artist-action" href="{{ $artwork['edit_url'] }}">Edit</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
