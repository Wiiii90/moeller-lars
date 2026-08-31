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

            <x-admin.table class="admin-table--data">
                <table>
                    <thead>
                        <tr>
                            <th scope="col" class="admin-table__selection">
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
                            <th scope="col" class="admin-table__drag">Drag</th>
                            <th scope="col" class="admin-table__position">Position</th>
                            <th scope="col" class="journal-visual">Image</th>
                            <th scope="col">{{ $isBlog ? 'Post' : 'Exhibition' }}</th>
                            <th scope="col">Status</th>
                            @if ($isBlog)
                                <th scope="col">Publication</th>
                            @else
                                <th scope="col">Timing</th>
                                <th scope="col">Schedule</th>
                            @endif
                            <th scope="col" class="admin-table__actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody @if ($dragEnabled) wire:sort="{{ $isBlog ? 'sortPost' : 'sortExhibition' }}" @endif>
                        @foreach ($entries as $entry)
                            <tr
                                class="{{ in_array($entry['id'], $selectedIds, true) ? 'is-selected' : '' }}"
                                wire:key="journal-{{ $template }}-{{ $entry['id'] }}"
                                @if ($dragEnabled) wire:sort:item="{{ $entry['id'] }}" @endif
                            >
                                <td class="admin-table__selection">
                                    @if ($isBlog)
                                        <input type="checkbox" wire:click="togglePostSelection({{ $entry['id'] }})" @checked(in_array($entry['id'], $selectedIds, true)) aria-label="Toggle selection for {{ $entry['title'] }}">
                                    @else
                                        <input type="checkbox" wire:click="toggleExhibitionSelection({{ $entry['id'] }})" @checked(in_array($entry['id'], $selectedIds, true)) aria-label="Toggle selection for {{ $entry['title'] }}">
                                    @endif
                                </td>
                                <td class="admin-table__drag">
                                    <button
                                        class="admin-action admin-order-action"
                                        type="button"
                                        @if ($dragEnabled) wire:sort:handle title="Drag to reorder" @else disabled title="Drag reorder is available only with no search/filter and all entries on one page" @endif
                                        aria-label="Drag {{ $entry['title'] }} to reorder"
                                    >⠿</button>
                                </td>
                                <td class="admin-table__position"><span class="admin-position">{{ $entry['rank'] }}</span></td>
                                <td class="journal-visual">
                                    <div class="journal-visual__thumbnail">
                                        @if ($entry['thumbnail_url'])
                                            <img src="{{ $entry['thumbnail_url'] }}" alt="" loading="lazy" decoding="async">
                                        @else
                                            <span aria-label="No image">—</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="admin-table__identity">
                                    <strong>{{ $entry['title'] }}</strong>
                                    @if ($isBlog && $entry['excerpt'])
                                        <small>{{ $entry['excerpt'] }}</small>
                                    @elseif (! $isBlog && $entry['location'])
                                        <small>{{ $entry['location'] }}</small>
                                    @endif
                                </td>
                                <td><span class="journal-state is-{{ $entry['state'] }}">{{ ucfirst($entry['state']) }}</span></td>
                                @if ($isBlog)
                                    <td class="journal-publication">{{ $entry['publication'] }}</td>
                                @else
                                    <td><span class="journal-timing is-{{ $entry['timing'] }}">{{ ucfirst($entry['timing']) }}</span></td>
                                    <td class="journal-schedule">
                                        @if ($entry['vernissage'])<em>Vernissage: {{ $entry['vernissage'] }}</em>@endif
                                        @if ($entry['date_text'] !== '')<span>{{ $entry['date_text'] }}</span>@endif
                                    </td>
                                @endif
                                <td class="admin-table__actions">
                                    <x-admin.toolbar>
                                        @if ($isBlog)
                                            <button class="admin-action" type="button" wire:click="mountAction('editPost', { post: {{ $entry['id'] }} })">Edit</button>
                                            @switch($entry['state'])
                                                @case('published')
                                                    <button class="admin-action admin-action--state" type="button" wire:click="unpublishPost({{ $entry['id'] }})">Unpublish</button>
                                                    <button class="admin-action" type="button" wire:click="archivePost({{ $entry['id'] }})">Archive</button>
                                                    @break
                                                @case('scheduled')
                                                    <button class="admin-action admin-action--state" type="button" wire:click="restorePostDraft({{ $entry['id'] }})">Cancel schedule</button>
                                                    @break
                                                @case('archived')
                                                    <button class="admin-action admin-action--state" type="button" wire:click="restorePostDraft({{ $entry['id'] }})">Restore</button>
                                                    @break
                                                @default
                                                    <button class="admin-action admin-action--state" type="button" wire:click="publishPost({{ $entry['id'] }})">Publish</button>
                                                    <button class="admin-action" type="button" wire:click="mountAction('schedulePost', { post: {{ $entry['id'] }} })">Schedule</button>
                                                    <button class="admin-action" type="button" wire:click="archivePost({{ $entry['id'] }})">Archive</button>
                                            @endswitch
                                            <button class="admin-action admin-order-action" type="button" wire:click="movePost({{ $entry['id'] }}, 'up')" @disabled(! $entry['can_move_up']) aria-label="Move {{ $entry['title'] }} up">↑</button>
                                            <button class="admin-action admin-order-action" type="button" wire:click="movePost({{ $entry['id'] }}, 'down')" @disabled(! $entry['can_move_down']) aria-label="Move {{ $entry['title'] }} down">↓</button>
                                            <button class="admin-action is-danger" type="button" wire:click="mountAction('deletePost', { post: {{ $entry['id'] }} })" @disabled(! $entry['can_delete']) title="{{ $entry['delete_help'] ?? 'Delete post' }}">Delete</button>
                                        @else
                                            <button class="admin-action" type="button" wire:click="mountAction('editExhibition', { exhibition: {{ $entry['id'] }} })">Edit</button>
                                            @if ($entry['state'] === 'published')
                                                <button class="admin-action admin-action--state" type="button" wire:click="unpublishExhibition({{ $entry['id'] }})">Unpublish</button>
                                            @else
                                                <button class="admin-action admin-action--state" type="button" wire:click="publishExhibition({{ $entry['id'] }})">Publish</button>
                                            @endif
                                            <button class="admin-action admin-order-action" type="button" wire:click="moveExhibition({{ $entry['id'] }}, 'up')" @disabled(! $entry['can_move_up']) aria-label="Move {{ $entry['title'] }} up">↑</button>
                                            <button class="admin-action admin-order-action" type="button" wire:click="moveExhibition({{ $entry['id'] }}, 'down')" @disabled(! $entry['can_move_down']) aria-label="Move {{ $entry['title'] }} down">↓</button>
                                            <button class="admin-action is-danger" type="button" wire:click="mountAction('deleteExhibition', { exhibition: {{ $entry['id'] }} })" @disabled(! $entry['can_delete']) title="{{ $entry['delete_help'] ?? 'Delete exhibition' }}">Delete</button>
                                        @endif
                                    </x-admin.toolbar>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @if ($entries === [])
                    @if ($unfilteredEntryCount > 0)
                        <x-admin.empty-state :title="'No matching '.$entryLabel" minimal>
                            <x-slot:actions><button class="admin-action" type="button" wire:click="resetFilters">Clear filters</button></x-slot:actions>
                        </x-admin.empty-state>
                    @else
                        <x-admin.empty-state :title="'No '.$entryLabel.' added to this Journal'" minimal>
                            <x-slot:actions><button class="admin-action" type="button" wire:click="mountAction('{{ $isBlog ? 'addPost' : 'addExhibition' }}')">Add {{ $entryLabelSingular }}</button></x-slot:actions>
                        </x-admin.empty-state>
                    @endif
                @endif
            </x-admin.table>

            <x-admin.add-row wire:click="mountAction('{{ $isBlog ? 'addPost' : 'addExhibition' }}')">Add {{ $entryLabelSingular }}</x-admin.add-row>

            <footer class="admin-pager">
                <label class="admin-pager__size">
                    <span>Per page</span>
                    <select wire:model.live.number="pageSize"><option value="25">25</option><option value="50">50</option><option value="100">100</option></select>
                </label>
                <span class="admin-pager__range">@if ($total === 0)0 of 0 @else{{ $resultStart }}–{{ $resultEnd }} of {{ $total }}@endif</span>
                <x-admin.toolbar class="admin-pager__actions">
                    <button class="admin-action" type="button" wire:click="previousPage" @disabled($page <= 1)>Previous</button>
                    <button class="admin-action" type="button" wire:click="nextPage" @disabled($page >= $pages)>Next</button>
                </x-admin.toolbar>
            </footer>
        </x-admin.section>
    </x-admin.workspace>
</x-filament-panels::page>
