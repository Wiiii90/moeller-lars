        <x-admin.table>
            <section class="custom-page-component-sequence admin-hierarchy" aria-label="Page component sequence">
            <div class="custom-page-component-sequence__header admin-hierarchy__header">
                <span class="admin-hierarchy__ordering-heading">Position</span>
                <span class="custom-page-component-sequence__layout-heading">Layout</span>
                <span>Component</span>
                <span>Content</span>
                <span class="custom-page-component-sequence__actions-heading admin-row-actions-heading">Actions</span>
                <label class="admin-hierarchy__selection admin-hierarchy__selection--trailing" aria-label="Select all visible components">
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
                <x-admin.empty-state title="Empty" :minimal="true" />
            @elseif ($components === [])
                <x-admin.empty-state title="No matches" :minimal="true">
                    <x-slot:actions>
                        <x-admin.clear-filters label="Clear filters" wire:click="resetComponentFilters" />
                    </x-slot:actions>
                </x-admin.empty-state>
            @else
                @php($componentRows = collect($components)->keyBy('target')->all())
                <div class="custom-page-component-sequence__rows"
                    @if ($reorderEnabled)
                        wire:sort="sortComponent"
                        wire:sort:group="custom-components"
                        wire:sort:group-id="root"
                    @endif
                >
                    @if ($reorderEnabled)
                        @foreach ($this->componentLayoutItems() as $componentBlock)
                            @include('filament.pages.partials.custom-page-component-item', [
                                'componentBlock' => $componentBlock,
                                'pageComponent' => null,
                                'componentRows' => $componentRows,
                                'reorderEnabled' => true,
                            ])
                        @endforeach
                    @else
                        @foreach ($components as $pageComponent)
                            @include('filament.pages.partials.custom-page-component-item', [
                                'componentBlock' => null,
                                'pageComponent' => $pageComponent,
                                'componentRows' => $componentRows,
                                'reorderEnabled' => false,
                            ])
                        @endforeach
                    @endif
                </div>
            @endif
            </section>
        </x-admin.table>
