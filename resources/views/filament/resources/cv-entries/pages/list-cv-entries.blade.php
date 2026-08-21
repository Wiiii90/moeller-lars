<x-filament-panels::page>
    @php($published = collect($entries)->where('state', 'published')->count())

    <div class="artist-workspace">
        <header class="artist-workspace__head">
            <div>
                <p class="artist-workspace__kicker">Vita / CV</p>
                <h2>Editorial sequence</h2>
            </div>
            <div class="artist-workspace__summary">
                <div><strong>{{ count($entries) }}</strong><span>Entries</span></div>
                <div><strong>{{ $published }}</strong><span>Published</span></div>
            </div>
        </header>

        @if ($entries !== [])
            <section class="artist-section-list" aria-label="Vita / CV entries">
                @foreach ($entries as $entry)
                    <article class="artist-section" wire:key="cv-entry-{{ $entry['id'] }}">
                        <div class="artist-section__identity">
                            <span class="artist-section__type">{{ $entry['section'] }}</span>
                            <strong>{{ $entry['title'] }}</strong>
                            <span class="artist-section__path">{{ $entry['meta'] }}</span>
                        </div>
                        <div class="artist-section__state">
                            <span class="{{ $entry['state'] === 'published' ? 'is-published' : '' }}">{{ ucfirst($entry['state']) }}</span>
                        </div>
                        <div class="artist-section__count">
                            <strong>{{ $entry['date'] }}</strong>
                            <span>Date</span>
                        </div>
                        <div class="artist-section__actions">
                            <a class="artist-action is-primary" href="{{ $entry['edit_url'] }}">Edit</a>
                            @if ($entry['public_url'])<a class="artist-action" href="{{ $entry['public_url'] }}" target="_blank" rel="noopener">View</a>@endif
                            <span class="artist-section__order" aria-label="Reorder {{ $entry['title'] }}">
                                <button class="artist-action" type="button" wire:click="moveEntry({{ $entry['id'] }}, 'up')" @disabled(! $entry['can_move_up']) aria-label="Move {{ $entry['title'] }} earlier">↑</button>
                                <button class="artist-action" type="button" wire:click="moveEntry({{ $entry['id'] }}, 'down')" @disabled(! $entry['can_move_down']) aria-label="Move {{ $entry['title'] }} later">↓</button>
                            </span>
                        </div>
                    </article>
                @endforeach
            </section>
        @else
            <section class="artist-gallery-empty"><p class="artist-workspace__kicker">Empty Vita</p><h3>Add the first entry</h3><p>Start with a biography, education item, award or other Vita entry.</p></section>
        @endif

        <footer class="artist-workspace__footnote"><span>Order controls change the public Vita sequence.</span><span>Contact and legal details are managed separately from entry content.</span></footer>
    </div>
</x-filament-panels::page>
