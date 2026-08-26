<x-filament-panels::page>
    <x-admin.workspace :title="$pageTitle" class="custom-page-workspace">
        <x-admin.metrics :columns="6" aria-label="Custom page overview">
            @foreach ($metrics as $metric)
                <x-admin.metric :label="$metric['label']" :value="$metric['value']">{{ $metric['description'] }}</x-admin.metric>
            @endforeach
        </x-admin.metrics>

        <div class="custom-page-workspace__page-controls" aria-label="Page actions">
            <span class="custom-page-workspace__control-label">Page</span>
            <div class="admin-toolbar">
                <button class="admin-action" type="button" wire:click="mountAction('pageSettings')">Settings</button>
                <button class="admin-action is-primary" type="button" wire:click="mountAction('addComponent')">Add component</button>
                @if ($previewUrl)
                    <a class="admin-action" href="{{ $previewUrl }}" target="_blank" rel="noopener">Preview</a>
                @endif
            </div>
        </div>

        <div class="custom-page-workspace__controls" aria-label="Component table tools">
            <label class="custom-page-workspace__search">
                <span>Search</span>
                <input type="search" wire:model.blur="componentSearch" placeholder="Search components and entries">
            </label>

            <label class="custom-page-workspace__type-filter">
                <span>Type</span>
                <select wire:model.live="componentType">
                    <option value="any">All components</option>
                    @foreach ($componentTypeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <div class="custom-page-workspace__tool-actions">
                @if ($componentSearch !== '' || $componentType !== 'any')
                    <button class="admin-action" type="button" wire:click="resetComponentFilters">Clear filters</button>
                @endif
            </div>

            <div class="custom-page-workspace__selection">
                <span class="custom-page-workspace__selection-count">{{ count($selectedComponentTargets) }} components</span>
                @if ($selectedComponentTargets !== [])
                    <button class="admin-action" type="button" wire:click="moveSelectedComponents('up')">↑</button>
                    <button class="admin-action" type="button" wire:click="moveSelectedComponents('down')">↓</button>
                    <button class="admin-action is-danger" type="button" wire:click="mountAction('deleteSelectedComponents')">Delete</button>
                @endif
            </div>

            <div class="custom-page-workspace__selection custom-page-workspace__selection--children">
                <span class="custom-page-workspace__selection-count">{{ count($selectedChildTargets) }} entries</span>
                @if ($selectedChildTargets !== [])
                    <button class="admin-action" type="button" wire:click="publishSelectedChildren">Publish</button>
                    <button class="admin-action" type="button" wire:click="unpublishSelectedChildren">Unpublish</button>
                    <button class="admin-action is-danger" type="button" wire:click="mountAction('deleteSelectedChildren')">Delete</button>
                @endif
            </div>
        </div>

        @php($reorderEnabled = trim($componentSearch) === '' && $componentType === 'any')

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
                                    class="custom-page-component__drag"
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
                                    <div class="custom-page-child-row custom-page-child-row--head" aria-hidden="true">
                                        <span></span>
                                        <span></span>
                                        <span>Content</span>
                                        <span>Status</span>
                                        <span>Actions</span>
                                    </div>
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
                                                    class="custom-page-child-row__drag"
                                                    type="button"
                                                    @if ($reorderEnabled) wire:sort:handle @else disabled @endif
                                                    aria-label="Drag {{ $child['entry'] }}"
                                                >⋮⋮</button>

                                                <div class="custom-page-child-row__entry">
                                                    <strong>
                                                        @if ($child['date'] !== '')<span class="custom-page-child-row__date">{{ $child['date'] }}</span> · @endif
                                                        {{ $child['entry'] }}
                                                    </strong>
                                                    @if ($child['detail'] !== '')<small>{{ $child['detail'] }}</small>@endif
                                                </div>

                                                <span class="custom-page-child-row__status {{ $child['published'] ? 'is-published' : 'is-unpublished' }}">{{ $child['status'] }}</span>

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
