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
            <div class="home-hero-surface admin-visual-stage admin-visual-stage--stackable" aria-label="Hero Artwork">
                <div class="home-hero-surface__visual admin-visual-stage__pane">
                    @if ($currentArtwork && $currentArtwork['thumbnail_url'])
                        <img src="{{ $currentArtwork['thumbnail_url'] }}" alt="" loading="eager" decoding="async">
                    @else
                        <span>No Hero Artwork</span>
                    @endif
                </div>

                <div class="home-hero-rail admin-visual-stage__pane" aria-label="Hero group">
                    <div class="home-hero-rail__summary">
                        <strong>{{ $heroGroupSource === 'manual' ? 'Manual' : 'Automatic' }} · {{ ucfirst($heroDisplayStrategy) }}</strong>
                        <span>
                            {{ $heroGroupSource === 'manual' ? count($manualHeroGroup) : $candidatePoolCount }}
                            {{ ($heroGroupSource === 'manual' ? count($manualHeroGroup) : $candidatePoolCount) === 1 ? 'artwork' : 'artworks' }}
                            @if ($heroDisplayStrategy === 'sequential' && $nextRotationAt)
                                · next {{ $nextRotationAt }}
                            @endif
                        </span>
                    </div>

                    @if ($heroRailRows !== [])
                        <div class="home-hero-rail__rows" @if ($heroGroupSource === 'manual') wire:sort="sortHeroArtwork" @endif>
                            @foreach ($heroRailRows as $row)
                                <article
                                    class="home-hero-candidate {{ $currentArtwork && $row['id'] === $currentArtwork['id'] ? 'is-current' : '' }} {{ $heroGroupSource === 'manual' ? 'is-manual' : '' }} {{ !($row['eligible'] ?? true) ? 'is-unavailable' : '' }}"
                                    wire:key="home-hero-row-{{ $row['id'] }}"
                                    @if ($heroGroupSource === 'manual') wire:sort:item="{{ $row['id'] }}" @endif
                                >
                                    @if ($heroGroupSource === 'manual')
                                        <button class="admin-drag-handle" type="button" wire:sort:handle aria-label="Drag {{ $row['title'] }}">⋮⋮</button>
                                    @endif

                                    <div class="home-hero-candidate__visual">
                                        @if ($row['thumbnail_url'])
                                            <img src="{{ $row['thumbnail_url'] }}" alt="" loading="lazy" decoding="async">
                                        @else
                                            <span>—</span>
                                        @endif
                                    </div>

                                    <div class="home-hero-candidate__meta">
                                        <strong title="{{ $row['title'] }}">{{ $row['title'] }}</strong>
                                        <span title="{{ $row['gallery'] ?: '—' }}">{{ $row['gallery'] ?: '—' }} · {{ $row['year'] ?: '—' }}</span>
                                        @if ($row['sequence_label'])
                                            <small>{{ $row['sequence_label'] }}</small>
                                        @endif
                                    </div>

                                    @if ($heroGroupSource === 'manual' && $heroDisplayStrategy === 'random')
                                        <label class="home-hero-weight">
                                            <span>Chance</span>
                                            <span class="home-hero-weight__input">
                                                <input
                                                    type="number"
                                                    min="0"
                                                    max="100"
                                                    step="0.01"
                                                    value="{{ $row['percentage'] }}"
                                                    wire:change="setHeroPercentage({{ $row['id'] }}, $event.target.value)"
                                                    aria-label="Random percentage for {{ $row['title'] }}"
                                                >
                                                <span>%</span>
                                            </span>
                                        </label>
                                    @endif

                                    @if ($heroGroupSource === 'manual')
                                        <div class="home-hero-candidate__actions admin-toolbar">
                                            <button class="admin-action" type="button" wire:click="moveHeroArtwork({{ $row['id'] }}, 'up')" @disabled(! $row['can_move_up']) aria-label="Move {{ $row['title'] }} up">↑</button>
                                            <button class="admin-action" type="button" wire:click="moveHeroArtwork({{ $row['id'] }}, 'down')" @disabled(! $row['can_move_down']) aria-label="Move {{ $row['title'] }} down">↓</button>
                                            <button class="admin-action is-danger" type="button" wire:click="removeHeroArtwork({{ $row['id'] }})" @disabled(count($manualHeroGroup) === 1)>Remove</button>
                                        </div>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    @else
                        <div class="home-hero-rail__empty">No eligible Hero Artwork</div>
                    @endif

                    @if ($heroGroupSource === 'manual')
                        <div class="home-hero-rail__add">
                            <x-admin.add-row wire:click="mountAction('addHeroArtwork')">Add artwork</x-admin.add-row>
                        </div>
                    @endif

                    @if ($selectionIssue)
                        <p class="home-hero-surface__issue">{{ $selectionIssue }}</p>
                    @endif
                </div>
            </div>

            @php
                $sourceRows = $this->sourceRows();
                $visibleSourceIds = collect($sourceRows->items())->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
            @endphp

            <x-admin.controls class="home-artwork-source-controls" aria-label="Gallery source controls">
                <x-slot:search>
                    <label class="admin-data-field">
                        <span>Search</span>
                        <input type="search" wire:model.live.debounce.300ms="sourceSearch" placeholder="Gallery" autocomplete="off">
                    </label>
                </x-slot:search>

                <x-slot:filters>
                    <label class="admin-data-field">
                        <span>Status</span>
                        <select wire:model.live="sourceStatusFilter">
                            <option value="any">Any</option>
                            <option value="published">Published</option>
                            <option value="unpublished">Unpublished</option>
                        </select>
                    </label>
                    <label class="admin-data-field">
                        <span>Source</span>
                        <select wire:model.live="sourceHomeFilter">
                            <option value="any">Any</option>
                            <option value="enabled">Enabled</option>
                            <option value="disabled">Unavailable / Disabled</option>
                        </select>
                    </label>
                </x-slot:filters>

                <x-slot:reset>
                    <div class="admin-data-control-group">
                        <span class="admin-data-control-label">Filter</span>
                        <button class="admin-action" type="button" wire:click="resetSourceFilters">Reset</button>
                    </div>
                </x-slot:reset>

                <x-slot:actions>
                    <div class="admin-data-control-group">
                        <span class="admin-data-control-label">Hero Artwork</span>
                        <div class="admin-toolbar">
                            <button class="admin-action" type="button" wire:click="mountAction('settings')">Settings</button>
                            @if ($heroGroupSource === 'manual')
                                <button class="admin-action" type="button" wire:click="mountAction('addHeroArtwork')">Add artwork</button>
                            @endif
                            <a class="admin-action" href="{{ $previewUrl }}" target="_blank" rel="noopener">Preview</a>
                        </div>
                    </div>
                </x-slot:actions>

                <x-slot:selection>
                    <div class="admin-data-control-group admin-selection" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false">
                        <span class="admin-data-control-label">Selection</span>
                        <div class="admin-selection__anchor">
                            <button class="admin-action admin-selection__trigger" type="button" x-on:click="open = ! open" x-bind:aria-expanded="open.toString()" aria-haspopup="menu" @disabled($selectedSourceIds === [])>
                                Selected Galleries <span class="admin-selection__count">{{ count($selectedSourceIds) }}</span>
                            </button>
                            <div class="admin-selection__menu" role="menu" x-show="open" x-cloak>
                                <button class="admin-action" type="button" role="menuitem" wire:click="setSelectedGalleryEligibility(true)" x-on:click="open = false">Enable preference</button>
                                <button class="admin-action" type="button" role="menuitem" wire:click="setSelectedGalleryEligibility(false)" x-on:click="open = false">Disable preference</button>
                            </div>
                        </div>
                    </div>
                </x-slot:selection>
            </x-admin.controls>

            @if ($sourceRows->count() > 0)
                <x-admin.table class="admin-data-table">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col" class="admin-table__selection">
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
                                        "
                                        @disabled($visibleSourceIds === [])
                                        aria-label="Toggle selection for visible Galleries"
                                    >
                                </th>
                                <th scope="col">Gallery</th>
                                <th scope="col">Candidates</th>
                                <th scope="col">Status</th>
                                <th scope="col">Source</th>
                                <th scope="col">Artworks</th>
                                <th scope="col">Newest Year</th>
                                <th scope="col" class="admin-table__actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sourceRows as $gallery)
                                @php
                                    $selected = in_array($gallery['id'], array_map('intval', $selectedSourceIds), true);
                                @endphp
                                <tr class="{{ $selected ? 'is-selected' : '' }}" wire:key="home-source-gallery-{{ $gallery['id'] }}">
                                    <td class="admin-table__selection">
                                        <input type="checkbox" value="{{ $gallery['id'] }}" wire:model.live="selectedSourceIds" aria-label="Select {{ $gallery['name'] }}">
                                    </td>
                                    <td class="admin-table__identity"><strong>{{ $gallery['name'] }}</strong></td>
                                    <td>
                                        <div class="home-source-candidates" aria-label="Candidates from {{ $gallery['name'] }}">
                                            @forelse ($gallery['candidates'] as $candidate)
                                                <a href="{{ $candidate['edit_url'] }}" title="{{ $candidate['title'] }} · {{ $candidate['year'] ?: '—' }}" aria-label="Edit {{ $candidate['title'] }}">
                                                    @if ($candidate['thumbnail_url'])
                                                        <img src="{{ $candidate['thumbnail_url'] }}" alt="" loading="lazy" decoding="async">
                                                    @else
                                                        <span>—</span>
                                                    @endif
                                                </a>
                                            @empty
                                                <span class="home-source-candidates__empty">—</span>
                                            @endforelse
                                        </div>
                                    </td>
                                    <td><span class="admin-status {{ $gallery['state'] === 'published' ? 'is-published' : '' }}">{{ $gallery['status_label'] }}</span></td>
                                    <td><span class="admin-status {{ $gallery['effective_enabled'] ? 'is-published' : '' }}">{{ $gallery['source_label'] }}</span></td>
                                    <td>{{ number_format($gallery['published_artworks']) }}</td>
                                    <td>{{ $gallery['newest_year'] ?: '—' }}</td>
                                    <td class="admin-table__actions">
                                        <div class="admin-row-actions admin-toolbar">
                                            <button class="admin-action admin-action--state" type="button" wire:click="toggleGalleryEligibility({{ $gallery['id'] }})">{{ $gallery['preference_enabled'] ? 'Disable preference' : 'Enable preference' }}</button>
                                            <a class="admin-action" href="{{ $gallery['workspace_url'] }}">Open Gallery</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-admin.table>
            @else
                <x-admin.empty-state :title="trim($sourceSearch) !== '' || $sourceStatusFilter !== 'any' || $sourceHomeFilter !== 'any' ? 'No matching Galleries' : 'No Gallery sources'" minimal>
                    @if (trim($sourceSearch) !== '' || $sourceStatusFilter !== 'any' || $sourceHomeFilter !== 'any')
                        <x-slot:actions><button class="admin-action" type="button" wire:click="resetSourceFilters">Clear filters</button></x-slot:actions>
                    @endif
                </x-admin.empty-state>
            @endif

            @if ($sourceRows->total() > $sourceRows->perPage() || $sourceRows->currentPage() > 1)
                <footer class="admin-pager">
                    <label class="admin-pager__size">
                        <span>Per page</span>
                        <select wire:model.live.number="sourcePerPage"><option value="10">10</option><option value="25">25</option></select>
                    </label>
                    <span class="admin-pager__range">{{ $sourceRows->firstItem() ?? 0 }}–{{ $sourceRows->lastItem() ?? 0 }} of {{ $sourceRows->total() }}</span>
                    <div class="admin-pager__actions admin-toolbar">
                        <button class="admin-action" type="button" wire:click="goToSourcePage({{ $sourceRows->currentPage() - 1 }})" @disabled($sourceRows->onFirstPage())>Previous</button>
                        <button class="admin-action" type="button" wire:click="goToSourcePage({{ $sourceRows->currentPage() + 1 }})" @disabled(! $sourceRows->hasMorePages())>Next</button>
                    </div>
                </footer>
            @endif

        @elseif (in_array($template, ['under_construction', 'custom'], true))
            @php
                $reorderEnabled = trim($componentSearch) === '' && $componentType === 'any';
                $visibleComponentTargets = collect($components)->pluck('target')->values()->all();
            @endphp

            <x-admin.controls aria-label="Home component controls">
                <x-slot:search>
                    <label class="admin-data-field">
                        <span>Search</span>
                        <input type="search" wire:model.live.debounce.300ms="componentSearch" placeholder="Components" autocomplete="off">
                    </label>
                </x-slot:search>

                <x-slot:filters>
                    <label class="admin-data-field">
                        <span>Type</span>
                        <select wire:model.live="componentType">
                            <option value="any">Any</option>
                            @foreach ($componentTypeOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                    </label>
                </x-slot:filters>

                <x-slot:reset>
                    <div class="admin-data-control-group">
                        <span class="admin-data-control-label">Filter</span>
                        <button class="admin-action" type="button" wire:click="resetComponentFilters">Reset</button>
                    </div>
                </x-slot:reset>

                <x-slot:actions>
                    <div class="admin-data-control-group">
                        <span class="admin-data-control-label">{{ strtoupper($templateLabel) }}</span>
                        <div class="admin-toolbar">
                            <button class="admin-action" type="button" wire:click="mountAction('settings')">Settings</button>
                            <button class="admin-action" type="button" wire:click="mountAction('addComponent')">Add component</button>
                            <a class="admin-action" href="{{ $previewUrl }}" target="_blank" rel="noopener">Preview</a>
                        </div>
                    </div>
                </x-slot:actions>

                <x-slot:selection>
                    <div class="admin-data-control-group admin-selection" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false">
                        <span class="admin-data-control-label">Selection</span>
                        <div class="admin-selection__anchor">
                            <button class="admin-action admin-selection__trigger" type="button" x-on:click="open = ! open" x-bind:aria-expanded="open.toString()" aria-haspopup="menu" @disabled($selectedComponentTargets === [])>
                                Selected components <span class="admin-selection__count">{{ count($selectedComponentTargets) }}</span>
                            </button>
                            <div class="admin-selection__menu" role="menu" x-show="open" x-cloak>
                                <button class="admin-action" type="button" role="menuitem" wire:click="moveSelectedComponents('up')" x-on:click="open = false" @disabled(! $reorderEnabled)>Move selected up</button>
                                <button class="admin-action" type="button" role="menuitem" wire:click="moveSelectedComponents('down')" x-on:click="open = false" @disabled(! $reorderEnabled)>Move selected down</button>
                                <button class="admin-action is-danger" type="button" role="menuitem" wire:click="mountAction('deleteSelectedComponents')" x-on:click="open = false">Delete selected</button>
                            </div>
                        </div>
                    </div>
                </x-slot:selection>
            </x-admin.controls>

            @if ($components !== [])
                <x-admin.table class="admin-data-table">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col" class="admin-table__selection">
                                    <input
                                        type="checkbox"
                                        x-data="{}"
                                        wire:click.prevent="toggleVisibleComponentSelection"
                                        x-effect="
                                            const visibleTargets = @js($visibleComponentTargets);
                                            const selectedTargets = $wire.selectedComponentTargets;
                                            const selectedCount = visibleTargets.filter((target) => selectedTargets.includes(target)).length;
                                            $el.checked = visibleTargets.length > 0 && selectedCount === visibleTargets.length;
                                            $el.indeterminate = selectedCount > 0 && selectedCount < visibleTargets.length;
                                        "
                                        aria-label="Toggle selection for visible Home components"
                                    >
                                </th>
                                <th scope="col">Drag</th>
                                <th scope="col">Position</th>
                                <th scope="col">Component</th>
                                <th scope="col">Content</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody @if ($reorderEnabled) wire:sort="sortComponent" @endif>
                            @foreach ($components as $component)
                                <tr wire:key="home-component-{{ $template }}-{{ $component['target'] }}" @if ($reorderEnabled) wire:sort:item="{{ $component['target'] }}" @endif>
                                    <td class="admin-table__selection">
                                        <input type="checkbox" value="{{ $component['target'] }}" wire:model.live="selectedComponentTargets" aria-label="Select {{ $component['type_label'] }}">
                                    </td>
                                    <td>
                                        <button class="admin-drag-handle" type="button" @if ($reorderEnabled) wire:sort:handle @else disabled @endif aria-label="Drag {{ $component['type_label'] }}">⋮⋮</button>
                                    </td>
                                    <td><span class="admin-position">{{ str_pad((string) $component['position'], 2, '0', STR_PAD_LEFT) }}</span></td>
                                    <td>{{ $component['type_label'] }}</td>
                                    <td class="admin-table__identity">
                                        <strong>{{ $component['content']['primary'] }}</strong>
                                        @if ($component['content']['secondary'] !== '')<small>{{ $component['content']['secondary'] }}</small>@endif
                                    </td>
                                    <td>
                                        <div class="admin-toolbar">
                                            @if ($component['editable'])<button class="admin-action" type="button" wire:click="mountAction('editComponent', { index: {{ $component['index'] }}, type: '{{ $component['type'] }}' })">Edit</button>@endif
                                            <button class="admin-action admin-order-action" type="button" wire:click="moveComponent({{ $component['index'] }}, '{{ $component['type'] }}', 'up')" @disabled(! $reorderEnabled || ! $component['can_move_up']) aria-label="Move {{ $component['type_label'] }} up">↑</button>
                                            <button class="admin-action admin-order-action" type="button" wire:click="moveComponent({{ $component['index'] }}, '{{ $component['type'] }}', 'down')" @disabled(! $reorderEnabled || ! $component['can_move_down']) aria-label="Move {{ $component['type_label'] }} down">↓</button>
                                            <button class="admin-action is-danger" type="button" wire:click="mountAction('removeComponent', { index: {{ $component['index'] }}, type: '{{ $component['type'] }}' })">Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-admin.table>
            @elseif ($componentDataset === [])
                <x-admin.empty-state title="No components" minimal />
            @else
                <x-admin.empty-state title="No matching components" minimal>
                    <x-slot:actions><button class="admin-action" type="button" wire:click="resetComponentFilters">Clear filters</button></x-slot:actions>
                </x-admin.empty-state>
            @endif

            <x-admin.add-row wire:click="mountAction('addComponent')">Add component</x-admin.add-row>

        @elseif ($template === 'skip_home')
            <div class="home-skip-tools admin-data-control-group" aria-label="Skip Home actions">
                <span class="admin-data-control-label">Skip Home</span>
                <div class="admin-toolbar">
                    <button class="admin-action" type="button" wire:click="mountAction('settings')">Settings</button>
                    <a class="admin-action" href="{{ $previewUrl }}" target="_blank" rel="noopener">Preview</a>
                </div>
            </div>
            @if ($skipTarget)
                <div class="home-workspace__skip-row">
                    <div><strong>{{ $skipTarget['label'] }}</strong><span>{{ $skipTarget['type'] }} · {{ $skipTarget['path'] }}</span></div>
                    <div class="home-workspace__redirect-expression" aria-label="Current Home redirect"><code>/</code><span aria-hidden="true">→</span><code>{{ $skipTarget['path'] }}</code></div>
                    <a class="admin-action" href="{{ $skipTarget['url'] }}" target="_blank" rel="noopener">Open target</a>
                </div>
            @else
                <x-admin.empty-state title="No redirect target" minimal />
            @endif
        @endif
    </x-admin.workspace>

    <x-filament-actions::modals />
</x-filament-panels::page>