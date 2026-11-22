<div>
    <x-admin.table class="admin-table--data admin-table--ranked" aria-label="Exhibition venues">
        <table class="admin-table--six-grid">
            <colgroup>
                <col class="admin-table__col-position">
                <col class="admin-table__col-drag">
                <col>
                <col>
                <col>
                <col class="admin-table__col-actions-move-delete">
            </colgroup>
            <thead>
                <tr>
                    <th scope="colgroup" colspan="2" class="admin-table__ordering-heading">Position</th>
                    <th scope="col">Venue</th>
                    <th scope="col">Address</th>
                    <th scope="col">Website</th>
                    <th scope="col" class="admin-table__actions"><span class="admin-row-actions-heading">Actions</span></th>
                </tr>
            </thead>
            <tbody wire:sort="sortExhibitionVenuePreset">
                @forelse ($venues as $venue)
                    <tr wire:key="exhibition-venue-{{ $venue->getKey() }}" wire:sort:item="{{ $venue->getKey() }}">
                        <td class="admin-table__position"><span class="admin-position">{{ $loop->iteration }}</span></td>
                        <td class="admin-table__drag">
                            <button class="admin-drag-handle" type="button" wire:sort:handle
                                aria-label="Drag venue {{ $venue->name }} to reorder">⋮⋮</button>
                        </td>
                        <td>{{ $venue->name }}</td>
                        <td>{{ collect([$venue->street, $venue->postal_code, $venue->city, $venue->country])->filter()->implode(', ') ?: '—' }}</td>
                        <td>
                            @if ($venue->website)
                                <a href="{{ $venue->website }}" target="_blank" rel="noopener noreferrer">
                                    {{ parse_url($venue->website, PHP_URL_HOST) ?: $venue->website }}
                                </a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="admin-table__actions">
                            <x-admin.toolbar class="admin-row-actions admin-row-actions--canonical admin-row-actions--stable admin-row-actions--move-delete">
                                <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::Edit"
                                    wire:click="mountAction('editExhibitionVenue', { venue: {{ $venue->getKey() }} })" />
                                <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::MoveUp"
                                    wire:click="moveExhibitionVenuePreset({{ $venue->getKey() }}, 'up')"
                                    :disabled="$loop->first" />
                                <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::MoveDown"
                                    wire:click="moveExhibitionVenuePreset({{ $venue->getKey() }}, 'down')"
                                    :disabled="$loop->last" />
                                <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::Delete"
                                    wire:click="deleteExhibitionVenuePreset({{ $venue->getKey() }})" />
                            </x-admin.toolbar>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="admin-table__empty-cell"><x-admin.empty-state title="Empty" minimal /></td></tr>
                @endforelse
            </tbody>
        </table>
    </x-admin.table>
    <x-admin.add-row wire:click="mountAction('addExhibitionVenue')">Add venue</x-admin.add-row>
</div>
