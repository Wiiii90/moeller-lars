@php
    use App\Domain\Content\JournalTemplate;
    use App\Domain\Content\SiteSectionType;

    $typeOptions = collect(SiteSectionType::cases())->mapWithKeys(fn (SiteSectionType $type): array => [$type->value => $type->compactLabel()])->all();
    $editableTypeOptions = SiteSectionType::compactOptions();
    $journalTemplateOptions = JournalTemplate::options();
    $selectedCount = count($selectedSectionIds);
@endphp

<x-filament-panels::page>
    <x-admin.workspace title="Pages" class="admin-pages-workspace">
        <x-admin.metrics :columns="6" class="pages-status-metrics admin-metrics--six-state">
            <x-admin.metric class="pages-metric pages-metric--total" label="Total pages" :value="$metrics['total']">All site sections</x-admin.metric>
            <x-admin.metric class="pages-metric pages-metric--published" label="Published" :value="$metrics['published']">Public now</x-admin.metric>
            <x-admin.metric class="pages-metric pages-metric--unpublished" label="Unpublished" :value="$metrics['unpublished']">Not public</x-admin.metric>
            <x-admin.metric class="pages-metric pages-metric--top-level" label="Top level" :value="$metrics['top_level']">Root pages</x-admin.metric>
            <x-admin.metric class="pages-metric pages-metric--children" label="Child pages" :value="$metrics['children']">Nested pages</x-admin.metric>
            <x-admin.metric class="pages-metric pages-metric--navigation" label="In navigation" :value="$metrics['navigation']">Visible menu items</x-admin.metric>
            <x-admin.metric class="pages-metric pages-metric--galleries" label="Galleries" :value="$metrics['galleries']">Gallery pages</x-admin.metric>
        </x-admin.metrics>

        <section aria-label="Pages editor">
            <x-admin.controls
                class="admin-task-controls admin-task-controls--pages admin-data-controls--dashboard-responsive"
                aria-label="Page controls"
                metric-grid
                :filter-count="2"
                :search-span="2"
            >
                <x-slot:search>
                    <label class="admin-task-field">
                        <span>SEARCH</span>
                        <x-admin.search-input
                            model="search"
                            placeholder="Search pages"
                        />
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
                    <div class="admin-task-control-group admin-pages__clear">
                        <div class="admin-task-control-actions">
                            <x-admin.clear-filters wire:click="resetFilters" :disabled="! $filtersActive" />
                        </div>
                    </div>
                </x-slot:reset>

                <x-slot:actions>
                    <div class="admin-task-control-group admin-pages__actions">
                        <span class="admin-task-control-label">PAGES</span>
                        <div class="admin-task-control-actions admin-editorial-actions">
                            <button class="admin-action admin-action--with-icon" type="button" wire:click="mountAction('pagesSettings')" aria-label="Page settings">
                                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Settings->mini()" class="admin-action__icon" />
                                <span class="admin-action__label">Settings</span>
                            </button>
                            <button class="admin-action admin-action--with-icon" type="button" wire:click="mountAction('addPage')" aria-label="Add page">
                                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Pages->mini()" class="admin-action__icon" />
                                <span class="admin-action__label">Add page</span>
                            </button>
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
                                <x-admin.selection-trigger-label>Selected</x-admin.selection-trigger-label>
                            </button>
                            <div class="admin-selection__menu" x-cloak x-show="open" x-on:click.outside="open = false" role="menu">
                                <button class="admin-action" type="button" role="menuitem" wire:click="bulkPublish" @disabled($selectedCount === 0)>Publish selected</button>
                                <button class="admin-action" type="button" role="menuitem" wire:click="bulkUnpublish" @disabled($selectedCount === 0)>Unpublish selected</button>
                                <button class="admin-action is-danger" type="button" role="menuitem" wire:click="mountAction('deleteSelectedPages')" x-on:click="open = false" @disabled($selectedCount === 0)>Delete selected</button>
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
                        @if ($reorderEnabled && $nestedNavigationEnabled)
                            <div class="admin-pages__root-drop" data-pages-drop="root" role="button" aria-label="Move dragged child page to top level"><span>Drop to top level</span></div>
                        @endif
                        <div class="admin-pages__primary-grid" role="presentation">
                            <span class="admin-hierarchy__ordering-heading" role="columnheader" data-column="position">Position</span>
                            <span role="columnheader" data-column="page">Name</span>
                        </div>
                        <span class="admin-pages__type" role="columnheader" data-column="page-type">Page type</span>
                        <span class="admin-pages__template" role="columnheader" data-column="template">Template</span>
                        <div class="admin-pages__utility-grid" role="presentation">
                            <span class="admin-row-actions-heading" role="columnheader" data-column="actions">Actions</span>
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

                                wire:sort:group="site-pages-root"
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

                                    @if ($section['children'] !== [] || ($reorderEnabled && $nestedNavigationEnabled))
                                        <div class="admin-hierarchy__children">
                                            <div
                                                class="admin-hierarchy__children-rows"
                                                role="rowgroup"
                                                aria-label="Child pages under {{ $section['title'] }}"
                                                @if ($reorderEnabled && $nestedNavigationEnabled)
                                                    data-drop-target="true"
                                                    wire:sort="sortSection"

                                                    wire:sort:group="site-pages-{{ $section['id'] }}"
                                                     wire:sort:group-id="{{ $section['id'] }}"
                                                @endif
                                            >
                                                @foreach ($section['children'] as $child)
                                                    <div wire:key="site-page-child-{{ $child['id'] }}" @if ($reorderEnabled) wire:sort:item="{{ $child['id'] }}" @endif>
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
                                <x-admin.empty-state title="No matching pages" minimal>
                                    <x-slot:actions><button class="admin-action" type="button" wire:click="resetFilters">Clear filters</button></x-slot:actions>
                                </x-admin.empty-state>
                            @else
                                <x-admin.empty-state title="No pages yet" minimal>
                                    <x-slot:actions><button class="admin-action" type="button" wire:click="mountAction('addPage')">Add page</button></x-slot:actions>
                                </x-admin.empty-state>
                            @endif
                        </div>
                    @endif
                </div>
            </x-admin.table>

            <x-admin.add-row wire:click="mountAction('addPage')">Add page</x-admin.add-row>

            <footer class="admin-pager" aria-label="Pages pagination">
                <x-admin.page-size-picker
                    :value="$perPage"
                    :options="[25, 50, 100]"
                    wire-model="perPage"
                    aria-label="Pages per page"
                />
                <span class="admin-pager__range">{{ $rangeStart }}–{{ $rangeEnd }} of {{ $totalGroups }}</span>
                <div class="admin-toolbar admin-pager__actions">
                    <button class="admin-action" type="button" wire:click="previousPage" @disabled($pageNumber <= 1)>Previous</button>
                    <button class="admin-action" type="button" wire:click="nextPage" @disabled($pageNumber >= $lastPage)>Next</button>
                </div>
            </footer>
        </section>
    </x-admin.workspace>
</x-filament-panels::page>
