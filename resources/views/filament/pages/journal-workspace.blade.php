<x-filament-panels::page>
    @php
        $isBlog = $template === \App\Domain\Content\JournalTemplate::Blog->value;
        $entries = $isBlog ? $posts : $exhibitions;
        $selectedIds = collect($isBlog ? $selectedPostIds : $selectedExhibitionIds)
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
        $selectedCapabilities = $this->selectedEntryCapabilities();
        $visibleIds = collect($entries)->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
        $resultStart = $total === 0 ? 0 : (($page - 1) * $pageSize) + 1;
        $resultEnd = $total === 0 ? 0 : min($total, $page * $pageSize);
        $dragEnabled = $this->canDragSort();
        $canMoveSelectedUp = $dragEnabled && collect($entries)->contains(static fn (array $entry): bool =>
            in_array((int) $entry['id'], $selectedIds, true) && $entry['can_move_up']
        );
        $canMoveSelectedDown = $dragEnabled && collect($entries)->contains(static fn (array $entry): bool =>
            in_array((int) $entry['id'], $selectedIds, true) && $entry['can_move_down']
        );
        $entryLabel = $isBlog ? 'posts' : 'exhibitions';
        $entryLabelSingular = $isBlog ? 'post' : 'exhibition';
    @endphp

    <x-admin.workspace :title="$journalTitle" class="journal-workspace">
        <x-admin.metrics :columns="6" class="journal-status-metrics admin-metrics--six-state {{ $isBlog ? 'journal-status-metrics--blog' : 'journal-status-metrics--exhibitions' }}" aria-label="{{ $isBlog ? 'Blog overview' : 'Exhibitions overview' }}">
            @foreach ($metrics as $metric)
                @php
                    $metricRole = match ($metric['label']) {
                        'Reads · 30d' => 'reads',
                        'Visits · 30d' => 'visits',
                        'Views · 30d' => 'views',
                        'Published' => 'published',
                        'Scheduled' => 'scheduled',
                        'Draft' => 'draft',
                        'Unpublished' => 'unpublished',
                        'Current' => 'current',
                        'Upcoming' => 'upcoming',
                        'Interactions · 30d' => 'interactions',
                        default => 'secondary',
                    };
                @endphp
                <x-admin.metric class="journal-metric journal-metric--{{ $metricRole }}" :label="$metric['label']" :value="$metric['value']">{{ $metric['description'] }}</x-admin.metric>
            @endforeach
        </x-admin.metrics>

        <section class="admin-visual-stage admin-visual-stage--split admin-visual-stage--editor-preview"
            aria-label="Journal editor and live public preview">
            @php($selectedStageIds = $this->journalStageSelectedIds())
            @php($selectedStageTotal = count($selectedStageIds))
            @php($selectedStagePosition = $this->journalStageSelectedPosition())
            <div class="gallery-stage__workspace admin-visual-stage__pane admin-visual-stage__editor-pane">
                <header class="gallery-stage__workspace-header">
                    <h2 class="admin-section__kicker admin-visual-stage__heading">{{ $isBlog ? __('Blog editor') : __('Exhibitions editor') }}</h2>
                    <div class="admin-stage-tabs gallery-stage__workspace-tabs" role="group" aria-label="Journal editor mode and selected entry navigation">
                        <button class="admin-action {{ $journalStagePanel === 'new' ? 'is-primary' : '' }}"
                            type="button" wire:click="showJournalStagePanel('new')"
                            aria-pressed="{{ $journalStagePanel === 'new' ? 'true' : 'false' }}">New</button>
                        <div class="admin-action gallery-stage__queue-control {{ $journalStagePanel === 'selected' ? 'is-primary' : '' }}"
                            role="group" aria-label="Selected Journal entry navigation">
                            <button class="gallery-stage__queue-arrow" type="button" wire:click="navigateSelectedJournalEntry(-1)"
                                @disabled($selectedStageTotal <= 1) aria-label="Previous selected entry">
                                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Previous->mini()" />
                            </button>
                            <button class="gallery-stage__queue-tab" type="button" wire:click="showJournalStagePanel('selected')"
                                @disabled($selectedStageTotal === 0) aria-pressed="{{ $journalStagePanel === 'selected' ? 'true' : 'false' }}">
                                Selected ({{ $selectedStagePosition }}/{{ $selectedStageTotal }})
                            </button>
                            <button class="gallery-stage__queue-arrow" type="button" wire:click="navigateSelectedJournalEntry(1)"
                                @disabled($selectedStageTotal <= 1) aria-label="Next selected entry">
                                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Next->mini()" />
                            </button>
                        </div>
                        <button class="admin-action {{ $journalStagePanel === 'settings' ? 'is-primary' : '' }}"
                            type="button" wire:click="showJournalStagePanel('settings')"
                            aria-pressed="{{ $journalStagePanel === 'settings' ? 'true' : 'false' }}">Settings</button>
                    </div>
                </header>

                @if ($journalStagePanel === 'new')
                    <div class="gallery-stage__form-scroll admin-visual-stage__field-scroll fi-fixed-positioning-context"
                        wire:change="rememberNewJournalEntry"
                        x-on:artist-rich-editor-commit="$wire.rememberNewJournalEntry()">
                        {{ $this->journalNewForm }}
                    </div>
                    <div class="gallery-stage__editor-actions admin-toolbar" aria-label="New Journal entry actions">
                        <div class="gallery-stage__action-group">
                            <button class="admin-action is-danger" type="button" wire:click="discardNewJournalEntry"
                                wire:loading.attr="disabled" wire:target="discardNewJournalEntry,submitNewJournalEntry">
                                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Clear->mini()" />
                                <span class="gallery-stage__action-label">Discard</span>
                            </button>
                            <button class="admin-action is-success" type="button" wire:click="submitNewJournalEntry"
                                wire:loading.attr="disabled" wire:target="discardNewJournalEntry,submitNewJournalEntry">
                                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::DialogSubmit->mini()" />
                                <span class="gallery-stage__action-label">Submit</span>
                            </button>
                        </div>
                    </div>
                @elseif ($journalStagePanel === 'selected' && $selectedJournalEntryId !== null)
                    <div class="gallery-stage__form-scroll admin-visual-stage__field-scroll fi-fixed-positioning-context"
                        wire:key="journal-selected-editor-{{ $selectedJournalEntryId }}"
                        wire:change="rememberSelectedJournalEdit"
                        x-on:artist-rich-editor-commit="$wire.rememberSelectedJournalEdit()">
                        {{ $this->journalSelectedForm }}
                    </div>
                    <div class="gallery-stage__editor-actions admin-toolbar" aria-label="Selected Journal entry actions">
                        <div class="gallery-stage__action-group gallery-stage__action-group--batch">
                            <button class="admin-action is-danger" type="button" wire:click="discardAllSelectedJournalEdits"
                                wire:loading.attr="disabled" wire:target="discardSelectedJournalEdit,discardAllSelectedJournalEdits">
                                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Clear->mini()" />
                                <span class="gallery-stage__action-label">Discard All</span>
                            </button>
                            <button class="admin-action is-success" type="button" wire:click="submitAllSelectedJournalEdits"
                                wire:loading.attr="disabled" wire:target="submitSelectedJournalEdit,submitAllSelectedJournalEdits">
                                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::SubmitAll->value" />
                                <span class="gallery-stage__action-label">Submit All</span>
                            </button>
                        </div>
                        <div class="gallery-stage__action-group">
                            <button class="admin-action is-danger" type="button" wire:click="discardSelectedJournalEdit"
                                wire:loading.attr="disabled" wire:target="discardSelectedJournalEdit,discardAllSelectedJournalEdits">
                                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Clear->mini()" />
                                <span class="gallery-stage__action-label">Discard</span>
                            </button>
                            <button class="admin-action is-success" type="button" wire:click="submitSelectedJournalEdit"
                                wire:loading.attr="disabled" wire:target="submitSelectedJournalEdit,submitAllSelectedJournalEdits">
                                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::DialogSubmit->mini()" />
                                <span class="gallery-stage__action-label">Submit</span>
                            </button>
                        </div>
                    </div>
                @elseif ($journalStagePanel === 'settings')
                    <div class="gallery-stage__settings admin-visual-stage__field-scroll fi-fixed-positioning-context">
                        {{ $this->journalStageSettingsForm }}
                    </div>
                @endif
            </div>
            <div class="admin-visual-stage__pane admin-visual-stage__preview-pane">
                <h2 class="admin-section__kicker admin-visual-stage__heading">{{ __('Live preview') }}</h2>
                <x-admin.live-preview
                    :heading="false"
                    :url="$this->journalStagePreviewUrl()"
                    :frame-title="$this->journalStagePreviewTitle()"
                    wire:key="journal-live-preview"
                >
                    <x-slot:focusAction>
                        <button class="admin-action admin-stage-tool-icon {{ $journalPreviewAutoFocus ? 'is-primary' : '' }}"
                            type="button"
                            wire:click="toggleJournalPreviewAutoFocus"
                            aria-label="Auto-focus preview to selection"
                            aria-pressed="{{ $journalPreviewAutoFocus ? 'true' : 'false' }}"
                            title="{{ $journalPreviewAutoFocus ? 'Auto-focus selection on' : 'Auto-focus selection off' }}">
                            <x-filament::icon icon="heroicon-m-cursor-arrow-rays" />
                        </button>
                    </x-slot:focusAction>
                </x-admin.live-preview>
            </div>
        </section>

        <x-admin.section class="journal-workspace__entries admin-visual-stage-followup" aria-label="{{ $isBlog ? 'Blog entries' : 'Exhibition entries' }}">
            <x-admin.controls class="admin-data-controls--dashboard-responsive" aria-label="{{ $isBlog ? 'Blog controls' : 'Exhibition controls' }}" metric-grid :filter-count="$isBlog ? 1 : 2" :search-span="$isBlog ? 3 : 2">
                <x-slot:search>
                    <label class="admin-data-field">
                        <span>{{ __('Search') }}</span>
                        <x-admin.search-input
                            model="search"
                            placeholder="{{ $isBlog ? 'Title or excerpt' : 'Title, venue, place or date' }}"
                        />
                    </label>
                </x-slot:search>

                <x-slot:filters>
                    <label class="admin-data-field">
                        <span>{{ __('Status') }}</span>
                        <select wire:model.live="statusFilter">
                            <option value="any">{{ __('Any') }}</option>
                            @if ($isBlog)
                                <option value="draft">{{ __('Draft') }}</option>
                                <option value="scheduled">{{ __('Scheduled') }}</option>
                                <option value="published">{{ __('Published') }}</option>
                                <option value="unpublished">{{ __('Unpublished') }}</option>
                            @else
                                <option value="published">{{ __('Published') }}</option>
                                <option value="unpublished">{{ __('Unpublished') }}</option>
                            @endif
                        </select>
                    </label>

                    @unless ($isBlog)
                        <label class="admin-data-field">
                            <span>{{ __('Timing') }}</span>
                            <select wire:model.live="timingFilter">
                                <option value="any">{{ __('Any') }}</option>
                                <option value="upcoming">{{ __('Upcoming') }}</option>
                                <option value="current">{{ __('Current') }}</option>
                                <option value="past">{{ __('Past') }}</option>
                                <option value="unknown">{{ __('Unknown') }}</option>
                            </select>
                        </label>
                    @endunless
                </x-slot:filters>

                <x-slot:reset>
                    <div class="admin-data-control-group">
                        <span class="admin-data-control-label">{{ __('Filter') }}</span>
                        <x-admin.clear-filters wire:click="resetFilters" :disabled="trim($search) === '' && $statusFilter === 'any' && ($isBlog || $timingFilter === 'any')" />
                    </div>
                </x-slot:reset>

                <x-slot:actions>
                    <div class="admin-data-control-group">
                        <span class="admin-data-control-label">{{ __('Journal') }}</span>
                        <div class="admin-toolbar admin-editorial-actions">
                            <button class="admin-action admin-action--with-icon" type="button" wire:click="mountAction('journalSettings')" aria-label="Journal settings">
                                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Settings->mini()" class="admin-action__icon" />
                                <span class="admin-action__label">{{ __('Settings') }}</span>
                            </button>
                            <button class="admin-action admin-action--with-icon" type="button" wire:click="mountAction('{{ $isBlog ? 'addPost' : 'addExhibition' }}')" aria-label="Add {{ $entryLabelSingular }}">
                                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Add->mini()" class="admin-action__icon" />
                                <span class="admin-action__label">Add {{ $entryLabelSingular }}</span>
                            </button>
                            @unless ($isBlog)
                                <button class="admin-action admin-action--with-icon" type="button" wire:click="mountAction('exhibitionOrganizers')" aria-label="Exhibition organizers">
                                    <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Organizers->mini()" class="admin-action__icon" />
                                    <span class="admin-action__label">Organizers</span>
                                </button>
                                <button class="admin-action admin-action--with-icon" type="button" wire:click="mountAction('exhibitionVenues')" aria-label="Exhibition venues">
                                    <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Venues->mini()" class="admin-action__icon" />
                                    <span class="admin-action__label">Venues</span>
                                </button>
                            @endunless
                            @if ($journalPreviewUrl)
                                <a class="admin-action admin-action--with-icon" href="{{ $journalPreviewUrl }}" target="_blank" rel="noopener" aria-label="Preview Journal">
                                    <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Preview->mini()" class="admin-action__icon" />
                                    <span class="admin-action__label">{{ __('Preview') }}</span>
                                </a>
                            @else
                                <button class="admin-action admin-action--with-icon" type="button" disabled title="A preview URL is unavailable for this Journal" aria-label="Preview Journal">
                                    <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Preview->mini()" class="admin-action__icon" />
                                    <span class="admin-action__label">{{ __('Preview') }}</span>
                                </button>
                            @endif
                        </div>
                    </div>
                </x-slot:actions>

                <x-slot:selection>
                    <div class="admin-data-control-group admin-selection" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
                        <span class="admin-data-control-label">{{ __('Selection') }}</span>
                        <div class="admin-selection__anchor">
                            <button
                                class="admin-action admin-selection__trigger"
                                type="button"
                                x-on:click="open = ! open"
                                x-bind:aria-expanded="open.toString()"
                                aria-haspopup="menu"
                                @disabled($selectedIds === [])
                            >
                                <x-admin.selection-trigger-label>{{ __('Selected') }}</x-admin.selection-trigger-label>
                            </button>
                            <div class="admin-selection__menu" role="menu" x-show="open" x-cloak x-on:click.outside="open = false">
                                <button class="admin-action" type="button" role="menuitem" wire:click="moveSelectedEntries('up')" x-on:click="open = false" @disabled(! $canMoveSelectedUp)>{{ __('Move selected up') }}</button>
                                <button class="admin-action" type="button" role="menuitem" wire:click="moveSelectedEntries('down')" x-on:click="open = false" @disabled(! $canMoveSelectedDown)>{{ __('Move selected down') }}</button>
                                @if ($isBlog)
                                    <button class="admin-action" type="button" role="menuitem" wire:click="publishSelectedPosts" x-on:click="open = false" @disabled(! $selectedCapabilities['publish'])>{{ __('Publish selected') }}</button>
                                    <button class="admin-action" type="button" role="menuitem" wire:click="unpublishSelectedPosts" x-on:click="open = false" @disabled(! $selectedCapabilities['unpublish'])>{{ __('Unpublish selected') }}</button>
                                    <button class="admin-action" type="button" role="menuitem" wire:click="restoreSelectedPosts" x-on:click="open = false" @disabled(! $selectedCapabilities['restore'])>{{ __('Restore selected to draft') }}</button>
                                @endif
                                <button class="admin-action is-danger" type="button" role="menuitem" wire:click="mountAction('{{ $isBlog ? 'deleteSelectedPosts' : 'deleteSelectedExhibitions' }}')" x-on:click="open = false" @disabled(! $selectedCapabilities['delete'])>{{ __('Delete selected') }}</button>
                            </div>
                        </div>
                        <span class="admin-selection__count" aria-label="{{ count($selectedIds) }} selected">{{ count($selectedIds) }}</span>
                    </div>
                </x-slot:selection>
            </x-admin.controls>

            <x-admin.table class="admin-table--data admin-table--ranked">
                <table class="admin-table--six-grid journal-table {{ $isBlog ? 'journal-table--blog admin-table--fold-dense' : 'journal-table--exhibitions admin-table--fold-at-compact' }}">
                    <colgroup>
                        <col class="admin-table__col-quarter-unit admin-table__col-position">
                        <col class="admin-table__col-quarter-unit admin-table__col-drag">
                        @if ($isBlog)
                            <col class="admin-table__col-half-unit journal-col--visual">
                            <col class="admin-table__col-one-unit journal-col--identity">
                            <col class="journal-col--publication-date admin-table__yield--narrow">
                            <col class="journal-col--status admin-table__split-only">
                            <col class="journal-table__actions--blog">
                        @else
                            <col class="admin-table__col-half-unit journal-col--visual">
                            <col class="admin-table__col-one-half-units journal-col--identity">
                            <col class="journal-col--date admin-table__yield--narrow">
                            <col class="journal-col--timing admin-table__split-only">
                            <col class="admin-table__col-two-units-minus-selection journal-table__actions--exhibitions">
                        @endif
                        <col class="admin-table__selection-col">
                    </colgroup>
                    <thead>
                        <tr>
                            <th scope="colgroup" colspan="2" class="admin-table__ordering-heading">{{ __('Position') }}</th>
                            <th scope="col" class="journal-visual journal-col--visual">{{ __('Image') }}</th>
                            <th scope="col" class="journal-col--identity">{{ $isBlog ? 'Post' : 'Exhibition' }}</th>
                            @if ($isBlog)
                                <th scope="col" class="journal-col--publication-date admin-table__yield--narrow">{{ __('Date') }}</th>
                                <th scope="col" class="journal-col--status admin-table__split-only">{{ __('Status') }}</th>
                            @else
                                <th scope="col" class="journal-col--date admin-table__yield--narrow">
                                    <span class="admin-table__split-label">{{ __('Date') }}</span>
                                    <span class="admin-table__folded-only">{{ __('Dates') }}</span>
                                </th>
                                <th scope="col" class="journal-col--timing admin-table__split-only">{{ __('Status') }}</th>
                            @endif
                            <th scope="col" class="admin-table__actions"><span class="admin-row-actions-heading">{{ __('Actions') }}</span></th>
                            <th scope="col" class="admin-table__selection admin-table__selection--trailing">
                                <input
                                    type="checkbox"
                                    x-data="{}"
                                    wire:click.prevent="toggleVisibleSelection"
                                    @if ($isBlog)
                                        x-effect="const visibleIds = @js($visibleIds); const selectedIds = $wire.selectedPostIds.map(Number); const count = visibleIds.filter((id) => selectedIds.includes(id)).length; $el.checked = visibleIds.length > 0 && count === visibleIds.length; $el.indeterminate = count > 0 && count < visibleIds.length; $el.setAttribute('aria-checked', $el.indeterminate ? 'mixed' : ($el.checked ? 'true' : 'false'));"
                                    @else
                                        x-effect="const visibleIds = @js($visibleIds); const selectedIds = $wire.selectedExhibitionIds.map(Number); const count = visibleIds.filter((id) => selectedIds.includes(id)).length; $el.checked = visibleIds.length > 0 && count === visibleIds.length; $el.indeterminate = count > 0 && count < visibleIds.length; $el.setAttribute('aria-checked', $el.indeterminate ? 'mixed' : ($el.checked ? 'true' : 'false'));"
                                    @endif
                                    @disabled($visibleIds === [])
                                    aria-label="Toggle selection for visible {{ $entryLabel }}"
                                >
                            </th>
                        </tr>
                    </thead>
                    <tbody @if ($dragEnabled) wire:sort="{{ $isBlog ? 'sortPost' : 'sortExhibition' }}" @endif>
                        @forelse ($entries as $entry)
                            <tr
                                class="{{ in_array($entry['id'], $selectedIds, true) ? 'is-selected' : '' }}"
                                wire:key="journal-{{ $template }}-{{ $entry['id'] }}"
                                @if ($dragEnabled) wire:sort:item="{{ $entry['id'] }}" @endif
                            >
                                <td class="admin-table__position"><span class="admin-position">{{ $entry['rank'] }}</span></td>
                                <td class="admin-table__drag">
                                    <button
                                        class="admin-drag-handle"
                                        type="button"
                                        @if ($dragEnabled) wire:sort:handle title="Drag to reorder" @else disabled title="Drag reorder is available only with no search/filter and all entries on one page" @endif
                                        aria-label="Drag {{ $entry['title'] }} to reorder"
                                    >⋮⋮</button>
                                </td>
                                <td class="journal-visual journal-col--visual">
                                    <div class="journal-visual__thumbnail">
                                        @if ($entry['thumbnail_url'])
                                            <img src="{{ $entry['thumbnail_url'] }}" alt="" loading="lazy" decoding="async">
                                        @else
                                            <span aria-label="No image">—</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="admin-table__identity admin-table__identity--ellipsis-meta journal-col--identity">
                                    <strong>{{ $entry['title'] }}</strong>
                                    @if ($isBlog)
                                        <small class="admin-responsive-meta">{{ ucfirst($entry['state']) }}@if ($entry['publication']) · {{ $entry['publication'] }}@endif</small>
                                    @else
                                        <small class="admin-responsive-meta">@if ($entry['date_range'] !== ''){{ $entry['date_range'] }} · @endif{{ ucfirst($entry['timing']) }}</small>
                                        @if ($entry['vernissage'])<small class="admin-responsive-meta">Vernissage: {{ $entry['vernissage'] }}</small>@endif
                                    @endif
                                    @if ($isBlog && $entry['excerpt'])
                                        <small>{{ $entry['excerpt'] }}</small>
                                    @elseif (! $isBlog && $entry['location'])
                                        <small>{{ $entry['location'] }}</small>
                                    @endif
                                </td>
                                @if ($isBlog)
                                    <td class="journal-publication-date journal-col--publication-date admin-table__yield--narrow">
                                        @if ($entry['publication'])<span class="journal-publication__date">{{ $entry['publication'] }}</span>@else<span aria-label="No publication date">—</span>@endif
                                        <div class="journal-publication__state admin-table__folded-only">
                                            <span class="journal-state is-{{ $entry['state'] }}">{{ ucfirst($entry['state']) }}</span>
                                        </div>
                                    </td>
                                    <td class="journal-col--status admin-table__split-only">
                                        <span class="journal-state is-{{ $entry['state'] }}">{{ ucfirst($entry['state']) }}</span>
                                    </td>
                                @else
                                    <td class="journal-date journal-col--date admin-table__yield--narrow">
                                        @if ($entry['date_range'] !== '')<span class="journal-date__range">{{ $entry['date_range'] }}</span>@else<span aria-label="No structured date">—</span>@endif
                                        @if ($entry['vernissage'])
                                            <span class="journal-date__vernissage">Vernissage: {{ $entry['vernissage'] }}</span>
                                        @endif
                                        <div class="journal-date__timing admin-table__folded-only">
                                            <span class="journal-timing is-{{ $entry['timing'] }}">{{ ucfirst($entry['timing']) }}</span>
                                        </div>
                                    </td>
                                    <td class="journal-col--timing admin-table__split-only">
                                        <span class="journal-timing is-{{ $entry['timing'] }}">{{ ucfirst($entry['timing']) }}</span>
                                    </td>
                                @endif
                                <td class="admin-table__actions">
                                    <x-admin.toolbar class="admin-row-actions admin-row-actions--canonical admin-row-actions--stable journal-row-actions {{ $isBlog ? 'admin-row-actions--journal-blog journal-row-actions--blog' : 'admin-row-actions--move-state-delete journal-row-actions--exhibitions' }}">
                                        @if ($isBlog)
                                            <x-admin.row-action
                                                :action="\App\Filament\Support\AdminRowAction::MoveUp"
                                                wire:click="movePost({{ $entry['id'] }}, 'up')"
                                                :disabled="! $entry['can_move_up']"
                                                aria-label="Move {{ $entry['title'] }} up"
                                            />
                                            <x-admin.row-action
                                                :action="\App\Filament\Support\AdminRowAction::MoveDown"
                                                wire:click="movePost({{ $entry['id'] }}, 'down')"
                                                :disabled="! $entry['can_move_down']"
                                                aria-label="Move {{ $entry['title'] }} down"
                                            />
                                            <x-admin.row-action
                                                :action="\App\Filament\Support\AdminRowAction::Edit"
                                                wire:click="mountAction('editPost', { post: {{ $entry['id'] }} })"
                                            />

                                            @if ($entry['state'] === 'published')
                                                <x-admin.row-action
                                                    :action="\App\Filament\Support\AdminRowAction::Unpublish"
                                                    wire:click="unpublishPost({{ $entry['id'] }})"
                                                />
                                            @elseif ($entry['state'] === 'scheduled')
                                                <x-admin.row-action
                                                    :action="\App\Filament\Support\AdminRowAction::CancelSchedule"
                                                    label="Cancel"
                                                    title="Cancel schedule"
                                                    aria-label="Cancel schedule"
                                                    wire:click="restorePostDraft({{ $entry['id'] }})"
                                                />
                                            @elseif ($entry['state'] === 'archived')
                                                <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::Publish" disabled />
                                            @else
                                                <x-admin.row-action
                                                    :action="\App\Filament\Support\AdminRowAction::Publish"
                                                    wire:click="publishPost({{ $entry['id'] }})"
                                                />
                                            @endif

                                            @if (in_array($entry['state'], ['draft', 'unpublished'], true))
                                                <x-admin.row-action
                                                    :action="\App\Filament\Support\AdminRowAction::Schedule"
                                                    wire:click="mountAction('schedulePost', { post: {{ $entry['id'] }} })"
                                                />
                                            @else
                                                <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::Schedule" disabled />
                                            @endif

                                            @if ($entry['state'] === 'archived')
                                                <x-admin.row-action
                                                    :action="\App\Filament\Support\AdminRowAction::Restore"
                                                    wire:click="restorePostDraft({{ $entry['id'] }})"
                                                />
                                            @endif

                                            <x-admin.row-action
                                                :action="\App\Filament\Support\AdminRowAction::Delete"
                                                wire:click="mountAction('deletePost', { post: {{ $entry['id'] }} })"
                                                :disabled="! $entry['can_delete']"
                                                title="{{ $entry['delete_help'] ?? 'Delete post' }}"
                                            />
                                        @else
                                            <x-admin.row-action
                                                :action="\App\Filament\Support\AdminRowAction::MoveUp"
                                                wire:click="moveExhibition({{ $entry['id'] }}, 'up')"
                                                :disabled="! $entry['can_move_up']"
                                                aria-label="Move {{ $entry['title'] }} up"
                                            />
                                            <x-admin.row-action
                                                :action="\App\Filament\Support\AdminRowAction::MoveDown"
                                                wire:click="moveExhibition({{ $entry['id'] }}, 'down')"
                                                :disabled="! $entry['can_move_down']"
                                                aria-label="Move {{ $entry['title'] }} down"
                                            />
                                            <x-admin.row-action
                                                :action="\App\Filament\Support\AdminRowAction::Edit"
                                                wire:click="mountAction('editExhibition', { exhibition: {{ $entry['id'] }} })"
                                            />
                                            @if ($entry['state'] === 'published')
                                                <x-admin.row-action
                                                    :action="\App\Filament\Support\AdminRowAction::Unpublish"
                                                    wire:click="unpublishExhibition({{ $entry['id'] }})"
                                                />
                                            @else
                                                <x-admin.row-action
                                                    :action="\App\Filament\Support\AdminRowAction::Publish"
                                                    wire:click="publishExhibition({{ $entry['id'] }})"
                                                />
                                            @endif
                                            <x-admin.row-action
                                                :action="\App\Filament\Support\AdminRowAction::Delete"
                                                wire:click="mountAction('deleteExhibition', { exhibition: {{ $entry['id'] }} })"
                                                :disabled="! $entry['can_delete']"
                                                title="{{ $entry['delete_help'] ?? 'Delete exhibition' }}"
                                            />
                                        @endif
                                    </x-admin.toolbar>
                                </td>
                                <td class="admin-table__selection admin-table__selection--trailing">
                                    @if ($isBlog)
                                        <input
                                            type="checkbox"
                                            x-data="{}"
                                            wire:click.prevent="togglePostSelection({{ $entry['id'] }})"
                                            x-effect="$el.checked = $wire.selectedPostIds.map(Number).includes({{ (int) $entry['id'] }})"
                                            aria-label="Toggle selection for {{ $entry['title'] }}"
                                        >
                                    @else
                                        <input
                                            type="checkbox"
                                            x-data="{}"
                                            wire:click.prevent="toggleExhibitionSelection({{ $entry['id'] }})"
                                            x-effect="$el.checked = $wire.selectedExhibitionIds.map(Number).includes({{ (int) $entry['id'] }})"
                                            aria-label="Toggle selection for {{ $entry['title'] }}"
                                        >
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="admin-table__empty-cell" colspan="8">
                                    @if ($unfilteredEntryCount > 0)
                                        <x-admin.empty-state :title="'No matches'" minimal>
                                            <x-slot:actions><x-admin.clear-filters label="Clear filters" wire:click="resetFilters" /></x-slot:actions>
                                        </x-admin.empty-state>
                                    @else
                                        <x-admin.empty-state :title="'Empty'" minimal>
                                            <x-slot:actions><button class="admin-action" type="button" wire:click="mountAction('{{ $isBlog ? 'addPost' : 'addExhibition' }}')">Add {{ $entryLabelSingular }}</button></x-slot:actions>
                                        </x-admin.empty-state>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </x-admin.table>

            <x-admin.add-row wire:click="mountAction('{{ $isBlog ? 'addPost' : 'addExhibition' }}')">Add {{ $entryLabelSingular }}</x-admin.add-row>

            <footer class="admin-pager">
                <x-admin.page-size-picker
                    :value="$pageSize"
                    :options="[25, 50, 100]"
                    wire-model="pageSize"
                    aria-label="{{ $isBlog ? 'Blog posts per page' : 'Exhibitions per page' }}"
                />
                <span class="admin-pager__range">@if ($total === 0)0 of 0 @else{{ $resultStart }}–{{ $resultEnd }} of {{ $total }}@endif</span>
                <x-admin.toolbar class="admin-pager__actions">
                    <button class="admin-action" type="button" wire:click="previousPage" @disabled($page <= 1)>{{ __('Previous') }}</button>
                    <button class="admin-action" type="button" wire:click="nextPage" @disabled($page >= $pages)>{{ __('Next') }}</button>
                </x-admin.toolbar>
            </footer>
        </x-admin.section>
    </x-admin.workspace>
</x-filament-panels::page>