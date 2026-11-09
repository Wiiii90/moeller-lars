<x-filament-panels::page>
    <x-admin.workspace title="Home" class="admin-home-workspace">
        @if ($metrics !== [])
            <div @if ($template === 'artwork') wire:init="loadHomeAnalytics" @endif>
                <x-admin.metrics :columns="count($metrics)" class="home-status-metrics admin-metrics--six-state" aria-label="Home overview">
                    @foreach ($metrics as $metric)
                        <x-admin.metric class="home-metric home-metric--{{ $metric['role'] }}" :label="$metric['label']" :value="$metric['value']">{{ $metric['description'] }}</x-admin.metric>
                    @endforeach
                </x-admin.metrics>
            </div>
        @endif

        @if ($template === 'artwork')
            @php
                $simulationCandidates = collect($heroRailRows)
                    ->filter(static fn (array $row): bool => $row['eligible'] ?? true)
                    ->map(static fn (array $row): array => [
                        'id' => $row['id'],
                        'eligible' => true,
                        'thumbnail_url' => $row['thumbnail_url'],
                        'weight' => $row['weight'] ?? null,
                    ])->values()->all();
                $simulationKey = md5(json_encode([
                    $heroGroupSource, $heroDisplayStrategy, $rotationIntervalCount,
                    $rotationIntervalUnit, $nextRotationAt, $simulationCandidates,
                ]));
            @endphp
            <div
                class="home-hero-surface admin-visual-stage"
                aria-label="Hero Artwork"
                wire:key="home-stage-{{ $simulationKey }}"
                x-data="adminHomeHeroStage({
                    rows: @js($simulationCandidates),
                    currentId: @js($currentArtwork['id'] ?? null),
                    strategy: @js($heroDisplayStrategy),
                    rotationCount: @js($rotationIntervalCount),
                    rotationUnit: @js($rotationIntervalUnit),
                    rotationStartedAt: @js($rotationStartedAt),
                    nextRotationAt: @js($nextRotationAt),
                    sampledAt: @js(now('UTC')->toIso8601String()),
                })"
            >
                <div class="home-hero-surface__visual admin-visual-stage__pane">
                    <img
                        @if ($currentArtwork && $currentArtwork['thumbnail_url']) src="{{ $currentArtwork['thumbnail_url'] }}" @endif
                        x-show="Boolean(active?.thumbnail_url)"
                        x-bind:src="active?.thumbnail_url || null"
                        alt=""
                        loading="eager"
                        decoding="async"
                    >
                    <span x-show="!active?.thumbnail_url">{{ __('No Hero Artwork preview') }}</span>
                </div>

                <div class="home-hero-rail admin-visual-stage__pane" aria-label="Hero group">
                    <div class="home-hero-rail__summary">
                        <strong>{{ $heroGroupSource === 'manual' ? 'Manual' : 'Automatic' }} · {{ ucfirst($heroDisplayStrategy) }}</strong>
                        <span>
                            {{ $heroGroupSource === 'manual' ? count($manualHeroGroup) : $candidatePoolCount }}
                            {{ ($heroGroupSource === 'manual' ? count($manualHeroGroup) : $candidatePoolCount) === 1 ? 'candidate' : 'candidates' }}
                        </span>
                        <span x-text="simulationLabel" aria-live="off">
                            @if ($heroDisplayStrategy === 'sequential' && $nextRotationAt)
                                Next {{ $nextRotationAt }}
                            @else
                                {{ ucfirst($heroDisplayStrategy) }} preview
                            @endif
                        </span>
                    </div>

                    @if ($heroRailRows !== [])
                        <div class="home-hero-rail__rows" @if ($heroGroupSource === 'manual') wire:sort="sortHeroArtwork" @endif>
                            @foreach ($heroRailRows as $row)
                                <article
                                    class="home-hero-candidate {{ $heroGroupSource === 'manual' ? 'is-manual' : '' }} {{ !($row['eligible'] ?? true) ? 'is-unavailable' : '' }}"
                                    x-bind:class="{ 'is-current': activeId === $el.dataset.candidateKey }"
                                    data-candidate-key="{{ $row['id'] }}"
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
                                            <span>{{ __('Chance') }}</span>
                                            <span class="home-hero-weight__input">
                                                <input
                                                    type="number"
                                                    min="0"
                                                    max="100"
                                                    step="0.01"
                                                    value="{{ $row['percentage'] }}"
                                                    wire:change="setHeroPercentage('{{ $row['id'] }}', $event.target.value)"
                                                    aria-label="Random percentage for {{ $row['title'] }}"
                                                >
                                                <span>%</span>
                                            </span>
                                        </label>
                                    @endif

                                    @if ($heroGroupSource === 'manual' || str_starts_with($row['id'], 'media:'))
                                        <div class="home-hero-candidate__actions admin-toolbar">
                                            @if ($heroGroupSource === 'manual')
                                            <button class="admin-action admin-order-action" type="button" wire:click="moveHeroArtwork('{{ $row['id'] }}', 'up')" @disabled(! $row['can_move_up']) aria-label="Move {{ $row['title'] }} up">↑</button>
                                            <button class="admin-action admin-order-action" type="button" wire:click="moveHeroArtwork('{{ $row['id'] }}', 'down')" @disabled(! $row['can_move_down']) aria-label="Move {{ $row['title'] }} down">↓</button>
                                            @endif
                                            <button class="admin-action is-danger" type="button" wire:click="removeHeroCandidate('{{ $row['id'] }}')" @disabled($heroGroupSource === 'manual' && count($manualHeroGroup) === 1)>{{ __('Remove') }}</button>
                                        </div>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    @else
                        <div class="home-hero-rail__empty">{{ __('No eligible Hero candidates') }}</div>
                    @endif

                    @if ($selectionIssue)
                        <p class="home-hero-surface__issue">{{ $selectionIssue }}</p>
                    @endif
                </div>
            </div>

            @php
                $sourceRows = $this->sourceRows();
                $visibleSourceIds = collect($sourceRows->items())->pluck('id')->map(fn ($id) => (int) $id)->filter()->values()->all();
                $sourceFiltersActive = trim($sourceSearch) !== '' || $sourceStatusFilter !== 'any';
                $sourceHasRecords = $sourceRows->total() > 0 || ($sourceFiltersActive
                    && \App\Models\ArtworkCategory::query()->whereHas('siteSection')->exists());
            @endphp

            <x-admin.controls class="home-artwork-source-controls admin-data-controls--dashboard-responsive" :metric-grid="true" :search-span="2" aria-label="Gallery source controls">
                <x-slot:search>
                    <label class="admin-data-field">
                        <span>{{ __('Search') }}</span>
                        <x-admin.search-input model="sourceSearch" placeholder="Gallery or candidate" />
                    </label>
                </x-slot:search>

                <x-slot:filters>
                    <label class="admin-data-field">
                        <span>{{ __('Status') }}</span>
                        <select wire:model.live="sourceStatusFilter">
                            <option value="any">{{ __('Any') }}</option>
                            <option value="published">{{ __('Published') }}</option>
                            <option value="unpublished">{{ __('Unpublished') }}</option>
                        </select>
                    </label>
                </x-slot:filters>

                <x-slot:reset>
                    <div class="admin-data-control-group">
                        <span class="admin-data-control-label">{{ __('Filter') }}</span>
                        <x-admin.clear-filters wire:click="resetSourceFilters" :disabled="! $sourceFiltersActive" />
                    </div>
                </x-slot:reset>

                <x-slot:actions>
                    <div class="admin-data-control-group">
                        <span class="admin-data-control-label">{{ __('Hero Artwork') }}</span>
                        <div class="admin-toolbar admin-editorial-actions home-workspace-actions">
                            <button class="admin-action admin-action--with-icon" type="button" wire:click="mountAction('settings')">
                                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Settings->mini()" class="admin-action__icon" />
                                <span class="admin-action__label">{{ __('Settings') }}</span>
                            </button>
                            <button
                                class="admin-action admin-action--with-icon"
                                type="button"
                                wire:click="mountAction('addCandidate')"
                                title="Add an eligible Gallery artwork or image/video from Storage"
                            >
                                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Add->mini()" class="admin-action__icon" />
                                <span class="admin-action__label">{{ __('Add Candidate') }}</span>
                            </button>
                            <a class="admin-action admin-action--with-icon" href="{{ $previewUrl }}" target="_blank" rel="noopener">
                                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Preview->mini()" class="admin-action__icon" />
                                <span class="admin-action__label">{{ __('Preview') }}</span>
                            </a>
                        </div>
                    </div>
                </x-slot:actions>

                <x-slot:selection>
                    @php
                        $sourceCapabilities = $this->selectedSourceCapabilities();
                    @endphp
                    <div class="admin-data-control-group admin-selection" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false">
                        <span class="admin-data-control-label">{{ __('Selection') }}</span>
                        <div class="admin-selection__anchor">
                            <button class="admin-action admin-selection__trigger" type="button" x-on:click="open = ! open" x-bind:aria-expanded="open.toString()" aria-haspopup="menu" @disabled($selectedSourceIds === [])>
                                <x-admin.selection-trigger-label>{{ __('Selected') }}</x-admin.selection-trigger-label>
                            </button>
                            <div class="admin-selection__menu" role="menu" x-show="open" x-cloak>
                                <button class="admin-action admin-action--with-icon admin-action--state" type="button" role="menuitem" wire:click="setSelectedGalleryEligibility(true)" x-on:click="open = false" @disabled(! $sourceCapabilities['enable'])>
                                    <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Publish->mini()" class="admin-action__icon" />
                                    <span class="admin-action__label">{{ __('Enable') }}</span>
                                </button>
                                <button class="admin-action admin-action--with-icon admin-action--state" type="button" role="menuitem" wire:click="setSelectedGalleryEligibility(false)" x-on:click="open = false" @disabled(! $sourceCapabilities['disable'])>
                                    <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Unpublish->mini()" class="admin-action__icon" />
                                    <span class="admin-action__label">{{ __('Disable') }}</span>
                                </button>
                            </div>
                        </div>
                        <span class="admin-selection__count" aria-label="{{ count($selectedSourceIds) }} selected">{{ count($selectedSourceIds) }}</span>
                    </div>
                </x-slot:selection>
            </x-admin.controls>

            <x-admin.table class="admin-data-table admin-table--six-grid home-source-table">
                <table>
                    <colgroup>
                        <col class="admin-table__col-one-unit home-source-table__col-gallery">
                        <col class="admin-table__col-one-half-units home-source-table__col-candidates">
                        <col class="admin-table__col-half-unit home-source-table__col-artworks">
                        <col class="admin-table__col-half-unit home-source-table__col-status">
                        <col class="home-source-table__col-actions">
                        <col class="admin-table__selection-col home-source-table__col-selection">
                    </colgroup>
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Gallery') }}</th>
                            <th scope="col" class="home-source-table__candidates">{{ __('Candidates') }}</th>
                            <th scope="col" class="home-source-table__artworks">{{ __('Artworks') }}</th>
                            <th scope="col" class="home-source-table__status">{{ __('Status') }}</th>
                            <th scope="col" class="admin-table__actions"><span class="admin-row-actions-heading">{{ __('Actions') }}</span></th>
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
                                    <small class="admin-responsive-meta">{{ $gallery['status_label'] }} · {{ number_format($gallery['candidate_count']) }}/{{ number_format($gallery['artworks_count']) }} candidates</small>
                                </td>
                                <td class="home-source-table__candidates">
                                    <div
                                        class="home-source-candidates"
                                        aria-label="Candidates from {{ $gallery['name'] }} — scroll horizontally"
                                        tabindex="0"
                                        x-data="{ dragging: false, startX: 0, startScroll: 0 }"
                                        x-on:pointerdown="if ($event.pointerType !== 'mouse' || $event.button !== 0) return; dragging = true; startX = $event.clientX; startScroll = $el.scrollLeft; $el.setPointerCapture($event.pointerId)"
                                        x-on:pointermove="if (dragging) $el.scrollLeft = startScroll - ($event.clientX - startX)"
                                        x-on:pointerup="dragging = false"
                                        x-on:pointercancel="dragging = false"
                                        x-on:lostpointercapture="dragging = false"
                                        x-bind:class="{ 'is-dragging': dragging }"
                                    >
                                        @forelse ($gallery['candidates'] as $candidate)
                                            <span class="home-source-candidate">
                                                <span
                                                    class="home-source-candidate__preview"
                                                    title="{{ $candidate['title'] }}{{ $candidate['year'] ? ' · '.$candidate['year'] : '' }}"
                                                >
                                                    @if ($candidate['thumbnail_url'])
                                                        <img src="{{ $candidate['thumbnail_url'] }}" alt="" loading="lazy" decoding="async">
                                                    @else
                                                        <span>—</span>
                                                    @endif
                                                </span>
                                            </span>
                                        @empty
                                            <span class="home-source-candidates__empty">—</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="home-source-table__artworks">{{ number_format($gallery['candidate_count']) }}/{{ number_format($gallery['artworks_count']) }}</td>
                                <td class="home-source-table__status"><span class="admin-status {{ $gallery['state'] === 'published' ? 'is-published' : '' }}">{{ $gallery['status_label'] }}</span></td>
                                <td class="admin-table__actions">
                                    <div class="admin-row-actions admin-row-actions--canonical admin-row-actions--stable admin-row-actions--state-open admin-toolbar home-source-table__row-actions">
                                        @if ($gallery['standalone'])
                                            <x-admin.row-action
                                                :action="\App\Filament\Support\AdminRowAction::Edit"
                                                label="Select Storage candidate"
                                                wire:click="mountAction('addCandidate')"
                                            />
                                        @else
                                        <x-admin.row-action
                                            class="home-source-table__eligibility-action"
                                            :action="$gallery['preference_enabled'] ? \App\Filament\Support\AdminRowAction::Unpublish : \App\Filament\Support\AdminRowAction::Publish"
                                            :label="$gallery['preference_enabled'] ? 'Disable' : 'Enable'"
                                            wire:click="toggleGalleryEligibility({{ $gallery['id'] }})"
                                            :disabled="! $gallery['preference_enabled'] && ! $gallery['can_enable']"
                                        />
                                        <x-admin.row-action
                                            class="home-source-table__open-action"
                                            :action="\App\Filament\Support\AdminRowAction::Open"
                                            label="Open Gallery"
                                            :href="$gallery['workspace_url']"
                                            wire:navigate
                                        />
                                        @endif
                                    </div>
                                </td>
                                <td class="admin-table__selection admin-table__selection--trailing">
                                    @unless ($gallery['standalone'])
                                        <input type="checkbox" value="{{ $gallery['id'] }}" wire:model.live="selectedSourceIds" aria-label="Select {{ $gallery['name'] }}">
                                    @endunless
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="admin-table__empty-cell" colspan="6">
                                    @if ($sourceHasRecords)
                                        <x-admin.empty-state title="No matching Galleries" minimal>
                                            <x-slot:actions><button class="admin-action" type="button" wire:click="resetSourceFilters">{{ __('Clear filters') }}</button></x-slot:actions>
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
                        <button class="admin-action" type="button" wire:click="goToSourcePage({{ $sourceRows->currentPage() - 1 }})" @disabled($sourceRows->onFirstPage())>{{ __('Previous') }}</button>
                        <button class="admin-action" type="button" wire:click="goToSourcePage({{ $sourceRows->currentPage() + 1 }})" @disabled(! $sourceRows->hasMorePages())>{{ __('Next') }}</button>
                    </div>
                </footer>
            @endif

            <x-admin.add-row wire:click="mountAction('addCandidate')">{{ __('Add Candidate') }}</x-admin.add-row>

        @elseif (in_array($template, ['under_construction', 'custom'], true))
            @php
                $reorderEnabled = trim($componentSearch) === '' && $componentType === 'any';
                $visibleComponentTargets = collect($components)->pluck('target')->values()->all();
                $visibleHomeChildTargets = collect($components)->flatMap(fn (array $component) => collect($component['children'] ?? [])->pluck('target'))->values()->all();
                $homeSelectionCount = count($selectedComponentTargets) + count($selectedHomeChildTargets);
                $selectedHomeTargets = array_merge($selectedComponentTargets, $selectedHomeChildTargets);
                $selectedHomeRows = collect($componentDataset)
                    ->flatMap(static fn (array $row): array => [$row, ...($row['children'] ?? [])])
                    ->filter(static fn (array $row): bool => in_array($row['target'] ?? null, $selectedHomeTargets, true));
                $canPublishHomeSelection = $selectedHomeRows->contains(static fn (array $row): bool => ! ($row['published'] ?? false));
                $canUnpublishHomeSelection = $selectedHomeRows->contains(static fn (array $row): bool => ($row['published'] ?? false));
                $canDeleteHomeSelection = $homeSelectionCount > 0
                    && $selectedHomeRows->count() === $homeSelectionCount
                    && $selectedHomeRows->every(static fn (array $row): bool => \App\Models\CustomPageSetting::canDeleteComponent($row));
                $componentFiltersActive = trim($componentSearch) !== '' || $componentType !== 'any';
            @endphp

            <x-admin.controls class="home-component-controls admin-data-controls--dashboard-responsive" :metric-grid="true" :search-span="3" aria-label="Home component controls">
                <x-slot:search>
                    <label class="admin-data-field">
                        <span>{{ __('Search') }}</span>
                        <x-admin.search-input model="componentSearch" placeholder="Components" />
                    </label>
                </x-slot:search>

                <x-slot:filters>
                    <label class="admin-data-field">
                        <span>{{ __('Type') }}</span>
                        <select wire:model.live="componentType">
                            <option value="any">{{ __('Any') }}</option>
                            @foreach ($componentTypeOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                    </label>
                </x-slot:filters>

                <x-slot:reset>
                    <div class="admin-data-control-group">
                        <span class="admin-data-control-label">{{ __('Filter') }}</span>
                        <x-admin.clear-filters wire:click="resetComponentFilters" :disabled="! $componentFiltersActive" />
                    </div>
                </x-slot:reset>

                <x-slot:actions>
                    <div class="admin-data-control-group">
                        <span class="admin-data-control-label">{{ strtoupper($templateLabel) }}</span>
                        <div class="admin-toolbar admin-editorial-actions home-workspace-actions">
                            <button class="admin-action admin-action--with-icon" type="button" wire:click="mountAction('settings')">
                                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Settings->mini()" class="admin-action__icon" />
                                <span class="admin-action__label">{{ __('Settings') }}</span>
                            </button>
                            <button class="admin-action admin-action--with-icon" type="button" wire:click="mountAction('addComponent')" aria-label="Add component">
                                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::CustomPage->mini()" class="admin-action__icon" />
                                <span class="admin-action__label">{{ __('Add component') }}</span>
                            </button>
                            <a class="admin-action admin-action--with-icon" href="{{ $previewUrl }}" target="_blank" rel="noopener">
                                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Preview->mini()" class="admin-action__icon" />
                                <span class="admin-action__label">{{ __('Preview') }}</span>
                            </a>
                        </div>
                    </div>
                </x-slot:actions>

                <x-slot:selection>
                    <div class="admin-data-control-group admin-selection" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false">
                        <span class="admin-data-control-label">{{ __('Selection') }}</span>
                        <div class="admin-selection__anchor">
                            <button class="admin-action admin-selection__trigger" type="button" x-on:click="open = ! open" x-bind:aria-expanded="open.toString()" aria-haspopup="menu" @disabled($homeSelectionCount === 0)>
                                <x-admin.selection-trigger-label>{{ __('Selected') }}</x-admin.selection-trigger-label>
                            </button>
                            <div class="admin-selection__menu" role="menu" x-show="open" x-cloak>
                                <button class="admin-action" type="button" role="menuitem" wire:click="moveSelectedComponents('up')" x-on:click="open = false" @disabled(! $reorderEnabled)>{{ __('Move selected up') }}</button>
                                <button class="admin-action" type="button" role="menuitem" wire:click="moveSelectedComponents('down')" x-on:click="open = false" @disabled(! $reorderEnabled)>{{ __('Move selected down') }}</button>
                                <button class="admin-action" type="button" role="menuitem" wire:click="publishSelectedHomeItems(true)" x-on:click="open = false" @disabled(! $canPublishHomeSelection)>{{ __('Publish selected') }}</button>
                                <button class="admin-action" type="button" role="menuitem" wire:click="publishSelectedHomeItems(false)" x-on:click="open = false" @disabled(! $canUnpublishHomeSelection)>{{ __('Unpublish selected') }}</button>
                                <button class="admin-action is-danger" type="button" role="menuitem" wire:click="mountAction('deleteSelectedComponents')" x-on:click="open = false" @disabled(! $canDeleteHomeSelection)>{{ __('Delete selected') }}</button>
                            </div>
                        </div>
                        <span class="admin-selection__count" aria-label="{{ $homeSelectionCount }} selected">{{ $homeSelectionCount }}</span>
                    </div>
                </x-slot:selection>
            </x-admin.controls>

            @include('filament.pages.partials.home-component-sequence')

            <x-admin.add-row wire:click="mountAction('addComponent')">{{ __('Add component') }}</x-admin.add-row>

        @elseif ($template === 'skip_home')
            <div class="home-skip-tools admin-data-control-group" aria-label="Skip Home actions">
                <span class="admin-data-control-label">{{ __('Skip Home') }}</span>
                <div class="admin-toolbar admin-editorial-actions home-workspace-actions">
                    <button class="admin-action admin-action--with-icon" type="button" wire:click="mountAction('settings')">
                        <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Settings->mini()" class="admin-action__icon" />
                        <span class="admin-action__label">{{ __('Settings') }}</span>
                    </button>
                    <a class="admin-action admin-action--with-icon" href="{{ $previewUrl }}" target="_blank" rel="noopener">
                        <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Preview->mini()" class="admin-action__icon" />
                        <span class="admin-action__label">{{ __('Preview') }}</span>
                    </a>
                </div>
            </div>
            @if ($skipTarget)
                <div class="home-workspace__skip-row">
                    <div><strong>{{ $skipTarget['label'] }}</strong><span>{{ $skipTarget['type'] }} · {{ $skipTarget['path'] }}</span></div>
                    <div class="home-workspace__redirect-expression" aria-label="Current Home redirect"><code>/</code><span aria-hidden="true">→</span><code>{{ $skipTarget['path'] }}</code></div>
                    <a class="admin-action" href="{{ $skipTarget['url'] }}" target="_blank" rel="noopener">{{ __('Open target') }}</a>
                </div>
            @else
                <x-admin.empty-state title="No redirect target" minimal />
            @endif
        @endif
    </x-admin.workspace>

    <x-filament-actions::modals />
</x-filament-panels::page>
