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
                $simulationAt = now('UTC');
                $manualHeroKeys = array_map(\App\Domain\Content\HomeHeroConfigurationService::memberKey(...), $manualHeroGroup);
                $simulationCandidates = collect($heroRailRows)
                    ->filter(static fn (array $row): bool => $row['eligible'] ?? true)
                    ->map(static fn (array $row): array => [
                        'id' => $row['id'],
                        'eligible' => true,
                        'thumbnail_url' => $row['thumbnail_url'],
                        'weight' => $row['weight'] ?? null,
                        'is_manual' => $row['is_manual'] ?? false,
                        'is_automatic' => $row['is_automatic'] ?? false,
                    ])->values()->all();
                $simulationKey = md5(json_encode([
                    $heroGroupSource, $heroDisplayStrategy, $rotationIntervalCount,
                    $rotationIntervalUnit, $nextRotationAt, $simulationCandidates,
                ]));
            @endphp
            <div
                class="home-hero-surface admin-visual-stage admin-visual-stage--split"
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
                    sampledAt: @js($simulationAt->toIso8601String()),
                })"
            >
                <div class="home-hero-surface__visual admin-visual-stage__pane">
                    <div class="home-hero-surface__visual-actions" aria-label="Actions for simulated Hero Artwork" x-show="activeId !== null" x-cloak>
                        <button class="admin-icon-action admin-icon-action--surface" type="button"
                            x-show="active?.is_manual" x-on:click="$wire.setManualHeroCandidateEnabled(activeId, false)"
                            title="Disable current Manual candidate" aria-label="Disable current Manual candidate">
                            <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Unpublish->mini()" />
                        </button>
                        <button class="admin-icon-action admin-icon-action--surface" type="button"
                            x-show="active?.is_automatic" x-on:click="$wire.setAutomaticHeroCandidateEnabled(Number(activeId.split(':')[1]), false)"
                            title="Disable current automatic candidate" aria-label="Disable current automatic candidate">
                            <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Unpublish->mini()" />
                        </button>
                        <button class="admin-icon-action admin-icon-action--surface is-danger" type="button"
                            x-show="active?.is_manual" x-on:click="$wire.removeHeroCandidate(activeId)"
                            title="Remove current Manual candidate" aria-label="Remove current Manual candidate">
                            <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Delete->mini()" />
                        </button>
                    </div>
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
                    <header class="home-hero-rail__summary">
                        <h2 class="admin-section__kicker home-hero-rail__title">{{ __('Hero Artwork') }}</h2>
                        <div class="home-hero-rail__simulation" aria-live="off">
                            <p>
                                <span>{{ __('Preview:') }}</span>
                                @if ($heroDisplayStrategy === 'sequential')
                                    {{ __('Sequential') }} (<span x-text="simulatedAt.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' })">{{ $simulationAt->format('j M Y') }}</span>)
                                @else
                                    {{ $heroDisplayStrategy === 'random' ? __('Random') : __('Top Only') }}
                                @endif
                            </p>
                            <p>
                                <span>{{ __('Next Rotation:') }}</span>
                                @if ($heroDisplayStrategy === 'sequential')
                                    <span x-text="nextAt ? nextAt.toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false, timeZone: 'UTC' }) + ' UTC' : 'Not scheduled'">{{ $nextRotationAt ? \Carbon\CarbonImmutable::parse($nextRotationAt)->setTimezone('UTC')->format('j M Y, H:i').' UTC' : __('Not scheduled') }}</span>
                                @elseif ($heroDisplayStrategy === 'random')
                                    {{ __('Every 10 seconds (Stage simulation)') }}
                                @else
                                    {{ __('No rotation') }}
                                @endif
                            </p>
                        </div>
                        <div class="home-hero-stage-settings {{ $heroGroupSource === 'manual' ? 'home-hero-stage-settings--manual' : '' }}" aria-label="Hero Stage quick settings">
                            <label class="admin-data-field">
                                <span>{{ __('Source') }}</span>
                                <select wire:change="updateHeroStageSetting('group_source', $event.target.value)" aria-label="Hero source">
                                    <option value="automatic" @selected($heroGroupSource === 'automatic')>{{ __('Automatic') }}</option>
                                    <option value="semi_automatic" @selected($heroGroupSource === 'semi_automatic')>{{ __('Semi-Automatic') }}</option>
                                    <option value="manual" @selected($heroGroupSource === 'manual')>{{ __('Manual') }}</option>
                                </select>
                            </label>
                            @if ($heroGroupSource !== 'manual')
                                <label class="admin-data-field">
                                    <span>{{ __('Source Rule') }}</span>
                                    <select wire:change="updateHeroStageSetting('source_rule', $event.target.value)" aria-label="Hero source rule">
                                        @foreach (\App\Domain\Content\HomeHeroConfigurationService::SOURCE_RULES as $value => $label)
                                            <option value="{{ $value }}" @selected($heroSourceRule === $value)>{{ __($label) }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="admin-data-field">
                                    <span>{{ __('Source Size') }}</span>
                                    <input type="number" min="1" max="{{ \App\Domain\Content\HomeHeroConfigurationService::MAX_GROUP_SIZE }}"
                                        step="1" value="{{ $heroGroupSize }}" wire:change="updateHeroStageSetting('group_size', $event.target.value)" aria-label="Hero source size">
                                </label>
                            @endif
                            <label class="admin-data-field">
                                <span>{{ __('Strategy') }}</span>
                                <select wire:change="updateHeroStageSetting('display_strategy', $event.target.value)" aria-label="Hero display strategy">
                                    <option value="ordered" @selected($heroDisplayStrategy === 'ordered')>{{ __('Top Only') }}</option>
                                    <option value="random" @selected($heroDisplayStrategy === 'random')>{{ __('Random') }}</option>
                                    <option value="sequential" @selected($heroDisplayStrategy === 'sequential')>{{ __('Sequential') }}</option>
                                </select>
                            </label>
                        </div>
                        <div class="home-hero-candidate__heading" aria-hidden="true">
                            <span class="home-hero-candidate__heading-position">{{ __('Position') }}</span>
                            <span class="home-hero-candidate__heading-chance">{{ $heroDisplayStrategy === 'random' ? __('Chance') : '' }}</span>
                            <span class="home-hero-candidate__heading-actions">{{ __('Actions') }}</span>
                        </div>
                    </header>

                    @if ($heroRailRows !== [] || $excludedHeroRows !== [])
                        <div class="home-hero-rail__rows" @if (in_array($heroGroupSource, ['manual', 'semi_automatic'], true)) wire:sort="sortHeroArtwork" @endif>
                            @foreach ($heroRailRows as $index => $row)
                                @php $isManual = $row['is_manual'] ?? false; @endphp
                                <article
                                    class="home-hero-candidate {{ !($row['eligible'] ?? true) ? 'is-unavailable' : '' }}"
                                    x-bind:class="{ 'is-current': activeId === $el.dataset.candidateKey }"
                                    data-candidate-key="{{ $row['id'] }}"
                                    wire:key="home-hero-row-{{ $row['id'] }}"
                                    @if ($isManual) wire:sort:item="{{ $row['id'] }}" @endif
                                >
                                    <span class="home-hero-candidate__position"><span class="admin-position">{{ $index + 1 }}</span></span>
                                    @if ($isManual)
                                        <button class="admin-drag-handle" type="button" wire:sort:handle aria-label="Drag {{ $row['title'] }}">⋮⋮</button>
                                    @else
                                        <span class="home-hero-candidate__drag-spacer" aria-hidden="true"></span>
                                    @endif

                                    <div class="home-hero-candidate__visual">
                                        @if ($row['thumbnail_url'])
                                            <img src="{{ $row['thumbnail_url'] }}" alt="" loading="lazy" decoding="async" draggable="false">
                                        @else
                                            <span>—</span>
                                        @endif
                                    </div>

                                    <div class="home-hero-candidate__meta">
                                        <strong title="{{ $row['title'] }}">{{ $row['title'] }}</strong>
                                        <span title="{{ $row['gallery'] ?: '—' }}">{{ $row['gallery'] ?: '—' }} · {{ $row['year'] ?: '—' }}</span>
                                        @if ($row['sequence_label'])
                                            <small>{{ __($row['sequence_label']) }}</small>
                                        @endif
                                    </div>

                                    <div class="home-hero-weight">
                                        @if ($heroDisplayStrategy === 'random' && ($row['eligible'] ?? false))
                                            <label class="home-hero-weight__input">
                                                <input
                                                    class="admin-form-control"
                                                    type="number"
                                                    min="0"
                                                    max="100"
                                                    step="0.01"
                                                    value="{{ $row['percentage'] }}"
                                                    wire:change="setHeroPercentage('{{ $row['id'] }}', $event.target.value)"
                                                    aria-label="Random percentage for {{ $row['title'] }}"
                                                >
                                                <span>%</span>
                                            </label>
                                        @elseif ($heroDisplayStrategy === 'random')
                                            <span class="home-hero-weight__inactive">—</span>
                                        @endif
                                    </div>

                                    <div class="home-hero-candidate__actions admin-toolbar">
                                        @if ($isManual)
                                            <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::MoveUp" :disabled="! $row['can_move_up']"
                                                wire:click="moveHeroArtwork('{{ $row['id'] }}', 'up')" />
                                            <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::MoveDown" :disabled="! $row['can_move_down']"
                                                wire:click="moveHeroArtwork('{{ $row['id'] }}', 'down')" />
                                            @if ($row['disabled'])
                                                <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::Publish" label="Enable"
                                                    wire:click="setManualHeroCandidateEnabled('{{ $row['id'] }}', true)" />
                                            @else
                                                <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::Unpublish" label="Disable"
                                                    wire:click="setManualHeroCandidateEnabled('{{ $row['id'] }}', false)" />
                                            @endif
                                            <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::Delete" label="Remove"
                                                wire:click="removeHeroCandidate('{{ $row['id'] }}')" />
                                        @elseif ($row['is_automatic'] ?? false)
                                            <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::Unpublish" label="Disable"
                                                wire:click="setAutomaticHeroCandidateEnabled({{ (int) substr($row['id'], 8) }}, false)" />
                                        @endif
                                    </div>
                                </article>
                            @endforeach

                            @if ($excludedHeroRows !== [])
                                <div class="home-hero-rail__exclusions">
                                    <div class="home-hero-rail__exclusions-title">{{ __('Excluded automatic candidates') }}</div>
                                    @foreach ($excludedHeroRows as $excluded)
                                        <article class="home-hero-candidate is-unavailable" wire:key="home-excluded-{{ $excluded['id'] }}">
                                            <span class="home-hero-candidate__position"><span class="admin-position">—</span></span>
                                            <span class="home-hero-candidate__drag-spacer" aria-hidden="true"></span>
                                            <div class="home-hero-candidate__visual">
                                                @if ($excluded['thumbnail_url'])
                                                    <img src="{{ $excluded['thumbnail_url'] }}" alt="" loading="lazy" decoding="async" draggable="false">
                                                @else
                                                    <span>—</span>
                                                @endif
                                            </div>
                                            <div class="home-hero-candidate__meta">
                                                <strong title="{{ $excluded['title'] }}">{{ $excluded['title'] }}</strong>
                                                <small>{{ __('Excluded') }}</small>
                                            </div>
                                            <div class="home-hero-weight"></div>
                                            <div class="home-hero-candidate__actions admin-toolbar">
                                                <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::Publish" label="Enable"
                                                    wire:click="setAutomaticHeroCandidateEnabled({{ (int) substr($excluded['id'], 8) }}, true)" />
                                            </div>
                                        </article>
                                    @endforeach
                                </div>
                            @endif
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
                $visibleSourceIds = collect($sourceRows->items())->pluck('id')->map(fn ($id) => (int) $id)
                    ->filter(fn (int $id) => $id > 0 || ($id === 0 && $heroGroupSource === 'semi_automatic'))
                    ->values()->all();
                $sourceFiltersActive = trim($sourceSearch) !== '';
                $sourceHasRecords = $sourceRows->total() > 0 || ($sourceFiltersActive
                    && \App\Models\ArtworkCategory::query()->whereHas('siteSection')->exists());
            @endphp

            <x-admin.controls class="home-artwork-source-controls admin-data-controls--dashboard-responsive" :metric-grid="true" :search-span="3" aria-label="Gallery source controls">
                <x-slot:search>
                    <label class="admin-data-field">
                        <span>{{ __('Search') }}</span>
                        <x-admin.search-input model="sourceSearch" placeholder="Gallery or candidate" />
                    </label>
                </x-slot:search>

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
                            @if ($heroGroupSource !== 'automatic')
                                <button
                                    class="admin-action admin-action--with-icon"
                                    type="button"
                                    wire:click="mountAction('addCandidate')"
                                    title="Add an eligible image or video to Manual Sourced Artworks"
                                >
                                    <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Add->mini()" class="admin-action__icon" />
                                    <span class="admin-action__label">{{ __('Add Candidate') }}</span>
                                </button>
                            @endif
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
                                <button class="admin-action admin-action--with-icon admin-action--state is-danger" type="button" role="menuitem" wire:click="mountAction('removeSelectedManualHeroCandidates')" x-on:click="open = false" @disabled(! $sourceCapabilities['remove'])>
                                    <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Delete->mini()" class="admin-action__icon" />
                                    <span class="admin-action__label">{{ __('Remove') }}</span>
                                </button>
                            </div>
                        </div>
                        <span class="admin-selection__count" aria-label="{{ count($selectedSourceIds) }} selected">{{ count($selectedSourceIds) }}</span>
                    </div>
                </x-slot:selection>
            </x-admin.controls>

            <x-admin.table class="admin-data-table home-source-table">
                <table class="admin-table--six-grid">
                    <colgroup>
                        <col class="admin-table__col-one-unit home-source-table__col-gallery">
                        <col class="home-source-table__col-candidates">
                        <col class="home-source-table__col-actions">
                        <col class="admin-table__selection-col home-source-table__col-selection">
                    </colgroup>
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Gallery') }}</th>
                            <th scope="col" class="home-source-table__candidates">{{ __('Candidates') }}</th>
                            <th scope="col" class="admin-table__actions"><span class="admin-row-actions-heading">{{ __('Actions') }}</span></th>
                            <th scope="col" class="admin-table__selection admin-table__selection--trailing">
                                <input
                                    type="checkbox"
                                    x-data="{}"
                                    wire:click.prevent="toggleVisibleSourceSelection"
                                    x-effect="
                                        const visibleIds = @js($this->visibleSourceSelectionTargets()).map(String);
                                        const selectedIds = ($wire.selectedSourceIds || []).map(String);
                                        const selectedCount = visibleIds.filter(id => selectedIds.includes(id)).length;
                                        $el.checked = visibleIds.length > 0 && selectedCount === visibleIds.length;
                                        $el.indeterminate = selectedCount > 0 && selectedCount < visibleIds.length;
                                        $el.disabled = visibleIds.length === 0;
                                        $el.setAttribute('aria-checked', $el.indeterminate ? 'mixed' : ($el.checked ? 'true' : 'false'));
                                    "
                                    @disabled($visibleSourceIds === [])
                                    aria-label="Select all visible Galleries"
                                >
                            </th>
                        </tr>
                    </thead>
                    <tbody wire:key="home-source-body-{{ $heroGroupSource }}-{{ $sourceRows->currentPage() }}">
                        @forelse ($sourceRows as $gallery)
                            @php
                                $manualSource = $gallery['standalone'];
                                $groupId = (int) $gallery['id'];
                                $firstCandidate = $gallery['candidates'][0] ?? null;
                                $expanded = in_array($groupId, $expandedSourceIds, true);
                                $selected = in_array(
                                    (string) ($expanded && $firstCandidate ? $firstCandidate['id'] : $groupId),
                                    array_map('strval', $selectedSourceIds), true,
                                );
                            @endphp
                            <tr
                                class="home-source-table__group {{ $selected ? 'is-selected' : '' }} {{ $expanded ? 'is-expanded' : '' }}"
                                wire:key="home-source-gallery-{{ $groupId }}"
                            >
                                <td class="admin-table__identity">
                                    <strong>
                                        @if ($gallery['workspace_url'])
                                            <a class="home-source-table__gallery-link" href="{{ $gallery['workspace_url'] }}" wire:navigate>{{ $gallery['name'] }}</a>
                                        @else
                                            {{ $gallery['name'] }}
                                        @endif
                                    </strong>
                                    <small class="admin-responsive-meta">{{ number_format($gallery['candidate_count']) }} candidates</small>
                                </td>
                                <td class="home-source-table__candidates">
                                    @if (! $expanded)
                                        <button
                                            type="button"
                                            class="home-source-candidates__toggle"
                                            x-data="{ dragging: false, moved: false, startX: 0, startScroll: 0 }"
                                            x-on:pointerdown="if ($event.pointerType !== 'mouse' || $event.button !== 0) return; dragging = true; moved = false; startX = $event.clientX; startScroll = $refs.strip.scrollLeft; $el.setPointerCapture($event.pointerId)"
                                            x-on:pointermove="if (!dragging) return; if (Math.abs($event.clientX - startX) > 6) moved = true; if (moved) $refs.strip.scrollLeft = startScroll - ($event.clientX - startX)"
                                            x-on:pointerup="dragging = false"
                                            x-on:pointercancel="dragging = false"
                                            x-on:lostpointercapture="dragging = false"
                                            x-on:dragstart.prevent
                                            x-on:click="if (moved) { $event.stopImmediatePropagation(); $event.preventDefault(); moved = false }"
                                            wire:click="toggleSourceExpansion({{ $groupId }})"
                                            aria-expanded="false"
                                            aria-label="Expand candidates in {{ $gallery['name'] }}"
                                        >
                                            <span class="home-source-candidates" x-ref="strip" x-bind:class="{ 'is-dragging': dragging }">
                                                @forelse ($gallery['candidates'] as $candidate)
                                                    <span class="home-source-candidate">
                                                        <span class="home-source-candidate__preview">
                                                            @if ($candidate['thumbnail_url'])
                                                                <img src="{{ $candidate['thumbnail_url'] }}" alt="" loading="lazy" decoding="async" draggable="false">
                                                            @else
                                                                <span>—</span>
                                                            @endif
                                                        </span>
                                                    </span>
                                                @empty
                                                    <span class="home-source-candidates__empty">—</span>
                                                @endforelse
                                            </span>
                                        </button>
                                    @else
                                        @include('filament.pages.partials.home-source-candidate-cell', ['candidate' => $firstCandidate, 'groupId' => $groupId])
                                    @endif
                                </td>
                                <td class="admin-table__actions">
                                    @if (! $expanded)
                                        <div class="admin-row-actions admin-row-actions--canonical admin-row-actions--stable admin-toolbar home-source-table__row-actions">
                                            @if ($manualSource)
                                                <span class="home-source-table__action-slot" aria-hidden="true"></span>
                                                <x-admin.row-action
                                                    :action="\App\Filament\Support\AdminRowAction::Delete"
                                                    label="Clear"
                                                    wire:click="mountAction('clearManualHeroCandidates')"
                                                    :disabled="$gallery['candidate_count'] === 0"
                                                />
                                            @else
                                                <x-admin.row-action
                                                    :action="$gallery['preference_enabled'] ? \App\Filament\Support\AdminRowAction::Unpublish : \App\Filament\Support\AdminRowAction::Publish"
                                                    :label="$gallery['preference_enabled'] ? 'Disable' : 'Enable'"
                                                    wire:click="toggleGalleryEligibility({{ $groupId }})"
                                                    :disabled="! $gallery['preference_enabled'] && ! $gallery['can_enable']"
                                                />
                                                <span class="home-source-table__action-slot" aria-hidden="true"></span>
                                            @endif
                                        </div>
                                    @else
                                        @include('filament.pages.partials.home-source-candidate-actions', ['candidate' => $firstCandidate, 'manualSource' => $manualSource])
                                    @endif
                                </td>
                                <td class="admin-table__selection admin-table__selection--trailing">
                                    @if ($expanded && $firstCandidate)
                                        <input type="checkbox" value="{{ $firstCandidate['id'] }}"
                                            wire:model.live="selectedSourceIds"
                                            aria-label="Select candidate {{ $firstCandidate['title'] }}">
                                    @elseif (! $expanded)
                                        <input type="checkbox" value="{{ $groupId }}"
                                            wire:model.live="selectedSourceIds"
                                            aria-label="Select source {{ $gallery['name'] }}">
                                    @endif
                                </td>
                            </tr>
                            @if ($expanded)
                                @foreach (array_slice($gallery['candidates'], 1) as $candidate)
                                    <tr
                                        class="home-source-table__candidate-row {{ $loop->last ? 'is-group-end' : '' }} {{ in_array((string) $candidate['id'], array_map('strval', $selectedSourceIds), true) ? 'is-selected' : '' }}"
                                        wire:key="home-source-item-{{ $groupId }}-{{ $candidate['id'] }}"
                                    >
                                        <td class="home-source-table__empty-gallery"></td>
                                        <td class="home-source-table__candidates">
                                            @include('filament.pages.partials.home-source-candidate-cell', ['candidate' => $candidate, 'groupId' => $groupId])
                                        </td>
                                        <td class="admin-table__actions">
                                            @include('filament.pages.partials.home-source-candidate-actions', ['candidate' => $candidate, 'manualSource' => $manualSource])
                                        </td>
                                        <td class="admin-table__selection admin-table__selection--trailing">
                                            <input type="checkbox" value="{{ $candidate['id'] }}"
                                                wire:model.live="selectedSourceIds"
                                                aria-label="Select candidate {{ $candidate['title'] }}">
                                        </td>
                                    </tr>
                                @endforeach
                            @endif
                        @empty
                            <tr>
                                <td class="admin-table__empty-cell" colspan="4">
                                    @if ($sourceHasRecords)
                                        <x-admin.empty-state title="No matching sources" minimal>
                                            <x-slot:actions><button class="admin-action" type="button" wire:click="resetSourceFilters">{{ __('Clear filters') }}</button></x-slot:actions>
                                        </x-admin.empty-state>
                                    @else
                                        <x-admin.empty-state title="No Hero sources" minimal />
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

            @if ($heroGroupSource !== 'automatic')
                <x-admin.add-row wire:click="mountAction('addCandidate')">{{ __('Add Candidate') }}</x-admin.add-row>
            @endif

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
