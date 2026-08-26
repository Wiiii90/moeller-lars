<x-filament-panels::page>
    <x-admin.workspace :title="$pageTitle" class="custom-page-workspace">
        <x-admin.metrics :columns="6" aria-label="Custom page overview">
            @foreach ($metrics as $metric)
                <x-admin.metric :label="$metric['label']" :value="$metric['value']">{{ $metric['description'] }}</x-admin.metric>
            @endforeach
        </x-admin.metrics>

        <div class="custom-page-workspace__page-controls" aria-label="Page actions">
            <span class="custom-page-workspace__control-label">Page</span>
            <div class="admin-toolbar custom-page-workspace__page-actions">
                <button class="admin-action" type="button" wire:click="mountAction('pageSettings')">Settings</button>
                <button class="admin-action is-primary" type="button" wire:click="mountAction('addComponent')">Add component</button>
                @if ($previewUrl)
                    <a class="admin-action" href="{{ $previewUrl }}" target="_blank" rel="noopener">Preview</a>
                @else
                    <button class="admin-action" type="button" disabled>Preview</button>
                @endif
            </div>
        </div>

        @php
            $reorderEnabled = trim($componentSearch) === '' && $componentType === 'any';
            $selectedParentCount = count($selectedComponentTargets);
            $selectedChildCount = count($selectedChildTargets);
            $selectedItemCount = $selectedParentCount + $selectedChildCount;
            $parentOnlySelection = $selectedParentCount > 0 && $selectedChildCount === 0;
            $childOnlySelection = $selectedChildCount > 0 && $selectedParentCount === 0;
            $selectedChildren = collect($components)
                ->flatMap(static fn (array $component): array => is_array($component['children'] ?? null) ? $component['children'] : [])
                ->filter(static fn (array $child): bool => in_array($child['target'] ?? null, $selectedChildTargets, true))
                ->values();
            $selectedCvChildren = $selectedChildren
                ->filter(static fn (array $child): bool => ($child['kind'] ?? null) === 'cv')
                ->values();
            $canMoveSelected = $parentOnlySelection && $reorderEnabled;
            $canPublishSelected = $childOnlySelection
                && $selectedChildren->isNotEmpty()
                && $selectedCvChildren->every(static fn (array $child): bool => in_array($child['state'] ?? null, ['draft', 'published'], true))
                && $selectedChildren->contains(static fn (array $child): bool => ($child['published'] ?? false) === false);
            $canUnpublishSelected = $childOnlySelection
                && $selectedChildren->isNotEmpty()
                && $selectedCvChildren->every(static fn (array $child): bool => ($child['state'] ?? null) === 'published')
                && $selectedChildren->contains(static fn (array $child): bool => ($child['published'] ?? false) === true);
            $canDeleteSelected = $parentOnlySelection || $childOnlySelection;
        @endphp

        <div class="custom-page-workspace__controls" aria-label="Component table tools">
            <label class="custom-page-workspace__field custom-page-workspace__search">
                <span>Search</span>
                <input type="search" wire:model.blur="componentSearch" placeholder="Search components and entries">
            </label>

            <label class="custom-page-workspace__field">
                <span>Type</span>
                <select wire:model.live="componentType">
                    <option value="any">All components</option>
                    @foreach ($componentTypeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <div class="custom-page-workspace__control-group">
                <span class="custom-page-workspace__control-label">Filter</span>
                <button class="admin-action" type="button" wire:click="resetComponentFilters">Reset</button>
            </div>

            <div
                class="custom-page-workspace__control-group custom-page-workspace__selection"
                x-data="{ open: false }"
                x-on:click.outside="open = false"
                x-on:keydown.escape.window="open = false"
            >
                <span class="custom-page-workspace__control-label">Selection</span>
                <div class="custom-page-workspace__selection-anchor">
                    <button
                        class="admin-action custom-page-workspace__selection-trigger"
                        type="button"
                        x-on:click="open = ! open"
                        x-bind:aria-expanded="open.toString()"
                        aria-haspopup="menu"
                        @disabled($selectedItemCount === 0)
                    >
                        Selected items
                        <span class="custom-page-workspace__selection-count">{{ $selectedItemCount }}</span>
                    </button>
                    <div class="custom-page-workspace__selection-menu" role="menu" x-show="open" x-cloak>
                        <button
                            class="admin-action"
                            type="button"
                            role="menuitem"
                            wire:click="moveSelectedComponents('up')"
                            x-on:click="open = false"
                            @disabled(! $canMoveSelected)
                        >Move selected up</button>
                        <button
                            class="admin-action"
                            type="button"
                            role="menuitem"
                            wire:click="moveSelectedComponents('down')"
                            x-on:click="open = false"
                            @disabled(! $canMoveSelected)
                        >Move selected down</button>
                        <button
                            class="admin-action"
                            type="button"
                            role="menuitem"
                            wire:click="publishSelectedChildren"
                            x-on:click="open = false"
                            @disabled(! $canPublishSelected)
                        >Publish selected</button>
                        <button
                            class="admin-action"
                            type="button"
                            role="menuitem"
                            wire:click="unpublishSelectedChildren"
                            x-on:click="open = false"
                            @disabled(! $canUnpublishSelected)
                        >Unpublish selected</button>
                        @if ($parentOnlySelection)
                            <button
                                class="admin-action is-danger"
                                type="button"
                                role="menuitem"
                                wire:click="mountAction('deleteSelectedComponents')"
                                x-on:click="open = false"
                                @disabled(! $canDeleteSelected)
                            >Delete selected</button>
                        @elseif ($childOnlySelection)
                            <button
                                class="admin-action is-danger"
                                type="button"
                                role="menuitem"
                                wire:click="mountAction('deleteSelectedChildren')"
                                x-on:click="open = false"
                                @disabled(! $canDeleteSelected)
                            >Delete selected</button>
                        @else
                            <button class="admin-action is-danger" type="button" role="menuitem" disabled>Delete selected</button>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <section class="custom-page-component-sequence" aria-label="Page component sequence">
            <div class="custom-page-component-sequence__header" aria-hidden="true">
                <span></span>
                <span></span>
                <span>Component</span>
                <span>Content</span>
                <span>Status</span>
                <span>Actions</span>
            </div>

            @if ($components === [])
                <x-admin.empty-state kicker="No components" title="No matching components">
                    <p>Add a component or clear the current filters.</p>
                </x-admin.empty-state>
            @else
                <div class="custom-page-component-sequence__rows" @if ($reorderEnabled) wire:sort="sortComponent" @endif>
                    @foreach ($components as $component)
                        <article
                            class="custom-page-component"
                            wire:key="custom-component-{{ $component['target'] }}"
                            @if ($reorderEnabled) wire:sort:item="{{ $component['target'] }}" @endif
                        >
                            <div class="custom-page-component__header">
                                <label class="custom-page-component__select" aria-label="Select {{ $component['type_label'] }}">
                                    <input type="checkbox" value="{{ $component['target'] }}" wire:model.live="selectedComponentTargets">
                                </label>

                                <button
                                    class="custom-page-row__drag custom-page-component__drag"
                                    type="button"
                                    @if ($reorderEnabled) wire:sort:handle @else disabled @endif
                                    aria-label="Drag {{ $component['type_label'] }}"
                                >⋮⋮</button>

                                <div class="custom-page-component__type">
                                    <select
                                        class="custom-page-component__type-select"
                                        aria-label="Component type"
                                        @disabled(! $reorderEnabled)
                                        wire:change="mountAction('changeComponentType', { componentIndex: {{ $component['index'] }}, componentType: '{{ $component['type'] }}', targetType: $event.target.value })"
                                    >
                                        @foreach ($componentTypeOptions as $value => $label)
                                            <option value="{{ $value }}" @selected($value === $component['type'])>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="custom-page-component__content">
                                    <strong>{{ $component['content']['primary'] }}</strong>
                                    @if ($component['content']['secondary'] !== '')
                                        <span>{{ $component['content']['secondary'] }}</span>
                                    @endif
                                    @if ($component['content']['meta'] !== '')
                                        <small>{{ $component['content']['meta'] }}</small>
                                    @endif
                                </div>

                                <div class="custom-page-component__status">
                                    <span class="custom-page-status {{ $component['published'] ? 'is-published' : 'is-unpublished' }}">{{ $component['status'] }}</span>
                                </div>

                                <div class="custom-page-component__actions admin-toolbar">
                                    <button class="admin-action" type="button" wire:click="mountAction('editComponent', { componentIndex: {{ $component['index'] }}, componentType: '{{ $component['type'] }}' })">Edit</button>
                                    <button
                                        class="admin-action"
                                        type="button"
                                        wire:click="setComponentPublished({{ $component['index'] }}, '{{ $component['type'] }}', {{ $component['published'] ? 'false' : 'true' }})"
                                    >{{ $component['published'] ? 'Unpublish' : 'Publish' }}</button>
                                    @if ($component['is_list'])
                                        <button class="admin-action" type="button" wire:click="mountAction('addListEntry', { componentIndex: {{ $component['index'] }}, componentType: 'list' })">Add entry</button>
                                    @elseif ($component['is_cv_list'])
                                        <button class="admin-action" type="button" wire:click="mountAction('addCvEntry')">Add CV entry</button>
                                    @elseif ($component['is_contact'] && $component['contact_child_count'] < 3)
                                        <button class="admin-action" type="button" wire:click="mountAction('addContactChild', { componentIndex: {{ $component['index'] }}, componentType: 'contact' })">Add child</button>
                                    @endif
                                    <button class="admin-action" type="button" wire:click="moveComponent({{ $component['index'] }}, '{{ $component['type'] }}', 'up')" @disabled(! $reorderEnabled || ! $component['can_move_up']) aria-label="Move component up">↑</button>
                                    <button class="admin-action" type="button" wire:click="moveComponent({{ $component['index'] }}, '{{ $component['type'] }}', 'down')" @disabled(! $reorderEnabled || ! $component['can_move_down']) aria-label="Move component down">↓</button>
                                    <button class="admin-action is-danger" type="button" wire:click="mountAction('deleteComponent', { componentIndex: {{ $component['index'] }}, componentType: '{{ $component['type'] }}' })">Delete</button>
                                </div>
                            </div>

                            @if ($component['children'] !== [])
                                <div class="custom-page-component__children">
                                    <div class="custom-page-component__children-rows" @if ($reorderEnabled) wire:sort="sortChild" @endif>
                                        @foreach ($component['children'] as $child)
                                            <div
                                                class="custom-page-child-row"
                                                wire:key="child-{{ $component['target'] }}-{{ $child['key'] }}"
                                                @if ($reorderEnabled) wire:sort:item="{{ $child['target'] }}" @endif
                                            >
                                                <label class="custom-page-child-row__select" aria-label="Select {{ $child['entry'] }}">
                                                    <input type="checkbox" value="{{ $child['target'] }}" wire:model.live="selectedChildTargets">
                                                </label>

                                                <button
                                                    class="custom-page-row__drag custom-page-child-row__drag"
                                                    type="button"
                                                    @if ($reorderEnabled) wire:sort:handle @else disabled @endif
                                                    aria-label="Drag {{ $child['entry'] }}"
                                                >⋮⋮</button>

                                                <div class="custom-page-child-row__type">
                                                    @if ($child['kind'] === 'cv')
                                                        CV Entry
                                                    @elseif ($child['kind'] === 'list')
                                                        List Entry
                                                    @else
                                                        {{ $child['entry'] }}
                                                    @endif
                                                </div>

                                                <div class="custom-page-child-row__content">
                                                    @if ($child['kind'] === 'contact')
                                                        <strong>{{ $child['detail'] }}</strong>
                                                    @else
                                                        <strong>
                                                            @if ($child['date'] !== '')
                                                                <span class="custom-page-child-row__date">{{ $child['date'] }}</span> ·
                                                            @endif
                                                            {{ $child['entry'] }}
                                                        </strong>
                                                        @if ($child['detail'] !== '')
                                                            <small>{{ $child['detail'] }}</small>
                                                        @endif
                                                    @endif
                                                </div>

                                                <div class="custom-page-child-row__status-cell">
                                                    <span class="custom-page-child-row__status {{ $child['published'] ? 'is-published' : 'is-unpublished' }}">{{ $child['status'] }}</span>
                                                </div>

                                                <div class="custom-page-child-row__actions admin-toolbar">
                                                    @if ($child['kind'] === 'cv')
                                                        <button class="admin-action" type="button" wire:click="mountAction('editCvEntry', { entry: {{ $child['entry_id'] }} })">Edit</button>
                                                        @if ($child['state'] === 'draft')
                                                            <button class="admin-action" type="button" wire:click="transitionCvEntry({{ $child['entry_id'] }}, 'publish')">Publish</button>
                                                            <button class="admin-action" type="button" wire:click="transitionCvEntry({{ $child['entry_id'] }}, 'archive')">Archive</button>
                                                        @elseif ($child['state'] === 'published')
                                                            <button class="admin-action" type="button" wire:click="transitionCvEntry({{ $child['entry_id'] }}, 'unpublish')">Unpublish</button>
                                                            <button class="admin-action" type="button" wire:click="transitionCvEntry({{ $child['entry_id'] }}, 'archive')">Archive</button>
                                                        @else
                                                            <button class="admin-action" type="button" wire:click="transitionCvEntry({{ $child['entry_id'] }}, 'restore')">Restore</button>
                                                        @endif
                                                        <button class="admin-action" type="button" wire:click="moveCvEntry({{ $child['entry_id'] }}, 'up')" @disabled(! $child['can_move_up']) aria-label="Move CV entry up">↑</button>
                                                        <button class="admin-action" type="button" wire:click="moveCvEntry({{ $child['entry_id'] }}, 'down')" @disabled(! $child['can_move_down']) aria-label="Move CV entry down">↓</button>
                                                        <button class="admin-action is-danger" type="button" wire:click="mountAction('deleteCvEntry', { entry: {{ $child['entry_id'] }} })">Delete</button>
                                                    @elseif ($child['kind'] === 'list')
                                                        <button class="admin-action" type="button" wire:click="mountAction('editListEntry', { componentIndex: {{ $component['index'] }}, componentType: 'list', itemIndex: {{ $child['item_index'] }} })">Edit</button>
                                                        <button class="admin-action" type="button" wire:click="setListEntryPublished({{ $component['index'] }}, 'list', {{ $child['item_index'] }}, {{ $child['published'] ? 'false' : 'true' }})">{{ $child['published'] ? 'Unpublish' : 'Publish' }}</button>
                                                        <button class="admin-action" type="button" wire:click="moveListEntry({{ $component['index'] }}, 'list', {{ $child['item_index'] }}, 'up')" @disabled(! $child['can_move_up']) aria-label="Move list entry up">↑</button>
                                                        <button class="admin-action" type="button" wire:click="moveListEntry({{ $component['index'] }}, 'list', {{ $child['item_index'] }}, 'down')" @disabled(! $child['can_move_down']) aria-label="Move list entry down">↓</button>
                                                        <button class="admin-action is-danger" type="button" wire:click="mountAction('deleteListEntry', { componentIndex: {{ $component['index'] }}, componentType: 'list', itemIndex: {{ $child['item_index'] }} })">Delete</button>
                                                    @elseif ($child['kind'] === 'contact')
                                                        <button class="admin-action" type="button" wire:click="mountAction('editContactChild', { componentIndex: {{ $component['index'] }}, componentType: 'contact', childType: '{{ $child['child_type'] }}' })">Edit</button>
                                                        <button class="admin-action" type="button" wire:click="setContactChildPublished({{ $component['index'] }}, 'contact', '{{ $child['child_type'] }}', {{ $child['published'] ? 'false' : 'true' }})">{{ $child['published'] ? 'Unpublish' : 'Publish' }}</button>
                                                        <button class="admin-action" type="button" wire:click="moveContactChild({{ $component['index'] }}, 'contact', '{{ $child['child_type'] }}', 'up')" @disabled(! $child['can_move_up']) aria-label="Move Contact child up">↑</button>
                                                        <button class="admin-action" type="button" wire:click="moveContactChild({{ $component['index'] }}, 'contact', '{{ $child['child_type'] }}', 'down')" @disabled(! $child['can_move_down']) aria-label="Move Contact child down">↓</button>
                                                        <button class="admin-action is-danger" type="button" wire:click="mountAction('deleteContactChild', { componentIndex: {{ $component['index'] }}, componentType: 'contact', childType: '{{ $child['child_type'] }}' })">Delete</button>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @elseif ($component['is_list'])
                                <div class="custom-page-component__children-empty">No list entries yet.</div>
                            @elseif ($component['is_cv_list'])
                                <div class="custom-page-component__children-empty">No CV entries yet.</div>
                            @elseif ($component['is_contact'])
                                <div class="custom-page-component__children-empty">No Contact children. Add Public Email, Social Media Links or Contact Form.</div>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif

            <button class="custom-page-component-add-row" type="button" wire:click="mountAction('addComponent')">
                <span aria-hidden="true">+</span>
                <strong>Add component</strong>
            </button>
        </section>
    </x-admin.workspace>

    <x-filament-actions::modals />
</x-filament-panels::page>
