        <x-admin.table>
            <section class="custom-page-component-sequence admin-hierarchy" aria-label="Page component sequence"
                x-data="{
                    dragging: false, dragArmed: false, startX: 0, startY: 0,
                    sourceIndex: null, sourceType: null, sourceCanNest: false,
                    sourceImage: null, sourceChild: null, hoverParent: null,
                    resetDrag() {
                        this.dragging = false; this.dragArmed = false;
                        this.sourceIndex = null; this.sourceType = null;
                        this.sourceCanNest = false; this.sourceImage = null;
                        this.sourceChild = null; this.hoverParent = null;
                    },
                }"
                x-on:pointerdown.capture="
                    const grip = $event.target.closest('[data-hierarchy-drag-handle]:not(:disabled)');
                    if (!grip) return;
                    const row = grip.closest('[data-component-index]');
                    if (!row) return;
                    dragArmed = true;
                    startX = $event.clientX; startY = $event.clientY;
                    sourceIndex = Number(row.dataset.componentIndex);
                    sourceType = row.dataset.componentType;
                    sourceCanNest = row.dataset.canNest === 'true';
                    sourceImage = grip.hasAttribute('data-image-index') ? Number(grip.dataset.imageIndex) : null;
                    sourceChild = grip.hasAttribute('data-child-index') ? Number(grip.dataset.childIndex) : null;
                "
                x-on:pointermove.window="
                    if (dragArmed && !dragging && ($event.buttons & 1) === 1 &&
                        Math.hypot($event.clientX - startX, $event.clientY - startY) >= 4) dragging = true;
                "
                x-on:dragstart.capture="if (dragArmed) dragging = true"
                x-on:pointerup.window="resetDrag()"
                x-on:pointercancel.window="resetDrag()"
                x-on:dragend.window="resetDrag()"
                x-on:drop.window="resetDrag()"
            >
            <div class="custom-page-component-sequence__header admin-hierarchy__header">
                <div class="admin-hierarchy__nest-target custom-page-component__unnest-target"
                    x-cloak x-show="dragging && sourceChild !== null"
                    x-on:dragover.stop.prevent
                    x-on:pointerup.stop.prevent="
                        if (dragging && sourceImage !== null && sourceChild !== null) {
                            $wire.unnestImageChild(sourceImage, sourceChild);
                        }
                        resetDrag();
                    "
                    x-on:drop.stop.prevent="
                        if (dragging && sourceImage !== null && sourceChild !== null) {
                            $wire.unnestImageChild(sourceImage, sourceChild);
                        }
                        resetDrag();
                    "
                ><span>Drop to page level</span></div>
                <span class="admin-hierarchy__ordering-heading">Position</span>
                <span>Component</span>
                <span>Content</span>
                <span class="custom-page-component-sequence__actions-heading admin-row-actions-heading">Actions</span>
                <label class="admin-hierarchy__selection admin-hierarchy__selection--trailing" aria-label="Select all visible components and entries">
                    <input
                        type="checkbox"
                        wire:change="selectAllVisible($event.target.checked)"
                        x-data
                        x-bind:checked="$wire.selectedComponentTargets.length === {{ $visibleSelectableCount }} && {{ $visibleSelectableCount }} > 0"
                        x-effect="$el.indeterminate = ($wire.selectedComponentTargets.length > 0) && ($wire.selectedComponentTargets.length < {{ $visibleSelectableCount }})"
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
                <div class="custom-page-component-sequence__rows" @if ($reorderEnabled && count($components) > 1) wire:sort="sortComponent" @endif>
                    @foreach ($components as $pageComponent)
                        <article
                            class="custom-page-component admin-hierarchy__group"
                            wire:key="custom-component-{{ $pageComponent['target'] }}"
                            data-component-index="{{ $pageComponent['index'] }}"
                            data-component-type="{{ $pageComponent['type'] }}"
                            data-can-nest="{{ $pageComponent['can_nest'] ? 'true' : 'false' }}"
                            @if ($reorderEnabled && count($components) > 1) wire:sort:item="{{ $pageComponent['target'] }}" @endif
                        >
                            <div class="custom-page-component__header admin-hierarchy__row">
                                <div class="admin-hierarchy__position-cell">
                                    <span class="admin-position" aria-label="Position {{ $pageComponent['position'] }}">
                                        {{ str_pad((string) $pageComponent['position'], 2, '0', STR_PAD_LEFT) }}
                                    </span>
                                </div>

                                <button class="admin-drag-handle custom-page-component__drag" type="button" data-hierarchy-drag-handle="true" @if ($reorderEnabled && count($components) > 1) wire:sort:handle @else disabled @endif aria-label="Drag {{ $pageComponent['type_label'] }}" title="{{ ! $reorderEnabled ? 'Clear filters and show all components to reorder' : (count($components) < 2 ? 'At least two components are needed to reorder' : 'Drag to reorder') }}">⋮⋮</button>

                                <div class="custom-page-component__type">
                                    <select
                                        class="admin-inline-select custom-page-component__type-select"
                                        aria-label="Component type"
                                        wire:change="mountAction('changeComponentType', { componentIndex: {{ $pageComponent['index'] }}, componentType: '{{ $pageComponent['type'] }}', targetType: $event.target.value })"
                                    >
                                        @foreach ($componentTypeOptions as $value => $label)
                                            <option value="{{ $value }}" @selected($value === $pageComponent['type'])>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="custom-page-component__content admin-hierarchy__content {{ in_array($pageComponent['type'], ['contact', 'legal_disclaimer'], true) ? 'custom-page-component__content--two-lines' : '' }}">
                                    <strong>{{ $pageComponent['content']['primary'] }}</strong>
                                    @if ($pageComponent['content']['secondary'] !== '')
                                        <span>{{ $pageComponent['content']['secondary'] }}</span>
                                    @endif
                                    @if ($pageComponent['content']['meta'] !== '')
                                        <small>{{ $pageComponent['content']['meta'] }}</small>
                                    @endif
                                </div>

                                <div class="admin-row-actions admin-row-actions--canonical admin-row-actions--stable admin-row-actions--move-state-delete custom-page-row-actions custom-page-component__actions admin-toolbar">
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
                                        wire:click="mountAction('{{ $pageComponent['type'] === 'list' ? 'editListComponent' : 'editComponent' }}', { componentIndex: {{ $pageComponent['index'] }}, componentType: '{{ $pageComponent['type'] }}' })"
                                        :disabled="! $pageComponent['editable']"
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

                            @if ($reorderEnabled && $pageComponent['accepts_children'])
                                <div class="admin-hierarchy__nest-target custom-page-component__nest-target"
                                    x-cloak
                                    x-show="dragging && sourceCanNest && sourceChild === null && sourceIndex !== {{ $pageComponent['index'] }}"
                                    x-bind:class="{ 'is-hovered': hoverParent === {{ $pageComponent['index'] }} }"
                                    x-on:pointerenter="if (dragging) hoverParent = {{ $pageComponent['index'] }}"
                                    x-on:pointerleave="if (hoverParent === {{ $pageComponent['index'] }}) hoverParent = null"
                                    x-on:dragover.stop.prevent
                                    x-on:pointerup.stop.prevent="
                                        if (dragging && sourceCanNest && sourceIndex !== null) {
                                            $wire.nestComponent(sourceIndex, sourceType, {{ $pageComponent['index'] }});
                                        }
                                        resetDrag();
                                    "
                                    x-on:drop.stop.prevent="
                                        if (dragging && sourceCanNest && sourceIndex !== null) {
                                            $wire.nestComponent(sourceIndex, sourceType, {{ $pageComponent['index'] }});
                                        }
                                        resetDrag();
                                    "
                                ><span>Drop inside image</span></div>
                            @endif

                            @if ($pageComponent['children'] !== [])
                                <div class="custom-page-component__children" role="list" aria-label="Content inside image"
                                    @if ($reorderEnabled && count($pageComponent['children']) > 1)
                                        wire:sort="sortImageChild"
                                        wire:sort:group="image-{{ $pageComponent['index'] }}"
                                        wire:sort:group-id="{{ $pageComponent['index'] }}"
                                    @endif
                                >
                                    @foreach ($pageComponent['children'] as $childIndex => $child)
                                        <div class="custom-page-component__child" role="listitem"
                                            wire:key="image-child-{{ $pageComponent['target'] }}-{{ $childIndex }}"
                                            @if ($reorderEnabled && count($pageComponent['children']) > 1) wire:sort:item="{{ $childIndex }}" @endif
                                        >
                                            <button type="button" class="admin-drag-handle custom-page-component__child-drag"
                                                data-hierarchy-drag-handle="true"
                                                data-image-index="{{ $pageComponent['index'] }}"
                                                data-child-index="{{ $childIndex }}"
                                                @if ($reorderEnabled && count($pageComponent['children']) > 1) wire:sort:handle @else disabled @endif
                                                aria-label="Reorder image child {{ $child['title'] }}"
                                            >⋮⋮</button>
                                            <span class="custom-page-component__child-type">{{ $child['type'] }}</span>
                                            <strong>{{ $child['title'] }}</strong>
                                            @if ($child['meta'] !== '')
                                                <small>{{ $child['meta'] }}</small>
                                            @endif
                                            @unless ($child['published'])
                                                <small>Unpublished</small>
                                            @endunless
                                            <button type="button" class="admin-action custom-page-component__child-out"
                                                wire:click="unnestImageChild({{ $pageComponent['index'] }}, {{ $childIndex }})"
                                                aria-label="Move {{ $child['title'] }} out of image"
                                            >Move out</button>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
            </section>
        </x-admin.table>
