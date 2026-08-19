<x-filament-panels::page>
    <div class="artist-workspace artist-media-library">
        <header class="artist-workspace__head">
            <div>
                <p class="artist-workspace__kicker">Library</p>
                <h2>Media library</h2>
                <p>A visual contact sheet for reusable site media. Usage and accessibility state stay visible without exposing storage hashes or implementation paths.</p>
            </div>
            <div class="artist-workspace__summary">
                <div><strong>{{ $total }}</strong><span>Shown</span></div>
                <div><strong>{{ $inUse }}</strong><span>In use</span></div>
                <div><strong>{{ $unused }}</strong><span>Unused</span></div>
            </div>
        </header>

        <div class="artist-media-library__toolbar">
            <div class="artist-media-library__filters" aria-label="Media usage filter">
                <button class="artist-action {{ $filter === 'all' ? 'is-primary' : '' }}" type="button" wire:click="showFilter('all')">All</button>
                <button class="artist-action {{ $filter === 'used' ? 'is-primary' : '' }}" type="button" wire:click="showFilter('used')">In use</button>
                <button class="artist-action {{ $filter === 'unused' ? 'is-primary' : '' }}" type="button" wire:click="showFilter('unused')">Unused</button>
            </div>
            <label class="artist-media-library__search"><span>Search</span><input type="search" wire:model.live.debounce.350ms="search" placeholder="Filename, ALT or credit"></label>
        </div>

        @if ($assets !== [])
            <section class="artist-media-sheet" aria-label="Media assets">
                @foreach ($assets as $asset)
                    <article class="artist-media-sheet__item" wire:key="media-asset-{{ $asset['id'] }}">
                        <div class="artist-media-sheet__image">
                            @if ($asset['thumbnail_url'])
                                <img src="{{ $asset['thumbnail_url'] }}" alt="" loading="lazy" decoding="async" @if($asset['thumbnail_width']) width="{{ $asset['thumbnail_width'] }}" @endif @if($asset['thumbnail_height']) height="{{ $asset['thumbnail_height'] }}" @endif>
                            @else
                                <span>No preview</span>
                            @endif
                            @if ($asset['alt_missing'])<span class="artist-media-sheet__flag">ALT missing</span>@endif
                        </div>
                        <div class="artist-media-sheet__caption">
                            <strong>{{ $asset['filename'] }}</strong>
                            <span>{{ $asset['usage_label'] }}</span>
                            <small>{{ $asset['dimensions'] }} · {{ $asset['size'] }}</small>
                        </div>
                        <div class="artist-media-sheet__actions">
                            <a class="artist-action is-primary" href="{{ $asset['edit_url'] }}">Edit</a>
                            @if ($asset['preview_url'])<a class="artist-action" href="{{ $asset['preview_url'] }}" target="_blank" rel="noopener">Preview</a>@endif
                        </div>
                    </article>
                @endforeach
            </section>
        @else
            <section class="artist-gallery-empty"><p class="artist-workspace__kicker">No matches</p><h3>No media in this view</h3><p>Change the usage filter or search, or upload a new image.</p></section>
        @endif

        <footer class="artist-media-library__pager">
            <button class="artist-action" type="button" wire:click="previousPage" @disabled($page <= 1)>Previous</button>
            <span>Page {{ $page }} of {{ $pages }}</span>
            <button class="artist-action" type="button" wire:click="nextPage" @disabled($page >= $pages)>Next</button>
        </footer>
    </div>
</x-filament-panels::page>
