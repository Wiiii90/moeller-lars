<x-filament-panels::page>
    @php($published = collect($exhibitions)->where('state', 'published')->count())

    <div class="artist-workspace">
        <header class="artist-workspace__head">
            <div>
                <p class="artist-workspace__kicker">Exhibitions</p>
                <h2>Exhibition programme</h2>
            </div>
            <div class="artist-workspace__summary">
                <div><strong>{{ count($exhibitions) }}</strong><span>Exhibitions</span></div>
                <div><strong>{{ $published }}</strong><span>Published</span></div>
            </div>
        </header>

        @if ($exhibitions !== [])
            <section class="artist-section-list" aria-label="Exhibitions">
                @foreach ($exhibitions as $exhibition)
                    <article class="artist-section" wire:key="exhibition-{{ $exhibition['id'] }}">
                        <div class="artist-section__identity">
                            <span class="artist-section__type">{{ $exhibition['type'] }}</span>
                            <strong>{{ $exhibition['title'] }}</strong>
                            <span class="artist-section__path">{{ $exhibition['meta'] }}</span>
                        </div>
                        <div class="artist-section__state">
                            <span class="{{ $exhibition['state'] === 'published' ? 'is-published' : '' }}">{{ ucfirst($exhibition['state']) }}</span>
                        </div>
                        <div class="artist-section__count">
                            <strong>{{ $exhibition['date'] }}</strong>
                            <span>Date</span>
                        </div>
                        <div class="artist-section__actions">
                            <a class="artist-action is-primary" href="{{ $exhibition['edit_url'] }}">Edit</a>
                            @if ($exhibition['public_url'])<a class="artist-action" href="{{ $exhibition['public_url'] }}" target="_blank" rel="noopener">View</a>@endif
                            <span class="artist-section__order" aria-label="Reorder {{ $exhibition['title'] }}">
                                <button class="artist-action" type="button" wire:click="moveExhibition({{ $exhibition['id'] }}, 'up')" @disabled(! $exhibition['can_move_up']) aria-label="Move {{ $exhibition['title'] }} earlier">↑</button>
                                <button class="artist-action" type="button" wire:click="moveExhibition({{ $exhibition['id'] }}, 'down')" @disabled(! $exhibition['can_move_down']) aria-label="Move {{ $exhibition['title'] }} later">↓</button>
                            </span>
                        </div>
                    </article>
                @endforeach
            </section>
        @else
            <section class="artist-gallery-empty"><p class="artist-workspace__kicker">Empty programme</p><h3>Add the first exhibition</h3><p>Create an exhibition draft, add venue/media details and publish it when ready.</p></section>
        @endif
    </div>
</x-filament-panels::page>
