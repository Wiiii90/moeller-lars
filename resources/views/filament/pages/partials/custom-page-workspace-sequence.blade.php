        <section class="custom-page-component-sequence admin-hierarchy" aria-label="Page component sequence">
            <div class="custom-page-component-sequence__header admin-hierarchy__header">
                <span class="admin-hierarchy__ordering-heading">Position</span>
                <span>Component</span>
                <span>Content</span>
                <span class="custom-page-component-sequence__actions-heading">Actions</span>
                <label class="admin-hierarchy__selection admin-hierarchy__selection--trailing" aria-label="Select all visible components and entries">
                    <input
                        type="checkbox"
                        wire:change="selectAllVisible($event.target.checked)"
                        x-data
                        x-bind:checked="$wire.selectedComponentTargets.length + $wire.selectedChildTargets.length === {{ $visibleSelectableCount }} && {{ $visibleSelectableCount }} > 0"
                        x-effect="$el.indeterminate = ($wire.selectedComponentTargets.length + $wire.selectedChildTargets.length > 0) && ($wire.selectedComponentTargets.length + $wire.selectedChildTargets.length < {{ $visibleSelectableCount }})"
                    >
                </label>
            </div>

            @if ($components === [] && $unfilteredComponentCount === 0)
                <x-admin.empty-state title="No components" :minimal="true" />
            @elseif ($components === [])
                <x-admin.empty-state title="No matching components" :minimal="true">
                    <x-slot:actions>
                        <button class="admin-action" type="button" wire:click="resetComponentFilters">Clear filters</button>
                    </x-slot:actions>
                </x-admin.empty-state>
            @else
                <div class="custom-page-component-sequence__rows" @if ($reorderEnabled) wire:sort="sortComponent" @endif>
                    @foreach ($components as $pageComponent)
                        <article
                            class="custom-page-component admin-hierarchy__group"
                            wire:key="custom-component-{{ $pageComponent['target'] }}"
                            @if ($reorderEnabled) wire:sort:item="{{ $pageComponent['target'] }}" @endif
                        >
                            <div class="custom-page-component__header admin-hierarchy__row">
                                <div class="admin-hierarchy__position-cell">
                                    <span class="admin-position" aria-label="Position {{ $pageComponent['position'] }}">
                                        {{ str_pad((string) $pageComponent['position'], 2, '0', STR_PAD_LEFT) }}
                                    </span>
                                </div>

                                <button class="admin-drag-handle custom-page-component__drag" type="button" @if ($reorderEnabled) wire:sort:handle @else disabled @endif aria-label="Drag {{ $pageComponent['type_label'] }}">⋮⋮</button>

                                <div class="custom-page-component__type">
                                    <select
                                        class="admin-inline-select custom-page-component__type-select"
                                        aria-label="Component type"
                                        @disabled(! $reorderEnabled)
                                        wire:change="mountAction('changeComponentType', { componentIndex: {{ $pageComponent['index'] }}, componentType: '{{ $pageComponent['type'] }}', targetType: $event.target.value })"
                                    >
                                        @foreach ($componentTypeOptions as $value => $label)
                                            <option value="{{ $value }}" @selected($value === $pageComponent['type'])>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="custom-page-component__content admin-hierarchy__content">
                                    <strong>{{ $pageComponent['content']['primary'] }}</strong>
                                    @if ($pageComponent['content']['secondary'] !== '')
                                        <span>{{ $pageComponent['content']['secondary'] }}</span>
                                    @endif
                                    @if ($pageComponent['content']['meta'] !== '')
                                        <small>{{ $pageComponent['content']['meta'] }}</small>
                                    @endif
                                </div>

                                <div class="admin-row-actions admin-row-actions--canonical custom-page-row-actions custom-page-component__actions admin-toolbar">
                                    <x-admin.row-action
                                        :action="\App\Filament\Support\AdminRowAction::MoveUp"
                                        wire:click="moveComponent({{ $pageComponent['index'] }}, '{{ $pageComponent['type'] }}', 'up')"
                                        :disabled="! $reorderEnabled || ! $pageComponent['can_move_up']"
                                        aria-label="Move component up"
                                    />
                                    <x-admin.row-action
                                        :action="\App\Filament\Support\AdminRowAction::MoveDown"
                                        wire:click="moveComponent({{ $pageComponent['index'] }}, '{{ $pageComponent['type'] }}', 'down')"
                                        :disabled="! $reorderEnabled || ! $pageComponent['can_move_down']"
                                        aria-label="Move component down"
                                    />
                                    <x-admin.row-action
                                        :action="\App\Filament\Support\AdminRowAction::Edit"
                                        wire:click="mountAction('editComponent', { componentIndex: {{ $pageComponent['index'] }}, componentType: '{{ $pageComponent['type'] }}' })"
                                    />
                                    <x-admin.row-action
                                        :action="$pageComponent['published'] ? \App\Filament\Support\AdminRowAction::Unpublish : \App\Filament\Support\AdminRowAction::Publish"
                                        wire:click="setComponentPublished({{ $pageComponent['index'] }}, '{{ $pageComponent['type'] }}', {{ $pageComponent['published'] ? 'false' : 'true' }})"
                                    />
                                    <x-admin.row-action
                                        :action="\App\Filament\Support\AdminRowAction::Delete"
                                        wire:click="mountAction('deleteComponent', { componentIndex: {{ $pageComponent['index'] }}, componentType: '{{ $pageComponent['type'] }}' })"
                                    />
                                </div>

                                <label class="admin-hierarchy__selection admin-hierarchy__selection--trailing" aria-label="Select {{ $pageComponent['type_label'] }}">
                                    <input type="checkbox" value="{{ $pageComponent['target'] }}" wire:model.live="selectedComponentTargets">
                                </label>
                            </div>

                            @if ($pageComponent['children'] !== [])
                                <div class="custom-page-component__children admin-hierarchy__children">
                                    <div class="custom-page-component__children-rows admin-hierarchy__children-rows" @if ($reorderEnabled) wire:sort="sortChild" @endif>
                                        @foreach ($pageComponent['children'] as $child)
                                            @php
                                                $childKindLabel = match ($child['kind']) {
                                                    'list' => 'List item',
                                                    'contact' => 'Contact item',
                                                    default => ucfirst((string) $child['kind']),
                                                };
                                            @endphp
                                            <div
                                                class="custom-page-child-row admin-hierarchy__row is-child {{ ($child['parent_published'] ?? true) ? '' : 'is-parent-unpublished' }}"
                                                wire:key="child-{{ $pageComponent['target'] }}-{{ $child['key'] }}"
                                                @if ($reorderEnabled) wire:sort:item="{{ $child['target'] }}" @endif
                                            >
                                                <div class="admin-hierarchy__position-cell">
                                                    <span class="admin-position" aria-label="Position {{ $child['position'] }}">
                                                        {{ str_pad((string) $child['position'], 2, '0', STR_PAD_LEFT) }}
                                                    </span>
                                                </div>

                                                <button class="admin-drag-handle custom-page-child-row__drag" type="button" @if ($reorderEnabled) wire:sort:handle @else disabled @endif aria-label="Drag {{ $child['entry'] }}">⋮⋮</button>

                                                <span class="custom-page-child-row__kind">{{ $childKindLabel }}</span>

                                                <div class="custom-page-child-row__content admin-hierarchy__content">
                                                    @if ($child['kind'] === 'list')
                                                        <strong>
                                                            @if ($child['date'] !== '')
                                                                <span class="custom-page-child-row__date">{{ $child['date'] }}</span> ·
                                                            @endif
                                                            {{ $child['entry'] }}
                                                        </strong>
                                                        @if ($child['detail'] !== '')
                                                            <small>{{ $child['detail'] }}</small>
                                                        @endif
                                                    @else
                                                        <strong>{{ $child['entry'] }}</strong>
                                                        @if ($child['detail'] !== '')
                                                            <small>{{ $child['detail'] }}</small>
                                                        @endif
                                                    @endif
                                                </div>

                                                <div class="admin-row-actions admin-row-actions--canonical custom-page-row-actions custom-page-child-row__actions admin-toolbar">
                                                    @if ($child['kind'] === 'list')
                                                        <x-admin.row-action
                                                            :action="\App\Filament\Support\AdminRowAction::MoveUp"
                                                            wire:click="moveListEntry({{ $pageComponent['index'] }}, 'list', {{ $child['item_index'] }}, 'up')"
                                                            :disabled="! $child['can_move_up']"
                                                            aria-label="Move list entry up"
                                                        />
                                                        <x-admin.row-action
                                                            :action="\App\Filament\Support\AdminRowAction::MoveDown"
                                                            wire:click="moveListEntry({{ $pageComponent['index'] }}, 'list', {{ $child['item_index'] }}, 'down')"
                                                            :disabled="! $child['can_move_down']"
                                                            aria-label="Move list entry down"
                                                        />
                                                        <x-admin.row-action
                                                            :action="\App\Filament\Support\AdminRowAction::Edit"
                                                            wire:click="mountAction('editListEntry', { componentIndex: {{ $pageComponent['index'] }}, componentType: 'list', itemIndex: {{ $child['item_index'] }} })"
                                                        />
                                                        <x-admin.row-action
                                                            :action="$child['published'] ? \App\Filament\Support\AdminRowAction::Unpublish : \App\Filament\Support\AdminRowAction::Publish"
                                                            wire:click="setListEntryPublished({{ $pageComponent['index'] }}, 'list', {{ $child['item_index'] }}, {{ $child['published'] ? 'false' : 'true' }})"
                                                        />
                                                        <x-admin.row-action
                                                            :action="\App\Filament\Support\AdminRowAction::Delete"
                                                            wire:click="mountAction('deleteListEntry', { componentIndex: {{ $pageComponent['index'] }}, componentType: 'list', itemIndex: {{ $child['item_index'] }} })"
                                                        />
                                                    @elseif ($child['kind'] === 'contact')
                                                        <x-admin.row-action
                                                            :action="\App\Filament\Support\AdminRowAction::MoveUp"
                                                            wire:click="moveContactChild({{ $pageComponent['index'] }}, 'contact', '{{ $child['child_type'] }}', 'up')"
                                                            :disabled="! $child['can_move_up']"
                                                            aria-label="Move Contact child up"
                                                        />
                                                        <x-admin.row-action
                                                            :action="\App\Filament\Support\AdminRowAction::MoveDown"
                                                            wire:click="moveContactChild({{ $pageComponent['index'] }}, 'contact', '{{ $child['child_type'] }}', 'down')"
                                                            :disabled="! $child['can_move_down']"
                                                            aria-label="Move Contact child down"
                                                        />
                                                        <x-admin.row-action
                                                            :action="\App\Filament\Support\AdminRowAction::Edit"
                                                            wire:click="mountAction('editContactChild', { componentIndex: {{ $pageComponent['index'] }}, componentType: 'contact', childType: '{{ $child['child_type'] }}' })"
                                                        />
                                                        <x-admin.row-action
                                                            :action="$child['published'] ? \App\Filament\Support\AdminRowAction::Unpublish : \App\Filament\Support\AdminRowAction::Publish"
                                                            wire:click="setContactChildPublished({{ $pageComponent['index'] }}, 'contact', '{{ $child['child_type'] }}', {{ $child['published'] ? 'false' : 'true' }})"
                                                        />
                                                        <x-admin.row-action
                                                            :action="\App\Filament\Support\AdminRowAction::Delete"
                                                            wire:click="mountAction('deleteContactChild', { componentIndex: {{ $pageComponent['index'] }}, componentType: 'contact', childType: '{{ $child['child_type'] }}' })"
                                                        />
                                                    @endif
                                                </div>

                                                <label class="admin-hierarchy__selection admin-hierarchy__selection--trailing" aria-label="Select {{ $child['entry'] }}">
                                                    <input type="checkbox" value="{{ $child['target'] }}" wire:model.live="selectedChildTargets">
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @elseif ($pageComponent['is_list'])
                                <div class="custom-page-component__children-empty">No list entries</div>
                            @elseif ($pageComponent['is_contact'])
                                <div class="custom-page-component__children-empty">No contact items</div>
                            @endif

                            @if ($pageComponent['is_list'])
                                <button class="custom-page-component-add-row custom-page-component-add-row--child" type="button" wire:click="mountAction('addListEntry', { componentIndex: {{ $pageComponent['index'] }}, componentType: 'list' })">
                                    <span aria-hidden="true">+</span>
                                    <strong>Add list item</strong>
                                </button>
                            @elseif ($pageComponent['is_contact'] && $pageComponent['contact_child_count'] < 3)
                                <button class="custom-page-component-add-row custom-page-component-add-row--child" type="button" wire:click="mountAction('addContactChild', { componentIndex: {{ $pageComponent['index'] }}, componentType: 'contact' })">
                                    <span aria-hidden="true">+</span>
                                    <strong>Add contact item</strong>
                                </button>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </section>