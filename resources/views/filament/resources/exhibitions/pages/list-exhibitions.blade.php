<x-filament-panels::page>
    @php
        $visibleIds = collect($exhibitions)->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
        $selectedIds = collect($selectedExhibitionIds)->map(static fn (mixed $id): int => (int) $id)->all();
        $resultStart = $total === 0 ? 0 : (($page - 1) * $pageSize) + 1;
        $resultEnd = $total === 0 ? 0 : min($total, $page * $pageSize);
        $dragEnabled = $this->canDragSort();
        $coverUrls = \App\Models\JournalEntryMedia::query()
            ->whereIn('exhibition_id', $visibleIds)
            ->where('role', \App\Models\JournalEntryMedia::ROLE_COVER)
            ->with('mediaAsset.variants')
            ->get()
            ->mapWithKeys(static function (\App\Models\JournalEntryMedia $usage): array {
                $asset = $usage->getRelationValue('mediaAsset');
                if (! $asset instanceof \App\Models\MediaAsset) {
                    return [];
                }

                $variant = $asset->getRelationValue('variants')->first(
                    static fn (\App\Models\MediaVariant $candidate): bool =>
                        $candidate->getAttribute('variant_kind') === \App\Domain\Media\PublicMedia::THUMBNAIL_KIND
                        && $candidate->getAttribute('transform_profile') === \App\Domain\Media\PublicMedia::PUBLIC_TRANSFORM_PROFILE
                        && $candidate->getAttribute('state') === 'available'
                );

                return $variant instanceof \App\Models\MediaVariant
                    ? [(int) $usage->getAttribute('exhibition_id') => route('admin.media.variant', $variant)]
                    : [];
            });
    @endphp

    <x-admin.workspace :title="$journalTitle" class="journal-workspace journal-workspace--exhibitions">
        <x-admin.metrics :columns="6" aria-label="Exhibitions overview">
            @foreach ($metrics as $metric)<x-admin.metric :label="$metric['label']" :value="$metric['value']">{{ $metric['description'] }}</x-admin.metric>@endforeach
        </x-admin.metrics>
        <x-admin.section class="journal-workspace__entries" aria-label="Exhibition entries">
            <div class="journal-workspace__surface">
                <div class="journal-workspace__controls is-exhibitions" aria-label="Exhibition controls">
                    <label class="journal-workspace__field journal-workspace__search"><span>Search exhibitions</span><input type="search" wire:model.live.debounce.300ms="search" placeholder="Title, venue, place or date" autocomplete="off"></label>
                    <label class="journal-workspace__field"><span>Status</span><select wire:model.live="statusFilter"><option value="any">Any</option><option value="published">Published</option><option value="unpublished">Unpublished</option></select></label>
                    <label class="journal-workspace__field"><span>Timing</span><select wire:model.live="timingFilter"><option value="any">Any</option><option value="upcoming">Upcoming</option><option value="current">Current</option><option value="past">Past</option><option value="unknown">Unknown</option></select></label>
                    <div class="journal-workspace__control-group"><span class="journal-workspace__control-label">Filter</span><button class="admin-action" type="button" wire:click="resetFilters">Reset</button></div>
                    <div class="journal-workspace__control-group journal-workspace__journal-group"><span class="journal-workspace__control-label">Journal</span><div class="journal-workspace__journal-actions"><button class="admin-action" type="button" wire:click="mountAction('journalSettings')">Settings</button><button class="admin-action" type="button" wire:click="mountAction('addExhibition')">Add exhibition</button>@if ($journalPublicUrl)<a class="admin-action" href="{{ $journalPublicUrl }}" target="_blank" rel="noopener">Preview</a>@else<button class="admin-action" type="button" disabled title="Publish this Journal in Pages before previewing it">Preview</button>@endif</div></div>
                    <div class="journal-workspace__control-group journal-workspace__selection" x-data="{ open: false }" x-on:keydown.escape.window="open = false"><span class="journal-workspace__control-label">Selection</span><div class="journal-workspace__selection-anchor"><button class="admin-action journal-workspace__selection-trigger" type="button" x-on:click="open = ! open" x-bind:aria-expanded="open.toString()" aria-haspopup="menu" @disabled($selectedExhibitionIds === [])>Selected exhibitions <span class="journal-workspace__selection-count">{{ count($selectedExhibitionIds) }}</span></button><div class="journal-workspace__selection-menu" role="menu" x-show="open" x-cloak x-on:click.outside="open = false"><button class="admin-action" type="button" role="menuitem" wire:click="moveSelectedEntries('up')" x-on:click="open = false">Move selected up</button><button class="admin-action" type="button" role="menuitem" wire:click="moveSelectedEntries('down')" x-on:click="open = false">Move selected down</button><button class="admin-action is-danger" type="button" role="menuitem" wire:click="mountAction('deleteSelectedExhibitions')" x-on:click="open = false">Delete selected</button></div></div></div>
                </div>

                <x-admin.table class="journal-workspace__table-wrap">
                    <table class="journal-workspace__table journal-workspace__table--exhibitions" style="min-width: 86rem; table-layout: fixed;">
                        <colgroup>
                            <col style="width: 2.5rem;">
                            <col style="width: 3.5rem;">
                            <col style="width: 5rem;">
                            <col style="width: 5rem;">
                            <col style="width: 18rem;">
                            <col style="width: 7.8rem;">
                            <col style="width: 6.8rem;">
                            <col style="width: 12rem;">
                            <col style="width: 25.4rem;">
                        </colgroup>
                        <thead><tr><th scope="col" class="journal-workspace__selection-head"><input type="checkbox" x-data="{}" wire:click.prevent="toggleVisibleSelection" x-effect="const visibleIds = @js($visibleIds); const selectedIds = $wire.selectedExhibitionIds.map(Number); const count = visibleIds.filter((id) => selectedIds.includes(id)).length; $el.checked = visibleIds.length > 0 && count === visibleIds.length; $el.indeterminate = count > 0 && count < visibleIds.length; $el.setAttribute('aria-checked', $el.indeterminate ? 'mixed' : ($el.checked ? 'true' : 'false'));" @disabled($visibleIds === []) aria-label="Toggle selection for visible exhibitions"></th><th scope="col" style="white-space: nowrap;">Drag</th><th scope="col" style="white-space: nowrap;">Position</th><th scope="col" class="journal-workspace__thumb-head" style="white-space: nowrap;">Cover</th><th scope="col">Exhibition</th><th scope="col" style="white-space: nowrap;">Status</th><th scope="col" style="white-space: nowrap;">Timing</th><th scope="col" style="white-space: nowrap;">Schedule</th><th scope="col" style="text-align: left; white-space: nowrap;">Actions</th></tr></thead>
                        <tbody @if ($dragEnabled) wire:sort="sortExhibition" @endif>
                            @foreach ($exhibitions as $exhibition)
                                <tr class="{{ in_array($exhibition['id'], $selectedIds, true) ? 'is-selected' : '' }}" wire:key="exhibition-{{ $exhibition['id'] }}" @if ($dragEnabled) wire:sort:item="{{ $exhibition['id'] }}" @endif>
                                    <td class="journal-workspace__selection-cell"><input type="checkbox" wire:click="toggleExhibitionSelection({{ $exhibition['id'] }})" @checked(in_array($exhibition['id'], $selectedIds, true)) aria-label="Toggle selection for {{ $exhibition['title'] }}"></td>
                                    <td style="white-space: nowrap;"><button class="admin-action journal-workspace__order-action" type="button" @if ($dragEnabled) wire:sort:handle title="Drag to reorder" @else disabled title="Drag reorder is available only with no search/filter and all entries on one page" @endif aria-label="Drag {{ $exhibition['title'] }} to reorder">⠿</button></td>
                                    <td style="white-space: nowrap;"><span class="journal-workspace__position-badge" style="width: 2.4rem; min-width: 2.4rem; height: 1.9rem; overflow: hidden; letter-spacing: .04em;">{{ $exhibition['rank'] }}</span></td>
                                    <td class="journal-workspace__thumb"><div style="display: grid; width: 3.6rem; height: 3rem; place-items: center; overflow: hidden; background: var(--admin-subtle);">@if ($coverUrls->has($exhibition['id']))<img src="{{ $coverUrls->get($exhibition['id']) }}" alt="" loading="lazy" style="display: block; width: 100%; height: 100%; object-fit: cover;">@else<span style="max-width: 3.2rem; color: var(--admin-faint); font-size: .62rem; line-height: 1.15; text-align: center; white-space: normal;">No cover image</span>@endif</div></td>
                                    <td class="journal-workspace__identity"><strong>{{ $exhibition['title'] }}</strong>@if ($exhibition['location'])<small>{{ $exhibition['location'] }}</small>@endif</td>
                                    <td style="white-space: nowrap;"><span class="journal-workspace__state is-{{ $exhibition['state'] }}">{{ ucfirst($exhibition['state']) }}</span></td>
                                    <td style="white-space: nowrap;"><span class="journal-workspace__timing is-{{ $exhibition['timing'] }}">{{ ucfirst($exhibition['timing']) }}</span></td>
                                    <td class="journal-workspace__schedule" style="overflow: hidden; white-space: nowrap;">@if ($exhibition['vernissage'])<em style="overflow: hidden; text-overflow: ellipsis;">Vernissage: {{ $exhibition['vernissage'] }}</em>@endif @if ($exhibition['date_text'] !== '')<span style="overflow: hidden; text-overflow: ellipsis;">{{ $exhibition['date_text'] }}</span>@endif</td>
                                    <td class="journal-workspace__actions" style="text-align: left; white-space: nowrap;"><div class="admin-toolbar">
                                        <button class="admin-action" type="button" wire:click="mountAction('editExhibition', { exhibition: {{ $exhibition['id'] }} })">Edit</button>
                                        @if ($exhibition['state'] === 'published')<button class="admin-action" type="button" wire:click="unpublishExhibition({{ $exhibition['id'] }})">Unpublish</button>@else<button class="admin-action" type="button" wire:click="publishExhibition({{ $exhibition['id'] }})">Publish</button>@endif
                                        <button class="admin-action journal-workspace__order-action" type="button" wire:click="moveExhibition({{ $exhibition['id'] }}, 'up')" @disabled(! $exhibition['can_move_up'])>↑</button><button class="admin-action journal-workspace__order-action" type="button" wire:click="moveExhibition({{ $exhibition['id'] }}, 'down')" @disabled(! $exhibition['can_move_down'])>↓</button><button class="admin-action is-danger" type="button" wire:click="mountAction('deleteExhibition', { exhibition: {{ $exhibition['id'] }} })" @disabled(! $exhibition['can_delete']) title="{{ $exhibition['delete_help'] ?? 'Delete exhibition' }}">Delete</button>
                                    </div></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if ($exhibitions === [])@if ($unfilteredEntryCount > 0)<x-admin.empty-state title="No matching exhibitions" minimal><x-slot:actions><button class="admin-action" type="button" wire:click="resetFilters">Clear filters</button></x-slot:actions></x-admin.empty-state>@else<x-admin.empty-state title="No exhibitions added to this Journal" minimal><x-slot:actions><button class="admin-action" type="button" wire:click="mountAction('addExhibition')">Add exhibition</button></x-slot:actions></x-admin.empty-state>@endif @endif
                </x-admin.table>

                <div class="journal-workspace__bottom-add" style="padding: 0;">
                    <button type="button" wire:click="mountAction('addExhibition')" style="display: grid; width: max-content; min-height: 2.7rem; grid-template-columns: 2.5rem auto; align-items: center; padding: 0; border: 0; background: transparent; color: var(--admin-muted); font: inherit; font-size: .76rem; text-align: left; cursor: pointer;">
                        <span aria-hidden="true" style="display: grid; width: 1.125rem; height: 1.125rem; margin: 0 auto; place-items: center; border: 1px solid var(--admin-line-strong); border-radius: .22rem; color: var(--admin-muted); font-size: .82rem; line-height: 1;">+</span>
                        <span style="padding-left: .6rem;">Add exhibition</span>
                    </button>
                </div>
                <footer class="journal-workspace__pager"><label class="journal-workspace__pager-size"><span>Per page</span><select wire:model.live.number="pageSize"><option value="25">25</option><option value="50">50</option><option value="100">100</option></select></label><span class="journal-workspace__pager-range">@if ($total === 0)0 of 0 @else{{ $resultStart }}–{{ $resultEnd }} of {{ $total }}@endif</span><div class="journal-workspace__pager-actions admin-toolbar"><button class="admin-action" type="button" wire:click="previousPage" @disabled($page <= 1)>Previous</button><button class="admin-action" type="button" wire:click="nextPage" @disabled($page >= $pages)>Next</button></div></footer>
            </div>
        </x-admin.section>
    </x-admin.workspace>
</x-filament-panels::page>
