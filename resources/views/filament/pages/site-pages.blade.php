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
        <x-admin.metrics :columns="6">
            <x-admin.metric label="Total pages" :value="$metrics['total']">All site sections</x-admin.metric>
            <x-admin.metric label="Published" :value="$metrics['published']">Public now</x-admin.metric>
            <x-admin.metric label="Unpublished" :value="$metrics['unpublished']">Not public</x-admin.metric>
            <x-admin.metric label="Top level" :value="$metrics['top_level']">Root pages</x-admin.metric>
            <x-admin.metric label="Child pages" :value="$metrics['children']">Nested pages</x-admin.metric>
            <x-admin.metric label="In navigation" :value="$metrics['navigation']">Menu visible</x-admin.metric>
        </x-admin.metrics>

        <section aria-label="Pages editor">
            <x-admin.controls class="admin-task-controls admin-task-controls--pages" aria-label="Page controls">
                <x-slot:search>
                    <label class="admin-task-field">
                        <span>SEARCH</span>
                        <input
                            type="search"
                            value="{{ $search }}"
                            placeholder="Search pages"
                            wire:model.live.debounce.300ms="search"
                        >
                    </label>
                </x-slot:search>

                <x-slot:filters>
                    <label class="admin-task-field">
                        <span>TYPE</span>
                        <select wire:model.live="typeFilter" aria-label="Filter by page type">
                            <option value="">All types</option>
                            @foreach ($typeOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="admin-task-field">
                        <span>STATUS</span>
                        <select wire:model.live="statusFilter" aria-label="Filter by page status">
                            <option value="">All statuses</option>
                            <option value="published">Published</option>
                            <option value="hidden">Unpublished</option>
                        </select>
                    </label>
                </x-slot:filters>

                <x-slot:reset>
                    <div class="admin-task-control-group">
                        <span class="admin-task-control-label">FILTER</span>
                        <div class="admin-task-control-actions">
                            <button class="admin-action" type="button" wire:click="resetFilters" @disabled(! $filtersActive)>Reset</button>
                        </div>
                    </div>
                </x-slot:reset>

                <x-slot:actions>
                    <div class="admin-task-control-group">
                        <span class="admin-task-control-label">PAGES</span>
                        <div class="admin-task-control-actions">
                            <button
                                class="admin-action"
                                type="button"
                                disabled
                                title="No collection-wide Pages settings are defined by the current domain contract."
                            >Settings</button>
                            <button class="admin-action" type="button" wire:click="startAddingPage">Add page</button>
                        </div>
                    </div>
                </x-slot:actions>

                <x-slot:selection>
                    <div class="admin-task-control-group admin-selection" x-data="{ open: false }">
                        <span class="admin-task-control-label">SELECTION</span>
                        <div class="admin-selection__anchor">
                            <button
                                class="admin-action admin-selection__trigger"
                                type="button"
                                x-on:click="open = ! open"
                                x-bind:aria-expanded="open"
                                aria-haspopup="menu"
                            >
                                <span>Selected</span>
                                <span class="admin-selection__count">{{ $selectedCount }}</span>
                            </button>
                            <div class="admin-selection__menu" x-cloak x-show="open" x-on:click.outside="open = false" role="menu">
                                <button class="admin-action" type="button" role="menuitem" wire:click="bulkPublish" @disabled($selectedCount === 0)>Publish selected</button>
                                <button class="admin-action" type="button" role="menuitem" wire:click="bulkUnpublish" @disabled($selectedCount === 0)>Unpublish selected</button>
                                <button
                                    class="admin-action is-danger"
                                    type="button"
                                    role="menuitem"
                                    wire:click="bulkDelete"
                                    wire:confirm="Delete the selected pages that satisfy their safety rules?"
                                    @disabled($selectedCount === 0)
                                >Delete selected</button>
                            </div>
                        </div>
                    </div>
                </x-slot:selection>
            </x-admin.controls>

            @if (! $reorderEnabled)
                <p class="admin-task-note">
                    {{ $filtersActive
                        ? 'Reordering is disabled while Search, Type or Status filters are active.'
                        : 'Reordering is disabled while only part of the canonical root-page order is visible.' }}
                </p>
            @endif

            <x-admin.table>
                <div class="admin-hierarchy admin-hierarchy--pages" role="table" aria-label="Pages">
                    <div class="admin-hierarchy__header" role="row">
                        <label class="admin-hierarchy__selection" role="columnheader" data-column="selection">
                            <input
                                type="checkbox"
                                aria-label="Select all visible pages"
                                aria-checked="{{ $selectionIndeterminate ? 'mixed' : ($allVisibleSelected ? 'true' : 'false') }}"
                                wire:click="toggleSelectAll"
                                @checked($allVisibleSelected)
                                x-data
                                x-effect="$el.indeterminate = {{ $selectionIndeterminate ? 'true' : 'false' }}"
                            >
                            <span class="sr-only">Selection</span>
                        </label>
                        <span role="columnheader" data-column="drag"><span class="sr-only">Drag</span></span>
                        <span role="columnheader" data-column="position">Position</span>
                        <span role="columnheader" data-column="page-type">Page type</span>
                        <span role="columnheader" data-column="page">Page</span>
                        <span role="columnheader" data-column="template">Template</span>
                        <span role="columnheader" data-column="status">Status</span>
                        <span role="columnheader" data-column="actions">Actions</span>
                    </div>

                    @if ($sections !== [])
                        <div
                            role="rowgroup"
                            @if ($reorderEnabled)
                                wire:sort="sortSection"
                                wire:sort:group="site-pages"
                                wire:sort:group-id="root"
                            @endif
                        >
                            @foreach ($sections as $section)
                                <div
                                    class="admin-hierarchy__group"
                                    wire:key="site-page-root-{{ $section['id'] }}"
                                    @if ($reorderEnabled) wire:sort:item="{{ $section['id'] }}" @endif
                                >
                                    @include('filament.pages.partials.site-section-row', [
                                        'section' => $section,
                                        'reorderEnabled' => $reorderEnabled,
                                        'editableTypeOptions' => $editableTypeOptions,
                                        'journalTemplateOptions' => $journalTemplateOptions,
                                    ])

                                    @if ($section['children'] !== [] || $reorderEnabled)
                                        <div class="admin-hierarchy__children">
                                            <div
                                                class="admin-hierarchy__children-rows"
                                                role="rowgroup"
                                                aria-label="Child pages under {{ $section['title'] }}"
                                                @if ($reorderEnabled)
                                                    data-drop-target="true"
                                                    wire:sort="sortSection"
                                                    wire:sort:group="site-pages"
                                                    wire:sort:group-id="{{ $section['id'] }}"
                                                @endif
                                            >
                                                @foreach ($section['children'] as $child)
                                                    <div
                                                        wire:key="site-page-child-{{ $child['id'] }}"
                                                        @if ($reorderEnabled) wire:sort:item="{{ $child['id'] }}" @endif
                                                    >
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
                            {{ $filtersActive ? 'No pages match the current filters.' : 'No pages exist yet.' }}
                        </div>
                    @endif
                </div>
            </x-admin.table>

            <div class="admin-bottom-add">
                <button class="admin-action" type="button" wire:click="startAddingPage" aria-expanded="{{ $addingPage ? 'true' : 'false' }}">
                    <span class="admin-bottom-add__plus" aria-hidden="true">+</span>
                    <span class="admin-bottom-add__label">Add page</span>
                </button>
            </div>

            @if ($addingPage)
                <form class="admin-task-form" wire:submit="createPage">
                    <label class="admin-task-field">
                        <span>PAGE TYPE</span>
                        <select wire:model.live="newPageType">
                            @foreach (SiteNodeType::creatableOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="admin-task-field">
                        <span>TITLE</span>
                        <input type="text" maxlength="160" wire:model="newPageTitle" required>
                    </label>

                    @if (SiteNodeType::tryFrom($newPageType)?->requiresSlug())
                        <label class="admin-task-field">
                            <span>PUBLIC SLUG</span>
                            <input type="text" maxlength="80" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" wire:model="newPageSlug" required>
                        </label>
                    @endif

                    @if ($newPageType === SiteNodeType::Journal->value)
                        <label class="admin-task-field">
                            <span>TEMPLATE</span>
                            <select wire:model="newJournalTemplate">
                                @foreach ($journalTemplateOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif

                    <div class="admin-task-form__actions">
                        <button class="admin-action is-primary" type="submit">Create</button>
                        <button class="admin-action" type="button" wire:click="cancelAddingPage">Cancel</button>
                    </div>
                </form>
            @endif

            <footer class="admin-pager" aria-label="Pages pagination">
                <label class="admin-pager__size">
                    <span>Per page</span>
                    <select wire:model.live.number="perPage" aria-label="Pages per page">
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </label>
                <span class="admin-pager__range">{{ $rangeStart }}–{{ $rangeEnd }} of {{ $totalGroups }}</span>
                <div class="admin-toolbar admin-pager__actions">
                    <button class="admin-action" type="button" wire:click="previousPage" @disabled($pageNumber <= 1)>Previous</button>
                    <button class="admin-action" type="button" wire:click="nextPage" @disabled($pageNumber >= $lastPage)>Next</button>
                </div>
            </footer>
        </section>
    </x-admin.workspace>
</x-filament-panels::page>
