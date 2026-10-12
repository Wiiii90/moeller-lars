<x-admin.table>
    <section class="custom-page-component-sequence home-component-sequence admin-hierarchy" aria-label="Home component sequence">
        <div class="custom-page-component-sequence__header admin-hierarchy__header">
            <span class="admin-hierarchy__ordering-heading">Position</span>
            <span>Component</span>
            <span>Content</span>
            <span class="admin-row-actions-heading">Actions</span>
            <label class="admin-hierarchy__selection admin-hierarchy__selection--trailing" aria-label="Select all visible Home components">
                <input
                    type="checkbox"
                    x-data
                    wire:click.prevent="toggleVisibleComponentSelection"
                    x-effect="
                        const targets = @js($visibleComponentTargets);
                        const selected = $wire.selectedComponentTargets;
                        const count = targets.filter((target) => selected.includes(target)).length;
                        $el.checked = targets.length > 0 && count === targets.length;
                        $el.indeterminate = count > 0 && count < targets.length;
                    "
                    @disabled($visibleComponentTargets === [])
                >
            </label>
        </div>

        @if ($components === [] && $componentDataset === [])
            <x-admin.empty-state title="No components" :minimal="true" />
        @elseif ($components === [])
            <x-admin.empty-state title="No matching components" :minimal="true">
                <x-slot:actions>
                    <button class="admin-action" type="button" wire:click="resetComponentFilters">Clear filters</button>
                </x-slot:actions>
            </x-admin.empty-state>
        @else
            <div class="custom-page-component-sequence__rows" @if ($reorderEnabled && count($components) > 1) wire:sort="sortComponent" @endif>
                @foreach ($components as $homeComponent)
                    <article
                        class="custom-page-component admin-hierarchy__group"
                        wire:key="home-component-{{ $template }}-{{ $homeComponent['target'] }}"
                        @if ($reorderEnabled && count($components) > 1) wire:sort:item="{{ $homeComponent['target'] }}" @endif
                    >
                        <div class="custom-page-component__header admin-hierarchy__row">
                            <div class="admin-hierarchy__position-cell">
                                <span class="admin-position" aria-label="Position {{ $homeComponent['position'] }}">
                                    {{ str_pad((string) $homeComponent['position'], 2, '0', STR_PAD_LEFT) }}
                                </span>
                            </div>

                            <button
                                class="admin-drag-handle custom-page-component__drag"
                                type="button"
                                @if ($reorderEnabled && count($components) > 1) wire:sort:handle @else disabled @endif
                                aria-label="Drag {{ $homeComponent['type_label'] }} to reorder"
                                title="{{ ! $reorderEnabled ? 'Clear filters and show all components to reorder' : (count($components) < 2 ? 'At least two components are needed to reorder' : 'Drag to reorder') }}"
                            >⋮⋮</button>

                            <div class="custom-page-component__type">
                                <select
                                    class="admin-inline-select custom-page-component__type-select"
                                    aria-label="Component type"
                                    wire:change="mountAction('changeComponentType', { index: {{ $homeComponent['index'] }}, type: '{{ $homeComponent['type'] }}', kind: '{{ $homeComponent['filter_type'] }}', targetKind: $event.target.value })"
                                >
                                    @foreach ($componentTypeOptions as $value => $label)
                                        <option value="{{ $value }}" @selected($value === $homeComponent['filter_type'])>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="custom-page-component__content admin-hierarchy__content">
                                <strong>{{ $homeComponent['content']['primary'] }}</strong>
                                @if ($homeComponent['content']['secondary'] !== '')
                                    <span>{{ $homeComponent['content']['secondary'] }}</span>
                                @endif
                            </div>

                            <div class="admin-row-actions admin-row-actions--canonical admin-row-actions--stable admin-row-actions--move-edit-delete custom-page-component__actions admin-toolbar">
                                <x-admin.row-action
                                    :action="\App\Filament\Support\AdminRowAction::MoveUp"
                                    wire:click="moveComponent({{ $homeComponent['index'] }}, '{{ $homeComponent['type'] }}', 'up')"
                                    :disabled="! $reorderEnabled || ! $homeComponent['can_move_up']"
                                    aria-label="Move {{ $homeComponent['type_label'] }} up"
                                />
                                <x-admin.row-action
                                    :action="\App\Filament\Support\AdminRowAction::MoveDown"
                                    wire:click="moveComponent({{ $homeComponent['index'] }}, '{{ $homeComponent['type'] }}', 'down')"
                                    :disabled="! $reorderEnabled || ! $homeComponent['can_move_down']"
                                    aria-label="Move {{ $homeComponent['type_label'] }} down"
                                />
                                <x-admin.row-action
                                    :action="\App\Filament\Support\AdminRowAction::Edit"
                                    wire:click="mountAction('editComponent', { index: {{ $homeComponent['index'] }}, type: '{{ $homeComponent['type'] }}' })"
                                    :disabled="! $homeComponent['editable']"
                                />
                                <x-admin.row-action
                                    :action="\App\Filament\Support\AdminRowAction::Delete"
                                    wire:click="mountAction('removeComponent', { index: {{ $homeComponent['index'] }}, type: '{{ $homeComponent['type'] }}' })"
                                />
                            </div>

                            <label class="admin-hierarchy__selection admin-hierarchy__selection--trailing" aria-label="Select {{ $homeComponent['type_label'] }}">
                                <input type="checkbox" value="{{ $homeComponent['target'] }}" wire:model.live="selectedComponentTargets">
                            </label>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</x-admin.table>
