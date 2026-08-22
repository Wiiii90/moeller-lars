<x-filament-panels::page>
    <x-admin.workspace :kicker="$galleryContext['parent_name'] ? 'Gallery · '.$galleryContext['parent_name'] : 'Gallery'" :title="$galleryContext['name']">
        <x-slot:actions>
            <nav class="admin-toolbar" aria-label="Gallery management">
                <a class="admin-action" href="{{ $galleryContext['pages_url'] }}">Pages</a>
                <a class="admin-action" href="{{ $galleryContext['all_artworks_url'] }}">All artworks</a>
                {{ $this->gallerySettingsAction }}
                @if ($galleryContext['public_url'])
                    <a class="admin-action" href="{{ $galleryContext['public_url'] }}" target="_blank" rel="noopener">View Gallery</a>
                @endif
                {{ $this->addArtworkAction }}
            </nav>
        </x-slot:actions>

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
            <x-admin.section kicker="Insights" title="30-day Gallery analytics">
                <x-admin.metrics :columns="4">
                    @if (($visits['state'] ?? null) === 'available')
                        <x-admin.metric label="Visits" :value="number_format((float) $visits['value'])" />
                    @endif
                    @if (($views['state'] ?? null) === 'available')
                        <x-admin.metric label="Views" :value="number_format((float) $views['value'])" />
                    @endif
                    @if (($interactions['state'] ?? null) === 'available')
                        <x-admin.metric label="Interactions" :value="number_format((float) $interactions['value'])" />
                    @endif
                    @if ($topWorks !== [])
                        <x-admin.metric label="Top work" :value="$topWorks[0]['title']" />
                    @endif
                </x-admin.metrics>
                <x-slot:actions>
                    <a class="admin-action" href="{{ \App\Filament\Pages\Analytics::getUrl() }}">Open Analytics</a>
                </x-slot:actions>
            </x-admin.section>
        @endif

        @if ($artworks !== [])
            @if ($moveTargets !== [])
                <x-admin.section kicker="Batch" title="Move selected artworks">
                    <div class="admin-toolbar">
                        <span>{{ count($selectedArtworkIds) }} selected</span>
                        <select wire:model="batchTargetGalleryId" aria-label="Move selected artworks to Gallery">
                            <option value="">Move selected to…</option>
                            @foreach ($moveTargets as $target)
                                <option value="{{ $target['id'] }}">
                                    {{ $target['name'] }}{{ $target['state'] === 'published' ? '' : ' · '.$target['state'] }}
                                </option>
                            @endforeach
                        </select>
                        <button class="admin-action" type="button" wire:click="reassignSelectedArtworks" @disabled(count($selectedArtworkIds) === 0)>
                            Move selected
                        </button>
                    </div>
                </x-admin.section>
            @endif

            <section class="admin-gallery-grid" aria-label="Artwork sequence for {{ $galleryContext['name'] }}">
                @foreach ($artworks as $artwork)
                    <article class="admin-gallery-grid__item" wire:key="gallery-artwork-{{ $artwork['id'] }}">
                        <div class="admin-gallery-grid__image">
                            @if ($artwork['thumbnail_url'])
                                <img src="{{ $artwork['thumbnail_url'] }}" alt="" loading="lazy" decoding="async">
                            @else
                                <span>No image</span>
                            @endif
                            <span class="admin-gallery-grid__sequence">{{ str_pad((string) $artwork['sequence'], 2, '0', STR_PAD_LEFT) }}</span>
                        </div>

                        <div class="admin-gallery-grid__caption">
                            <div class="admin-gallery-grid__identity">
                                <strong>{{ $artwork['title'] }}</strong>
                                <span>
                                    @if ($artwork['year']){{ $artwork['year'] }}@endif
                                    @if ($artwork['medium']){{ $artwork['year'] ? ' · ' : '' }}{{ $artwork['medium'] }}@endif
                                    @if ($artwork['dimensions']){{ ($artwork['year'] || $artwork['medium']) ? ' · ' : '' }}{{ $artwork['dimensions'] }}@endif
                                </span>
                            </div>
                            <span class="admin-gallery-grid__state {{ $artwork['state'] === 'published' || $artwork['is_ready'] ? 'is-published' : '' }}">
                                {{ $artwork['state_label'] }} · {{ $artwork['readiness_label'] }}
                            </span>
                        </div>

                        <div class="admin-gallery-grid__actions admin-toolbar">
                            @if ($moveTargets !== [])
                                <label class="admin-action">
                                    <input type="checkbox" wire:model.live="selectedArtworkIds" value="{{ $artwork['id'] }}">
                                    Select
                                </label>
                            @endif
                            <a class="admin-action is-primary" href="{{ $artwork['edit_url'] }}">Edit</a>
                            @if ($artwork['media_preview_url'])<a class="admin-action" href="{{ $artwork['media_preview_url'] }}">Images</a>@endif
                            @if ($artwork['public_url'])<a class="admin-action" href="{{ $artwork['public_url'] }}" target="_blank" rel="noopener">View</a>@endif
                            <span class="admin-toolbar" aria-label="Reorder {{ $artwork['title'] }}">
                                <button class="admin-action" type="button" wire:click="moveArtwork({{ $artwork['id'] }}, 'up')" aria-label="Move {{ $artwork['title'] }} earlier" @disabled(! $artwork['can_move_up'])>↑</button>
                                <button class="admin-action" type="button" wire:click="moveArtwork({{ $artwork['id'] }}, 'down')" aria-label="Move {{ $artwork['title'] }} later" @disabled(! $artwork['can_move_down'])>↓</button>
                            </span>
                        </div>

                        @if ($moveTargets !== [])
                            <div class="admin-gallery-grid__actions admin-toolbar">
                                <select wire:model="moveTargetGalleryIds.{{ $artwork['id'] }}" aria-label="Move {{ $artwork['title'] }} to Gallery">
                                    <option value="">Move to Gallery…</option>
                                    @foreach ($moveTargets as $target)
                                        <option value="{{ $target['id'] }}">
                                            {{ $target['name'] }}{{ $target['state'] === 'published' ? '' : ' · '.$target['state'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <button class="admin-action" type="button" wire:click="reassignArtwork({{ $artwork['id'] }})">Move</button>
                            </div>
                        @endif
                    </article>
                @endforeach
            </section>
        @else
            <x-admin.empty-state kicker="Empty Gallery" title="Add the first artwork">
                <p>This Gallery is ready. Add an artwork draft and its primary image before publishing it.</p>
                <x-slot:actions>{{ $this->addArtworkAction }}</x-slot:actions>
            </x-admin.empty-state>
        @endif
    </x-admin.workspace>

    <x-filament-actions::modals />
</x-filament-panels::page>
