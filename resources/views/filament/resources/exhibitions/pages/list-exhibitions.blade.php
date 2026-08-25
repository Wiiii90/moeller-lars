<x-filament-panels::page>
    @php
        $visibleIds = collect($exhibitions)->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
        $selectedIds = collect($selectedExhibitionIds)->map(static fn (mixed $id): int => (int) $id)->all();
        $resultStart = $total === 0 ? 0 : (($page - 1) * $pageSize) + 1;
        $resultEnd = $total === 0 ? 0 : min($total, $page * $pageSize);
    @endphp

    <x-admin.workspace :title="$journalTitle" class="journal-workspace journal-workspace--exhibitions">
        <x-admin.metrics :columns="6" aria-label="Exhibitions overview">@foreach ($metrics as $metric)<x-admin.metric :label="$metric['label']" :value="$metric['value']" />@endforeach</x-admin.metrics>

        <x-admin.section class="journal-workspace__entries" aria-label="Exhibition entries">
            <div class="journal-workspace__controls is-exhibitions" aria-label="Exhibitions controls">
                <label class="journal-workspace__field journal-workspace__search"><span>Search exhibitions</span><input type="search" value="{{ $search }}" wire:blur="commitSearch($event.target.value)" x-on:keydown.enter.prevent="$el.blur()" placeholder="Title, place, date or vernissage" autocomplete="off"></label>
                <label class="journal-workspace__field"><span>Status</span><select wire:change="commitStatusFilter($event.target.value)">
                    <option value="any" @selected($statusFilter === 'any')>Any</option>
                    <option value="draft" @selected($statusFilter === 'draft')>Draft</option>
                    <option value="published" @selected($statusFilter === 'published')>Published</option>
                    <option value="archived" @selected($statusFilter === 'archived')>Archived</option>
                </select></label>
                <label class="journal-workspace__field"><span>Timing</span><select wire:change="commitTimingFilter($event.target.value)">
                    <option value="any" @selected($timingFilter === 'any')>Any</option>
                    <option value="upcoming" @selected($timingFilter === 'upcoming')>Upcoming</option>
                    <option value="current" @selected($timingFilter === 'current')>Current</option>
                    <option value="past" @selected($timingFilter === 'past')>Past</option>
                    <option value="unknown" @selected($timingFilter === 'unknown')>Unknown</option>
                </select></label>
                <div class="journal-workspace__control-group"><span class="journal-workspace__control-label">Filter</span><button class="admin-action" type="button" wire:click="resetFilters">Reset</button></div>
                <div class="journal-workspace__control-group journal-workspace__journal-group"><span class="journal-workspace__control-label">Journal</span><div class="journal-workspace__journal-actions"><button class="admin-action" type="button" wire:click="mountAction('journalSettings')">Settings</button><button class="admin-action" type="button" wire:click="mountAction('addExhibition')">Add exhibition</button>@if ($journalPublicUrl)<a class="admin-action" href="{{ $journalPublicUrl }}" target="_blank" rel="noopener">Preview</a>@else<button class="admin-action" type="button" disabled>Preview</button>@endif</div></div>
                <div class="journal-workspace__control-group journal-workspace__selection" x-data="{ open: false }" x-on:keydown.escape.window="open = false"><span class="journal-workspace__control-label">Selection</span><div class="journal-workspace__selection-anchor"><button class="admin-action journal-workspace__selection-trigger" type="button" x-on:click="open = !open" x-bind:aria-expanded="open.toString()" @disabled($selectedExhibitionIds === [])>Selected exhibitions <span class="journal-workspace__selection-count">{{ count($selectedExhibitionIds) }}</span></button><div class="journal-workspace__selection-menu" role="menu" x-show="open" x-cloak x-on:click.outside="open=false">
                    <button class="admin-action" type="button" wire:click="moveSelectedEntries('up')" x-on:click="open=false">Move selected up</button><button class="admin-action" type="button" wire:click="moveSelectedEntries('down')" x-on:click="open=false">Move selected down</button><button class="admin-action" type="button" wire:click="publishSelectedExhibitions" x-on:click="open=false">Publish selected</button><button class="admin-action" type="button" wire:click="archiveSelectedExhibitions" x-on:click="open=false">Archive selected</button><button class="admin-action" type="button" wire:click="restoreSelectedExhibitions" x-on:click="open=false">Restore selected</button><button class="admin-action" type="button" wire:click="mountAction('deleteSelectedExhibitions')" x-on:click="open=false">Delete selected</button>
                </div></div></div>
            </div>

            <x-admin.table class="journal-workspace__table-wrap">
                <table class="journal-workspace__table journal-workspace__table--exhibitions">
                    <thead><tr><th scope="col" class="journal-workspace__selection-head"><input type="checkbox" x-data="{}" wire:click.prevent="toggleVisibleSelection" x-effect="const visibleIds=@js($visibleIds);const selectedIds=$wire.selectedExhibitionIds.map(Number);const n=visibleIds.filter((id)=>selectedIds.includes(id)).length;$el.checked=visibleIds.length>0&&n===visibleIds.length;$el.indeterminate=n>0&&n<visibleIds.length;" @disabled($visibleIds === [])></th><th scope="col">Exhibition</th><th scope="col">Status</th><th scope="col">Timing</th><th scope="col">Schedule</th><th scope="col">Actions</th></tr></thead>
                    <tbody>@foreach ($exhibitions as $exhibition)
                        <tr class="{{ in_array($exhibition['id'], $selectedIds, true) ? 'is-selected' : '' }}" wire:key="exhibition-{{ $exhibition['id'] }}">
                            <td class="journal-workspace__selection-cell"><input type="checkbox" wire:model.live="selectedExhibitionIds" value="{{ $exhibition['id'] }}"></td>
                            <td class="journal-workspace__identity"><strong>{{ $exhibition['title'] }}</strong>@if ($exhibition['location'])<small>{{ $exhibition['location'] }}</small>@endif</td>
                            <td><span class="journal-workspace__state is-{{ $exhibition['state'] }}">{{ ucfirst($exhibition['state']) }}</span></td>
                            <td><span class="journal-workspace__timing is-{{ $exhibition['timing'] }}">{{ ucfirst($exhibition['timing']) }}</span></td>
                            <td class="journal-workspace__schedule">@if ($exhibition['vernissage'])<em>Vernissage: {{ $exhibition['vernissage'] }}</em>@endif @if ($exhibition['date_text'] !== '')<span>{{ $exhibition['date_text'] }}</span>@endif</td>
                            <td class="journal-workspace__actions"><div class="journal-workspace__row-actions">
                                <button class="admin-action journal-workspace__edit-action" type="button" wire:click="mountAction('editExhibition', { exhibition: {{ $exhibition['id'] }} })">Edit</button>
                                @if ($exhibition['public_url'])<a class="journal-workspace__icon-action" href="{{ $exhibition['public_url'] }}" target="_blank" rel="noopener" title="View public Journal"><x-filament::icon icon="heroicon-m-arrow-top-right-on-square" /></a>@else<button class="journal-workspace__icon-action" type="button" disabled><x-filament::icon icon="heroicon-m-arrow-top-right-on-square" /></button>@endif
                                @switch($exhibition['state'])
                                    @case('published')<button class="admin-action journal-workspace__lifecycle-action" type="button" wire:click="archiveExhibition({{ $exhibition['id'] }})">Archive</button>@break
                                    @case('archived')<button class="admin-action journal-workspace__lifecycle-action" type="button" wire:click="restoreExhibition({{ $exhibition['id'] }})">Restore</button>@break
                                    @default<button class="admin-action journal-workspace__lifecycle-action" type="button" wire:click="publishExhibition({{ $exhibition['id'] }})">Publish</button>
                                @endswitch
                                <button class="admin-action journal-workspace__order-action" type="button" wire:click="moveExhibition({{ $exhibition['id'] }}, 'up')" @disabled(! $exhibition['can_move_up'])>↑</button><button class="admin-action journal-workspace__order-action" type="button" wire:click="moveExhibition({{ $exhibition['id'] }}, 'down')" @disabled(! $exhibition['can_move_down'])>↓</button><button class="journal-workspace__icon-action" type="button" wire:click="mountAction('deleteExhibition', { exhibition: {{ $exhibition['id'] }} })" @disabled(! $exhibition['can_delete']) title="{{ $exhibition['delete_help'] ?? 'Delete exhibition' }}"><x-filament::icon icon="heroicon-m-trash" /></button>
                            </div></td>
                        </tr>
                    @endforeach</tbody>
                </table>
                @if ($exhibitions === [])@if ($unfilteredEntryCount > 0)<x-admin.empty-state title="No matching exhibitions" minimal><x-slot:actions><button class="admin-action" type="button" wire:click="resetFilters">Clear filters</button></x-slot:actions></x-admin.empty-state>@else<x-admin.empty-state title="No exhibitions added to this Journal" minimal><x-slot:actions><button class="admin-action" type="button" wire:click="mountAction('addExhibition')">Add exhibition</button></x-slot:actions></x-admin.empty-state>@endif @endif
            </x-admin.table>

            <footer class="journal-workspace__pager">
                <label class="journal-workspace__pager-size"><span>Per page</span>
                    <select wire:change="setPageSize($event.target.value)">
                        <option value="25" @selected($pageSize === 25)>25</option>
                        <option value="50" @selected($pageSize === 50)>50</option>
                        <option value="100" @selected($pageSize === 100)>100</option>
                    </select>
                </label>
                <span class="journal-workspace__pager-range">@if ($total === 0)0 of 0 @else{{ $resultStart }}–{{ $resultEnd }} of {{ $total }}@endif</span>
                <div class="journal-workspace__pager-actions admin-toolbar"><button class="admin-action" type="button" wire:click="previousPage" @disabled($page <= 1)>Previous</button><button class="admin-action" type="button" wire:click="nextPage" @disabled($page >= $pages)>Next</button></div>
            </footer>
        </x-admin.section>
    </x-admin.workspace>
</x-filament-panels::page>
