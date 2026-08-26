<x-filament-panels::page>
    @php
        $componentReorderEnabled = trim($componentSearch) === '' && $componentType === 'any';
    @endphp

    <x-admin.workspace :title="$pageTitle" class="custom-page-workspace">
        <x-admin.metrics :columns="6" aria-label="Custom page overview">
            @foreach ($metrics as $metric)
                <x-admin.metric :label="$metric['label']" :value="$metric['value']">{{ $metric['description'] }}</x-admin.metric>
            @endforeach
        </x-admin.metrics>

        <x-admin.section class="custom-page-workspace__components" aria-label="Page components">
            <div class="custom-page-workspace__controls" aria-label="Component controls">
                <label class="custom-page-workspace__field custom-page-workspace__search">
                    <span>Search components</span>
                    <input
                        type="search"
                        wire:model.blur="componentSearch"
                        x-on:keydown.enter.prevent="$el.blur()"
                        placeholder="Heading, entry, contact, text"
                        autocomplete="off"
                    >
                </label>

                <label class="custom-page-workspace__field">
                    <span>Type</span>
                    <select wire:model.change="componentType">
                        <option value="any">Any</option>
                        @foreach ($componentTypeOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="custom-page-workspace__control-group">
                    <span class="custom-page-workspace__control-label">Filter</span>
                    <button class="admin-action" type="button" wire:click="resetComponentFilters">Reset</button>
                </div>

                <div class="custom-page-workspace__control-group custom-page-workspace__preview">
                    <span class="custom-page-workspace__control-label">Page</span>
                    @if ($publicUrl)
                        <a class="admin-action" href="{{ $publicUrl }}" target="_blank" rel="noopener">Preview</a>
                    @else
                        <button
                            class="admin-action"
                            type="button"
                            disabled
                            title="Publish this page to preview the public Custom Page"
                        >Preview</button>
                    @endif
                </div>

                <div
                    class="custom-page-workspace__control-group custom-page-workspace__selection"
                    x-data="{ open: false }"
                    x-on:keydown.escape.window="open = false"
                >
                    <span class="custom-page-workspace__control-label">Selection</span>
                    <div class="custom-page-workspace__selection-anchor">
                        <button
                            class="admin-action custom-page-workspace__selection-trigger"
                            type="button"
                            x-on:click="open = !open"
                            x-bind:aria-expanded="open"
                            aria-haspopup="menu"
                            @disabled($selectedComponentTargets === [])
                        >
                            Selected components
                            <span class="custom-page-workspace__selection-count">{{ count($selectedComponentTargets) }}</span>
                        </button>
                        <div
                            class="custom-page-workspace__selection-menu"
                            x-show="open"
                            x-cloak
                            x-on:click.outside="open = false"
                            role="menu"
                        >
                            <button
                                class="admin-action"
                                type="button"
                                role="menuitem"
                                wire:click="moveSelectedComponents('up')"
                                x-on:click="open = false"
                                @disabled(! $componentReorderEnabled)
                                title="{{ $componentReorderEnabled ? 'Move selected components up' : 'Clear filters to reorder' }}"
                            >Move selected up</button>
                            <button
                                class="admin-action"
                                type="button"
                                role="menuitem"
                                wire:click="moveSelectedComponents('down')"
                                x-on:click="open = false"
                                @disabled(! $componentReorderEnabled)
                                title="{{ $componentReorderEnabled ? 'Move selected components down' : 'Clear filters to reorder' }}"
                            >Move selected down</button>
                            <button
                                class="admin-action"
                                type="button"
                                role="menuitem"
                                wire:click="mountAction('deleteSelectedComponents')"
                                x-on:click="open = false"
                            >Delete selected</button>
                        </div>
                    </div>
                </div>
            </div>

            @if ($components !== [])
                <section
                    class="custom-page-component-sequence"
                    aria-label="Component sequence"
                    @if ($componentReorderEnabled)
                        wire:sort="sortComponent"
                    @endif
                >
                    <header class="custom-page-component-sequence__header" aria-hidden="true">
                        <span></span>
                        <span></span>
                        <span>Component</span>
                        <span>Content</span>
                        <span>Actions</span>
                    </header>

                    @foreach ($components as $component)
                        <article
                            @class([
                                'custom-page-component',
                                'has-children' => $component['children'] !== [],
                                'is-selected' => in_array($component['target'], $selectedComponentTargets, true),
                            ])
                            wire:key="custom-page-component-{{ $component['index'] }}-{{ $component['type'] }}"
                            @if ($componentReorderEnabled)
                                wire:sort:item="{{ $component['target'] }}"
                            @endif
                        >
                            <header class="custom-page-component__header">
                                <label class="custom-page-component__selection">
                                    <input
                                        type="checkbox"
                                        wire:model.live="selectedComponentTargets"
                                        value="{{ $component['target'] }}"
                                        aria-label="Select {{ $component['type_label'] }} component"
                                    >
                                </label>

                                <button
                                    class="custom-page-component__drag-handle"
                                    type="button"
                                    @if ($componentReorderEnabled)
                                        wire:sort:handle
                                    @endif
                                    @disabled(! $componentReorderEnabled)
                                    title="{{ $componentReorderEnabled ? 'Drag to reorder component' : 'Clear filters to reorder' }}"
                                    aria-label="{{ $componentReorderEnabled ? 'Drag '.$component['type_label'].' component to reorder' : 'Clear filters to reorder '.$component['type_label'].' component' }}"
                                >⋮⋮</button>

                                <select
                                    class="custom-page-component__type-select"
                                    aria-label="Component type"
                                    x-on:change="
                                        const targetType = $event.target.value;
                                        $event.target.value = @js($component['type']);
                                        if (targetType !== @js($component['type'])) {
                                            $wire.mountAction('changeComponentType', {
                                                componentIndex: @js($component['index']),
                                                componentType: @js($component['type']),
                                                targetType,
                                            });
                                        }
                                    "
                                >
                                    @foreach ($componentTypeOptions as $value => $label)
                                        <option value="{{ $value }}" @selected($component['type'] === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>

                                <div class="custom-page-component__content">
                                    @if ($component['content']['primary'] !== '' || $component['content']['secondary'] !== '' || $component['content']['meta'] !== '')
                                        <div class="custom-page-component__content-copy">
                                            @if ($component['content']['primary'] !== '')
                                                <strong>{{ $component['content']['primary'] }}</strong>
                                            @endif
                                            @if ($component['content']['secondary'] !== '')
                                                <span>{{ $component['content']['secondary'] }}</span>
                                            @endif
                                            @if ($component['content']['meta'] !== '')
                                                <small>{{ $component['content']['meta'] }}</small>
                                            @endif
                                        </div>
                                    @endif
                                </div>

                                <div class="custom-page-component__actions admin-toolbar">
                                    @if ($component['is_cv_list'])
                                        <button class="admin-action" type="button" wire:click="mountAction('addCvEntry')">Add entry</button>
                                    @elseif ($component['is_list'])
                                        <button
                                            class="admin-action"
                                            type="button"
                                            wire:click="mountAction('addListEntry', { componentIndex: {{ $component['index'] }}, componentType: '{{ $component['type'] }}' })"
                                        >Add entry</button>
                                    @endif

                                    @if ($component['editable'])
                                        <button
                                            class="admin-action"
                                            type="button"
                                            wire:click="mountAction('editComponent', { componentIndex: {{ $component['index'] }}, componentType: '{{ $component['type'] }}' })"
                                        >Edit</button>
                                    @endif

                                    <button
                                        class="admin-action custom-page-component__order-action"
                                        type="button"
                                        wire:click="moveComponent({{ $component['index'] }}, '{{ $component['type'] }}', 'up')"
                                        @disabled(! $componentReorderEnabled || ! $component['can_move_up'])
                                        title="{{ $componentReorderEnabled ? 'Move component up' : 'Clear filters to reorder' }}"
                                    >↑</button>
                                    <button
                                        class="admin-action custom-page-component__order-action"
                                        type="button"
                                        wire:click="moveComponent({{ $component['index'] }}, '{{ $component['type'] }}', 'down')"
                                        @disabled(! $componentReorderEnabled || ! $component['can_move_down'])
                                        title="{{ $componentReorderEnabled ? 'Move component down' : 'Clear filters to reorder' }}"
                                    >↓</button>
                                    <button
                                        class="admin-action"
                                        type="button"
                                        wire:click="mountAction('deleteComponent', { componentIndex: {{ $component['index'] }}, componentType: '{{ $component['type'] }}' })"
                                    >Delete</button>
                                </div>
                            </header>

                            @if ($component['is_cv_list'] || $component['is_list'] || $component['is_contact'])
                                <div class="custom-page-component__children" aria-label="{{ $component['type_label'] }} details">
                                    @if ($component['children'] !== [])
                                        <div class="custom-page-child-row custom-page-child-row--head" aria-hidden="true">
                                            <span>{{ $component['is_contact'] ? '' : 'Date' }}</span>
                                            <span>{{ $component['is_contact'] ? 'Setting' : 'Entry' }}</span>
                                            <span>Status</span>
                                            <span>Actions</span>
                                        </div>

                                        @foreach ($component['children'] as $child)
                                            <div
                                                class="custom-page-child-row"
                                                wire:key="custom-page-child-{{ $component['index'] }}-{{ $child['key'] }}"
                                            >
                                                <span class="custom-page-child-row__date">{{ $child['date'] ?? '' }}</span>

                                                <span class="custom-page-child-row__entry">
                                                    <strong>{{ $child['entry'] }}</strong>
                                                    @if (($child['detail'] ?? '') !== '')
                                                        <small>{{ $child['detail'] }}</small>
                                                    @endif
                                                </span>

                                                <span @class([
                                                    'custom-page-child-row__status',
                                                    'is-published' => ($child['status'] ?? '') === 'Published',
                                                    'is-on' => ($child['status'] ?? '') === 'On',
                                                    'is-visible' => ($child['status'] ?? '') === 'Visible',
                                                ])>{{ $child['status'] }}</span>

                                                <span class="custom-page-child-row__actions admin-toolbar">
                                                    @if ($child['kind'] === 'cv')
                                                        <button class="admin-action" type="button" wire:click="mountAction('editCvEntry', { entry: {{ $child['entry_id'] }} })">Edit</button>
                                                        @if ($child['state'] === 'draft')
                                                            <button class="admin-action" type="button" wire:click="transitionCvEntry({{ $child['entry_id'] }}, 'publish')">Publish</button>
                                                            <button class="admin-action" type="button" wire:click="transitionCvEntry({{ $child['entry_id'] }}, 'archive')">Archive</button>
                                                        @elseif ($child['state'] === 'published')
                                                            <button class="admin-action" type="button" wire:click="transitionCvEntry({{ $child['entry_id'] }}, 'unpublish')">Unpublish</button>
                                                            <button class="admin-action" type="button" wire:click="transitionCvEntry({{ $child['entry_id'] }}, 'archive')">Archive</button>
                                                        @elseif (in_array($child['state'], ['archived', 'hidden'], true))
                                                            <button class="admin-action" type="button" wire:click="transitionCvEntry({{ $child['entry_id'] }}, 'restore')">Restore</button>
                                                        @endif
                                                        <button
                                                            class="admin-action custom-page-component__order-action"
                                                            type="button"
                                                            wire:click="moveCvEntry({{ $child['entry_id'] }}, 'up')"
                                                            @disabled(! $child['can_move_up'])
                                                            title="{{ $componentReorderEnabled ? 'Move CV entry up' : 'Clear filters to reorder' }}"
                                                        >↑</button>
                                                        <button
                                                            class="admin-action custom-page-component__order-action"
                                                            type="button"
                                                            wire:click="moveCvEntry({{ $child['entry_id'] }}, 'down')"
                                                            @disabled(! $child['can_move_down'])
                                                            title="{{ $componentReorderEnabled ? 'Move CV entry down' : 'Clear filters to reorder' }}"
                                                        >↓</button>
                                                        <button class="admin-action" type="button" wire:click="mountAction('deleteCvEntry', { entry: {{ $child['entry_id'] }} })">Delete</button>
                                                    @elseif ($child['kind'] === 'list')
                                                        <button
                                                            class="admin-action"
                                                            type="button"
                                                            wire:click="mountAction('editListEntry', { componentIndex: {{ $component['index'] }}, componentType: '{{ $component['type'] }}', itemIndex: {{ $child['item_index'] }} })"
                                                        >Edit</button>
                                                        <button
                                                            class="admin-action"
                                                            type="button"
                                                            wire:click="mountAction('deleteListEntry', { componentIndex: {{ $component['index'] }}, componentType: '{{ $component['type'] }}', itemIndex: {{ $child['item_index'] }} })"
                                                        >Delete</button>
                                                    @elseif (($child['action'] ?? null) === 'edit')
                                                        <button
                                                            class="admin-action"
                                                            type="button"
                                                            wire:click="mountAction('editComponent', { componentIndex: {{ $component['index'] }}, componentType: '{{ $component['type'] }}' })"
                                                        >Edit</button>
                                                    @elseif (($child['action'] ?? null) === 'toggle')
                                                        <button
                                                            class="admin-action"
                                                            type="button"
                                                            wire:click="setContactToggle({{ $component['index'] }}, '{{ $component['type'] }}', '{{ $child['field'] }}', {{ $child['enabled'] ? 'false' : 'true' }})"
                                                        >{{ $child['enabled'] ? 'Turn off' : 'Turn on' }}</button>
                                                    @elseif (($child['action'] ?? null) === 'social')
                                                        <button
                                                            class="admin-action"
                                                            type="button"
                                                            wire:click="setContactSocialPlatform({{ $component['index'] }}, '{{ $component['type'] }}', '{{ $child['platform'] }}', {{ $child['enabled'] ? 'false' : 'true' }})"
                                                        >{{ $child['enabled'] ? 'Turn off' : 'Turn on' }}</button>
                                                    @endif
                                                </span>
                                            </div>
                                        @endforeach
                                    @else
                                        <div class="custom-page-component__children-empty">No matching entries</div>
                                    @endif
                                </div>
                            @endif
                        </article>
                    @endforeach
                </section>
            @elseif ($unfilteredComponentCount > 0)
                <x-admin.empty-state title="No matching components" minimal>
                    <x-slot:actions>
                        <button class="admin-action" type="button" wire:click="resetComponentFilters">Clear filters</button>
                    </x-slot:actions>
                </x-admin.empty-state>
            @else
                <x-admin.empty-state title="No components added to this page" minimal />
            @endif

            <button class="custom-page-component-add-row" type="button" wire:click="mountAction('addComponent')">
                <span aria-hidden="true">+</span>
                <strong>Add component</strong>
            </button>
        </x-admin.section>
    </x-admin.workspace>

    <x-filament-actions::modals />
</x-filament-panels::page>
