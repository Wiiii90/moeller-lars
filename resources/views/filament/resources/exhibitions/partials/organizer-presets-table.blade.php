<div>
    <x-admin.table class="admin-table--data admin-table--ranked" aria-label="Exhibition organizers">
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
                    <th scope="col">Organizer</th>
                    <th scope="col">City</th>
                    <th scope="col">Website</th>
                    <th scope="col" class="admin-table__actions"><span class="admin-row-actions-heading">Actions</span></th>
                </tr>
            </thead>
            <tbody wire:sort="sortExhibitionOrganizerPreset">
                @forelse ($organizers as $organizer)
                    <tr wire:key="exhibition-organizer-{{ $organizer->getKey() }}" wire:sort:item="{{ $organizer->getKey() }}">
                        <td class="admin-table__position"><span class="admin-position">{{ $loop->iteration }}</span></td>
                        <td class="admin-table__drag">
                            <button class="admin-drag-handle" type="button" wire:sort:handle
                                aria-label="Drag organizer {{ $organizer->name }} to reorder">⋮⋮</button>
                        </td>
                        <td>{{ $organizer->name }}</td>
                        <td>{{ $organizer->city ?: '—' }}</td>
                        <td>
                            @if ($organizer->website)
                                <a href="{{ $organizer->website }}" target="_blank" rel="noopener noreferrer">
                                    {{ parse_url($organizer->website, PHP_URL_HOST) ?: $organizer->website }}
                                </a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="admin-table__actions">
                            <x-admin.toolbar class="admin-row-actions admin-row-actions--canonical admin-row-actions--stable admin-row-actions--move-delete">
                                <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::Edit"
                                    wire:click="mountAction('editExhibitionOrganizer', { organizer: {{ $organizer->getKey() }} })" />
                                <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::MoveUp"
                                    wire:click="moveExhibitionOrganizerPreset({{ $organizer->getKey() }}, 'up')"
                                    :disabled="$loop->first" />
                                <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::MoveDown"
                                    wire:click="moveExhibitionOrganizerPreset({{ $organizer->getKey() }}, 'down')"
                                    :disabled="$loop->last" />
                                <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::Delete"
                                    wire:click="deleteExhibitionOrganizerPreset({{ $organizer->getKey() }})" />
                            </x-admin.toolbar>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="admin-table__empty-cell"><x-admin.empty-state title="Empty" minimal /></td></tr>
                @endforelse
            </tbody>
        </table>
    </x-admin.table>
    <x-admin.add-row wire:click="mountAction('addExhibitionOrganizer')">Add organizer</x-admin.add-row>
</div>
