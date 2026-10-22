<x-admin.table>
    <section class="custom-page-component-sequence home-component-sequence admin-hierarchy" aria-label="Home component sequence">
        <div class="custom-page-component-sequence__header admin-hierarchy__header">
            <span class="admin-hierarchy__ordering-heading">{{ __('Position') }}</span>
            <span>{{ __('Component') }}</span>
            <span>{{ __('Content') }}</span>
            <span class="admin-row-actions-heading">{{ __('Actions') }}</span>
            <label class="admin-hierarchy__selection admin-hierarchy__selection--trailing" aria-label="Select all visible Home items">
                <input
                    type="checkbox"
                    x-data
                    wire:click.prevent="toggleVisibleComponentSelection"
                    x-effect="
                        const targets = @js($visibleComponentTargets);
                        const childTargets = @js($visibleHomeChildTargets);
                        const selected = $wire.selectedComponentTargets;
                        const childSelected = $wire.selectedHomeChildTargets;
                        const count = targets.filter((target) => selected.includes(target)).length
                            + childTargets.filter((target) => childSelected.includes(target)).length;
                        $el.checked = (targets.length + childTargets.length) > 0
                            && count === (targets.length + childTargets.length);
                        $el.indeterminate = count > 0 && count < (targets.length + childTargets.length);
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
                    <button class="admin-action" type="button" wire:click="resetComponentFilters">{{ __('Clear filters') }}</button>
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

                            <div class="admin-row-actions admin-row-actions--canonical admin-row-actions--stable admin-row-actions--move-state-delete custom-page-component__actions admin-toolbar">
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
                                    wire:click="mountAction('{{ $homeComponent['type'] === 'list' ? 'editListComponent' : 'editComponent' }}', { index: {{ $homeComponent['index'] }}, type: '{{ $homeComponent['type'] }}' })"
                                    :disabled="! $homeComponent['editable']"
                                />
                                <x-admin.row-action
                                    :action="$homeComponent['published'] ? \App\Filament\Support\AdminRowAction::Unpublish : \App\Filament\Support\AdminRowAction::Publish"
                                    wire:click="setComponentPublished({{ $homeComponent['index'] }}, '{{ $homeComponent['type'] }}', {{ $homeComponent['published'] ? 'false' : 'true' }})"
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

                        @if ($homeComponent['children'] !== [])
                            <div class="custom-page-component__children admin-hierarchy__children">
                                <div class="custom-page-component__children-rows admin-hierarchy__children-rows"
                                    @if ($reorderEnabled && count($homeComponent['children']) > 1) wire:sort="sortHomeChild" @endif>
                                    @foreach ($homeComponent['children'] as $child)
                                        <div
                                            class="custom-page-child-row admin-hierarchy__row is-child {{ $child['parent_published'] ? '' : 'is-parent-unpublished' }}"
                                            wire:key="home-child-{{ $homeComponent['target'] }}-{{ $child['kind'] }}-{{ $child['key'] }}"
                                            @if ($reorderEnabled && count($homeComponent['children']) > 1) wire:sort:item="{{ $child['target'] }}" @endif
                                        >
                                            <div class="admin-hierarchy__position-cell">
                                                <span class="admin-position">{{ str_pad((string) $child['position'], 2, '0', STR_PAD_LEFT) }}</span>
                                            </div>
                                            <button class="admin-drag-handle custom-page-child-row__drag" type="button"
                                                @if ($reorderEnabled && count($homeComponent['children']) > 1) wire:sort:handle @else disabled @endif
                                                aria-label="Drag {{ $child['title'] }} to reorder">⋮⋮</button>
                                            <span class="custom-page-child-row__kind">{{ __('Contact item') }}</span>
                                            <div class="custom-page-child-row__content admin-hierarchy__content">
                                                <strong>{{ $child['title'] }}</strong>
                                                @if ($child['detail'] !== '') <small>{{ $child['detail'] }}</small> @endif
                                            </div>
                                            <div class="admin-row-actions admin-row-actions--canonical admin-row-actions--stable admin-row-actions--move-state-delete custom-page-child-row__actions admin-toolbar">
                                                <x-admin.row-action
                                                    :action="\App\Filament\Support\AdminRowAction::MoveUp"
                                                    :disabled="! $reorderEnabled || ! $child['can_move_up']"
                                                    wire:click="moveHomeChild({{ $homeComponent['index'] }}, '{{ $homeComponent['type'] }}', @js($child['key']), 'up')"
                                                />
                                                <x-admin.row-action
                                                    :action="\App\Filament\Support\AdminRowAction::MoveDown"
                                                    :disabled="! $reorderEnabled || ! $child['can_move_down']"
                                                    wire:click="moveHomeChild({{ $homeComponent['index'] }}, '{{ $homeComponent['type'] }}', @js($child['key']), 'down')"
                                                />
                                                <x-admin.row-action
                                                    :action="\App\Filament\Support\AdminRowAction::Edit"
                                                    wire:click="mountAction('editHomeChild', { index: {{ $homeComponent['index'] }}, type: '{{ $homeComponent['type'] }}', child: @js($child['key']) })"
                                                />
                                                <x-admin.row-action
                                                    :action="$child['published'] ? \App\Filament\Support\AdminRowAction::Unpublish : \App\Filament\Support\AdminRowAction::Publish"
                                                    wire:click="publishHomeChild({{ $homeComponent['index'] }}, '{{ $homeComponent['type'] }}', @js($child['key']), {{ $child['published'] ? 'false' : 'true' }})"
                                                />
                                                <x-admin.row-action
                                                    :action="\App\Filament\Support\AdminRowAction::Delete"
                                                    wire:click="mountAction('deleteHomeChild', { index: {{ $homeComponent['index'] }}, type: '{{ $homeComponent['type'] }}', child: @js($child['key']) })"
                                                />
                                            </div>
                                            <label class="admin-hierarchy__selection admin-hierarchy__selection--trailing" aria-label="Select {{ $child['title'] }}">
                                                <input type="checkbox" value="{{ $child['target'] }}" wire:model.live="selectedHomeChildTargets">
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @elseif ($homeComponent['type'] === 'contact')
                            <div class="custom-page-component__children-empty">{{ __('No contact items') }}</div>
                        @endif

                        @if ($homeComponent['type'] === 'contact' && count($homeComponent['children']) < 3)
                            <button class="custom-page-component-add-row custom-page-component-add-row--child" type="button"
                                wire:click="mountAction('addHomeChild', { index: {{ $homeComponent['index'] }}, type: 'contact' })">
                                <span aria-hidden="true">+</span><strong>{{ __('Add contact item') }}</strong>
                            </button>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</x-admin.table>
