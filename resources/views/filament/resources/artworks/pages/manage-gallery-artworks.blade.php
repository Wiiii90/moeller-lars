<x-filament-panels::page>
    <div class="artist-workspace artist-gallery-workspace">
        <header class="artist-workspace__head artist-gallery-workspace__head">
            <div>
                <p class="artist-workspace__kicker">
                    Gallery
                    @if ($galleryContext['parent_name'])
                        · {{ $galleryContext['parent_name'] }}
                    @endif
                </p>
                <h2>{{ $galleryContext['name'] }}</h2>
                <p>
                    {{ count($artworks) }} {{ count($artworks) === 1 ? 'artwork' : 'artworks' }}
                    · {{ $publishedCount }} published
                    · {{ $galleryContext['path'] }}
                </p>
            </div>

            <nav class="artist-gallery-tools" aria-label="Gallery management">
                <a class="artist-action" href="{{ $galleryContext['pages_url'] }}">Pages</a>
                <a class="artist-action" href="{{ $galleryContext['all_artworks_url'] }}">All artworks</a>
                <a class="artist-action" href="{{ $galleryContext['settings_url'] }}">Settings</a>
                @if ($galleryContext['public_url'])
                    <a class="artist-action" href="{{ $galleryContext['public_url'] }}" target="_blank" rel="noopener">View gallery</a>
                @endif
                <a class="artist-action is-primary" href="{{ $galleryContext['create_url'] }}">Add artwork</a>
            </nav>
        </header>

        @if ($artworks !== [])
            <section class="artist-contact-sheet" aria-label="Artwork sequence for {{ $galleryContext['name'] }}">
                @foreach ($artworks as $artwork)
                    <article class="artist-contact-sheet__item" wire:key="gallery-artwork-{{ $artwork['id'] }}">
                        <div class="artist-contact-sheet__image">
                            @if ($artwork['thumbnail_url'])
                                <img
                                    src="{{ $artwork['thumbnail_url'] }}"
                                    alt=""
                                    loading="lazy"
                                    decoding="async"
                                >
                            @else
                                <span>No image</span>
                            @endif
                            <span class="artist-contact-sheet__sequence">{{ str_pad((string) $artwork['sequence'], 2, '0', STR_PAD_LEFT) }}</span>
                        </div>

                        <div class="artist-contact-sheet__caption">
                            <div class="artist-contact-sheet__identity">
                                <strong>{{ $artwork['title'] }}</strong>
                                <span>
                                    @if ($artwork['year']){{ $artwork['year'] }}@endif
                                    @if ($artwork['medium']){{ $artwork['year'] ? ' · ' : '' }}{{ $artwork['medium'] }}@endif
                                    @if ($artwork['dimensions']){{ ($artwork['year'] || $artwork['medium']) ? ' · ' : '' }}{{ $artwork['dimensions'] }}@endif
                                </span>
                            </div>

                            <span class="artist-contact-sheet__state {{ $artwork['state'] === 'published' ? 'is-published' : '' }}">
                                {{ $artwork['state_label'] }}
                            </span>
                        </div>

                        <div class="artist-contact-sheet__actions">
                            <a class="artist-action is-primary" href="{{ $artwork['edit_url'] }}">Edit</a>
                            @if ($artwork['public_url'])
                                <a class="artist-action" href="{{ $artwork['public_url'] }}" target="_blank" rel="noopener">View</a>
                            @endif
                            <span class="artist-contact-sheet__order" aria-label="Reorder {{ $artwork['title'] }}">
                                <button
                                    class="artist-action"
                                    type="button"
                                    wire:click="moveArtwork({{ $artwork['id'] }}, 'up')"
                                    aria-label="Move {{ $artwork['title'] }} earlier"
                                    @disabled(! $artwork['can_move_up'])
                                >↑</button>
                                <button
                                    class="artist-action"
                                    type="button"
                                    wire:click="moveArtwork({{ $artwork['id'] }}, 'down')"
                                    aria-label="Move {{ $artwork['title'] }} later"
                                    @disabled(! $artwork['can_move_down'])
                                >↓</button>
                            </span>
                        </div>
                    </article>
                @endforeach
            </section>
        @else
            <section class="artist-gallery-empty">
                <p class="artist-workspace__kicker">Empty Gallery</p>
                <h3>Add the first artwork</h3>
                <p>This Gallery is ready. Add an artwork draft and its primary image before publishing it.</p>
                <a class="artist-action is-primary" href="{{ $galleryContext['create_url'] }}">Add artwork</a>
            </section>
        @endif

        <footer class="artist-workspace__footnote">
            <span>This sequence is the Gallery order used by the public artwork collection.</span>
            <span>All artworks remains available for cross-Gallery editorial work; normal Gallery editing starts here.</span>
        </footer>
    </div>
</x-filament-panels::page>
