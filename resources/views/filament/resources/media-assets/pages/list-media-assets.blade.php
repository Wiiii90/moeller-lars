<x-filament-panels::page>
    <link rel="stylesheet" href="{{ asset('css/admin-media.css') }}">

    <x-admin.workspace kicker="Media" title="Find, reuse and manage media" class="media-workspace">
        <x-slot:summary>
            <div><strong>{{ $total }}</strong><span>Matches</span></div>
            <div><strong>{{ $inUse }}</strong><span>Referenced</span></div>
            <div><strong>{{ $unused }}</strong><span>Unreferenced</span></div>
        </x-slot:summary>

        <section class="media-workspace__controls" aria-label="Media search and filters">
            <label class="media-workspace__search">
                <span>Search media</span>
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Filename, ALT, credit, copyright or MIME">
            </label>

            <div class="media-workspace__filters">
                <label><span>Type</span><select wire:model.live="type">
                    <option value="all">All types</option>
                    <option value="image">All images</option>
                    <option value="video">All video</option>
                    <option value="image/jpeg">JPEG</option>
                    <option value="image/png">PNG</option>
                    <option value="image/webp">WebP</option>
                    <option value="video/mp4">MP4</option>
                    <option value="video/webm">WebM</option>
                </select></label>
                <label><span>Reference</span><select wire:model.live="usage">
                    <option value="all">Any usage</option>
                    <option value="used">Referenced</option>
                    <option value="unused">Unreferenced</option>
                </select></label>
                <label><span>Context</span><select wire:model.live="context">
                    <option value="all">Any context</option>
                    <option value="artwork">Artworks / galleries</option>
                    <option value="exhibition">Exhibitions</option>
                    <option value="vita">Vita / CV</option>
                    <option value="blog">Blog</option>
                    <option value="identity">Site identity</option>
                    <option value="unassigned">Unassigned library</option>
                </select></label>
                <label><span>Status</span><select wire:model.live="state">
                    <option value="available">Available</option>
                    <option value="all">All states</option>
                    <option value="quarantined">Quarantined</option>
                    <option value="deleted">Deleted</option>
                </select></label>
                <button class="artist-action" type="button" wire:click="resetFilters">Reset</button>
            </div>

            <div class="media-workspace__view-switcher" aria-label="Media view mode">
                <span>View</span>
                <button class="artist-action {{ $viewMode === 'list' ? 'is-primary' : '' }}" type="button" wire:click="setViewMode('list')">List</button>
                <button class="artist-action {{ $viewMode === 'grid' ? 'is-primary' : '' }}" type="button" wire:click="setViewMode('grid')">Grid</button>
                <button class="artist-action {{ $viewMode === 'dense' ? 'is-primary' : '' }}" type="button" wire:click="setViewMode('dense')">Dense</button>
            </div>
        </section>

        @if ($assets !== [])
            @if ($viewMode === 'grid')
                <section class="media-workspace__grid" aria-label="Media assets grid">
                    @foreach ($assets as $asset)
                        <article class="media-workspace__grid-item" wire:key="media-grid-{{ $asset['id'] }}">
                            <a class="media-workspace__visual" href="{{ $asset['preview_url'] }}">
                                @if ($asset['thumbnail_url'])
                                    <img
                                        src="{{ $asset['thumbnail_url'] }}"
                                        alt=""
                                        loading="lazy"
                                        decoding="async"
                                        @if ($asset['thumbnail_width'])
                                            width="{{ $asset['thumbnail_width'] }}"
                                        @endif
                                        @if ($asset['thumbnail_height'])
                                            height="{{ $asset['thumbnail_height'] }}"
                                        @endif
                                    >
                                @else
                                    <span>{{ strtoupper($asset['kind']) }}</span>
                                @endif
                                @if ($asset['shared'])
                                    <em>Shared</em>
                                @elseif ($asset['usage'] === 0)
                                    <em>Unreferenced</em>
                                @endif
                            </a>
                            <div class="media-workspace__grid-meta">
                                <strong title="{{ $asset['filename'] }}">{{ $asset['filename'] }}</strong>
                                <span>{{ $asset['type_label'] }} · {{ $asset['size'] }}</span>
                                <small>{{ $asset['usage_label'] }}</small>
                            </div>
                            <div class="media-workspace__actions">
                                <a class="artist-action" href="{{ $asset['preview_url'] }}">Preview</a>
                                <a class="artist-action" href="{{ $asset['edit_url'] }}">Edit</a>
                            </div>
                        </article>
                    @endforeach
                </section>
            @else
                <div class="media-workspace__table-wrap {{ $viewMode === 'dense' ? 'is-dense' : '' }}">
                    <table class="media-workspace__table">
                        <thead>
                            <tr>
                                @if ($viewMode === 'list')
                                    <th scope="col" class="media-workspace__thumb-head">Preview</th>
                                @endif
                                <th scope="col">Media</th>
                                <th scope="col">Type</th>
                                <th scope="col">Usage</th>
                                <th scope="col">Status</th>
                                <th scope="col">Size</th>
                                <th scope="col"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($assets as $asset)
                                <tr wire:key="media-row-{{ $asset['id'] }}">
                                    @if ($viewMode === 'list')
                                        <td class="media-workspace__thumb">
                                            <a href="{{ $asset['preview_url'] }}">
                                                @if ($asset['thumbnail_url'])
                                                    <img src="{{ $asset['thumbnail_url'] }}" alt="" loading="lazy" decoding="async">
                                                @else
                                                    <span>{{ strtoupper($asset['kind']) }}</span>
                                                @endif
                                            </a>
                                        </td>
                                    @endif
                                    <td class="media-workspace__identity">
                                        <a href="{{ $asset['preview_url'] }}"><strong>{{ $asset['filename'] }}</strong></a>
                                        <small>
                                            @if ($asset['credit'] !== '')
                                                {{ $asset['credit'] }} ·
                                            @endif
                                            {{ $asset['created'] }}
                                            @if ($asset['alt_missing'])
                                                · ALT missing
                                            @endif
                                        </small>
                                    </td>
                                    <td>
                                        <strong class="media-workspace__type">{{ $asset['type_label'] }}</strong>
                                        @if ($asset['dimensions'] !== '—')
                                            <small>{{ $asset['dimensions'] }}</small>
                                        @endif
                                    </td>
                                    <td><span class="media-workspace__usage {{ $asset['usage'] === 0 ? 'is-unused' : '' }}">{{ $asset['shared'] ? 'Shared · ' : '' }}{{ $asset['usage_label'] }}</span></td>
                                    <td><span class="media-workspace__state is-{{ $asset['state'] }}">{{ ucfirst($asset['state']) }}</span></td>
                                    <td class="media-workspace__size">{{ $asset['size'] }}</td>
                                    <td class="media-workspace__actions">
                                        <a class="artist-action" href="{{ $asset['preview_url'] }}">Preview</a>
                                        <a class="artist-action" href="{{ $asset['edit_url'] }}">Edit</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @else
            <section class="artist-gallery-empty">
                <p class="artist-workspace__kicker">No matches</p>
                <h3>No media in this view</h3>
                <p>Change the filters or search, or upload a new supported media asset.</p>
                <button class="artist-action" type="button" wire:click="resetFilters">Clear filters</button>
            </section>
        @endif

        <footer class="media-workspace__pager">
            <button class="artist-action" type="button" wire:click="previousPage" @disabled($page <= 1)>Previous</button>
            <span>Page {{ $page }} of {{ $pages }}</span>
            <button class="artist-action" type="button" wire:click="nextPage" @disabled($page >= $pages)>Next</button>
        </footer>
    </x-admin.workspace>
</x-filament-panels::page>
