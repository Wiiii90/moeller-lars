<x-filament-panels::page>
    @php
        $componentReorderEnabled = trim($componentSearch) === '' && $componentType === 'any';
        $componentTargets = collect($components)->pluck('target')->values()->all();
        $cvReorderEnabled = trim($cvSearch) === '' && $cvSection === 'any' && $cvStatus === 'any';
        $selectedCvIds = collect($selectedCvEntryIds)
            ->filter(static fn (mixed $id): bool => is_numeric($id))
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
        $visibleCvIds = collect($cvEntries)->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
        $visibleSelectedCvCount = count(array_intersect($visibleCvIds, $selectedCvIds));
        $allVisibleCvSelected = $visibleCvIds !== [] && $visibleSelectedCvCount === count($visibleCvIds);
        $selectedCvStates = collect($cvEntries)
            ->whereIn('id', $selectedCvIds)
            ->pluck('state')
            ->values();
        $canPublishSelectedCv = $selectedCvStates->contains('draft');
        $canUnpublishSelectedCv = $selectedCvStates->contains('published');
        $canArchiveSelectedCv = $selectedCvStates->contains(static fn (string $state): bool => $state !== 'archived');
        $canRestoreSelectedCv = $selectedCvStates->contains(static fn (string $state): bool => in_array($state, ['archived', 'hidden'], true));
        $cvResultStart = $cvTotal === 0 ? 0 : (($cvPage - 1) * $cvPageSize) + 1;
        $cvResultEnd = $cvTotal === 0 ? 0 : min($cvTotal, $cvPage * $cvPageSize);
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
                        placeholder="Heading, list entry, text"
                        autocomplete="off"
                    >
                </label>

                <label class="custom-page-workspace__field">
                    <span>Type</span>
                    <select wire:model.change="componentType">
                        <option value="any">Any</option>
                        <option value="image">Image</option>
                        <option value="cv_list">CV List</option>
                        <option value="text">Text</option>
                        <option value="list">List</option>
                        <option value="divider">Divider</option>
                        <option value="contact">Contact</option>
                    </select>
                </label>

                <div class="custom-page-workspace__control-group">
                    <span class="custom-page-workspace__control-label">Filter</span>
                    <button class="admin-action" type="button" wire:click="resetComponentFilters">Reset</button>
                </div>

                <div class="custom-page-workspace__control-group custom-page-workspace__page-actions">
                    <span class="custom-page-workspace__control-label">Page</span>
                    <div class="admin-toolbar">
                        <button class="admin-action" type="button" wire:click="mountAction('addComponent')">Add component</button>
                        @if ($publicUrl)
                            <a class="admin-action" href="{{ $publicUrl }}" target="_blank" rel="noopener">Preview</a>
                        @else
                            <button
                                class="admin-action"
                                type="button"
                                disabled
                                title="Publish this page to preview the public Custom Page"
                                aria-label="Preview unavailable until this page is published"
                            >Preview</button>
                        @endif
                    </div>
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
                    x-data="{
                        enabled: @js($componentReorderEnabled),
                        original: @js($componentTargets),
                        dragging: null,
                        over: null,
                        before: true,
                        start(event, target) {
                            if (! this.enabled) {
                                event.preventDefault();
                                return;
                            }
                            this.dragging = target;
                            this.over = null;
                            event.dataTransfer.effectAllowed = 'move';
                            event.dataTransfer.setData('text/plain', target);
                        },
                        hover(event, target) {
                            if (! this.enabled || this.dragging === null || this.dragging === target) return;
                            const rect = event.currentTarget.getBoundingClientRect();
                            this.over = target;
                            this.before = event.clientY < rect.top + (rect.height / 2);
                            event.dataTransfer.dropEffect = 'move';
                        },
                        leave(event, target) {
                            if (this.over === target && ! event.currentTarget.contains(event.relatedTarget)) this.over = null;
                        },
                        drop(event, target) {
                            if (! this.enabled || this.dragging === null || this.dragging === target) {
                                this.finish();
                                return;
                            }
                            const order = [...this.original];
                            const from = order.indexOf(this.dragging);
                            if (from < 0) {
                                this.finish();
                                return;
                            }
                            order.splice(from, 1);
                            let at = order.indexOf(target);
                            if (at < 0) {
                                this.finish();
                                return;
                            }
                            if (! this.before) at += 1;
                            order.splice(at, 0, this.dragging);
                            const changed = JSON.stringify(order) !== JSON.stringify(this.original);
                            this.finish();
                            if (changed) $wire.reorderComponents(order);
                        },
                        finish() {
                            this.dragging = null;
                            this.over = null;
                            this.before = true;
                        },
                    }"
                >
                    <header class="custom-page-component-sequence__header" aria-hidden="true">
                        <span></span>
                        <span></span>
                        <span>Component</span>
                        <span>Summary</span>
                        <span>Actions</span>
                    </header>

                    @foreach ($components as $component)
                        <article
                            @class(['custom-page-component', 'is-selected' => in_array($component['target'], $selectedComponentTargets, true)])
                            wire:key="custom-page-component-{{ $component['index'] }}-{{ $component['type'] }}"
                            data-component-target="{{ $component['target'] }}"
                            x-bind:class="{
                                'is-dragging': dragging === @js($component['target']),
                                'is-drop-target': over === @js($component['target']),
                                'is-drop-before': over === @js($component['target']) && before,
                                'is-drop-after': over === @js($component['target']) && ! before,
                            }"
                            x-on:dragenter.prevent="hover($event, @js($component['target']))"
                            x-on:dragover.prevent="hover($event, @js($component['target']))"
                            x-on:dragleave="leave($event, @js($component['target']))"
                            x-on:drop.prevent="drop($event, @js($component['target']))"
                        >
                            <header class="custom-page-component__header">
                                <button
                                    class="custom-page-component__drag-handle"
                                    type="button"
                                    draggable="{{ $componentReorderEnabled ? 'true' : 'false' }}"
                                    x-on:dragstart.stop="start($event, @js($component['target']))"
                                    x-on:dragend.stop="finish()"
                                    @disabled(! $componentReorderEnabled)
                                    title="{{ $componentReorderEnabled ? 'Drag to reorder component' : 'Clear filters to reorder' }}"
                                    aria-label="{{ $componentReorderEnabled ? 'Drag '.$component['type_label'].' component to reorder' : 'Clear filters to reorder '.$component['type_label'].' component' }}"
                                >⋮⋮</button>

                                <label class="custom-page-component__selection">
                                    <input
                                        type="checkbox"
                                        wire:model.live="selectedComponentTargets"
                                        value="{{ $component['target'] }}"
                                        aria-label="Select {{ $component['type_label'] }} component"
                                    >
                                </label>

                                <strong @class(['custom-page-component__type', 'is-divider' => $component['is_divider']])>{{ $component['type_label'] }}</strong>
                                @if ($component['is_divider'])
                                    <span class="custom-page-component__divider-preview" aria-label="Divider preview"><span></span></span>
                                @else
                                    <span class="custom-page-component__summary">{{ $component['summary'] }}</span>
                                @endif

                                <div class="custom-page-component__actions admin-toolbar">
                                    @if ($component['is_cv_list'])
                                        <button class="admin-action" type="button" wire:click="mountAction('addCvEntry')">Add CV entry</button>
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
                                        aria-label="{{ $componentReorderEnabled ? 'Move '.$component['type_label'].' component up' : 'Clear filters to reorder '.$component['type_label'].' component' }}"
                                    >↑</button>
                                    <button
                                        class="admin-action custom-page-component__order-action"
                                        type="button"
                                        wire:click="moveComponent({{ $component['index'] }}, '{{ $component['type'] }}', 'down')"
                                        @disabled(! $componentReorderEnabled || ! $component['can_move_down'])
                                        title="{{ $componentReorderEnabled ? 'Move component down' : 'Clear filters to reorder' }}"
                                        aria-label="{{ $componentReorderEnabled ? 'Move '.$component['type_label'].' component down' : 'Clear filters to reorder '.$component['type_label'].' component' }}"
                                    >↓</button>
                                    <button
                                        class="admin-action"
                                        type="button"
                                        wire:click="mountAction('deleteComponent', { componentIndex: {{ $component['index'] }}, componentType: '{{ $component['type'] }}' })"
                                    >Delete</button>
                                </div>
                            </header>

                            @if ($component['is_cv_list'])
                                <div class="custom-page-cv" aria-label="CV entries">
                                    <div class="custom-page-cv__controls" aria-label="CV controls">
                                        <label class="custom-page-workspace__field custom-page-cv__search">
                                            <span>Search CV</span>
                                            <input
                                                type="search"
                                                wire:model.blur="cvSearch"
                                                x-on:keydown.enter.prevent="$el.blur()"
                                                placeholder="Search CV"
                                                autocomplete="off"
                                            >
                                        </label>

                                        <label class="custom-page-workspace__field">
                                            <span>Section</span>
                                            <select wire:model.change="cvSection">
                                                <option value="any">Any section</option>
                                                @foreach ($cvSections as $section)
                                                    <option value="{{ $section }}">{{ $section }}</option>
                                                @endforeach
                                            </select>
                                        </label>

                                        <label class="custom-page-workspace__field">
                                            <span>Status</span>
                                            <select wire:model.change="cvStatus">
                                                <option value="any">Any</option>
                                                <option value="draft">Draft</option>
                                                <option value="published">Published</option>
                                                <option value="archived">Archived</option>
                                                @if ($hasLegacyHiddenCvEntries)
                                                    <option value="hidden">Hidden</option>
                                                @endif
                                            </select>
                                        </label>

                                        <div class="custom-page-workspace__control-group">
                                            <span class="custom-page-workspace__control-label">Filter</span>
                                            <button class="admin-action" type="button" wire:click="resetCvFilters">Reset</button>
                                        </div>

                                        <div
                                            class="custom-page-workspace__control-group custom-page-cv__selection"
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
                                                    @disabled($selectedCvIds === [])
                                                >
                                                    Selected CV entries
                                                    <span class="custom-page-workspace__selection-count">{{ count($selectedCvIds) }}</span>
                                                </button>
                                                <div
                                                    class="custom-page-workspace__selection-menu custom-page-cv__selection-menu"
                                                    x-show="open"
                                                    x-cloak
                                                    x-on:click.outside="open = false"
                                                    role="menu"
                                                >
                                                    <button class="admin-action" type="button" role="menuitem" wire:click="moveSelectedCvEntries('up')" x-on:click="open = false" @disabled(! $cvReorderEnabled) title="{{ $cvReorderEnabled ? 'Move selected CV entries up' : 'Clear filters to reorder' }}">Move selected up</button>
                                                    <button class="admin-action" type="button" role="menuitem" wire:click="moveSelectedCvEntries('down')" x-on:click="open = false" @disabled(! $cvReorderEnabled) title="{{ $cvReorderEnabled ? 'Move selected CV entries down' : 'Clear filters to reorder' }}">Move selected down</button>
                                                    @if ($canPublishSelectedCv)
                                                        <button class="admin-action" type="button" role="menuitem" wire:click="transitionSelectedCvEntries('publish')" x-on:click="open = false">Publish selected</button>
                                                    @endif
                                                    @if ($canUnpublishSelectedCv)
                                                        <button class="admin-action" type="button" role="menuitem" wire:click="transitionSelectedCvEntries('unpublish')" x-on:click="open = false">Unpublish selected</button>
                                                    @endif
                                                    @if ($canArchiveSelectedCv)
                                                        <button class="admin-action" type="button" role="menuitem" wire:click="transitionSelectedCvEntries('archive')" x-on:click="open = false">Archive selected</button>
                                                    @endif
                                                    @if ($canRestoreSelectedCv)
                                                        <button class="admin-action" type="button" role="menuitem" wire:click="transitionSelectedCvEntries('restore')" x-on:click="open = false">Restore selected to draft</button>
                                                    @endif
                                                    <button class="admin-action" type="button" role="menuitem" wire:click="mountAction('deleteSelectedCvEntries')" x-on:click="open = false">Delete selected</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    @if ($cvEntries !== [])
                                        <div class="custom-page-cv__table-wrap">
                                            <table class="custom-page-cv__table">
                                                <thead>
                                                    <tr>
                                                        <th scope="col" class="custom-page-cv__selection-head">
                                                            <input
                                                                type="checkbox"
                                                                x-data="{}"
                                                                wire:click.prevent="toggleVisibleCvSelection"
                                                                x-effect="
                                                                    $el.checked = @js($allVisibleCvSelected);
                                                                    $el.indeterminate = @js($visibleSelectedCvCount > 0 && ! $allVisibleCvSelected);
                                                                    $el.setAttribute('aria-checked', $el.indeterminate ? 'mixed' : ($el.checked ? 'true' : 'false'));
                                                                "
                                                                aria-label="Toggle selection for visible CV entries"
                                                            >
                                                        </th>
                                                        <th scope="col">Date</th>
                                                        <th scope="col">Entry</th>
                                                        <th scope="col">Section</th>
                                                        <th scope="col">Status</th>
                                                        <th scope="col">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($cvEntries as $entry)
                                                        <tr @class(['is-selected' => in_array((int) $entry['id'], $selectedCvIds, true)]) wire:key="custom-page-cv-entry-{{ $entry['id'] }}">
                                                            <td class="custom-page-cv__selection-cell">
                                                                <input type="checkbox" wire:model.live="selectedCvEntryIds" value="{{ $entry['id'] }}" aria-label="Select {{ $entry['title'] }}">
                                                            </td>
                                                            <td class="custom-page-cv__date">{{ $entry['date'] }}</td>
                                                            <td class="custom-page-cv__entry">
                                                                <strong>{{ $entry['title'] }}</strong>
                                                                @if ($entry['meta'] !== '')<small>{{ $entry['meta'] }}</small>@endif
                                                            </td>
                                                            <td>{{ $entry['section'] }}</td>
                                                            <td><span class="custom-page-cv__state {{ $entry['state'] === 'published' ? 'is-published' : '' }}">{{ $entry['state_label'] }}</span></td>
                                                            <td class="custom-page-cv__actions">
                                                                <div class="admin-toolbar">
                                                                    <button class="admin-action" type="button" wire:click="mountAction('editCvEntry', { entry: {{ $entry['id'] }} })">Edit</button>
                                                                    @if ($entry['state'] === 'draft')
                                                                        <button class="admin-action" type="button" wire:click="transitionCvEntry({{ $entry['id'] }}, 'publish')">Publish</button>
                                                                        <button class="admin-action" type="button" wire:click="transitionCvEntry({{ $entry['id'] }}, 'archive')">Archive</button>
                                                                    @elseif ($entry['state'] === 'published')
                                                                        <button class="admin-action" type="button" wire:click="transitionCvEntry({{ $entry['id'] }}, 'unpublish')">Unpublish</button>
                                                                        <button class="admin-action" type="button" wire:click="transitionCvEntry({{ $entry['id'] }}, 'archive')">Archive</button>
                                                                    @elseif (in_array($entry['state'], ['archived', 'hidden'], true))
                                                                        <button class="admin-action" type="button" wire:click="transitionCvEntry({{ $entry['id'] }}, 'restore')">Restore</button>
                                                                    @endif
                                                                    <button
                                                                        class="admin-action custom-page-component__order-action"
                                                                        type="button"
                                                                        wire:click="moveCvEntry({{ $entry['id'] }}, 'up')"
                                                                        @disabled(! $entry['can_move_up'])
                                                                        title="{{ $cvReorderEnabled ? 'Move CV entry up' : 'Clear filters to reorder' }}"
                                                                    >↑</button>
                                                                    <button
                                                                        class="admin-action custom-page-component__order-action"
                                                                        type="button"
                                                                        wire:click="moveCvEntry({{ $entry['id'] }}, 'down')"
                                                                        @disabled(! $entry['can_move_down'])
                                                                        title="{{ $cvReorderEnabled ? 'Move CV entry down' : 'Clear filters to reorder' }}"
                                                                    >↓</button>
                                                                    <button class="admin-action" type="button" wire:click="mountAction('deleteCvEntry', { entry: {{ $entry['id'] }} })">Delete</button>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @elseif ($cvEntryCount > 0)
                                        <x-admin.empty-state title="No matching CV entries" minimal>
                                            <x-slot:actions>
                                                <button class="admin-action" type="button" wire:click="resetCvFilters">Clear filters</button>
                                            </x-slot:actions>
                                        </x-admin.empty-state>
                                    @else
                                        <x-admin.empty-state title="No CV entries added" minimal>
                                            <x-slot:actions>
                                                <button class="admin-action" type="button" wire:click="mountAction('addCvEntry')">Add CV entry</button>
                                            </x-slot:actions>
                                        </x-admin.empty-state>
                                    @endif

                                    @if ($cvEntryCount > 0)
                                        <footer class="custom-page-cv__pager">
                                            <label class="custom-page-cv__pager-size">
                                                <span>Per page</span>
                                                <select wire:model.change="cvPageSize">
                                                    <option value="25">25</option>
                                                    <option value="50">50</option>
                                                    <option value="100">100</option>
                                                </select>
                                            </label>
                                            <span class="custom-page-cv__pager-range">
                                                @if ($cvTotal === 0)
                                                    0 of 0
                                                @else
                                                    {{ $cvResultStart }}–{{ $cvResultEnd }} of {{ $cvTotal }}
                                                @endif
                                            </span>
                                            <div class="custom-page-cv__pager-actions admin-toolbar">
                                                <button class="admin-action" type="button" wire:click="previousCvPage" @disabled($cvPage <= 1)>Previous</button>
                                                <button class="admin-action" type="button" wire:click="nextCvPage" @disabled($cvPage >= $cvPages)>Next</button>
                                            </div>
                                        </footer>
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
                <x-admin.empty-state title="No components added to this page" minimal>
                    <x-slot:actions>
                        <button class="admin-action" type="button" wire:click="mountAction('addComponent')">Add component</button>
                    </x-slot:actions>
                </x-admin.empty-state>
            @endif
        </x-admin.section>
    </x-admin.workspace>

    <x-filament-actions::modals />
</x-filament-panels::page>
