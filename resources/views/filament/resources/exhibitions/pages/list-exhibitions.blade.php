<x-filament-panels::page>
    @php($published = collect($exhibitions)->where('state', 'published')->count())

    <x-admin.workspace kicker="Exhibitions" title="Exhibition programme">
        <x-slot:summary>
            <div><strong>{{ count($exhibitions) }}</strong><span>Exhibitions</span></div>
            <div><strong>{{ $published }}</strong><span>Published</span></div>
        </x-slot:summary>

        @if ($exhibitions !== [])
            <x-admin.list aria-label="Exhibitions">
                @foreach ($exhibitions as $exhibition)
                    <article class="admin-list__row" wire:key="exhibition-{{ $exhibition['id'] }}">
                        <div class="admin-list__identity">
                            <span class="admin-list__eyebrow">{{ $exhibition['type'] }}</span>
                            <strong>{{ $exhibition['title'] }}</strong>
                            <span>{{ $exhibition['meta'] }}</span>
                        </div>
                        <div class="admin-list__meta">
                            <span>{{ ucfirst($exhibition['state']) }}</span>
                        </div>
                        <div class="admin-list__count">
                            <strong>{{ $exhibition['date'] }}</strong>
                            <span>Date</span>
                        </div>
                        <div class="admin-toolbar">
                            <a class="admin-action is-primary" href="{{ $exhibition['edit_url'] }}">Edit</a>
                            @if ($exhibition['public_url'])<a class="admin-action" href="{{ $exhibition['public_url'] }}" target="_blank" rel="noopener">View</a>@endif
                            <span class="admin-toolbar" aria-label="Reorder {{ $exhibition['title'] }}">
                                <button class="admin-action" type="button" wire:click="moveExhibition({{ $exhibition['id'] }}, 'up')" @disabled(! $exhibition['can_move_up']) aria-label="Move {{ $exhibition['title'] }} earlier">↑</button>
                                <button class="admin-action" type="button" wire:click="moveExhibition({{ $exhibition['id'] }}, 'down')" @disabled(! $exhibition['can_move_down']) aria-label="Move {{ $exhibition['title'] }} later">↓</button>
                            </span>
                        </div>
                    </article>
                @endforeach
            </x-admin.list>
        @else
            <x-admin.empty-state kicker="Empty programme" title="Add the first exhibition">
                <p>Create an exhibition draft, add venue/media details and publish it when ready.</p>
            </x-admin.empty-state>
        @endif
    </x-admin.workspace>
</x-filament-panels::page>
