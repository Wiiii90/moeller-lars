<x-filament-panels::page>
    <x-admin.workspace kicker="Homepage" title="Home" class="admin-home-workspace">
        <x-slot:actions>
            <a class="admin-action" href="{{ $previewUrl }}" target="_blank" rel="noopener">Preview site</a>
            <a class="admin-action" href="{{ \App\Filament\Pages\SitePages::getUrl() }}">Pages</a>
        </x-slot:actions>

        <x-admin.section kicker="Selection" title="Current homepage artwork">
            @if ($selectionIssue)
                <x-admin.empty-state kicker="Needs attention" title="Homepage selection is ambiguous">
                    <p>{{ $selectionIssue }} Open one of the newest eligible artworks below and choose the explicit homepage tie-breaker.</p>
                </x-admin.empty-state>
            @elseif ($currentArtwork)
                <x-admin.list>
                    <article class="admin-list__row admin-list__row--media">
                        <div class="admin-list__identity">
                            <span class="admin-list__eyebrow">Live selection</span>
                            <strong>{{ $currentArtwork['title'] }}</strong>
                            <span>
                                {{ $currentArtwork['gallery'] }}
                                @if ($currentArtwork['year']) · {{ $currentArtwork['year'] }}@endif
                                @if ($currentArtwork['date']) · {{ $currentArtwork['date'] }}@endif
                            </span>
                        </div>
                        <div class="admin-list__meta">
                            <span>Published</span>
                            @if ($currentArtwork['featured'])<span>Explicit tie-breaker</span>@endif
                        </div>
                        <div class="admin-list__media">
                            @if ($currentArtwork['thumbnail_url'])
                                <img src="{{ $currentArtwork['thumbnail_url'] }}" alt="" width="76" height="58" loading="eager" decoding="async">
                            @else
                                <span>No image</span>
                            @endif
                        </div>
                        <div class="admin-toolbar">
                            <a class="admin-action is-primary" href="{{ $currentArtwork['edit_url'] }}">Edit artwork</a>
                            @if ($currentArtwork['gallery_url'])
                                <a class="admin-action" href="{{ $currentArtwork['gallery_url'] }}">Open Gallery</a>
                            @endif
                        </div>
                    </article>
                </x-admin.list>
            @else
                <x-admin.empty-state kicker="No selection" title="No homepage artwork is available">
                    <p>Publish artwork in a Gallery that is enabled as a homepage source.</p>
                </x-admin.empty-state>
            @endif
        </x-admin.section>

        <x-admin.section kicker="Sources" title="Homepage Galleries">
            @if ($galleries !== [])
                <x-admin.list>
                    @foreach ($galleries as $gallery)
                        <article class="admin-list__row" wire:key="home-gallery-{{ $gallery['id'] }}">
                            <div class="admin-list__identity">
                                <span class="admin-list__eyebrow">Gallery</span>
                                <strong>{{ $gallery['name'] }}</strong>
                                <span>{{ ucfirst($gallery['state']) }}</span>
                            </div>
                            <div class="admin-list__meta">
                                <button
                                    type="button"
                                    class="admin-action {{ $gallery['eligible'] ? 'is-primary' : '' }}"
                                    wire:click="toggleGalleryEligibility({{ $gallery['id'] }})"
                                    aria-pressed="{{ $gallery['eligible'] ? 'true' : 'false' }}"
                                >{{ $gallery['eligible'] ? 'Used on Home' : 'Not used on Home' }}</button>
                            </div>
                            <div class="admin-list__count">
                                <strong>{{ $gallery['published_artworks'] }}</strong>
                                <span>published · newest {{ $gallery['newest_year'] ?: '—' }}</span>
                            </div>
                            <div class="admin-toolbar">
                                <a class="admin-action" href="{{ $gallery['workspace_url'] }}">Open Gallery</a>
                            </div>
                        </article>
                    @endforeach
                </x-admin.list>
            @else
                <x-admin.empty-state kicker="No Galleries" title="No Gallery sources exist">
                    <p>Create a Gallery from Pages before configuring homepage sources.</p>
                </x-admin.empty-state>
            @endif
        </x-admin.section>

        @if ($newestEligibleArtworks !== [])
            <x-admin.section kicker="Candidates" title="Newest eligible artworks">
                <x-admin.list>
                    @foreach ($newestEligibleArtworks as $artwork)
                        <article class="admin-list__row" wire:key="home-candidate-{{ $artwork['id'] }}">
                            <div class="admin-list__identity">
                                <span class="admin-list__eyebrow">Artwork</span>
                                <strong>{{ $artwork['title'] }}</strong>
                                <span>
                                    {{ $artwork['gallery'] }}
                                    @if ($artwork['date']) · {{ $artwork['date'] }}@elseif ($artwork['year']) · {{ $artwork['year'] }}@endif
                                </span>
                            </div>
                            <div class="admin-list__meta">
                                <span>Published</span>
                                @if ($artwork['featured'])<span>Explicit tie-breaker</span>@endif
                            </div>
                            <div class="admin-list__media">
                                @if ($artwork['thumbnail_url'])
                                    <img src="{{ $artwork['thumbnail_url'] }}" alt="" width="64" height="48" loading="lazy" decoding="async">
                                @else
                                    <span>No image</span>
                                @endif
                            </div>
                            <div class="admin-toolbar">
                                <a class="admin-action" href="{{ $artwork['edit_url'] }}">Edit</a>
                            </div>
                        </article>
                    @endforeach
                </x-admin.list>
            </x-admin.section>
        @endif
    </x-admin.workspace>
</x-filament-panels::page>
