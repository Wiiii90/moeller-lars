<div x-data="{ adding: false }">
    <x-admin.table class="admin-table--data admin-table--ranked" aria-label="Exhibition organizers">
        <table class="admin-table--six-grid">
            <colgroup>
                <col class="admin-table__col-position">
                <col class="admin-table__col-drag">
                <col>
                <col class="admin-table__col-actions-move-delete">
            </colgroup>
            <thead>
                <tr>
                    <th scope="colgroup" colspan="2" class="admin-table__ordering-heading">Position</th>
                    <th scope="col">Organizer</th>
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
                        <td>
                            <input class="admin-inline-input" type="text" maxlength="240"
                                value="{{ $organizer->name }}"
                                aria-label="Organizer name {{ $loop->iteration }}"
                                wire:change="renameExhibitionOrganizerPreset({{ $organizer->getKey() }}, $event.target.value)"
                                x-on:keydown.enter.prevent="$el.blur()">
                        </td>
                        <td class="admin-table__actions">
                            <x-admin.toolbar class="admin-row-actions admin-row-actions--canonical admin-row-actions--stable admin-row-actions--move-delete">
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
                    <tr><td colspan="4" class="admin-table__empty-cell"><x-admin.empty-state title="Empty" minimal /></td></tr>
                @endforelse
            </tbody>
            <tbody x-show="adding" x-cloak>
                <tr>
                    <td colspan="2"></td>
                    <td>
                        <input class="admin-inline-input" type="text" maxlength="240" x-ref="newOrganizer"
                            aria-label="New organizer name" placeholder="Organizer name"
                            x-on:keydown.enter.prevent="$el.blur()"
                            x-on:keydown.escape.prevent="adding = false; $el.value = ''"
                            x-on:change="$wire.addExhibitionOrganizerPreset($el.value).then(saved => { if (saved) { adding = false; $el.value = '' } })">
                    </td>
                    <td class="admin-table__actions"></td>
                </tr>
            </tbody>
        </table>
    </x-admin.table>
    <x-admin.add-row x-on:click="adding = true; $nextTick(() => $refs.newOrganizer.focus())">Add organizer</x-admin.add-row>
</div>
