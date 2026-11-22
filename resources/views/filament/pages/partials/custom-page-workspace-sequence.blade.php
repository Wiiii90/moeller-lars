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
                            @if ($reorderEnabled) wire:sort:item="{{ $pageComponent['target'] }}" @endif
                        >
                            @include('filament.pages.partials.custom-page-component-row', [
                                'row' => $pageComponent,
                                'reorderEnabled' => $reorderEnabled, 'componentTypeOptions' => $componentTypeOptions,
                            ])

                            @if ($pageComponent['embedded_children'] !== [])
                                <div class="custom-page-component__embedded-children" role="group"
                                    aria-label="{{ $pageComponent['type_label'] }} page template">
                                    @foreach ($pageComponent['embedded_children'] as $child)
                                        <div class="custom-page-component__embedded-child"
                                            wire:key="embedded-page-{{ $pageComponent['index'] }}-{{ $child['id'] }}">
                                            <span class="custom-page-component__embedded-child-title">
                                                {{ __('Source page') }}: {{ $child['title'] }}
                                            </span>
                                            @if ($child['workspace_url'])
                                                <a class="admin-action" href="{{ $child['workspace_url'] }}">
                                                    {{ __('Open source editor') }}
                                                </a>
                                            @endif
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
