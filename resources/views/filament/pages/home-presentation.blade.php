<x-filament-panels::page>
    <x-admin.workspace title="Home" class="admin-home-workspace">
        @if ($metrics !== [])
            <div @if ($template === 'artwork') wire:init="loadHomeAnalytics" @endif>
                <x-admin.metrics :columns="count($metrics)" aria-label="Home overview">
                    @foreach ($metrics as $metric)
                        <x-admin.metric :label="$metric['label']" :value="$metric['value']">{{ $metric['description'] }}</x-admin.metric>
                    @endforeach
                </x-admin.metrics>
            </div>
        @endif

        @if ($template === 'artwork')
            @php($additionalHeroCandidates = collect($heroCandidates)->reject(fn (array $candidate): bool => $currentArtwork !== null && $candidate['id'] === $currentArtwork['id'])->values())
            <div class="home-hero-surface {{ $additionalHeroCandidates->isEmpty() ? 'is-compact' : 'has-candidates' }}" aria-label="Hero Artwork">
                <div class="home-hero-surface__current">
                    <div class="home-hero-surface__visual">
                        @if ($currentArtwork && $currentArtwork['thumbnail_url'])
                            <img
                                src="{{ $currentArtwork['thumbnail_url'] }}"
                                alt=""
                                loading="eager"
                                decoding="async"
                            >
                        @else
                            <span>No Hero Artwork</span>
                        @endif
                    </div>
                </div>

                @if ($currentArtwork || $additionalHeroCandidates->isNotEmpty() || $selectionIssue)
                    <div class="home-hero-candidate-rail" aria-label="Hero artwork context">
                        @if ($currentArtwork)
                            <article class="home-hero-candidate is-current" wire:key="home-current-hero-{{ $currentArtwork['id'] }}">
                                <div class="home-hero-candidate__visual">
                                    @if ($currentArtwork['thumbnail_url'])
                                        <img src="{{ $currentArtwork['thumbnail_url'] }}" alt="" loading="lazy" decoding="async">
                                    @else
                                        <span>—</span>
                                    @endif
                                </div>
                                <div class="home-hero-candidate__meta">
                                    <strong title="{{ $currentArtwork['title'] }}">{{ $currentArtwork['title'] }}</strong>
                                    <span title="{{ $currentArtwork['gallery'] ?: '—' }}">{{ $currentArtwork['gallery'] ?: '—' }}</span>
                                    <small>
                                        {{ $currentArtwork['year'] ?: '—' }}
                                        · {{ $heroMode === 'manual' ? 'Manual' : ($heroSelection === 'random' ? 'Random preview' : 'Current') }}
                                    </small>
                                </div>
                            </article>
                        @endif

                        @foreach ($additionalHeroCandidates as $candidate)
                            <article class="home-hero-candidate" wire:key="home-hero-candidate-{{ $candidate['id'] }}">
                                <div class="home-hero-candidate__visual">
                                    @if ($candidate['thumbnail_url'])
                                        <img src="{{ $candidate['thumbnail_url'] }}" alt="" loading="lazy" decoding="async">
                                    @else
                                        <span>—</span>
                                    @endif
                                </div>
                                <div class="home-hero-candidate__meta">
                                    <strong title="{{ $candidate['title'] }}">{{ $candidate['title'] }}</strong>
                                    <span title="{{ $candidate['gallery'] ?: '—' }}">{{ $candidate['gallery'] ?: '—' }}</span>
                                    <small>{{ $candidate['year'] ?: '—' }}</small>
                                </div>
                            </article>
                        @endforeach

                        @if ($selectionIssue)
                            <p class="home-hero-surface__issue">{{ $selectionIssue }}</p>
                        @endif
                    </div>
                @endif
            </div>

            @php($sourceRows = $this->sourceRows())
            @php($visibleSourceIds = collect($sourceRows->items())->pluck('id')->map(fn ($id) => (int) $id)->values()->all())
            <div class="home-source-controls-divider" aria-hidden="true"></div>
            <div class="home-source-controls" aria-label="Gallery source controls">
                <label class="home-source-controls__field home-source-controls__search">
                    <span>Search</span>
                    <input
                        type="search"
                        wire:model.live.debounce.300ms="sourceSearch"
                        placeholder="Gallery"
                        autocomplete="off"
                    >
                </label>

                <label class="home-source-controls__field">
                    <span>Status</span>
                    <select wire:model.live="sourceStatusFilter">
                        <option value="any">Any</option>
                        <option value="published">Published</option>
                        <option value="unpublished">Unpublished</option>
                    </select>
                </label>

                <label class="home-source-controls__field">
                    <span>Source</span>
                    <select wire:model.live="sourceHomeFilter">
                        <option value="any">Any</option>
                        <option value="enabled">Enabled</option>
                        <option value="disabled">Disabled</option>
                    </select>
                </label>

                <div class="home-source-controls__control-group">
                    <span class="home-source-controls__control-label">Filter</span>
                    <button class="admin-action" type="button" wire:click="resetSourceFilters">Reset</button>
                </div>

                <div class="home-source-controls__control-group home-source-controls__template">
                    <span class="home-source-controls__control-label">HERO ARTWORK</span>
                    <div class="home-source-controls__template-actions">
                        <button class="admin-action" type="button" wire:click="mountAction('settings')">Settings</button>
                        <a class="admin-action" href="{{ $previewUrl }}" target="_blank" rel="noopener">Preview</a>
                    </div>
                </div>

                <div
                    class="home-source-controls__control-group home-source-controls__selection"
                    x-data="{ open: false }"
                    x-on:click.outside="open = false"
                    x-on:keydown.escape.window="open = false"
                >
                    <span class="home-source-controls__control-label">Selection</span>
                    <div class="home-source-controls__selection-anchor">
                        <button
                            class="admin-action home-source-controls__selection-trigger"
                            type="button"
                            x-on:click="open = ! open"
                            x-bind:aria-expanded="open.toString()"
                            aria-haspopup="menu"
                            @disabled($selectedSourceIds === [])
                        >
                            Selected Galleries
                            <span class="home-source-controls__selection-count">{{ count($selectedSourceIds) }}</span>
                        </button>
                        <div class="home-source-controls__selection-menu" role="menu" x-show="open" x-cloak>
                            <button
                                class="admin-action"
                                type="button"
                                role="menuitem"
                                wire:click="setSelectedGalleryEligibility(true)"
                                x-on:click="open = false"
                            >Enable Source</button>
                            <button
                                class="admin-action"
                                type="button"
                                role="menuitem"
                                wire:click="setSelectedGalleryEligibility(false)"
                                x-on:click="open = false"
                            >Disable Source</button>
                        </div>
                    </div>
                </div>
            </div>

            @if ($sourceRows->count() > 0)
                <x-admin.table class="home-source-list">
                    <table class="home-source-list__table">
                        <thead>
                            <tr>
                                <th scope="col" class="home-source-list__selection-head">
                                    <input
                                        type="checkbox"
                                        x-data="{}"
                                        wire:click.prevent="toggleVisibleSourceSelection"
                                        x-effect="
                                            const visibleIds = @js($visibleSourceIds);
                                            const selectedIds = $wire.selectedSourceIds.map(Number);
                                            const selectedCount = visibleIds.filter((id) => selectedIds.includes(id)).length;
                                            $el.checked = visibleIds.length > 0 && selectedCount === visibleIds.length;
                                            $el.indeterminate = selectedCount > 0 && selectedCount < visibleIds.length;
                                            $el.setAttribute('aria-checked', $el.indeterminate ? 'mixed' : ($el.checked ? 'true' : 'false'));
                                        "
                                        @disabled($visibleSourceIds === [])
                                        aria-label="Toggle selection for visible Galleries"
                                    >
                                </th>
                                <th scope="col">Gallery</th>
                                <th scope="col">Candidates</th>
                                <th scope="col">Status</th>
                                <th scope="col">Artworks</th>
                                <th scope="col">Newest Year</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sourceRows as $gallery)
                                @php($selected = in_array($gallery['id'], array_map('intval', $selectedSourceIds), true))
                                <tr class="{{ $selected ? 'is-selected' : '' }}" wire:key="home-source-gallery-{{ $gallery['id'] }}">
                                    <td class="home-source-list__selection-cell">
                                        <input
                                            type="checkbox"
                                            value="{{ $gallery['id'] }}"
                                            wire:model.live="selectedSourceIds"
                                            aria-label="Select {{ $gallery['name'] }}"
                                        >
                                    </td>
                                    <td class="home-source-list__gallery">
                                        <strong>{{ $gallery['name'] }}</strong>
                                    </td>
                                    <td>
                                        <div class="home-source-candidates" aria-label="Candidates from {{ $gallery['name'] }}">
                                            @forelse ($gallery['candidates'] as $candidate)
                                                <a
                                                    href="{{ $candidate['edit_url'] }}"
                                                    title="{{ $candidate['title'] }} · {{ $candidate['year'] ?: '—' }}"
                                                    aria-label="Edit {{ $candidate['title'] }}"
                                                >
                                                    @if ($candidate['thumbnail_url'])
                                                        <img src="{{ $candidate['thumbnail_url'] }}" alt="" loading="lazy" decoding="async">
                                                    @else
                                                        <span>—</span>
                                                    @endif
                                                    <small>{{ $candidate['title'] }}</small>
                                                </a>
                                            @empty
                                                <span class="home-source-candidates__empty">—</span>
                                            @endforelse
                                        </div>
                                    </td>
                                    <td>
                                        <span class="home-source-list__status is-{{ $gallery['state'] === 'published' ? 'published' : 'unpublished' }}">
                                            {{ $gallery['status_label'] }}
                                        </span>
                                    </td>
                                    <td>{{ number_format($gallery['published_artworks']) }}</td>
                                    <td>{{ $gallery['newest_year'] ?: '—' }}</td>
                                    <td class="home-source-list__actions">
                                        <div class="admin-toolbar">
                                            <span class="home-source-list__source-action">
                                                <button
                                                    class="admin-action"
                                                    type="button"
                                                    wire:click="toggleGalleryEligibility({{ $gallery['id'] }})"
                                                >{{ $gallery['eligible'] ? 'Disable Source' : 'Enable Source' }}</button>
                                            </span>
                                            <a class="admin-action" href="{{ $gallery['workspace_url'] }}">Open Gallery</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-admin.table>
            @else
                @if (trim($sourceSearch) !== '' || $sourceStatusFilter !== 'any' || $sourceHomeFilter !== 'any')
                    <x-admin.empty-state title="No matching Galleries" minimal>
                        <x-slot:actions>
                            <button class="admin-action" type="button" wire:click="resetSourceFilters">Clear filters</button>
                        </x-slot:actions>
                    </x-admin.empty-state>
                @else
                    <x-admin.empty-state title="No Gallery sources" minimal />
                @endif
            @endif

            @if ($sourceRows->total() > $sourceRows->perPage() || $sourceRows->currentPage() > 1)
                <footer class="home-source-pager">
                    <label class="home-source-pager__size">
                        <span>Per page</span>
                        <select wire:model.live.number="sourcePerPage">
                            <option value="10">10</option>
                            <option value="25">25</option>
                        </select>
                    </label>

                    <span class="home-source-pager__range">
                        {{ $sourceRows->firstItem() ?? 0 }}–{{ $sourceRows->lastItem() ?? 0 }} of {{ $sourceRows->total() }}
                    </span>

                    <div class="home-source-pager__actions admin-toolbar">
                        <button
                            class="admin-action"
                            type="button"
                            wire:click="goToSourcePage({{ $sourceRows->currentPage() - 1 }})"
                            @disabled($sourceRows->onFirstPage())
                        >Previous</button>
                        <button
                            class="admin-action"
                            type="button"
                            wire:click="goToSourcePage({{ $sourceRows->currentPage() + 1 }})"
                            @disabled(! $sourceRows->hasMorePages())
                        >Next</button>
                    </div>
                </footer>
            @endif
        @elseif (in_array($template, ['under_construction', 'custom'], true))
            @php($reorderEnabled = trim($componentSearch) === '' && $componentType === 'any')
            <div class="home-component-controls" aria-label="Home component controls">
                <label class="home-component-controls__field home-component-controls__search">
                    <span>Search</span>
                    <input
                        type="search"
                        wire:model.live.debounce.300ms="componentSearch"
                        placeholder="Components"
                        autocomplete="off"
                    >
                </label>

                <label class="home-component-controls__field">
                    <span>Type</span>
                    <select wire:model.live="componentType">
                        <option value="any">Any</option>
                        @foreach ($componentTypeOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="home-component-controls__control-group">
                    <span class="home-component-controls__control-label">Filter</span>
                    <button class="admin-action" type="button" wire:click="resetComponentFilters">Reset</button>
                </div>

                <div class="home-component-controls__control-group home-component-controls__template">
                    <span class="home-component-controls__control-label">{{ strtoupper($templateLabel) }}</span>
                    <div class="home-component-controls__template-actions">
                        <button class="admin-action" type="button" wire:click="mountAction('settings')">Settings</button>
                        <button class="admin-action" type="button" wire:click="mountAction('addComponent')">Add component</button>
                        <a class="admin-action" href="{{ $previewUrl }}" target="_blank" rel="noopener">Preview</a>
                    </div>
                </div>

                <div
                    class="home-component-controls__control-group home-component-controls__selection"
                    x-data="{ open: false }"
                    x-on:click.outside="open = false"
                    x-on:keydown.escape.window="open = false"
                >
                    <span class="home-component-controls__control-label">Selection</span>
                    <div class="home-component-controls__selection-anchor">
                        <button
                            class="admin-action home-component-controls__selection-trigger"
                            type="button"
                            x-on:click="open = ! open"
                            x-bind:aria-expanded="open.toString()"
                            aria-haspopup="menu"
                            @disabled($selectedComponentTargets === [])
                        >
                            Selected components
                            <span class="home-component-controls__selection-count">{{ count($selectedComponentTargets) }}</span>
                        </button>
                        <div class="home-component-controls__selection-menu" role="menu" x-show="open" x-cloak>
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
                    @if ($componentDataset === [])
                        <x-admin.empty-state title="No components" minimal>
                            <x-slot:actions>
                                <button class="admin-action" type="button" wire:click="mountAction('addComponent')">Add component</button>
                            </x-slot:actions>
                        </x-admin.empty-state>
                    @else
                        <x-admin.empty-state title="No matching components" minimal>
                            <x-slot:actions>
                                <button class="admin-action" type="button" wire:click="resetComponentFilters">Clear filters</button>
                            </x-slot:actions>
                        </x-admin.empty-state>
                    @endif
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
            <div class="home-skip-tools" aria-label="Skip Home actions">
                <div class="home-skip-tools__control-group">
                    <span class="home-skip-tools__control-label">SKIP HOME</span>
                    <div class="home-skip-tools__actions">
                        <button class="admin-action" type="button" wire:click="mountAction('settings')">Settings</button>
                        <a class="admin-action" href="{{ $previewUrl }}" target="_blank" rel="noopener">Preview</a>
                    </div>
                </div>
            </div>

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
                <x-admin.empty-state title="No redirect target" minimal />
            @endif
        @endif
    </x-admin.workspace>

    <x-filament-actions::modals />
</x-filament-panels::page>
