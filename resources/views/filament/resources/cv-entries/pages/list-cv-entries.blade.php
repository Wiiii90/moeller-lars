<x-filament-panels::page>
    @php($published = collect($entries)->where('state', 'published')->count())

    <x-admin.workspace kicker="Vita / CV" title="Editorial sequence">
        <x-slot:summary>
            <div><strong>{{ count($entries) }}</strong><span>Entries</span></div>
            <div><strong>{{ $published }}</strong><span>Published</span></div>
        </x-slot:summary>

        @if ($entries !== [])
            <x-admin.list aria-label="Vita / CV entries">
                @foreach ($entries as $entry)
                    <article class="admin-list__row" wire:key="cv-entry-{{ $entry['id'] }}">
                        <div class="admin-list__identity">
                            <span class="admin-list__eyebrow">{{ $entry['section'] }}</span>
                            <strong>{{ $entry['title'] }}</strong>
                            <span>{{ $entry['meta'] }}</span>
                        </div>
                        <div class="admin-list__meta">
                            <span>{{ ucfirst($entry['state']) }}</span>
                        </div>
                        <div class="admin-list__count">
                            <strong>{{ $entry['date'] }}</strong>
                            <span>Date</span>
                        </div>
                        <x-admin.toolbar>
                            <a class="admin-action is-primary" href="{{ $entry['edit_url'] }}">Edit</a>
                            @if ($entry['public_url'])<a class="admin-action" href="{{ $entry['public_url'] }}" target="_blank" rel="noopener">View</a>@endif
                            <span class="admin-toolbar" aria-label="Reorder {{ $entry['title'] }}">
                                <button class="admin-action" type="button" wire:click="moveEntry({{ $entry['id'] }}, 'up')" @disabled(! $entry['can_move_up']) aria-label="Move {{ $entry['title'] }} earlier">↑</button>
                                <button class="admin-action" type="button" wire:click="moveEntry({{ $entry['id'] }}, 'down')" @disabled(! $entry['can_move_down']) aria-label="Move {{ $entry['title'] }} later">↓</button>
                            </span>
                        </x-admin.toolbar>
                    </article>
                @endforeach
            </x-admin.list>
        @else
            <x-admin.empty-state kicker="Empty Vita" title="Add the first entry">
                <p>Start with a biography, education item, award or other Vita entry.</p>
            </x-admin.empty-state>
        @endif
    </x-admin.workspace>
</x-filament-panels::page>
