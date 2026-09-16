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
                    @foreach ($components as $component)
                        <article
                            class="custom-page-component admin-hierarchy__group"
                            wire:key="custom-component-{{ $component['target'] }}"
                            @if ($reorderEnabled) wire:sort:item="{{ $component['target'] }}" @endif
                        >
                            <div class="custom-page-component__header admin-hierarchy__row">
                                <div class="admin-hierarchy__position-cell">
                                    <span class="admin-position" aria-label="Position {{ $component['position'] }}">
                                        {{ str_pad((string) $component['position'], 2, '0', STR_PAD_LEFT) }}
                                    </span>
                                </div>

                                <button class="admin-drag-handle custom-page-component__drag" type="button" @if ($reorderEnabled) wire:sort:handle @else disabled @endif aria-label="Drag {{ $component['type_label'] }}">⋮⋮</button>

                                <div class="custom-page-component__type">
                                    <select
                                        class="admin-inline-select custom-page-component__type-select"
                                        aria-label="Component type"
                                        @disabled(! $reorderEnabled)
                                        wire:change="mountAction('changeComponentType', { componentIndex: {{ $component['index'] }}, componentType: '{{ $component['type'] }}', targetType: $event.target.value })"
                                    >
                                        @foreach ($componentTypeOptions as $value => $label)
                                            <option value="{{ $value }}" @selected($value === $component['type'])>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="custom-page-component__content admin-hierarchy__content">
                                    <strong>{{ $component['content']['primary'] }}</strong>
                                    @if ($component['content']['secondary'] !== '')
                                        <span>{{ $component['content']['secondary'] }}</span>
                                    @endif
                                    @if ($component['content']['meta'] !== '')
                                        <small>{{ $component['content']['meta'] }}</small>
                                    @endif
                                </div>

                                <div class="admin-row-actions admin-row-actions--canonical custom-page-row-actions custom-page-component__actions admin-toolbar">
                                    <button class="admin-action admin-action--with-icon admin-order-action admin-order-action--labeled" type="button" wire:click="moveComponent({{ $component['index'] }}, '{{ $component['type'] }}', 'up')" @disabled(! $reorderEnabled || ! $component['can_move_up']) aria-label="Move component up">
                                        <x-filament::icon :icon="\App\Filament\Support\AdminIcon::MoveUp->mini()" class="admin-action__icon" />
                                        <span class="admin-action__label">Move up</span>
                                    </button>
                                    <button class="admin-action admin-action--with-icon admin-order-action admin-order-action--labeled" type="button" wire:click="moveComponent({{ $component['index'] }}, '{{ $component['type'] }}', 'down')" @disabled(! $reorderEnabled || ! $component['can_move_down']) aria-label="Move component down">
                                        <x-filament::icon :icon="\App\Filament\Support\AdminIcon::MoveDown->mini()" class="admin-action__icon" />
                                        <span class="admin-action__label">Move down</span>
                                    </button>
                                    <button class="admin-action admin-action--with-icon" type="button" wire:click="mountAction('editComponent', { componentIndex: {{ $component['index'] }}, componentType: '{{ $component['type'] }}' })">
                                        <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Edit->mini()" class="admin-action__icon" />
                                        <span class="admin-action__label">Edit</span>
                                    </button>
                                    <button class="admin-action admin-action--with-icon admin-action--state" type="button" wire:click="setComponentPublished({{ $component['index'] }}, '{{ $component['type'] }}', {{ $component['published'] ? 'false' : 'true' }})">
                                        <x-filament::icon :icon="$component['published'] ? \App\Filament\Support\AdminIcon::Unpublish->mini() : \App\Filament\Support\AdminIcon::Publish->mini()" class="admin-action__icon" />
                                        <span class="admin-action__label">{{ $component['published'] ? 'Unpublish' : 'Publish' }}</span>
                                    </button>
                                    <button class="admin-action admin-action--with-icon is-danger" type="button" wire:click="mountAction('deleteComponent', { componentIndex: {{ $component['index'] }}, componentType: '{{ $component['type'] }}' })">
                                        <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Delete->mini()" class="admin-action__icon" />
                                        <span class="admin-action__label">Delete</span>
                                    </button>
                                </div>

                                <label class="admin-hierarchy__selection admin-hierarchy__selection--trailing" aria-label="Select {{ $component['type_label'] }}">
                                    <input type="checkbox" value="{{ $component['target'] }}" wire:model.live="selectedComponentTargets">
                                </label>
                            </div>

                            @if ($component['children'] !== [])
                                <div class="custom-page-component__children admin-hierarchy__children">
                                    <div class="custom-page-component__children-rows admin-hierarchy__children-rows" @if ($reorderEnabled) wire:sort="sortChild" @endif>
                                        @foreach ($component['children'] as $child)
                                            @php
                                                $childKindLabel = match ($child['kind']) {
                                                    'cv' => 'CV entry',
                                                    'list' => 'List item',
                                                    'contact' => 'Contact item',
                                                    default => ucfirst((string) $child['kind']),
                                                };
                                            @endphp
                                            <div
                                                class="custom-page-child-row admin-hierarchy__row is-child {{ ($child['parent_published'] ?? true) ? '' : 'is-parent-unpublished' }}"
                                                wire:key="child-{{ $component['target'] }}-{{ $child['key'] }}"
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
                                                    @if ($child['kind'] === 'cv' || $child['kind'] === 'list')
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
                                                    @if ($child['kind'] === 'cv')
                                                        <button class="admin-action admin-action--with-icon admin-order-action admin-order-action--labeled" type="button" wire:click="moveCvEntry({{ $child['entry_id'] }}, 'up')" @disabled(! $child['can_move_up']) aria-label="Move CV entry up">
                                                            <x-filament::icon :icon="\App\Filament\Support\AdminIcon::MoveUp->mini()" class="admin-action__icon" />
                                                            <span class="admin-action__label">Move up</span>
                                                        </button>
                                                        <button class="admin-action admin-action--with-icon admin-order-action admin-order-action--labeled" type="button" wire:click="moveCvEntry({{ $child['entry_id'] }}, 'down')" @disabled(! $child['can_move_down']) aria-label="Move CV entry down">
                                                            <x-filament::icon :icon="\App\Filament\Support\AdminIcon::MoveDown->mini()" class="admin-action__icon" />
                                                            <span class="admin-action__label">Move down</span>
                                                        </button>
                                                        <button class="admin-action admin-action--with-icon" type="button" wire:click="mountAction('editCvEntry', { entry: {{ $child['entry_id'] }} })">
                                                            <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Edit->mini()" class="admin-action__icon" />
                                                            <span class="admin-action__label">Edit</span>
                                                        </button>
                                                        <button class="admin-action admin-action--with-icon admin-action--state" type="button" wire:click="transitionCvEntry({{ $child['entry_id'] }}, '{{ $child['published'] ? 'unpublish' : 'publish' }}')">
                                                            <x-filament::icon :icon="$child['published'] ? \App\Filament\Support\AdminIcon::Unpublish->mini() : \App\Filament\Support\AdminIcon::Publish->mini()" class="admin-action__icon" />
                                                            <span class="admin-action__label">{{ $child['published'] ? 'Unpublish' : 'Publish' }}</span>
                                                        </button>
                                                        <button class="admin-action admin-action--with-icon is-danger" type="button" wire:click="mountAction('deleteCvEntry', { entry: {{ $child['entry_id'] }} })">
                                                            <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Delete->mini()" class="admin-action__icon" />
                                                            <span class="admin-action__label">Delete</span>
                                                        </button>
                                                    @elseif ($child['kind'] === 'list')
                                                        <button class="admin-action admin-action--with-icon admin-order-action admin-order-action--labeled" type="button" wire:click="moveListEntry({{ $component['index'] }}, 'list', {{ $child['item_index'] }}, 'up')" @disabled(! $child['can_move_up']) aria-label="Move list entry up">
                                                            <x-filament::icon :icon="\App\Filament\Support\AdminIcon::MoveUp->mini()" class="admin-action__icon" />
                                                            <span class="admin-action__label">Move up</span>
                                                        </button>
                                                        <button class="admin-action admin-action--with-icon admin-order-action admin-order-action--labeled" type="button" wire:click="moveListEntry({{ $component['index'] }}, 'list', {{ $child['item_index'] }}, 'down')" @disabled(! $child['can_move_down']) aria-label="Move list entry down">
                                                            <x-filament::icon :icon="\App\Filament\Support\AdminIcon::MoveDown->mini()" class="admin-action__icon" />
                                                            <span class="admin-action__label">Move down</span>
                                                        </button>
                                                        <button class="admin-action admin-action--with-icon" type="button" wire:click="mountAction('editListEntry', { componentIndex: {{ $component['index'] }}, componentType: 'list', itemIndex: {{ $child['item_index'] }} })">
                                                            <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Edit->mini()" class="admin-action__icon" />
                                                            <span class="admin-action__label">Edit</span>
                                                        </button>
                                                        <button class="admin-action admin-action--with-icon admin-action--state" type="button" wire:click="setListEntryPublished({{ $component['index'] }}, 'list', {{ $child['item_index'] }}, {{ $child['published'] ? 'false' : 'true' }})">
                                                            <x-filament::icon :icon="$child['published'] ? \App\Filament\Support\AdminIcon::Unpublish->mini() : \App\Filament\Support\AdminIcon::Publish->mini()" class="admin-action__icon" />
                                                            <span class="admin-action__label">{{ $child['published'] ? 'Unpublish' : 'Publish' }}</span>
                                                        </button>
                                                        <button class="admin-action admin-action--with-icon is-danger" type="button" wire:click="mountAction('deleteListEntry', { componentIndex: {{ $component['index'] }}, componentType: 'list', itemIndex: {{ $child['item_index'] }} })">
                                                            <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Delete->mini()" class="admin-action__icon" />
                                                            <span class="admin-action__label">Delete</span>
                                                        </button>
                                                    @elseif ($child['kind'] === 'contact')
                                                        <button class="admin-action admin-action--with-icon admin-order-action admin-order-action--labeled" type="button" wire:click="moveContactChild({{ $component['index'] }}, 'contact', '{{ $child['child_type'] }}', 'up')" @disabled(! $child['can_move_up']) aria-label="Move Contact child up">
                                                            <x-filament::icon :icon="\App\Filament\Support\AdminIcon::MoveUp->mini()" class="admin-action__icon" />
                                                            <span class="admin-action__label">Move up</span>
                                                        </button>
                                                        <button class="admin-action admin-action--with-icon admin-order-action admin-order-action--labeled" type="button" wire:click="moveContactChild({{ $component['index'] }}, 'contact', '{{ $child['child_type'] }}', 'down')" @disabled(! $child['can_move_down']) aria-label="Move Contact child down">
                                                            <x-filament::icon :icon="\App\Filament\Support\AdminIcon::MoveDown->mini()" class="admin-action__icon" />
                                                            <span class="admin-action__label">Move down</span>
                                                        </button>
                                                        <button class="admin-action admin-action--with-icon" type="button" wire:click="mountAction('editContactChild', { componentIndex: {{ $component['index'] }}, componentType: 'contact', childType: '{{ $child['child_type'] }}' })">
                                                            <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Edit->mini()" class="admin-action__icon" />
                                                            <span class="admin-action__label">Edit</span>
                                                        </button>
                                                        <button class="admin-action admin-action--with-icon admin-action--state" type="button" wire:click="setContactChildPublished({{ $component['index'] }}, 'contact', '{{ $child['child_type'] }}', {{ $child['published'] ? 'false' : 'true' }})">
                                                            <x-filament::icon :icon="$child['published'] ? \App\Filament\Support\AdminIcon::Unpublish->mini() : \App\Filament\Support\AdminIcon::Publish->mini()" class="admin-action__icon" />
                                                            <span class="admin-action__label">{{ $child['published'] ? 'Unpublish' : 'Publish' }}</span>
                                                        </button>
                                                        <button class="admin-action admin-action--with-icon is-danger" type="button" wire:click="mountAction('deleteContactChild', { componentIndex: {{ $component['index'] }}, componentType: 'contact', childType: '{{ $child['child_type'] }}' })">
                                                            <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Delete->mini()" class="admin-action__icon" />
                                                            <span class="admin-action__label">Delete</span>
                                                        </button>
                                                    @endif
                                                </div>

                                                <label class="admin-hierarchy__selection admin-hierarchy__selection--trailing" aria-label="Select {{ $child['entry'] }}">
                                                    <input type="checkbox" value="{{ $child['target'] }}" wire:model.live="selectedChildTargets">
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @elseif ($component['is_list'])
                                <div class="custom-page-component__children-empty">No list entries</div>
                            @elseif ($component['is_cv_list'])
                                <div class="custom-page-component__children-empty">No CV entries</div>
                            @elseif ($component['is_contact'])
                                <div class="custom-page-component__children-empty">No contact items</div>
                            @endif

                            @if ($component['is_list'])
                                <button class="custom-page-component-add-row custom-page-component-add-row--child" type="button" wire:click="mountAction('addListEntry', { componentIndex: {{ $component['index'] }}, componentType: 'list' })">
                                    <span aria-hidden="true">+</span>
                                    <strong>Add list item</strong>
                                </button>
                            @elseif ($component['is_cv_list'])
                                <button class="custom-page-component-add-row custom-page-component-add-row--child" type="button" wire:click="mountAction('addCvEntry')">
                                    <span aria-hidden="true">+</span>
                                    <strong>Add CV entry</strong>
                                </button>
                            @elseif ($component['is_contact'] && $component['contact_child_count'] < 3)
                                <button class="custom-page-component-add-row custom-page-component-add-row--child" type="button" wire:click="mountAction('addContactChild', { componentIndex: {{ $component['index'] }}, componentType: 'contact' })">
                                    <span aria-hidden="true">+</span>
                                    <strong>Add contact item</strong>
                                </button>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </section>