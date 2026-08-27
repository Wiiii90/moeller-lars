@php
    use App\Domain\Content\JournalTemplate;
    use App\Domain\Content\SiteNodeType;

    $typeOptions = collect(SiteNodeType::cases())->mapWithKeys(fn (SiteNodeType $type): array => [$type->value => $type->label()])->all();
    $editableTypeOptions = SiteNodeType::editableOptions();
    $journalTemplateOptions = JournalTemplate::options();
    $selectedCount = count($selectedSectionIds);
@endphp

<x-filament-panels::page>
    <x-admin.workspace title="Pages">
        <section class="pages-browser" aria-label="Pages editor">
            <div class="pages-controls" aria-label="Page controls">
                <label class="pages-control pages-control--search">
                    <span>SEARCH</span>
                    <input
                        type="search"
                        value="{{ $search }}"
                        placeholder="Search pages"
                        wire:model.live.debounce.300ms="search"
                    >
                </label>

                <label class="pages-control">
                    <span>TYPE</span>
                    <select wire:model.live="typeFilter" aria-label="Filter by page type">
                        <option value="">All types</option>
                        @foreach ($typeOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="pages-control">
                    <span>STATUS</span>
                    <select wire:model.live="statusFilter" aria-label="Filter by page status">
                        <option value="">All statuses</option>
                        <option value="published">Published</option>
                        <option value="hidden">Unpublished</option>
                    </select>
                </label>

                <div class="pages-control pages-control--reset">
                    <span>FILTER</span>
                    <button class="pages-text-action" type="button" wire:click="resetFilters" @disabled(! $filtersActive)>Reset</button>
                </div>

                <div class="pages-control pages-selection-actions" aria-label="Selection actions">
                    <span>SELECTION</span>
                    <div class="pages-selection-actions__row">
                        <strong>{{ $selectedCount }}</strong>
                        @if ($selectedCount > 0)
                            <button class="pages-text-action" type="button" wire:click="bulkPublish">Publish</button>
                            <button class="pages-text-action" type="button" wire:click="bulkUnpublish">Unpublish</button>
                            <button class="pages-text-action" type="button" wire:click="bulkDelete" wire:confirm="Delete the selected pages that satisfy their safety rules?">Delete</button>
                        @else
                            <span class="pages-muted">selected</span>
                        @endif
                    </div>
                </div>
            </div>

            @if ($filtersActive)
                <p class="pages-reorder-note">Reordering is disabled while Search, Type or Status filters are active.</p>
            @endif

            <div class="pages-table-shell">
                <div class="pages-table" role="table" aria-label="Pages">
                    <div class="pages-table__header" role="row">
                        <div role="columnheader" data-column="selection">
                            <input
                                type="checkbox"
                                aria-label="Select all pages"
                                aria-checked="{{ $selectionIndeterminate ? 'mixed' : ($allVisibleSelected ? 'true' : 'false') }}"
                                wire:click="toggleSelectAll"
                                @checked($allVisibleSelected)
                                x-data
                                x-effect="$el.indeterminate = {{ $selectionIndeterminate ? 'true' : 'false' }}"
                            >
                            <span class="sr-only">Selection</span>
                        </div>
                        <div role="columnheader" data-column="drag">Drag</div>
                        <div role="columnheader" data-column="position">Position</div>
                        <div role="columnheader" data-column="page-type">Page type</div>
                        <div role="columnheader" data-column="page">Page</div>
                        <div role="columnheader" data-column="template">Template</div>
                        <div role="columnheader" data-column="status">Status</div>
                        <div role="columnheader" data-column="navigation">Navigation</div>
                        <div role="columnheader" data-column="actions">Actions</div>
                    </div>

                    @if (! $filtersActive)
                        <div
                            class="pages-sort-group pages-sort-group--root"
                            role="rowgroup"
                            wire:sort="sortSection"
                            wire:sort:group="site-pages"
                            wire:sort:group-id="root"
                        >
                            @foreach ($sections as $section)
                                <div
                                    class="pages-sort-block"
                                    wire:key="site-page-root-{{ $section['id'] }}"
                                    wire:sort:item="{{ $section['id'] }}"
                                >
                                    @include('filament.pages.partials.site-section-row', [
                                        'section' => $section,
                                        'reorderEnabled' => true,
                                        'editableTypeOptions' => $editableTypeOptions,
                                        'journalTemplateOptions' => $journalTemplateOptions,
                                    ])

                                    <div
                                        class="pages-sort-group pages-sort-group--children"
                                        role="rowgroup"
                                        aria-label="Child pages under {{ $section['title'] }}"
                                        wire:sort="sortSection"
                                        wire:sort:group="site-pages"
                                        wire:sort:group-id="{{ $section['id'] }}"
                                    >
                                        @foreach ($section['children'] as $child)
                                            <div
                                                wire:key="site-page-child-{{ $child['id'] }}"
                                                wire:sort:item="{{ $child['id'] }}"
                                            >
                                                @include('filament.pages.partials.site-section-row', [
                                                    'section' => $child,
                                                    'reorderEnabled' => true,
                                                    'editableTypeOptions' => $editableTypeOptions,
                                                    'journalTemplateOptions' => $journalTemplateOptions,
                                                ])
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="pages-filtered-rows" role="rowgroup">
                            @foreach ($filteredRows as $section)
                                <div wire:key="site-page-filtered-{{ $section['id'] }}">
                                    @include('filament.pages.partials.site-section-row', [
                                        'section' => $section,
                                        'reorderEnabled' => false,
                                        'editableTypeOptions' => $editableTypeOptions,
                                        'journalTemplateOptions' => $journalTemplateOptions,
                                    ])
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if ($filteredRows === [])
                        <div class="pages-empty-row" role="row">
                            <span>{{ $filtersActive ? 'No pages match the current filters.' : 'No pages exist yet.' }}</span>
                        </div>
                    @endif
                </div>
            </div>

            <div class="pages-add-row">
                <button class="pages-add-trigger" type="button" wire:click="startAddingPage" aria-expanded="{{ $addingPage ? 'true' : 'false' }}">
                    <span class="pages-add-plus" aria-hidden="true">+</span>
                    <span>Add page</span>
                </button>
            </div>

            @if ($addingPage)
                <form class="pages-add-form" wire:submit="createPage">
                    <label class="pages-control">
                        <span>PAGE TYPE</span>
                        <select wire:model.live="newPageType">
                            @foreach (SiteNodeType::creatableOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="pages-control pages-add-form__title">
                        <span>TITLE</span>
                        <input type="text" maxlength="160" wire:model="newPageTitle" required>
                    </label>

                    @if (SiteNodeType::tryFrom($newPageType)?->requiresSlug())
                        <label class="pages-control pages-add-form__slug">
                            <span>PUBLIC SLUG</span>
                            <input type="text" maxlength="80" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" wire:model="newPageSlug" required>
                        </label>
                    @endif

                    @if ($newPageType === SiteNodeType::Journal->value)
                        <label class="pages-control">
                            <span>TEMPLATE</span>
                            <select wire:model="newJournalTemplate">
                                @foreach ($journalTemplateOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif

                    <div class="pages-add-form__actions">
                        <button class="pages-text-action" type="submit">Create</button>
                        <button class="pages-text-action" type="button" wire:click="cancelAddingPage">Cancel</button>
                    </div>
                </form>
            @endif
        </section>

        <style>
            .pages-browser {
                --pages-grid: 2.75rem 2.75rem 4.75rem 11rem minmax(13rem, 1fr) 10rem 7.5rem 7.5rem 29rem;
                --pages-border: rgb(148 163 184 / 0.24);
                --pages-muted: rgb(100 116 139);
            }

            .dark .pages-browser {
                --pages-border: rgb(148 163 184 / 0.2);
                --pages-muted: rgb(148 163 184);
            }

            .pages-controls {
                display: grid;
                grid-template-columns: minmax(15rem, 1.5fr) minmax(9rem, .8fr) minmax(9rem, .8fr) minmax(6rem, .5fr) minmax(19rem, 1.2fr);
                gap: 1rem;
                align-items: end;
                padding: .25rem 0 1rem;
            }

            .pages-control {
                display: grid;
                gap: .35rem;
                min-width: 0;
            }

            .pages-control > span:first-child {
                font-size: .68rem;
                font-weight: 650;
                letter-spacing: .09em;
                color: var(--pages-muted);
            }

            .pages-control input,
            .pages-control select,
            .pages-type-select,
            .pages-template-select {
                width: 100%;
                min-height: 2.15rem;
                border: 1px solid var(--pages-border);
                border-radius: .25rem;
                background: transparent;
                padding: .3rem .5rem;
                color: inherit;
                font: inherit;
            }

            .pages-control--reset {
                align-content: end;
            }

            .pages-text-action {
                width: max-content;
                border: 0;
                background: transparent;
                padding: .15rem 0;
                color: inherit;
                font: inherit;
                font-weight: 600;
                text-decoration: underline;
                text-decoration-color: transparent;
                text-underline-offset: .2rem;
                cursor: pointer;
            }

            .pages-text-action:hover:not(:disabled),
            .pages-text-action:focus-visible:not(:disabled) {
                text-decoration-color: currentColor;
            }

            .pages-text-action:disabled {
                color: var(--pages-muted);
                cursor: default;
            }

            .pages-selection-actions__row {
                display: flex;
                min-height: 2.15rem;
                align-items: center;
                gap: .75rem;
                white-space: nowrap;
            }

            .pages-muted,
            .pages-reorder-note {
                color: var(--pages-muted);
            }

            .pages-reorder-note {
                margin: .7rem 0 0;
                font-size: .78rem;
            }

            .pages-table-shell {
                overflow-x: auto;
            }

            .pages-table {
                min-width: 96rem;
            }

            .pages-table__header,
            .pages-row {
                display: grid;
                grid-template-columns: var(--pages-grid);
                column-gap: .7rem;
                align-items: center;
            }

            .pages-table__header {
                min-height: 2.8rem;
                border-bottom: 1px solid var(--pages-border);
                color: var(--pages-muted);
                font-size: .69rem;
                font-weight: 700;
                letter-spacing: .075em;
                text-transform: uppercase;
            }

            .pages-row {
                min-height: 3.75rem;
                border-bottom: 1px solid var(--pages-border);
                font-size: .86rem;
            }

            .pages-row > [role="cell"],
            .pages-table__header > [role="columnheader"] {
                min-width: 0;
            }

            .pages-page-cell {
                display: grid;
                gap: .12rem;
            }

            .pages-page-cell small {
                color: var(--pages-muted);
                font-size: .74rem;
            }

            .pages-position-box {
                display: inline-grid;
                width: 3.35rem;
                min-width: 3.35rem;
                height: 2rem;
                place-items: center;
                border: 1px solid var(--pages-border);
                border-radius: .2rem;
                font-variant-numeric: tabular-nums;
            }

            .pages-drag-handle {
                display: inline-grid;
                width: 2rem;
                height: 2rem;
                place-items: center;
                border: 0;
                background: transparent;
                color: var(--pages-muted);
                cursor: grab;
                font-size: 1rem;
                letter-spacing: -.15em;
            }

            .pages-drag-placeholder {
                color: var(--pages-muted);
            }

            .pages-actions {
                display: grid;
                grid-template-columns: 4.5rem 6.75rem 5.75rem 2.25rem 2.25rem 4.75rem;
                justify-content: start;
                align-items: center;
                text-align: left;
            }

            .pages-actions__slot {
                min-width: 0;
                text-align: left;
            }

            .pages-action-link,
            .pages-action-button {
                border: 0;
                background: transparent;
                padding: .2rem 0;
                color: inherit;
                font: inherit;
                font-weight: 600;
                text-decoration: none;
                cursor: pointer;
            }

            .pages-action-link:hover,
            .pages-action-link:focus-visible,
            .pages-action-button:hover:not(:disabled),
            .pages-action-button:focus-visible:not(:disabled) {
                text-decoration: underline;
                text-underline-offset: .2rem;
            }

            .pages-action-button:disabled {
                color: var(--pages-muted);
                cursor: default;
            }

            .pages-empty-row {
                min-height: 4rem;
                display: flex;
                align-items: center;
                border-bottom: 1px solid var(--pages-border);
                color: var(--pages-muted);
            }

            .pages-add-row {
                padding-top: .85rem;
            }

            .pages-add-trigger {
                display: inline-flex;
                align-items: center;
                gap: .55rem;
                border: 0;
                background: transparent;
                padding: 0;
                color: inherit;
                font: inherit;
                font-weight: 600;
                cursor: pointer;
            }

            .pages-add-plus {
                display: inline-grid;
                width: 1.8rem;
                height: 1.8rem;
                place-items: center;
                border: 1px solid var(--pages-border);
                border-radius: .18rem;
                font-size: 1.1rem;
                font-weight: 400;
            }

            .pages-add-form {
                display: grid;
                grid-template-columns: minmax(10rem, .8fr) minmax(14rem, 1.3fr) minmax(12rem, 1fr) minmax(9rem, .7fr) auto;
                gap: 1rem;
                align-items: end;
                padding: 1rem 0 0;
                max-width: 70rem;
            }

            .pages-add-form__actions {
                display: flex;
                gap: .75rem;
                align-items: center;
                min-height: 2.15rem;
            }

            @media (max-width: 1100px) {
                .pages-controls {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }

                .pages-selection-actions {
                    grid-column: 1 / -1;
                }

                .pages-add-form {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }
        </style>
    </x-admin.workspace>
</x-filament-panels::page>
