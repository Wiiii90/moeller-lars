<x-filament-panels::page>
    <x-admin.workspace title="Home" class="admin-home-workspace">
        @if ($metrics !== [])
            <x-admin.metrics :columns="count($metrics)" aria-label="Home overview">
                @foreach ($metrics as $metric)
                    <x-admin.metric :label="$metric['label']" :value="$metric['value']">{{ $metric['description'] }}</x-admin.metric>
                @endforeach
            </x-admin.metrics>
        @endif

        <div class="home-workspace__template-controls" aria-label="Home actions">
            <div class="home-workspace__control-group">
                <span class="home-workspace__control-label">{{ $templateLabel }}</span>
                <div class="home-workspace__actions">
                    <button class="admin-action" type="button" wire:click="mountAction('settings')">Settings</button>
                    @if (in_array($template, ['under_construction', 'custom'], true))
                        <button class="admin-action is-primary" type="button" wire:click="mountAction('addComponent')">Add component</button>
                    @endif
                    <a class="admin-action" href="{{ $previewUrl }}" target="_blank" rel="noopener">Preview</a>
                </div>
            </div>
        </div>

        @if ($template === 'artwork')
            <x-admin.section title="Current Home artwork">
                @if ($selectionIssue)
                    <p class="home-workspace__notice">{{ $selectionIssue }} Choose an explicit Home tie-breaker on one of the newest eligible artworks below.</p>
                @endif

                @if ($currentArtwork)
                    <div class="home-table home-table--artwork">
                        <div class="home-table__header" aria-hidden="true">
                            <span>Artwork</span>
                            <span>Gallery</span>
                            <span>Year / date</span>
                            <span>Tie-breaker</span>
                            <span>Actions</span>
                        </div>
                        <div class="home-table__row" wire:key="home-current-artwork-{{ $currentArtwork['id'] }}">
                            <div class="home-table__identity">
                                @if ($currentArtwork['thumbnail_url'])
                                    <img src="{{ $currentArtwork['thumbnail_url'] }}" alt="" width="54" height="42" loading="eager" decoding="async">
                                @endif
                                <strong>{{ $currentArtwork['title'] }}</strong>
                            </div>
                            <span>{{ $currentArtwork['gallery'] ?: '—' }}</span>
                            <span>{{ $currentArtwork['date'] ?: ($currentArtwork['year'] ?: '—') }}</span>
                            <span>{{ $currentArtwork['featured'] ? 'Explicit' : 'Automatic' }}</span>
                            <div class="admin-toolbar home-table__actions">
                                <a class="admin-action" href="{{ $currentArtwork['preview_url'] }}" target="_blank" rel="noopener">Preview</a>
                                <a class="admin-action" href="{{ $currentArtwork['edit_url'] }}">Edit</a>
                                @if ($currentArtwork['gallery_url'])
                                    <a class="admin-action" href="{{ $currentArtwork['gallery_url'] }}">Open Gallery</a>
                                @endif
                            </div>
                        </div>
                    </div>
                @else
                    <x-admin.empty-state title="No Home artwork is available">
                        <p>Publish artwork in a Gallery that is enabled as a Home source.</p>
                    </x-admin.empty-state>
                @endif
            </x-admin.section>

            <x-admin.section title="Gallery Sources">
                @if ($galleries !== [])
                    <div class="home-table home-table--sources">
                        <div class="home-table__header" aria-hidden="true">
                            <span>Gallery</span>
                            <span>Public state</span>
                            <span>Home source</span>
                            <span>Published artworks</span>
                            <span>Newest year</span>
                            <span>Actions</span>
                        </div>
                        @foreach ($galleries as $gallery)
                            <div class="home-table__row" wire:key="home-gallery-{{ $gallery['id'] }}">
                                <strong>{{ $gallery['name'] }}</strong>
                                <span>{{ ucfirst($gallery['state']) }}</span>
                                <button
                                    type="button"
                                    class="admin-action {{ $gallery['eligible'] ? 'is-primary' : '' }}"
                                    wire:click="toggleGalleryEligibility({{ $gallery['id'] }})"
                                    aria-pressed="{{ $gallery['eligible'] ? 'true' : 'false' }}"
                                >{{ $gallery['eligible'] ? 'Enabled' : 'Disabled' }}</button>
                                <span>{{ number_format($gallery['published_artworks']) }}</span>
                                <span>{{ $gallery['newest_year'] ?: '—' }}</span>
                                <div class="admin-toolbar home-table__actions">
                                    <a class="admin-action" href="{{ $gallery['workspace_url'] }}">Open Gallery</a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <x-admin.empty-state title="No Gallery sources exist" />
                @endif
            </x-admin.section>

            <x-admin.section title="Hero Candidates">
                @if ($newestEligibleArtworks !== [])
                    <div class="home-table home-table--artwork">
                        <div class="home-table__header" aria-hidden="true">
                            <span>Artwork</span>
                            <span>Gallery</span>
                            <span>Year / date</span>
                            <span>Tie-breaker</span>
                            <span>Actions</span>
                        </div>
                        @foreach ($newestEligibleArtworks as $artwork)
                            <div class="home-table__row" wire:key="home-candidate-{{ $artwork['id'] }}">
                                <div class="home-table__identity">
                                    @if ($artwork['thumbnail_url'])
                                        <img src="{{ $artwork['thumbnail_url'] }}" alt="" width="54" height="42" loading="lazy" decoding="async">
                                    @endif
                                    <strong>{{ $artwork['title'] }}</strong>
                                </div>
                                <span>{{ $artwork['gallery'] ?: '—' }}</span>
                                <span>{{ $artwork['date'] ?: ($artwork['year'] ?: '—') }}</span>
                                <span>{{ $artwork['featured'] ? 'Explicit' : 'Automatic' }}</span>
                                <div class="admin-toolbar home-table__actions">
                                    <a class="admin-action" href="{{ $artwork['preview_url'] }}" target="_blank" rel="noopener">Preview</a>
                                    <a class="admin-action" href="{{ $artwork['edit_url'] }}">Edit</a>
                                    @if ($artwork['gallery_url'])
                                        <a class="admin-action" href="{{ $artwork['gallery_url'] }}">Open Gallery</a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <x-admin.empty-state title="No eligible artworks exist">
                        <p>Enable a published Gallery as a Home source and ensure its published artwork has a year.</p>
                    </x-admin.empty-state>
                @endif
            </x-admin.section>
        @elseif (in_array($template, ['under_construction', 'custom'], true))
            @if ($readinessWarning)
                <p class="home-workspace__notice">{{ $readinessWarning }}</p>
            @endif

            <div class="home-component-tools" aria-label="Home component tools">
                <label class="gallery-workspace__field home-component-tools__search">
                    <span>Search</span>
                    <input
                        type="search"
                        wire:model.live.debounce.300ms="componentSearch"
                        placeholder="Search components"
                        autocomplete="off"
                    >
                </label>

                <label class="gallery-workspace__field">
                    <span>Type</span>
                    <select wire:model.live="componentType">
                        <option value="any">All components</option>
                        @foreach ($componentTypeOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="gallery-workspace__control-group">
                    <span class="gallery-workspace__control-label">Filter</span>
                    <button class="admin-action" type="button" wire:click="resetComponentFilters">Reset</button>
                </div>

                @php($reorderEnabled = trim($componentSearch) === '' && $componentType === 'any')
                <div
                    class="gallery-workspace__control-group gallery-workspace__selection home-component-tools__selection"
                    x-data="{ open: false }"
                    x-on:click.outside="open = false"
                    x-on:keydown.escape.window="open = false"
                >
                    <span class="gallery-workspace__control-label">Selection</span>
                    <div class="gallery-workspace__selection-anchor">
                        <button
                            class="admin-action gallery-workspace__selection-trigger"
                            type="button"
                            x-on:click="open = ! open"
                            x-bind:aria-expanded="open.toString()"
                            aria-haspopup="menu"
                            @disabled($selectedComponentTargets === [])
                        >
                            Selected components
                            <span class="gallery-workspace__selection-count">{{ count($selectedComponentTargets) }}</span>
                        </button>
                        <div class="gallery-workspace__selection-menu" role="menu" x-show="open" x-cloak>
                            <button
                                class="admin-action"
                                type="button"
                                role="menuitem"
                                wire:click="moveSelectedComponents('up')"
                                x-on:click="open = false"
                                @disabled(! $reorderEnabled)
                            >Move selected up</button>
                            <button
                                class="admin-action"
                                type="button"
                                role="menuitem"
                                wire:click="moveSelectedComponents('down')"
                                x-on:click="open = false"
                                @disabled(! $reorderEnabled)
                            >Move selected down</button>
                            <button
                                class="admin-action is-danger"
                                type="button"
                                role="menuitem"
                                wire:click="mountAction('deleteSelectedComponents')"
                                x-on:click="open = false"
                            >Delete selected</button>
                        </div>
                    </div>
                </div>
            </div>

            <section class="custom-page-component-sequence home-component-sequence" aria-label="Home component sequence">
                <div class="custom-page-component-sequence__header" aria-hidden="true">
                    <span></span>
                    <span></span>
                    <span>Component</span>
                    <span>Content</span>
                    <span>Actions</span>
                </div>

                @if ($components === [])
                    <x-admin.empty-state title="No matching components">
                        <p>Add a component or reset the current filters.</p>
                    </x-admin.empty-state>
                @else
                    <div class="custom-page-component-sequence__rows" @if ($reorderEnabled) wire:sort="sortComponent" @endif>
                        @foreach ($components as $component)
                            <article
                                class="custom-page-component"
                                wire:key="home-component-{{ $template }}-{{ $component['target'] }}"
                                @if ($reorderEnabled) wire:sort:item="{{ $component['target'] }}" @endif
                            >
                                <div class="custom-page-component__header">
                                    <label class="custom-page-component__select" aria-label="Select {{ $component['type_label'] }}">
                                        <input type="checkbox" value="{{ $component['target'] }}" wire:model.live="selectedComponentTargets">
                                    </label>

                                    <button
                                        class="custom-page-row__drag"
                                        type="button"
                                        @if ($reorderEnabled) wire:sort:handle @else disabled @endif
                                        aria-label="Drag {{ $component['type_label'] }}"
                                    >⋮⋮</button>

                                    <div class="home-component-sequence__type">
                                        <strong>{{ $component['type_label'] }}</strong>
                                    </div>

                                    <div class="custom-page-component__content">
                                        <strong>{{ $component['content']['primary'] }}</strong>
                                        @if ($component['content']['secondary'] !== '')
                                            <span>{{ $component['content']['secondary'] }}</span>
                                        @endif
                                    </div>

                                    <div class="custom-page-component__actions admin-toolbar">
                                        @if ($component['editable'])
                                            <button
                                                class="admin-action"
                                                type="button"
                                                wire:click="mountAction('editComponent', { index: {{ $component['index'] }}, type: '{{ $component['type'] }}' })"
                                            >Edit</button>
                                        @endif
                                        <button
                                            class="admin-action"
                                            type="button"
                                            wire:click="moveComponent({{ $component['index'] }}, '{{ $component['type'] }}', 'up')"
                                            @disabled(! $reorderEnabled || ! $component['can_move_up'])
                                            aria-label="Move {{ $component['type_label'] }} up"
                                        >↑</button>
                                        <button
                                            class="admin-action"
                                            type="button"
                                            wire:click="moveComponent({{ $component['index'] }}, '{{ $component['type'] }}', 'down')"
                                            @disabled(! $reorderEnabled || ! $component['can_move_down'])
                                            aria-label="Move {{ $component['type_label'] }} down"
                                        >↓</button>
                                        <button
                                            class="admin-action is-danger"
                                            type="button"
                                            wire:click="mountAction('removeComponent', { index: {{ $component['index'] }}, type: '{{ $component['type'] }}' })"
                                        >Delete</button>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif

                <button class="custom-page-component-add-row" type="button" wire:click="mountAction('addComponent')">
                    <span aria-hidden="true">+</span>
                    <strong>Add component</strong>
                </button>
            </section>
        @elseif ($template === 'skip_home')
            <x-admin.section title="Skip Home">
                @if ($skipTarget)
                    <div class="home-workspace__skip-row">
                        <div>
                            <strong>{{ $skipTarget['label'] }}</strong>
                            <span>{{ $skipTarget['type'] }} · {{ $skipTarget['path'] }}</span>
                        </div>
                        <div class="home-workspace__redirect-expression" aria-label="Current Home redirect">
                            <code>/</code><span aria-hidden="true">→</span><code>{{ $skipTarget['path'] }}</code>
                        </div>
                        <a class="admin-action" href="{{ $skipTarget['url'] }}" target="_blank" rel="noopener">Open target</a>
                    </div>
                @else
                    <x-admin.empty-state title="No redirect target is available">
                        <p>{{ $readinessWarning }} The public root renders a safe fallback instead of redirecting.</p>
                    </x-admin.empty-state>
                @endif
            </x-admin.section>
        @endif
    </x-admin.workspace>

    <x-filament-actions::modals />
</x-filament-panels::page>
