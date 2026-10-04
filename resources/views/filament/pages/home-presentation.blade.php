<x-filament-panels::page>
    <x-admin.workspace title="Home" class="admin-home-workspace">
        @if ($metrics !== [])
            <div @if ($template === 'artwork') wire:init="loadHomeAnalytics" @endif>
                <x-admin.metrics :columns="count($metrics)" class="home-status-metrics" aria-label="Home overview">
                    @foreach ($metrics as $metric)
                        <x-admin.metric class="home-metric home-metric--{{ $metric['role'] }}" :label="$metric['label']" :value="$metric['value']">{{ $metric['description'] }}</x-admin.metric>
                    @endforeach
                </x-admin.metrics>
            </div>
        @endif

        @if ($template === 'artwork')
            <div class="home-hero-surface admin-visual-stage" aria-label="Hero Artwork">
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
                                            <button class="admin-action admin-order-action" type="button" wire:click="moveHeroArtwork({{ $row['id'] }}, 'up')" @disabled(! $row['can_move_up']) aria-label="Move {{ $row['title'] }} up">↑</button>
                                            <button class="admin-action admin-order-action" type="button" wire:click="moveHeroArtwork({{ $row['id'] }}, 'down')" @disabled(! $row['can_move_down']) aria-label="Move {{ $row['title'] }} down">↓</button>
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
                $sourceFiltersActive = trim($sourceSearch) !== '' || $sourceStatusFilter !== 'any' || $sourceHomeFilter !== 'any';
                $sourceHasRecords = $sourceRows->total() > 0 || ($sourceFiltersActive
                    && \App\Models\ArtworkCategory::query()->whereHas('siteSection')->exists());
            @endphp

            <x-admin.controls class="home-artwork-source-controls" :metric-grid="true" :search-span="2" aria-label="Gallery source controls">
                <x-slot:search>
                    <label class="admin-data-field">
                        <span>Search</span>
                        <x-admin.search-input model="sourceSearch" placeholder="Gallery" />
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
                        <x-admin.clear-filters wire:click="resetSourceFilters" :disabled="! $sourceFiltersActive" />
                    </div>
                </x-slot:reset>

                <x-slot:actions>
                    <div class="admin-data-control-group">
                        <span class="admin-data-control-label">Hero Artwork</span>
                        <div class="admin-toolbar home-workspace-actions">
                            <button class="admin-action admin-action--with-icon" type="button" wire:click="mountAction('settings')">
                                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Settings->mini()" class="admin-action__icon" />
                                <span class="admin-action__label">Settings</span>
                            </button>
                            <button
                                class="admin-action admin-action--with-icon"
                                type="button"
                                wire:click="mountAction('addHeroArtwork')"
                                @disabled($heroGroupSource !== 'manual')
                                title="{{ $heroGroupSource === 'manual' ? 'Add artwork to the manual Hero group' : 'Switch Hero source to Manual to add artworks directly' }}"
                            >
                                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Add->mini()" class="admin-action__icon" />
                                <span class="admin-action__label">Add artwork</span>
                            </button>
                            <a class="admin-action admin-action--with-icon" href="{{ $previewUrl }}" target="_blank" rel="noopener">
                                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Preview->mini()" class="admin-action__icon" />
                                <span class="admin-action__label">Preview</span>
                            </a>
                        </div>
                    </div>
                </x-slot:actions>

                <x-slot:selection>
                    <div class="admin-data-control-group admin-selection" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false">
                        <span class="admin-data-control-label">Selection</span>
                        <div class="admin-selection__anchor">
                            <button class="admin-action admin-selection__trigger" type="button" x-on:click="open = ! open" x-bind:aria-expanded="open.toString()" aria-haspopup="menu" @disabled($selectedSourceIds === [])>
                                <x-admin.selection-trigger-label>Selected Galleries</x-admin.selection-trigger-label> <span class="admin-selection__count">{{ count($selectedSourceIds) }}</span>
                            </button>
                            <div class="admin-selection__menu" role="menu" x-show="open" x-cloak>
                                <button class="admin-action admin-action--with-icon admin-action--state" type="button" role="menuitem" wire:click="setSelectedGalleryEligibility(true)" x-on:click="open = false">
                                    <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Publish->mini()" class="admin-action__icon" />
                                    <span class="admin-action__label">Enable</span>
                                </button>
                                <button class="admin-action admin-action--with-icon admin-action--state" type="button" role="menuitem" wire:click="setSelectedGalleryEligibility(false)" x-on:click="open = false">
                                    <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Unpublish->mini()" class="admin-action__icon" />
                                    <span class="admin-action__label">Disable</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </x-slot:selection>
            </x-admin.controls>

            <x-admin.table class="admin-data-table admin-table--six-grid home-source-table">
                <table>
                    <colgroup>
                        <col class="admin-table__col-one-unit home-source-table__col-gallery">
                        <col class="admin-table__col-one-unit home-source-table__col-candidates">
                        <col class="admin-table__col-half-unit home-source-table__col-status">
                        <col class="admin-table__col-half-unit home-source-table__col-source">
                        <col class="admin-table__col-half-unit home-source-table__col-artworks">
                        <col class="admin-table__col-half-unit home-source-table__col-year">
                        <col class="home-source-table__col-actions">
                        <col class="admin-table__selection-col home-source-table__col-selection">
                    </colgroup>
                    <thead>
                        <tr>
                            <th scope="col">Gallery</th>
                            <th scope="col" class="home-source-table__candidates">Candidates</th>
                            <th scope="col" class="home-source-table__status">Status</th>
                            <th scope="col" class="home-source-table__source">Source</th>
                            <th scope="col" class="home-source-table__artworks">Artworks</th>
                            <th scope="col" class="home-source-table__year">Newest Year</th>
                            <th scope="col" class="admin-table__actions">Actions</th>
                            <th scope="col" class="admin-table__selection admin-table__selection--trailing">
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
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sourceRows as $gallery)
                            @php
                                $selected = in_array($gallery['id'], array_map('intval', $selectedSourceIds), true);
                            @endphp
                            <tr class="{{ $selected ? 'is-selected' : '' }}" wire:key="home-source-gallery-{{ $gallery['id'] }}">
                                <td class="admin-table__identity">
                                    <strong>{{ $gallery['name'] }}</strong>
                                    <small class="admin-responsive-meta">{{ $gallery['status_label'] }} · {{ $gallery['source_label'] }} · {{ number_format($gallery['published_artworks']) }} artworks · {{ $gallery['newest_year'] ?: '—' }}</small>
                                </td>
                                <td class="home-source-table__candidates">
                                    <div class="home-source-candidates" aria-label="Candidates from {{ $gallery['name'] }}">
                                        @forelse ($gallery['candidates'] as $candidate)
                                            <span class="home-source-candidate">
                                                <button
                                                    class="home-source-candidate__preview"
                                                    type="button"
                                                    wire:click="mountAction('editArtwork', { artwork: {{ $candidate['id'] }} })"
                                                    title="Edit {{ $candidate['title'] }}"
                                                    aria-label="Edit {{ $candidate['title'] }}"
                                                >
                                                    @if ($candidate['thumbnail_url'])
                                                        <img src="{{ $candidate['thumbnail_url'] }}" alt="" loading="lazy" decoding="async">
                                                    @else
                                                        <span>—</span>
                                                    @endif
                                                </button>
                                                <x-admin.row-action
                                                    class="home-source-candidate__edit"
                                                    :action="\App\Filament\Support\AdminRowAction::Edit"
                                                    wire:click="mountAction('editArtwork', { artwork: {{ $candidate['id'] }} })"
                                                    aria-label="Edit {{ $candidate['title'] }}"
                                                />
                                            </span>
                                        @empty
                                            <span class="home-source-candidates__empty">—</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="home-source-table__status"><span class="admin-status {{ $gallery['state'] === 'published' ? 'is-published' : '' }}">{{ $gallery['status_label'] }}</span></td>
                                <td class="home-source-table__source"><span class="admin-status {{ $gallery['effective_enabled'] ? 'is-published' : '' }}">{{ $gallery['source_label'] }}</span></td>
                                <td class="home-source-table__artworks">{{ number_format($gallery['published_artworks']) }}</td>
                                <td class="home-source-table__year">{{ $gallery['newest_year'] ?: '—' }}</td>
                                <td class="admin-table__actions">
                                    <div class="admin-row-actions admin-row-actions--canonical admin-toolbar">
                                        <x-admin.row-action
                                            :action="$gallery['preference_enabled'] ? \App\Filament\Support\AdminRowAction::Unpublish : \App\Filament\Support\AdminRowAction::Publish"
                                            :label="$gallery['preference_enabled'] ? 'Disable' : 'Enable'"
                                            wire:click="toggleGalleryEligibility({{ $gallery['id'] }})"
                                        />
                                        <x-admin.row-action
                                            :action="\App\Filament\Support\AdminRowAction::Open"
                                            label="Open Gallery"
                                            :href="$gallery['workspace_url']"
                                            wire:navigate
                                        />
                                    </div>
                                </td>
                                <td class="admin-table__selection admin-table__selection--trailing">
                                    <input type="checkbox" value="{{ $gallery['id'] }}" wire:model.live="selectedSourceIds" aria-label="Select {{ $gallery['name'] }}">
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="admin-table__empty-cell" colspan="8">
                                    @if ($sourceHasRecords)
                                        <x-admin.empty-state title="No matching Galleries" minimal>
                                            <x-slot:actions><button class="admin-action" type="button" wire:click="resetSourceFilters">Clear filters</button></x-slot:actions>
                                        </x-admin.empty-state>
                                    @else
                                        <x-admin.empty-state title="No Gallery sources" minimal />
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </x-admin.table>

            @if ($sourceRows->total() > $sourceRows->perPage() || $sourceRows->currentPage() > 1)
                <footer class="admin-pager">
                    <x-admin.page-size-picker
                        :value="$sourcePerPage"
                        :options="[10, 25]"
                        wire-model="sourcePerPage"
                        aria-label="Gallery sources per page"
                    />
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
                $componentFiltersActive = trim($componentSearch) !== '' || $componentType !== 'any';
            @endphp

            <x-admin.controls class="home-component-controls" :metric-grid="true" :search-span="3" aria-label="Home component controls">
                <x-slot:search>
                    <label class="admin-data-field">
                        <span>Search</span>
                        <x-admin.search-input model="componentSearch" placeholder="Components" />
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
                        <x-admin.clear-filters wire:click="resetComponentFilters" :disabled="! $componentFiltersActive" />
                    </div>
                </x-slot:reset>

                <x-slot:actions>
                    <div class="admin-data-control-group">
                        <span class="admin-data-control-label">{{ strtoupper($templateLabel) }}</span>
                        <div class="admin-toolbar home-workspace-actions">
                            <button class="admin-action admin-action--with-icon" type="button" wire:click="mountAction('settings')">
                                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Settings->mini()" class="admin-action__icon" />
                                <span class="admin-action__label">Settings</span>
                            </button>
                            <button class="admin-action admin-action--with-icon" type="button" wire:click="mountAction('addComponent')">
                                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Add->mini()" class="admin-action__icon" />
                                <span class="admin-action__label">Add component</span>
                            </button>
                            <a class="admin-action admin-action--with-icon" href="{{ $previewUrl }}" target="_blank" rel="noopener">
                                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Preview->mini()" class="admin-action__icon" />
                                <span class="admin-action__label">Preview</span>
                            </a>
                        </div>
                    </div>
                </x-slot:actions>

                <x-slot:selection>
                    <div class="admin-data-control-group admin-selection" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false">
                        <span class="admin-data-control-label">Selection</span>
                        <div class="admin-selection__anchor">
                            <button class="admin-action admin-selection__trigger" type="button" x-on:click="open = ! open" x-bind:aria-expanded="open.toString()" aria-haspopup="menu" @disabled($selectedComponentTargets === [])>
                                <x-admin.selection-trigger-label>Selected components</x-admin.selection-trigger-label> <span class="admin-selection__count">{{ count($selectedComponentTargets) }}</span>
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

            <x-admin.table class="admin-data-table admin-table--ranked admin-table--six-grid home-components-table">
                <table>
                    <colgroup>
                        <col class="admin-table__col-position home-components-table__col-position">
                        <col class="admin-table__col-drag home-components-table__col-drag">
                        <col class="admin-table__col-one-unit home-components-table__col-type">
                        <col class="home-components-table__col-content">
                        <col class="home-components-table__col-actions">
                        <col class="admin-table__selection-col home-components-table__col-selection">
                    </colgroup>
                    <thead>
                        <tr>
                            <th scope="colgroup" colspan="2" class="admin-table__ordering-heading">Position</th>
                            <th scope="col" class="home-components-table__type">Component</th>
                            <th scope="col">Content</th>
                            <th scope="col" class="admin-table__actions">Actions</th>
                            <th scope="col" class="admin-table__selection admin-table__selection--trailing">
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
                                    @disabled($visibleComponentTargets === [])
                                    aria-label="Toggle selection for visible Home components"
                                >
                            </th>
                        </tr>
                    </thead>
                    <tbody @if ($reorderEnabled) wire:sort="sortComponent" @endif>
                        @forelse ($components as $homeComponent)
                            <tr wire:key="home-component-{{ $template }}-{{ $homeComponent['target'] }}" @if ($reorderEnabled) wire:sort:item="{{ $homeComponent['target'] }}" @endif>
                                <td class="admin-table__position"><span class="admin-position">{{ str_pad((string) $homeComponent['position'], 2, '0', STR_PAD_LEFT) }}</span></td>
                                <td class="admin-table__drag">
                                    <button
                                        class="admin-drag-handle"
                                        type="button"
                                        @if ($reorderEnabled) wire:sort:handle title="Drag to reorder" @else disabled title="Clear search/filter to reorder" @endif
                                        aria-label="Drag {{ $homeComponent['type_label'] }} to reorder"
                                    >⋮⋮</button>
                                </td>
                                <td class="home-components-table__type">{{ $homeComponent['type_label'] }}</td>
                                <td class="admin-table__identity">
                                    <strong>{{ $homeComponent['content']['primary'] }}</strong>
                                    <small class="admin-responsive-meta">{{ $homeComponent['type_label'] }}</small>
                                    @if ($homeComponent['content']['secondary'] !== '')<small>{{ $homeComponent['content']['secondary'] }}</small>@endif
                                </td>
                                <td class="admin-table__actions">
                                    <div class="admin-row-actions admin-row-actions--canonical admin-toolbar">
                                        <x-admin.row-action
                                            :action="\App\Filament\Support\AdminRowAction::MoveUp"
                                            wire:click="moveComponent({{ $homeComponent['index'] }}, '{{ $homeComponent['type'] }}', 'up')"
                                            :disabled="! $reorderEnabled || ! $homeComponent['can_move_up']"
                                            aria-label="Move {{ $homeComponent['type_label'] }} up"
                                        />
                                        <x-admin.row-action
                                            :action="\App\Filament\Support\AdminRowAction::MoveDown"
                                            wire:click="moveComponent({{ $homeComponent['index'] }}, '{{ $homeComponent['type'] }}', 'down')"
                                            :disabled="! $reorderEnabled || ! $homeComponent['can_move_down']"
                                            aria-label="Move {{ $homeComponent['type_label'] }} down"
                                        />
                                        <x-admin.row-action
                                            :action="\App\Filament\Support\AdminRowAction::Edit"
                                            wire:click="mountAction('editComponent', { index: {{ $homeComponent['index'] }}, type: '{{ $homeComponent['type'] }}' })"
                                            :disabled="! $homeComponent['editable']"
                                        />
                                        <x-admin.row-action
                                            :action="\App\Filament\Support\AdminRowAction::Delete"
                                            wire:click="mountAction('removeComponent', { index: {{ $homeComponent['index'] }}, type: '{{ $homeComponent['type'] }}' })"
                                        />
                                    </div>
                                </td>
                                <td class="admin-table__selection admin-table__selection--trailing">
                                    <input type="checkbox" value="{{ $homeComponent['target'] }}" wire:model.live="selectedComponentTargets" aria-label="Select {{ $homeComponent['type_label'] }}">
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="admin-table__empty-cell" colspan="6">
                                    <x-admin.empty-state :title="$componentDataset === [] ? 'No components' : 'No matching components'" minimal>
                                        @if ($componentDataset !== [])
                                            <x-slot:actions><button class="admin-action" type="button" wire:click="resetComponentFilters">Clear filters</button></x-slot:actions>
                                        @endif
                                    </x-admin.empty-state>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </x-admin.table>

            <x-admin.add-row wire:click="mountAction('addComponent')">Add component</x-admin.add-row>

        @elseif ($template === 'skip_home')
            <div class="home-skip-tools admin-data-control-group" aria-label="Skip Home actions">
                <span class="admin-data-control-label">Skip Home</span>
                <div class="admin-toolbar home-workspace-actions">
                    <button class="admin-action admin-action--with-icon" type="button" wire:click="mountAction('settings')">
                        <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Settings->mini()" class="admin-action__icon" />
                        <span class="admin-action__label">Settings</span>
                    </button>
                    <a class="admin-action admin-action--with-icon" href="{{ $previewUrl }}" target="_blank" rel="noopener">
                        <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Preview->mini()" class="admin-action__icon" />
                        <span class="admin-action__label">Preview</span>
                    </a>
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
