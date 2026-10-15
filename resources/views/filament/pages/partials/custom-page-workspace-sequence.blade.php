        <x-admin.table>
            <section class="custom-page-component-sequence admin-hierarchy" aria-label="Page component sequence">
            <div class="custom-page-component-sequence__header admin-hierarchy__header">
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
                <div class="custom-page-component-sequence__rows"
                    @if ($reorderEnabled)
                        wire:sort="sortComponent"
                        wire:sort:group="custom-components"
                        wire:sort:group-id="root"
                    @endif
                >
                    @foreach ($components as $pageComponent)
                        <article
                            class="custom-page-component admin-hierarchy__group"
                            wire:key="custom-component-{{ $pageComponent['target'] }}"
                            data-component-index="{{ $pageComponent['index'] }}"
                            data-component-type="{{ $pageComponent['type'] }}"
                            data-can-nest="{{ $pageComponent['can_nest'] ? 'true' : 'false' }}"
                            @if ($reorderEnabled) wire:sort:item="{{ $pageComponent['target'] }}" @endif
                        >
                            @include('filament.pages.partials.custom-page-component-row', [
                                'row' => $pageComponent, 'isChild' => false,
                                'reorderEnabled' => $reorderEnabled, 'componentTypeOptions' => $componentTypeOptions,
                            ])

                            @if ($pageComponent['accepts_children'] || $pageComponent['children'] !== [])
                                <div class="custom-page-component__children admin-hierarchy__children"
                                    role="rowgroup" aria-label="Components inside image"
                                >
                                    <div class="admin-hierarchy__children-rows"
                                        @if ($reorderEnabled && $pageComponent['accepts_children'])
                                            wire:sort="sortImageChild"
                                            wire:sort:group="custom-components"
                                            wire:sort:group-id="{{ $pageComponent['index'] }}"
                                        @endif
                                    >
                                        @foreach ($pageComponent['children'] as $child)
                                            <div class="custom-page-component__child-group"
                                                wire:key="image-child-{{ $pageComponent['target'] }}-{{ $child['target'] }}"
                                                @if ($reorderEnabled) wire:sort:item="{{ $child['target'] }}" @endif
                                            >
                                                @include('filament.pages.partials.custom-page-component-row', [
                                                    'row' => $child, 'isChild' => true,
                                                    'reorderEnabled' => $reorderEnabled,
                                                    'componentTypeOptions' => $componentTypeOptions,
                                                ])
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
            </section>
        </x-admin.table>
