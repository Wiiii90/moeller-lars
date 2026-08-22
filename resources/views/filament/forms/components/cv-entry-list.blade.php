<div class="admin-component-records">
    <div class="admin-toolbar">
        <a class="admin-action is-primary" href="{{ $createUrl }}">Add CV entry</a>
    </div>

    @if ($entries !== [])
        <x-admin.list aria-label="CV entries">
            @foreach ($entries as $entry)
                <article class="admin-list__row" wire:key="custom-page-cv-entry-{{ $entry['id'] }}">
                    <div class="admin-list__identity">
                        <span class="admin-list__eyebrow">{{ $entry['year'] }}</span>
                        <strong>{{ $entry['title'] }}</strong>
                    </div>
                    <div class="admin-list__meta">
                        <span>{{ ucfirst($entry['state']) }}</span>
                    </div>
                    <x-admin.toolbar>
                        <a class="admin-action is-primary" href="{{ $entry['edit_url'] }}">Edit</a>
                        <button
                            class="admin-action"
                            type="button"
                            wire:click="moveCvEntry({{ $entry['id'] }}, 'up')"
                            @disabled(! $entry['can_move_up'])
                            aria-label="Move {{ $entry['title'] }} earlier"
                        >↑</button>
                        <button
                            class="admin-action"
                            type="button"
                            wire:click="moveCvEntry({{ $entry['id'] }}, 'down')"
                            @disabled(! $entry['can_move_down'])
                            aria-label="Move {{ $entry['title'] }} later"
                        >↓</button>
                        <button
                            class="admin-action is-danger"
                            type="button"
                            wire:click="removeCvEntry({{ $entry['id'] }})"
                            wire:confirm="Remove this CV entry? Its Media asset will be kept."
                        >Remove</button>
                    </x-admin.toolbar>
                </article>
            @endforeach
        </x-admin.list>
    @else
        <p class="admin-muted">No CV entries yet.</p>
    @endif
</div>
