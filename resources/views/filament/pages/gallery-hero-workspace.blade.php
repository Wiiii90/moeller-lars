<x-filament-panels::page>
    <x-admin.workspace :title="$pageTitle" class="admin-gallery-hero-workspace">
        @if ($metrics !== [])
            <div wire:init="loadHeroAnalytics">
                <x-admin.metrics :columns="count($metrics)" class="gallery-hero-status-metrics admin-metrics--six-state" aria-label="Hero Artworks overview">
                    @foreach ($metrics as $metric)
                        <x-admin.metric class="gallery-hero-metric gallery-hero-metric--{{ $metric['role'] }}" :label="$metric['label']" :value="$metric['value']">{{ $metric['description'] }}</x-admin.metric>
                    @endforeach
                </x-admin.metrics>
            </div>
        @endif

            @php
                $simulationAt = now('UTC');
                $simulationCandidates = collect($heroRailRows)
                    ->filter(static fn (array $row): bool => $row['eligible'] ?? true)
                    ->map(static fn (array $row): array => [
                        'id' => $row['id'],
                        'visual_key' => $row['visual_key'],
                        'eligible' => true,
                        'thumbnail_url' => $row['thumbnail_url'],
                        'weight' => $row['weight'] ?? null,
                        'is_manual' => $row['is_manual'] ?? false,
                        'is_automatic' => $row['is_automatic'] ?? false,
                        'dummy_gallery_id' => $row['dummy_gallery_id'] ?? null,
                    ])->values()->all();
                $simulationKey = md5(json_encode([
                    $heroGroupSource, $heroDisplayStrategy, $rotationIntervalCount,
                    $rotationIntervalUnit, $nextRotationAt, $simulationCandidates,
                ]));
            @endphp
            <div
                class="gallery-hero-surface admin-visual-stage admin-visual-stage--split admin-visual-stage--editor-preview"
                aria-label="Hero Artwork"
                wire:key="gallery-hero-stage-{{ $simulationKey }}"
                x-data="adminGalleryHeroStage({
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
                <div class="gallery-hero-rail admin-visual-stage__pane admin-visual-stage__editor-pane" aria-label="Hero group">
                    <header class="gallery-hero-rail__summary">
                        <h2 class="admin-section__kicker admin-visual-stage__heading gallery-hero-rail__title">{{ __('Hero Artwork') }}</h2>
                        <div class="gallery-hero-rail__simulation" aria-live="off">
                            <span>{{ __('Preview:') }}
                                <span x-text="simulatedAt.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' })">{{ $simulationAt->format('j M Y') }}</span>
                            </span>
                            <span class="gallery-hero-rail__simulation-separator" aria-hidden="true">|</span>
                            <span>{{ __('Next Rotation:') }}
                                @if ($heroDisplayStrategy === 'sequential')
                                    <span x-text="nextAt ? nextAt.toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false, timeZone: 'UTC' }) + ' UTC' : 'Not scheduled'">{{ $nextRotationAt ? \Carbon\CarbonImmutable::parse($nextRotationAt)->setTimezone('UTC')->format('j M Y, H:i').' UTC' : __('Not scheduled') }}</span>
                                @elseif ($heroDisplayStrategy === 'random')
                                    {{ $randomAvailable ? __('Every 10 seconds (Stage simulation)') : __('No rotation') }}
                                @else
                                    {{ __('No rotation') }}
                                @endif
                            </span>
                        </div>
                        <div class="gallery-hero-stage-settings" aria-label="Hero Stage quick settings">
                            <label class="admin-data-field">
                                <span>{{ __('Source') }}</span>
                                <select wire:change="updateHeroStageSetting('group_source', $event.target.value)" aria-label="Hero source">
                                    <option value="automatic" @selected($heroGroupSource === 'automatic')>{{ __('Automatic') }}</option>
                                    <option value="semi_automatic" @selected($heroGroupSource === 'semi_automatic')>{{ __('Semi-Automatic') }}</option>
                                    <option value="manual" @selected($heroGroupSource === 'manual')>{{ __('Manual') }}</option>
                                </select>
                            </label>
                            <label class="admin-data-field">
                                <span>{{ __('Ranking') }}</span>
                                <select wire:change="updateHeroStageSetting('source_rule', $event.target.value)" aria-label="Hero ranking">
                                    @foreach (\App\Domain\Content\GalleryHeroConfigurationService::SOURCE_RULES as $value => $label)
                                        <option value="{{ $value }}" @selected($heroSourceRule === $value)>{{ __($label) }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <div class="admin-data-field">
                                <span>{{ __('Candidates') }}</span>
                                <div class="gallery-hero-stage-candidates">
                                    <select wire:change="updateHeroStageSetting('selection_mode', $event.target.value)" aria-label="Candidate scope">
                                        @foreach (\App\Domain\Content\GalleryHeroConfigurationService::SELECTION_MODES as $value => $label)
                                            <option value="{{ $value }}" @selected($heroSelectionMode === $value)>{{ __($label) }}</option>
                                        @endforeach
                                    </select>
                                    <input type="number" min="1" max="{{ \App\Domain\Content\GalleryHeroConfigurationService::MAX_GROUP_SIZE }}"
                                        step="1" value="{{ $heroGroupSize }}" wire:change="updateHeroStageSetting('group_size', $event.target.value)" aria-label="Candidate amount">
                                </div>
                            </div>
                            <label class="admin-data-field">
                                <span>{{ __('Display') }}</span>
                                <select wire:change="updateHeroStageSetting('display_strategy', $event.target.value)" aria-label="Hero display strategy">
                                    <option value="ordered" @selected($heroDisplayStrategy === 'ordered')>{{ __('Top Only') }}</option>
                                    <option value="random" @selected($heroDisplayStrategy === 'random') @disabled(! $randomAvailable)>{{ __('Random') }}</option>
                                    <option value="sequential" @selected($heroDisplayStrategy === 'sequential')>{{ __('Sequential') }}</option>
                                </select>
                            </label>
                        </div>
                    </header>

                    @if ($heroRailRows !== [] || $excludedHeroRows !== [])
                        <div class="gallery-hero-rail__rows">
                            @foreach ($heroRailRows as $index => $row)
                                @php $isManual = $row['is_manual'] ?? false; @endphp
                                <article
                                    class="gallery-hero-candidate {{ !($row['eligible'] ?? true) ? 'is-unavailable' : '' }}"
                                    x-bind:class="{ 'is-current': activeId === $el.dataset.candidateKey }"
                                    data-candidate-key="{{ $row['id'] }}"
                                    wire:key="gallery-hero-row-{{ $row['id'] }}"

                                >
                                    <span class="gallery-hero-candidate__position"><span class="admin-position">{{ $index + 1 }}</span></span>

                                    <button type="button" class="gallery-hero-candidate__visual"
                                        x-on:click="selectCandidate(@js($row['id']))"
                                        x-bind:aria-pressed="(activeId === @js($row['id'])).toString()"
                                        aria-label="Show {{ $row['title'] }} in live preview">
                                        @if ($row['thumbnail_url'])
                                            <img src="{{ $row['thumbnail_url'] }}" alt="" loading="lazy" decoding="async" draggable="false">
                                        @else
                                            <span>—</span>
                                        @endif
                                    </button>

                                    <div class="gallery-hero-candidate__meta">
                                        <strong title="{{ $row['title'] }}">{{ $row['title'] }}</strong>
                                        <span title="{{ implode(', ', array_filter([$row['gallery'], $row['date']])) }}">{{ implode(', ', array_filter([$row['gallery'], $row['date']])) ?: '—' }}</span>
                                        {{-- The two-line candidate contract keeps status in the action state and accessible title. --}}
                                    </div>

                                    <div class="gallery-hero-weight">
                                        @if ($heroDisplayStrategy === 'random' && ($row['eligible'] ?? false))
                                            <x-admin.inline-percentage
                                                :value="$row['percentage']"
                                                :label="'Base random chance for '.$row['title']"
                                                wire:change="setHeroPercentage('{{ $row['id'] }}', $event.target.value)"
                                            />
                                            <span class="gallery-hero-weight__next"
                                                x-text="'Next ' + nextChance($el.closest('[data-candidate-key]').dataset.candidateKey)">
                                                {{ __('Next') }} {{ $row['next_percentage'] }}%
                                            </span>
                                        @elseif ($heroDisplayStrategy === 'random')
                                            <span class="gallery-hero-weight__inactive">—</span>
                                        @endif
                                    </div>

                                    <div class="gallery-hero-candidate__actions admin-toolbar">
                                        @if ($isManual)

                                            <x-admin.row-action
                                                :action="\App\Filament\Support\AdminRowAction::Unpublish" label="Disable"
                                                wire:click="toggleDummyMember({{ $row['dummy_gallery_id'] }}, '{{ $row['id'] }}')" />
                                            <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::Delete" label="Remove"
                                                wire:click="removeDummyMember({{ $row['dummy_gallery_id'] }}, '{{ $row['id'] }}')" />
                                        @elseif ($row['is_automatic'] ?? false)

                                            <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::Unpublish" label="Disable"
                                                wire:click="setAutomaticHeroCandidateEnabled({{ (int) substr($row['id'], 8) }}, false)" />
                                        @endif
                                    </div>
                                </article>
                            @endforeach

                            @if ($excludedHeroRows !== [])
                                <div class="gallery-hero-rail__exclusions">
                                    <div class="gallery-hero-rail__exclusions-title">{{ __('Excluded automatic candidates') }}</div>
                                    @foreach ($excludedHeroRows as $excluded)
                                        <article class="gallery-hero-candidate is-unavailable" wire:key="gallery-hero-excluded-{{ $excluded['id'] }}">
                                            <span class="gallery-hero-candidate__position"><span class="admin-position">—</span></span>
                                                    <div class="gallery-hero-candidate__visual">
                                                @if ($excluded['thumbnail_url'])
                                                    <img src="{{ $excluded['thumbnail_url'] }}" alt="" loading="lazy" decoding="async" draggable="false">
                                                @else
                                                    <span>—</span>
                                                @endif
                                            </div>
                                            <div class="gallery-hero-candidate__meta">
                                                <strong title="{{ $excluded['title'] }}">{{ $excluded['title'] }}</strong>
                                                <small>{{ __('Excluded') }}</small>
                                            </div>
                                            <div class="gallery-hero-weight"></div>
                                            <div class="gallery-hero-candidate__actions admin-toolbar">
                                                <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::Publish" label="Enable"
                                                    wire:click="setAutomaticHeroCandidateEnabled({{ (int) substr($excluded['id'], 8) }}, true)" />
                                            </div>
                                        </article>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="gallery-hero-rail__empty">{{ __('No eligible Hero candidates') }}</div>
                    @endif

                    @if ($selectionIssue)
                        <p class="gallery-hero-surface__issue">{{ $selectionIssue }}</p>
                    @endif
                </div>
                <div class="admin-visual-stage__pane admin-visual-stage__preview-pane"
                    x-on:load.capture="if ($event.target.matches('iframe.admin-live-preview__frame')) syncPreview()">
                    <h2 class="admin-section__kicker admin-visual-stage__heading">{{ __('Live preview') }}</h2>
                    <x-admin.live-preview :heading="false" :url="$previewUrl"
                        frame-title="Hero Artwork public page" wire:key="gallery-hero-live-preview" />
                </div>
            </div>

            @php
                $sourceRows = $this->sourceRows();
                $visibleSourceIds = collect($sourceRows->items())->pluck('id')
                    ->map(fn ($id): int => (int) $id)->values()->all();
                $sourceFiltersActive = trim($sourceSearch) !== '';
                $sourceHasRecords = $sourceRows->total() > 0 || $sourceFiltersActive;
            @endphp

            <x-admin.controls class="gallery-hero-artwork-source-controls admin-data-controls--dashboard-responsive" :metric-grid="true" :search-span="3" aria-label="Gallery source controls">
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
                        <div class="admin-toolbar admin-editorial-actions gallery-hero-workspace-actions">
                            <button class="admin-action admin-action--with-icon" type="button" wire:click="mountAction('settings')">
                                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Settings->mini()" class="admin-action__icon" />
                                <span class="admin-action__label">{{ __('Settings') }}</span>
                            </button>
                            @if ($heroGroupSource !== 'automatic')
                                <button class="admin-action admin-action--with-icon" type="button"
                                    wire:click="mountAction('addDummyGallery')" title="Add Dummy Gallery">
                                    <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Add->mini()" class="admin-action__icon" />
                                    <span class="admin-action__label">{{ __('Add Dummy Gallery') }}</span>
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
                                <button class="admin-action admin-action--with-icon admin-action--state is-danger" type="button" role="menuitem" wire:click="mountAction('removeSelectedDummyMembers')" x-on:click="open = false" @disabled(! $sourceCapabilities['remove'])>
                                    <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Delete->mini()" class="admin-action__icon" />
                                    <span class="admin-action__label">{{ __('Remove') }}</span>
                                </button>
                            </div>
                        </div>
                        <span class="admin-selection__count" aria-label="{{ count($selectedSourceIds) }} selected">{{ count($selectedSourceIds) }}</span>
                    </div>
                </x-slot:selection>
            </x-admin.controls>

            <x-admin.table class="admin-data-table gallery-hero-source-table {{ str_starts_with($heroSourceRule, 'gallery_') ? 'gallery-hero-source-table--gallery-order' : '' }}">
                <table class="admin-table--six-grid">
                    <colgroup>
                        <col class="admin-table__col-one-unit gallery-hero-source-table__col-gallery">
                        <col class="gallery-hero-source-table__col-candidates">
                        <col class="gallery-hero-source-table__col-actions">
                        <col class="admin-table__selection-col gallery-hero-source-table__col-selection">
                    </colgroup>
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Gallery') }}</th>
                            <th scope="col" class="gallery-hero-source-table__candidates">{{ __('Candidates') }}</th>
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
                                    aria-label="Select all visible sources and candidates"
                                >
                            </th>
                        </tr>
                    </thead>
                    <tbody wire:key="gallery-hero-source-body-{{ $heroGroupSource }}-{{ $sourceRows->currentPage() }}">
                        @forelse ($sourceRows as $gallery)
                            @php
                                $manualSource = $gallery['standalone'];
                                $groupId = (int) $gallery['id'];
                                $expanded = in_array($groupId, $expandedSourceIds, true);
                                $selected = in_array((string) $groupId, array_map('strval', $selectedSourceIds), true);
                            @endphp
                            <tr
                                class="gallery-hero-source-table__group {{ $selected ? 'is-selected' : '' }}"
                                wire:key="gallery-hero-source-gallery-{{ $groupId }}"
                            >
                                <td class="admin-table__identity">
                                    <strong>
                                        @if ($manualSource)
                                            <button class="gallery-hero-source-table__gallery-link gallery-hero-source-table__gallery-link--button"
                                                type="button" wire:click="mountAction('editDummyGallery', { gallery: {{ $groupId }} })">{{ $gallery['name'] }}</button>
                                        @elseif ($gallery['workspace_url'])
                                            <a class="gallery-hero-source-table__gallery-link" href="{{ $gallery['workspace_url'] }}" wire:navigate>{{ $gallery['name'] }}</a>
                                        @else
                                            {{ $gallery['name'] }}
                                        @endif
                                    </strong>
                                    <small class="admin-responsive-meta">{{ number_format($gallery['candidate_count']) }} candidates</small>
                                </td>
                                <td class="gallery-hero-source-table__candidates">
                                    <button
                                        type="button"
                                        class="gallery-hero-source-candidates__toggle"
                                        x-data="{ dragging: false, moved: false, startX: 0, startScroll: 0 }"
                                        x-on:pointerdown="if ($event.pointerType !== 'mouse' || $event.button !== 0) return; dragging = true; moved = false; startX = $event.clientX; startScroll = $refs.strip.scrollLeft; $el.setPointerCapture($event.pointerId)"
                                        x-on:pointermove="if (!dragging) return; if (Math.abs($event.clientX - startX) > 6) moved = true; if (moved) $refs.strip.scrollLeft = startScroll - ($event.clientX - startX)"
                                        x-on:pointerup="dragging = false"
                                        x-on:pointercancel="dragging = false"
                                        x-on:lostpointercapture="dragging = false"
                                        x-on:dragstart.prevent
                                        x-on:click="if (moved) { $event.stopImmediatePropagation(); $event.preventDefault(); moved = false }"
                                        wire:click="toggleSourceExpansion({{ $groupId }})"
                                        aria-expanded="{{ $expanded ? 'true' : 'false' }}"
                                        aria-label="{{ $expanded ? 'Collapse' : 'Expand' }} candidates in {{ $gallery['name'] }}"
                                    >
                                        <span class="gallery-hero-source-candidates" x-ref="strip" x-bind:class="{ 'is-dragging': dragging }">
                                            @forelse ($gallery['candidates'] as $candidate)
                                                <span class="gallery-hero-source-candidate">
                                                    <span class="gallery-hero-source-candidate__preview">
                                                        @if ($candidate['thumbnail_url'])
                                                            <img src="{{ $candidate['thumbnail_url'] }}" alt="" loading="lazy" decoding="async" draggable="false">
                                                        @else
                                                            <span>—</span>
                                                        @endif
                                                    </span>
                                                </span>
                                            @empty
                                                <span class="gallery-hero-source-candidates__empty">—</span>
                                            @endforelse
                                        </span>
                                    </button>
                                </td>
                                <td class="admin-table__actions">
                                    <div class="admin-row-actions admin-row-actions--canonical admin-row-actions--stable {{ str_starts_with($heroSourceRule, 'gallery_') ? 'admin-row-actions--hero-ranked' : 'admin-row-actions--hero-source' }} admin-toolbar gallery-hero-source-table__row-actions">
                                        @if (str_starts_with($heroSourceRule, 'gallery_'))
                                            <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::MoveUp"
                                                :disabled="$sourceSearch !== ''" wire:click="moveHeroSource({{ $groupId }}, 'up')" />
                                            <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::MoveDown"
                                                :disabled="$sourceSearch !== ''" wire:click="moveHeroSource({{ $groupId }}, 'down')" />
                                        @endif
                                        @if ($manualSource)
                                            <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::Edit"
                                                label="Edit" wire:click="mountAction('editDummyGallery', { gallery: {{ $groupId }} })" />
                                        @else
                                            <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::Edit"
                                                label="Edit"
                                                wire:click="mountAction('editRegularHeroGallery', { gallery: {{ $groupId }} })" />
                                        @endif
                                        <x-admin.row-action
                                            :action="$gallery['preference_enabled'] ? \App\Filament\Support\AdminRowAction::Unpublish : \App\Filament\Support\AdminRowAction::Publish"
                                            :label="$gallery['preference_enabled'] ? 'Disable' : 'Enable'"
                                            wire:click="{{ $manualSource ? 'toggleDummyGallery' : 'toggleGalleryEligibility' }}({{ $groupId }})"
                                            :disabled="! $gallery['preference_enabled'] && ! $gallery['can_enable']" />
                                        @if ($manualSource)
                                            <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::Delete"
                                                label="Delete" wire:click="mountAction('deleteDummyGallery', { gallery: {{ $groupId }} })" />
                                        @else
                                            <span class="gallery-hero-source-table__action-slot" aria-hidden="true"></span>
                                        @endif
                                    </div>
                                </td>
                                <td class="admin-table__selection admin-table__selection--trailing">
                                    <input type="checkbox" value="{{ $groupId }}"
                                        wire:model.live="selectedSourceIds"
                                        aria-label="Select source {{ $gallery['name'] }}">
                                </td>
                            </tr>
                            @if ($expanded)
                                @foreach ($gallery['candidates'] as $candidate)
                                    <tr
                                        class="gallery-hero-source-table__candidate-row {{ in_array(\App\Filament\Pages\GalleryHeroWorkspace::sourceCandidateSelectionId($groupId, $candidate['id']), array_map('strval', $selectedSourceIds), true) ? 'is-selected' : '' }}"
                                        wire:key="gallery-hero-source-item-{{ $groupId }}-{{ $candidate['id'] }}"
                                    >
                                        <td class="gallery-hero-source-table__empty-gallery"></td>
                                        <td class="gallery-hero-source-table__candidates">
                                            @include('filament.pages.partials.gallery-hero-source-candidate-cell', ['candidate' => $candidate])
                                        </td>
                                        <td class="admin-table__actions">
                                            @include('filament.pages.partials.gallery-hero-source-candidate-actions', ['candidate' => $candidate, 'manualSource' => $manualSource, 'groupId' => $groupId])
                                        </td>
                                        <td class="admin-table__selection admin-table__selection--trailing">
                                            <input type="checkbox" value="{{ \App\Filament\Pages\GalleryHeroWorkspace::sourceCandidateSelectionId($groupId, $candidate['id']) }}"
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
                                        <x-admin.empty-state title="No matches" minimal>
                                            <x-slot:actions><x-admin.clear-filters label="Clear filters" wire:click="resetSourceFilters" /></x-slot:actions>
                                        </x-admin.empty-state>
                                    @else
                                        <x-admin.empty-state title="Empty" minimal />
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </x-admin.table>

            @if ($heroGroupSource !== 'automatic')
                <x-admin.add-row wire:click="mountAction('addDummyGallery')">{{ __('Add Dummy Gallery') }}</x-admin.add-row>
            @endif

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

    </x-admin.workspace>

    <x-filament-actions::modals />
</x-filament-panels::page>
