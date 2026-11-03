<div x-data="{ adding: false }">
    <x-admin.table class="admin-table--data admin-table--ranked" aria-label="Material presets">
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
                    <th scope="col">Material</th>
                    <th scope="col" class="admin-table__actions"><span class="admin-row-actions-heading">Actions</span></th>
                </tr>
            </thead>
            <tbody wire:sort="sortMaterialPreset">
                @forelse ($presets as $preset)
                    <tr wire:key="material-preset-{{ $preset->getKey() }}" wire:sort:item="{{ $preset->getKey() }}">
                        <td class="admin-table__position"><span class="admin-position">{{ $loop->iteration }}</span></td>
                        <td class="admin-table__drag">
                            <button class="admin-drag-handle" type="button" wire:sort:handle title="Drag to reorder"
                                aria-label="Drag material {{ html_entity_decode((string) $preset->name, ENT_QUOTES | ENT_HTML5, 'UTF-8') }} to reorder">⋮⋮</button>
                        </td>
                        <td>
                            <input class="admin-inline-input" type="text" maxlength="240"
                                value="{{ html_entity_decode((string) $preset->name, ENT_QUOTES | ENT_HTML5, 'UTF-8') }}"
                                aria-label="Material name {{ $loop->iteration }}"
                                wire:change="renameMaterialPreset({{ $preset->getKey() }}, $event.target.value)"
                                x-on:keydown.enter.prevent="$el.blur()">
                        </td>
                        <td class="admin-table__actions">
                            <x-admin.toolbar class="admin-row-actions admin-row-actions--canonical admin-row-actions--stable admin-row-actions--move-delete">
                                <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::MoveUp"
                                    wire:click="moveMaterialPreset({{ $preset->getKey() }}, 'up')"
                                    :disabled="$loop->first" />
                                <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::MoveDown"
                                    wire:click="moveMaterialPreset({{ $preset->getKey() }}, 'down')"
                                    :disabled="$loop->last" />
                                <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::Delete"
                                    wire:click="deleteMaterialPreset({{ $preset->getKey() }})" />
                            </x-admin.toolbar>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="admin-table__empty-cell"><x-admin.empty-state title="No materials yet" minimal /></td></tr>
                @endforelse
            </tbody>
            <tbody x-show="adding" x-cloak>
                <tr>
                    <td colspan="2"></td>
                    <td>
                        <input class="admin-inline-input" type="text" maxlength="240" x-ref="newMaterial"
                            aria-label="New material name" placeholder="Material name"
                            x-on:keydown.enter.prevent="$el.blur()"
                            x-on:keydown.escape.prevent="adding = false; $el.value = ''"
                            x-on:change="$wire.addMaterialPreset($el.value).then(saved => { if (saved) { adding = false; $el.value = '' } })">
                    </td>
                    <td class="admin-table__actions"></td>
                </tr>
            </tbody>
        </table>
    </x-admin.table>
    <x-admin.add-row x-on:click="adding = true; $nextTick(() => $refs.newMaterial.focus())">Add material</x-admin.add-row>
</div>
