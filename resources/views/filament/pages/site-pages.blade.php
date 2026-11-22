@php
    use App\Domain\Content\JournalTemplate;
    use App\Domain\Content\SiteSectionType;

    $typeOptions = collect(SiteSectionType::cases())->mapWithKeys(fn (SiteSectionType $type): array => [$type->value => $type->compactLabel()])->all();
    $editableTypeOptions = SiteSectionType::compactOptions();
    $journalTemplateOptions = JournalTemplate::options();
    $selectedCount = count($selectedSectionIds);
    $selectedIds = array_map('intval', $selectedSectionIds);
    $selectedRows = collect($filteredRows)->filter(static fn (array $row): bool =>
        in_array((int) $row['id'], $selectedIds, true)
    );
    $canPublishSelected = $selectedRows->contains(static fn (array $row): bool =>
        $row['can_change_publication'] && $row['state'] !== 'published'
    );
    $canUnpublishSelected = $selectedRows->contains(static fn (array $row): bool =>
        $row['can_change_publication'] && $row['state'] === 'published'
    );
    $canDeleteSelected = $selectedRows->contains(static fn (array $row): bool =>
        $row['delete_available']
    );
@endphp

<x-filament-panels::page>
    <x-admin.workspace title="Pages" class="admin-pages-workspace">
        <x-admin.metrics :columns="6" class="pages-status-metrics admin-metrics--six-state">
            <x-admin.metric class="pages-metric pages-metric--total" label="Total pages" :value="$metrics['total']">{{ __('All site sections') }}</x-admin.metric>
            <x-admin.metric class="pages-metric pages-metric--published" label="Published" :value="$metrics['published']">{{ __('Public now') }}</x-admin.metric>
            <x-admin.metric class="pages-metric pages-metric--unpublished" label="Unpublished" :value="$metrics['unpublished']">{{ __('Not public') }}</x-admin.metric>
            <x-admin.metric class="pages-metric pages-metric--top-level" label="Top level" :value="$metrics['top_level']">{{ __('Root pages') }}</x-admin.metric>
            <x-admin.metric class="pages-metric pages-metric--children" label="Child pages" :value="$metrics['children']">{{ __('Nested pages') }}</x-admin.metric>
            <x-admin.metric class="pages-metric pages-metric--navigation" label="In navigation" :value="$metrics['navigation']">{{ __('Visible menu items') }}</x-admin.metric>
            <x-admin.metric class="pages-metric pages-metric--galleries" label="Galleries" :value="$metrics['galleries']">{{ __('Gallery pages') }}</x-admin.metric>
        </x-admin.metrics>

        <section class="admin-visual-stage admin-visual-stage--split admin-visual-stage--editor-preview"
            aria-label="Pages editor and live public preview">
            <div class="admin-visual-stage__pane admin-visual-stage__editor-pane">
                <h2 class="admin-section__kicker admin-visual-stage__heading">{{ __('Editor') }}</h2>
                <div class="admin-stage-tabs admin-stage-tabs--three" role="tablist" aria-label="Pages editing mode">
                    @foreach (['create' => 'Create', 'navigation' => 'Navigation', 'settings' => 'Settings'] as $panel => $label)
                        <button class="admin-action {{ $pagesStagePanel === $panel ? 'is-primary' : '' }}"
                            type="button" role="tab" aria-selected="{{ $pagesStagePanel === $panel ? 'true' : 'false' }}"
                            wire:click="showPagesStagePanel('{{ $panel }}')">{{ __($label) }}</button>
                    @endforeach
                </div>
                @if ($pagesStagePanel === 'create')
                    <form class="admin-stage-workspace admin-visual-stage__field-scroll" wire:submit.prevent="createStagePage">
                        <label class="admin-stage-workspace__field">
                            <span>{{ __('Page type') }}</span>
                            <select wire:model.live="newPageType">
                                @foreach ($editableTypeOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        @if ($newPageType === 'gallery')
                            <label class="admin-stage-workspace__field">
                                <span>{{ __('Template') }}</span>
                                <select wire:model="stageNewGalleryTemplate">
                                    @foreach (\App\Domain\Content\GalleryTemplate::options() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @elseif ($newPageType === 'journal')
                            <label class="admin-stage-workspace__field">
                                <span>{{ __('Template') }}</span>
                                <select wire:model="newJournalTemplate">
                                    @foreach ($journalTemplateOptions as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endif
                        <label class="admin-stage-workspace__field">
                            <span>{{ __('Name') }}</span>
                            <input type="text" maxlength="160" wire:model="newPageTitle" placeholder="{{ __('Page name') }}">
                        </label>
                        <label class="admin-stage-workspace__field">
                            <span>{{ __('Navigation parent') }}</span>
                            <select wire:model="newPageParent">
                                <option value="">{{ __('Top level') }}</option>
                                @foreach ($parentOptions as $id => $label)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button class="admin-action is-primary" type="submit">
                            <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Add->mini()" />
                            {{ __('Create page') }}
                        </button>
                    </form>
                @elseif ($pagesStagePanel === 'navigation')
                    @php($stageSelected = $this->stageSelectedPage())
                    <nav class="pages-stage__navigation admin-visual-stage__field-scroll"
                        aria-label="Visual website navigation editor">
                        <div class="pages-stage__menu" wire:sort="sortStageSection"
                            wire:sort:group="pages-stage-navigation" wire:sort:group-id="root">
                            @foreach ($stageNavigationSections as $section)
                                <div class="pages-stage__root" wire:key="stage-page-{{ $section['id'] }}"
                                    wire:sort:item="{{ $section['id'] }}">
                                    <div class="pages-stage__entry {{ ($stageSelected['id'] ?? null) === $section['id'] ? 'is-selected' : '' }}">
                                        <button class="admin-drag-handle" type="button"
                                            @unless ($section['is_home']) wire:sort:handle @endunless
                                            @disabled($section['is_home'])
                                            aria-label="Move {{ $section['title'] }}">
                                            <x-filament::icon icon="heroicon-m-bars-3" />
                                        </button>
                                        <button class="pages-stage__label" type="button"
                                            wire:click="focusStagePage({{ $section['id'] }})">
                                            <span>{{ $section['navigation_label'] ?: $section['title'] }}</span>
                                            @if ($section['state'] !== 'published')
                                                <x-filament::icon icon="heroicon-m-eye-slash" class="pages-stage__unpublished" />
                                            @endif
                                        </button>
                                        @if ($section['children'] !== [])
                                            <span class="pages-stage__child-count">{{ count($section['children']) }}</span>
                                        @endif
                                    </div>
                                    @unless ($section['is_home'])
                                        <div class="pages-stage__submenu{{ $section['children'] === [] ? ' pages-stage__submenu--empty' : '' }}"
                                            wire:sort="sortStageSection"
                                            wire:sort:group="pages-stage-navigation"
                                            wire:sort:group-id="{{ $section['id'] }}"
                                            @if ($section['children'] === []) wire:sort:config="{ emptyInsertThreshold: 0 }" @endif>
                                            @foreach ($section['children'] as $child)
                                                <div class="pages-stage__entry pages-stage__entry--child {{ ($stageSelected['id'] ?? null) === $child['id'] ? 'is-selected' : '' }}"
                                                    wire:key="stage-child-{{ $child['id'] }}"
                                                    wire:sort:item="{{ $child['id'] }}">
                                                    <button class="admin-drag-handle" type="button"
                                                        wire:sort:handle aria-label="Move {{ $child['title'] }}">
                                                        <x-filament::icon icon="heroicon-m-bars-3" />
                                                    </button>
                                                    <button class="pages-stage__label" type="button"
                                                        wire:click="focusStagePage({{ $child['id'] }})">
                                                        <span>{{ $child['navigation_label'] ?: $child['title'] }}</span>
                                                        @if ($child['state'] !== 'published')
                                                            <x-filament::icon icon="heroicon-m-eye-slash" class="pages-stage__unpublished" />
                                                        @endif
                                                    </button>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endunless
                                </div>
                            @endforeach
                        </div>
                    </nav>
                @else
                    @php($stageSelected = $this->stageSelectedPage())
                    <div class="admin-stage-workspace admin-visual-stage__field-scroll" aria-label="Page and navigation settings">
                        <div class="admin-stage-workspace__group">
                            <strong>{{ __('Page defaults') }}</strong>
                            <label class="pages-stage__toggle">
                                <input type="checkbox" @checked($stagePagesAutoPublish)
                                    wire:change="setPagesStageAutoPublish($event.target.checked)">
                                {{ __('Auto-publish new pages') }}
                            </label>
                        </div>
                        @if ($stageSelected !== null)
                            <div class="admin-stage-workspace__group">
                                <strong>{{ $stageSelected['navigation_label'] ?: $stageSelected['title'] }}</strong>
                                @if ($stageSelected['is_home'])
                                    <label class="pages-stage__toggle">
                                        <input type="checkbox" @checked($stageSelected['show_in_navigation'])
                                            wire:change="setPagesStageHomeNavigation($event.target.checked)">
                                        {{ __('Show Home in navigation') }}
                                    </label>
                                @else
                                    <label class="admin-stage-workspace__field">
                                        <span>{{ __('Navigation parent') }}</span>
                                        <select wire:change="setPagesStageParent({{ $stageSelected['id'] }}, $event.target.value)">
                                            <option value="" @selected($stageSelected['parent_id'] === null)>{{ __('Top level') }}</option>
                                            @foreach ($parentOptions as $id => $label)
                                                @continue((int) $id === (int) $stageSelected['id'])
                                                <option value="{{ $id }}" @selected((int) $stageSelected['parent_id'] === (int) $id)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                @endif
                            </div>
                        @endif
                    </div>
                @endif
            </div>
            <div class="admin-visual-stage__pane admin-visual-stage__preview-pane">
                <h2 class="admin-section__kicker admin-visual-stage__heading">{{ __('Live preview') }}</h2>
                <x-admin.live-preview
                    :heading="false"
                    :url="$this->pagesStagePreviewUrl()"
                    :frame-title="$this->pagesStagePreviewTitle()"
                    wire:key="pages-live-preview"
                >
                    <x-slot:focusAction>
                        <button class="admin-action admin-stage-tool-icon {{ $pagesPreviewAutoFocus ? 'is-primary' : '' }}"
                            type="button"
                            wire:click="togglePagesPreviewAutoFocus"
                            aria-label="Auto-focus preview to selection"
                            aria-pressed="{{ $pagesPreviewAutoFocus ? 'true' : 'false' }}"
                            title="{{ $pagesPreviewAutoFocus ? 'Auto-focus selection on' : 'Auto-focus selection off' }}">
                            <x-filament::icon icon="heroicon-m-cursor-arrow-rays" />
                        </button>
                    </x-slot:focusAction>
                </x-admin.live-preview>
            </div>
        </section>

        <section class="admin-visual-stage-followup" aria-label="Pages editor">
            <x-admin.controls
                class="admin-task-controls admin-task-controls--pages admin-data-controls--dashboard-responsive"
                aria-label="Page controls"
                metric-grid
                :filter-count="2"
                :search-span="2"
            >
                <x-slot:search>
                    <label class="admin-task-field">
                        <span>{{ __('SEARCH') }}</span>
                        <x-admin.search-input
                            model="search"
                            placeholder="Search pages"
                        />
                    </label>
                </x-slot:search>

                <x-slot:filters>
                    <label class="admin-task-field">
                        <span>{{ __('TYPE') }}</span>
                        <select wire:model.live="typeFilter" aria-label="Filter by page type">
                            <option value="">{{ __('All types') }}</option>
                            @foreach ($typeOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="admin-task-field">
                        <span>{{ __('STATUS') }}</span>
                        <select wire:model.live="statusFilter" aria-label="Filter by page status">
                            <option value="">{{ __('All statuses') }}</option>
                            <option value="published">{{ __('Published') }}</option>
                            <option value="hidden">{{ __('Unpublished') }}</option>
                        </select>
                    </label>
                </x-slot:filters>

                <x-slot:reset>
                    <div class="admin-task-control-group admin-pages__clear">
                        <div class="admin-task-control-actions">
                            <x-admin.clear-filters wire:click="resetFilters" :disabled="! $filtersActive" />
                        </div>
                    </div>
                </x-slot:reset>

                <x-slot:actions>
                    <div class="admin-task-control-group admin-pages__actions">
                        <span class="admin-task-control-label">{{ __('PAGES') }}</span>
                        <div class="admin-task-control-actions admin-editorial-actions">
                            <button class="admin-action admin-action--with-icon" type="button" wire:click="mountAction('pagesSettings')" aria-label="Page settings">
                                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Settings->mini()" class="admin-action__icon" />
                                <span class="admin-action__label">{{ __('Settings') }}</span>
                            </button>
                            <button class="admin-action admin-action--with-icon" type="button" wire:click="mountAction('addPage')" aria-label="Add page">
                                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Add->mini()" class="admin-action__icon" />
                                <span class="admin-action__label">{{ __('Add page') }}</span>
                            </button>
                            <a class="admin-action admin-action--with-icon"
                                href="{{ $this->pagesStagePreviewUrl() }}"
                                target="_blank" rel="noopener noreferrer" aria-label="Preview selected page">
                                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Preview->mini()" class="admin-action__icon" />
                                <span class="admin-action__label">{{ __('Preview') }}</span>
                            </a>
                        </div>
                    </div>
                </x-slot:actions>

                <x-slot:selection>
                    <div class="admin-task-control-group admin-selection admin-pages__selection" x-data="{ open: false }">
                        <div class="admin-selection__anchor">
                            <button
                                class="admin-action admin-selection__trigger"
                                type="button"
                                x-on:click="open = ! open"
                                x-bind:aria-expanded="open"
                                aria-haspopup="menu"
                                aria-label="Selected pages: {{ $selectedCount }}"
                                @disabled($selectedCount === 0)
                            >
                                <x-admin.selection-trigger-label>{{ __('Selected') }}</x-admin.selection-trigger-label>
                            </button>
                            <div class="admin-selection__menu" x-cloak x-show="open" x-on:click.outside="open = false" role="menu">
                                <button class="admin-action" type="button" role="menuitem" wire:click="bulkPublish" @disabled(! $canPublishSelected)>{{ __('Publish selected') }}</button>
                                <button class="admin-action" type="button" role="menuitem" wire:click="bulkUnpublish" @disabled(! $canUnpublishSelected)>{{ __('Unpublish selected') }}</button>
                                <button class="admin-action is-danger" type="button" role="menuitem" wire:click="mountAction('deleteSelectedPages')" x-on:click="open = false" @disabled(! $canDeleteSelected)>{{ __('Delete selected') }}</button>
                            </div>
                        </div>
                        <span class="admin-selection__count" aria-label="{{ $selectedCount }} selected">{{ $selectedCount }}</span>
                    </div>
                </x-slot:selection>
            </x-admin.controls>

            <x-admin.table>
                <div
                    class="admin-hierarchy admin-hierarchy--pages"
                    role="table"
                    aria-label="Pages"
                    data-admin-hierarchy="pages"
                >
                    <div class="admin-hierarchy__header admin-pages__header" role="row">
                        <div class="admin-pages__primary-grid" role="presentation">
                            <span class="admin-hierarchy__ordering-heading" role="columnheader" data-column="position">{{ __('Position') }}</span>
                            <span role="columnheader" data-column="page">{{ __('Name') }}</span>
                        </div>
                        <span class="admin-pages__type" role="columnheader" data-column="page-type">{{ __('Page type') }}</span>
                        <span class="admin-pages__template" role="columnheader" data-column="template">{{ __('Template') }}</span>
                        <div class="admin-pages__utility-grid" role="presentation">
                            <span class="admin-row-actions-heading" role="columnheader" data-column="actions">{{ __('Actions') }}</span>
                            <label class="admin-hierarchy__selection admin-hierarchy__selection--trailing" role="columnheader" data-column="selection">
                                <input
                                    type="checkbox"
                                    aria-label="Select all visible pages"
                                    wire:click.prevent="toggleSelectAll"
                                    x-data="{}"
                                    x-effect="
                                        const visibleIds = $wire.filteredRows.map((row) => Number(row.id));
                                        const selectedIds = $wire.selectedSectionIds.map(Number);
                                        const count = visibleIds.filter((id) => selectedIds.includes(id)).length;
                                        $el.checked = visibleIds.length > 0 && count === visibleIds.length;
                                        $el.indeterminate = count > 0 && count < visibleIds.length;
                                        $el.disabled = visibleIds.length === 0;
                                        $el.setAttribute('aria-checked', $el.indeterminate ? 'mixed' : ($el.checked ? 'true' : 'false'));
                                    "
                                >
                            </label>
                        </div>
                    </div>

                    @if ($sections !== [])
                        <div
                            class="admin-pages__root-rows"
                            role="rowgroup"
                            @if ($reorderEnabled)
                                wire:sort="sortSection"

                                wire:sort:group="site-pages"
                                    wire:sort:group-id="root"
                            @endif
                        >
                            @foreach ($sections as $section)
                                <div class="admin-hierarchy__group" wire:key="site-page-root-{{ $section['id'] }}" @if ($reorderEnabled) wire:sort:item="{{ $section['id'] }}" @endif>
                                    @include('filament.pages.partials.site-section-row', [
                                        'section' => $section,
                                        'reorderEnabled' => $reorderEnabled,
                                        'editableTypeOptions' => $editableTypeOptions,
                                        'journalTemplateOptions' => $journalTemplateOptions,
                                    ])

                                    @if ($section['children'] !== [] || ($reorderEnabled && ! $section['is_home']))
                                        <div class="admin-hierarchy__children{{ $section['children'] === [] && $reorderEnabled ? ' admin-pages__empty-child-parent' : '' }}">
                                            <div
                                                class="admin-hierarchy__children-rows{{ $section['children'] === [] && $reorderEnabled ? ' admin-pages__empty-child-rows' : '' }}"
                                                role="rowgroup"
                                                aria-label="Child pages under {{ $section['title'] }}"
                                                @if ($reorderEnabled && ! $section['is_home'])
                                                    data-drop-target="true"
                                                    wire:sort="sortSection"
                                                    @if ($section['children'] === [])
                                                        wire:sort:config="{ emptyInsertThreshold: 0 }"
                                                    @endif
                                                    wire:sort:group="site-pages"
                                                     wire:sort:group-id="{{ $section['id'] }}"
                                                @endif
                                            >
                                                @foreach ($section['children'] as $child)
                                                    <div wire:key="site-page-child-{{ $child['id'] }}" @if ($reorderEnabled && ! $section['is_home']) wire:sort:item="{{ $child['id'] }}" @endif>
                                                        @include('filament.pages.partials.site-section-row', [
                                                            'section' => $child,
                                                            'reorderEnabled' => $reorderEnabled,
                                                            'editableTypeOptions' => $editableTypeOptions,
                                                            'journalTemplateOptions' => $journalTemplateOptions,
                                                        ])
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="admin-hierarchy__empty" role="row">
                            @if ($metrics['total'] > 0)
                                <x-admin.empty-state title="No matches" minimal>
                                    <x-slot:actions><x-admin.clear-filters label="Clear filters" wire:click="resetFilters" /></x-slot:actions>
                                </x-admin.empty-state>
                            @else
                                <x-admin.empty-state title="Empty" minimal>
                                    <x-slot:actions><button class="admin-action" type="button" wire:click="mountAction('addPage')">{{ __('Add page') }}</button></x-slot:actions>
                                </x-admin.empty-state>
                            @endif
                        </div>
                    @endif
                </div>
            </x-admin.table>

            <x-admin.add-row wire:click="mountAction('addPage')">{{ __('Add page') }}</x-admin.add-row>

            <footer class="admin-pager" aria-label="Pages pagination">
                <x-admin.page-size-picker
                    :value="$perPage"
                    :options="[25, 50, 100]"
                    wire-model="perPage"
                    aria-label="Pages per page"
                />
                <span class="admin-pager__range">{{ $rangeStart }}–{{ $rangeEnd }} of {{ $totalGroups }}</span>
                <div class="admin-toolbar admin-pager__actions">
                    <button class="admin-action" type="button" wire:click="previousPage" @disabled($pageNumber <= 1)>{{ __('Previous') }}</button>
                    <button class="admin-action" type="button" wire:click="nextPage" @disabled($pageNumber >= $lastPage)>{{ __('Next') }}</button>
                </div>
            </footer>
        </section>
    </x-admin.workspace>
</x-filament-panels::page>
