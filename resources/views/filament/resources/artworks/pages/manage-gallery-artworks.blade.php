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

        @php
            $analyticsAvailable = is_array($analytics)
                && in_array($analytics['status'] ?? null, ['available', 'stale'], true);
            $visits = $analytics['page']['visits'] ?? null;
            $views = $analytics['page']['views'] ?? null;
            $interactions = $analytics['artwork_interactions'] ?? null;
            $topWorks = ($analytics['artworks']['state'] ?? null) === 'available'
                ? array_slice($analytics['artworks']['rows'] ?? [], 0, 3)
                : [];
            $trendRows = ($analytics['trend']['state'] ?? null) === 'available'
                ? ($analytics['trend']['rows'] ?? [])
                : [];
            $lastTrend = $trendRows === [] ? null : end($trendRows);
            $hasAnalyticsSignal = $analyticsAvailable && (
                (($visits['state'] ?? null) === 'available' && (float) ($visits['value'] ?? 0) > 0)
                || (($views['state'] ?? null) === 'available' && (float) ($views['value'] ?? 0) > 0)
                || (($interactions['state'] ?? null) === 'available' && (float) ($interactions['value'] ?? 0) > 0)
                || $topWorks !== []
                || $trendRows !== []
            );
        @endphp

        @if ($hasAnalyticsSignal)
            <section aria-label="30-day gallery analytics">
                <nav class="artist-gallery-tools">
                    <span><strong>30d analytics</strong></span>
                    @if (($visits['state'] ?? null) === 'available')
                        <span>{{ number_format((float) $visits['value']) }} visits</span>
                    @endif
                    @if (($views['state'] ?? null) === 'available')
                        <span>{{ number_format((float) $views['value']) }} views</span>
                    @endif
                    @if (($interactions['state'] ?? null) === 'available')
                        <span>{{ number_format((float) $interactions['value']) }} artwork interactions</span>
                    @endif
                    @if ($topWorks !== [])
                        <span>Top work: {{ $topWorks[0]['title'] }}</span>
                    @endif
                    @if (is_array($lastTrend) && is_string($lastTrend['date'] ?? null))
                        <span>Latest tracked day: {{ $lastTrend['date'] }}</span>
                    @endif
                    <a class="artist-action" href="{{ \App\Filament\Pages\Analytics::getUrl() }}">Open Analytics</a>
                </nav>
            </section>
        @endif

        @if ($artworks !== [])
            @if ($moveTargets !== [])
                <section aria-label="Batch artwork actions">
                    <nav class="artist-gallery-tools">
                        <span>{{ count($selectedArtworkIds) }} selected</span>
                        <select wire:model="batchTargetGalleryId" aria-label="Move selected artworks to Gallery">
                            <option value="">Move selected to…</option>
                            @foreach ($moveTargets as $target)
                                <option value="{{ $target['id'] }}">
                                    {{ $target['name'] }}{{ $target['state'] === 'published' ? '' : ' · '.$target['state'] }}
                                </option>
                            @endforeach
                        </select>
                        <button class="artist-action" type="button" wire:click="reassignSelectedArtworks" @disabled(count($selectedArtworkIds) === 0)>
                            Move selected
                        </button>
                    </nav>
                </section>
            @endif

            <section class="artist-contact-sheet" aria-label="Artwork sequence for {{ $galleryContext['name'] }}">
                @foreach ($artworks as $artwork)
                    <article class="artist-contact-sheet__item" wire:key="gallery-artwork-{{ $artwork['id'] }}">
                        <div class="artist-contact-sheet__image">
                            @if ($artwork['thumbnail_url'])
                                <img src="{{ $artwork['thumbnail_url'] }}" alt="" loading="lazy" decoding="async">
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
                            <span class="artist-contact-sheet__state {{ $artwork['state'] === 'published' || $artwork['is_ready'] ? 'is-published' : '' }}">
                                {{ $artwork['state_label'] }} · {{ $artwork['readiness_label'] }}
                            </span>
                        </div>

                        <div class="artist-contact-sheet__actions">
                            @if ($moveTargets !== [])
                                <label class="artist-action">
                                    <input type="checkbox" wire:model.live="selectedArtworkIds" value="{{ $artwork['id'] }}">
                                    Select
                                </label>
                            @endif
                            <a class="artist-action is-primary" href="{{ $artwork['edit_url'] }}">Edit</a>
                            @if ($artwork['media_preview_url'])<a class="artist-action" href="{{ $artwork['media_preview_url'] }}">Images</a>@endif
                            @if ($artwork['public_url'])<a class="artist-action" href="{{ $artwork['public_url'] }}" target="_blank" rel="noopener">View</a>@endif
                            <span class="artist-contact-sheet__order" aria-label="Reorder {{ $artwork['title'] }}">
                                <button class="artist-action" type="button" wire:click="moveArtwork({{ $artwork['id'] }}, 'up')" aria-label="Move {{ $artwork['title'] }} earlier" @disabled(! $artwork['can_move_up'])>↑</button>
                                <button class="artist-action" type="button" wire:click="moveArtwork({{ $artwork['id'] }}, 'down')" aria-label="Move {{ $artwork['title'] }} later" @disabled(! $artwork['can_move_down'])>↓</button>
                            </span>
                        </div>

                        @if ($moveTargets !== [])
                            <div class="artist-contact-sheet__actions">
                                <select wire:model="moveTargetGalleryIds.{{ $artwork['id'] }}" aria-label="Move {{ $artwork['title'] }} to Gallery">
                                    <option value="">Move to Gallery…</option>
                                    @foreach ($moveTargets as $target)
                                        <option value="{{ $target['id'] }}">
                                            {{ $target['name'] }}{{ $target['state'] === 'published' ? '' : ' · '.$target['state'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <button class="artist-action" type="button" wire:click="reassignArtwork({{ $artwork['id'] }})">Move</button>
                            </div>
                        @endif
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
            <span>An artwork keeps one owning Gallery. Moving it removes it from this Gallery and reassigns that ownership without touching shared MediaAssets.</span>
            <span>Draft/site Preview remains the Pages preview integration; public View actions here never publish or synthesize draft routes.</span>
        </footer>
    </div>
</x-filament-panels::page>
