<x-filament-panels::page>
    @php
        $isBlog = $template === \App\Domain\Content\JournalTemplate::Blog->value;
        $entries = $isBlog ? $posts : $exhibitions;
        $selectedIds = collect($isBlog ? $selectedPostIds : $selectedExhibitionIds)
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
        $visibleIds = collect($entries)->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
        $resultStart = $total === 0 ? 0 : (($page - 1) * $pageSize) + 1;
        $resultEnd = $total === 0 ? 0 : min($total, $page * $pageSize);
        $dragEnabled = $this->canDragSort();
        $entryLabel = $isBlog ? 'posts' : 'exhibitions';
        $entryLabelSingular = $isBlog ? 'post' : 'exhibition';
    @endphp

    <x-admin.workspace :title="$journalTitle" class="journal-workspace">
        <x-admin.metrics :columns="6" aria-label="{{ $isBlog ? 'Blog overview' : 'Exhibitions overview' }}">
            @foreach ($metrics as $metric)
                <x-admin.metric :label="$metric['label']" :value="$metric['value']">{{ $metric['description'] }}</x-admin.metric>
            @endforeach
        </x-admin.metrics>

        <x-admin.section class="journal-workspace__entries" aria-label="{{ $isBlog ? 'Blog entries' : 'Exhibition entries' }}">
            <x-admin.controls aria-label="{{ $isBlog ? 'Blog controls' : 'Exhibition controls' }}">
                <x-slot:search>
                    <label class="admin-field admin-control-bar__search">
                        <span class="admin-field__label">Search {{ $entryLabel }}</span>
                        <input
                            type="search"
                            wire:model.live.debounce.300ms="search"
                            placeholder="{{ $isBlog ? 'Title or excerpt' : 'Title, venue, place or date' }}"
                            autocomplete="off"
                        >
                    </label>
                </x-slot:search>

                <x-slot:filters>
                    <label class="admin-field">
                        <span class="admin-field__label">Status</span>
                        <select wire:model.live="statusFilter">
                            <option value="any">Any</option>
                            @if ($isBlog)
                                <option value="draft">Draft</option>
                                <option value="scheduled">Scheduled</option>
                                <option value="published">Published</option>
                                <option value="unpublished">Unpublished</option>
                                <option value="archived">Archived</option>
                            @else
                                <option value="published">Published</option>
                                <option value="unpublished">Unpublished</option>
                            @endif
                        </select>
                    </label>

                    @unless ($isBlog)
                        <label class="admin-field">
                            <span class="admin-field__label">Timing</span>
                            <select wire:model.live="timingFilter">
                                <option value="any">Any</option>
                                <option value="upcoming">Upcoming</option>
                                <option value="current">Current</option>
                                <option value="past">Past</option>
                                <option value="unknown">Unknown</option>
                            </select>
                        </label>
                    @endunless
                </x-slot:filters>

                <x-slot:reset>
                    <div class="admin-control-group">
                        <span class="admin-control-group__label">Filter</span>
                        <div class="admin-control-group__actions">
                            <button class="admin-action" type="button" wire:click="resetFilters">Reset</button>
                        </div>
                    </div>
                </x-slot:reset>

                <x-slot:actions>
                    <div class="admin-control-group">
                        <span class="admin-control-group__label">Journal</span>
                        <div class="admin-control-group__actions">
                            <button class="admin-action" type="button" wire:click="mountAction('journalSettings')">Settings</button>
                            <button class="admin-action" type="button" wire:click="mountAction('{{ $isBlog ? 'addPost' : 'addExhibition' }}')">Add {{ $entryLabelSingular }}</button>
                            @if ($journalPublicUrl)
                                <a class="admin-action" href="{{ $journalPublicUrl }}" target="_blank" rel="noopener">Preview</a>
                            @else
                                <button class="admin-action" type="button" disabled title="Publish this Journal in Pages before previewing it">Preview</button>
                            @endif
                        </div>
                    </div>
                </x-slot:actions>

                <x-slot:selection>
                    <div class="admin-control-group admin-selection" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
                        <span class="admin-control-group__label">Selection</span>
                        <div class="admin-selection__anchor">
                            <button
                                class="admin-action admin-selection__trigger"
                                type="button"
                                x-on:click="open = ! open"
                                x-bind:aria-expanded="open.toString()"
                                aria-haspopup="menu"
                                @disabled($selectedIds === [])
                            >
                                Selected {{ $entryLabel }} <span class="admin-selection__count">{{ count($selectedIds) }}</span>
                            </button>
                            <div class="admin-selection__menu" role="menu" x-show="open" x-cloak x-on:click.outside="open = false">
                                <button class="admin-action" type="button" role="menuitem" wire:click="moveSelectedEntries('up')" x-on:click="open = false">Move selected up</button>
                                <button class="admin-action" type="button" role="menuitem" wire:click="moveSelectedEntries('down')" x-on:click="open = false">Move selected down</button>
                                @if ($isBlog)
                                    <button class="admin-action" type="button" role="menuitem" wire:click="publishSelectedPosts" x-on:click="open = false">Publish selected</button>
                                    <button class="admin-action" type="button" role="menuitem" wire:click="unpublishSelectedPosts" x-on:click="open = false">Unpublish selected</button>
                                    <button class="admin-action" type="button" role="menuitem" wire:click="archiveSelectedPosts" x-on:click="open = false">Archive selected</button>
                                    <button class="admin-action" type="button" role="menuitem" wire:click="restoreSelectedPosts" x-on:click="open = false">Restore selected to draft</button>
                                @endif
                                <button class="admin-action is-danger" type="button" role="menuitem" wire:click="mountAction('{{ $isBlog ? 'deleteSelectedPosts' : 'deleteSelectedExhibitions' }}')" x-on:click="open = false">Delete selected</button>
                            </div>
                        </div>
                    </div>
                </x-slot:selection>
            </x-admin.controls>

            <x-admin.table class="admin-table--data admin-table--ranked">
                <table class="admin-table--six-grid journal-table {{ $isBlog ? 'journal-table--blog' : 'journal-table--exhibitions' }}">
                    <colgroup>
                        <col class="admin-table__col-quarter-unit">
                        <col class="admin-table__col-quarter-unit">
                        @if ($isBlog)
                            <col class="admin-table__col-half-unit">
                            <col class="admin-table__col-one-unit">
                            <col class="admin-table__col-half-unit">
                            <col class="admin-table__col-one-unit">
                            <col class="journal-table__actions--blog">
                        @else
                            <col class="admin-table__col-one-half-units">
                            <col class="admin-table__col-half-unit">
                            <col class="admin-table__col-one-half-units">
                            <col class="admin-table__col-two-units-minus-selection">
                        @endif
                        <col class="admin-table__selection-col">
                    </colgroup>
                    <thead>
                        <tr>
                            <th scope="colgroup" colspan="2" class="admin-table__ordering-heading">Position</th>
                            @if ($isBlog)
                                <th scope="col" class="journal-visual">Image</th>
                            @endif
                            <th scope="col">{{ $isBlog ? 'Post' : 'Exhibition' }}</th>
                            @if ($isBlog)
                                <th scope="col">Status</th>
                                <th scope="col">Publication</th>
                            @else
                                <th scope="col">Timing</th>
                                <th scope="col">Schedule</th>
                            @endif
                            <th scope="col" class="admin-table__actions">Actions</th>
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
                                @if ($isBlog)
                                    <td class="journal-visual">
                                        <div class="journal-visual__thumbnail">
                                            @if ($entry['thumbnail_url'])
                                                <img src="{{ $entry['thumbnail_url'] }}" alt="" loading="lazy" decoding="async">
                                            @else
                                                <span aria-label="No image">—</span>
                                            @endif
                                        </div>
                                    </td>
                                @endif
                                <td class="admin-table__identity">
                                    <strong>{{ $entry['title'] }}</strong>
                                    @if ($isBlog && $entry['excerpt'])
                                        <small>{{ $entry['excerpt'] }}</small>
                                    @elseif (! $isBlog && $entry['location'])
                                        <small>{{ $entry['location'] }}</small>
                                    @endif
                                </td>
                                @if ($isBlog)
                                    <td><span class="journal-state is-{{ $entry['state'] }}">{{ ucfirst($entry['state']) }}</span></td>
                                    <td class="journal-publication">{{ $entry['publication'] }}</td>
                                @else
                                    <td><span class="journal-timing is-{{ $entry['timing'] }}">{{ ucfirst($entry['timing']) }}</span></td>
                                    <td class="journal-schedule">
                                        @if ($entry['vernissage'])<em>Vernissage: {{ $entry['vernissage'] }}</em>@endif
                                        @if ($entry['date_text'] !== '')<span>{{ $entry['date_text'] }}</span>@endif
                                    </td>
                                @endif
                                <td class="admin-table__actions">
                                    <x-admin.toolbar class="admin-row-actions admin-row-actions--canonical journal-row-actions {{ $isBlog ? 'journal-row-actions--blog' : 'journal-row-actions--exhibitions' }}">
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
                                                    wire:click="restorePostDraft({{ $entry['id'] }})"
                                                />
                                            @elseif ($entry['state'] === 'archived')
                                                <x-admin.row-action
                                                    :action="\App\Filament\Support\AdminRowAction::Restore"
                                                    wire:click="restorePostDraft({{ $entry['id'] }})"
                                                />
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

                                            @if (in_array($entry['state'], ['draft', 'unpublished', 'published'], true))
                                                <x-admin.row-action
                                                    :action="\App\Filament\Support\AdminRowAction::Archive"
                                                    wire:click="archivePost({{ $entry['id'] }})"
                                                />
                                            @else
                                                <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::Archive" disabled />
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
                                        <input type="checkbox" wire:click="togglePostSelection({{ $entry['id'] }})" @checked(in_array($entry['id'], $selectedIds, true)) aria-label="Toggle selection for {{ $entry['title'] }}">
                                    @else
                                        <input type="checkbox" wire:click="toggleExhibitionSelection({{ $entry['id'] }})" @checked(in_array($entry['id'], $selectedIds, true)) aria-label="Toggle selection for {{ $entry['title'] }}">
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="admin-table__empty-cell" colspan="{{ $isBlog ? 8 : 7 }}">
                                    @if ($unfilteredEntryCount > 0)
                                        <x-admin.empty-state :title="'No matching '.$entryLabel" minimal>
                                            <x-slot:actions><button class="admin-action" type="button" wire:click="resetFilters">Clear filters</button></x-slot:actions>
                                        </x-admin.empty-state>
                                    @else
                                        <x-admin.empty-state :title="'No '.$entryLabel.' added to this Journal'" minimal>
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
                    <button class="admin-action" type="button" wire:click="previousPage" @disabled($page <= 1)>Previous</button>
                    <button class="admin-action" type="button" wire:click="nextPage" @disabled($page >= $pages)>Next</button>
                </x-admin.toolbar>
            </footer>
        </x-admin.section>
    </x-admin.workspace>
</x-filament-panels::page>