<x-admin.section class="admin-dashboard__feed-section" aria-label="Dashboard feed">
    @php
        $selectedCount = count($selectedFeedKeys);
        $selectableFeedKeys = collect($feed)->pluck('key')->values()->all();
        $selectedVisibleKeys = array_values(array_intersect($selectableFeedKeys, $selectedFeedKeys));
        $allVisibleSelected = $selectableFeedKeys !== [] && count($selectedVisibleKeys) === count($selectableFeedKeys);
        $selectionIndeterminate = count($selectedVisibleKeys) > 0 && ! $allVisibleSelected;
        $feedHasRecords = $feedPagination['total'] > 0 || (trim($feedSearch) !== '' || $feedType !== 'all' || $notificationFilter !== 'all')
            && app(\App\Domain\Admin\DashboardFeed::class)->paginate('', 'all', 1, 25)['total'] > 0;
        $pinnedFeed = collect($feed)->filter(fn (array $item): bool => ($item['pinned'] ?? false) === true)->values();
        $regularFeed = collect($feed)->reject(fn (array $item): bool => ($item['pinned'] ?? false) === true)->values();
    @endphp

    <x-admin.controls class="admin-dashboard__feed-controls" aria-label="Dashboard feed filters">
        <x-slot:search>
            <label class="admin-data-field">
                <span>Search</span>
                <input type="search" wire:model.live.debounce.300ms="feedSearch" placeholder="Title, sender or message" autocomplete="off">
            </label>
        </x-slot:search>

        <x-slot:filters>
            <label class="admin-data-field">
                <span>Type</span>
                <select wire:model.live="feedType">
                    @foreach ($feedTypes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </x-slot:filters>

        <x-slot:reset>
            <div class="admin-data-control-group">
                <span class="admin-data-control-label">Filter</span>
                <button class="admin-action" type="button" wire:click="resetFeed">Reset</button>
            </div>
        </x-slot:reset>

        <x-slot:actions>
            <div class="admin-data-control-group">
                <span class="admin-data-control-label">Dashboard</span>
                <button class="admin-action" type="button" wire:click="mountAction('dashboardSettings')">Settings</button>
            </div>
        </x-slot:actions>

        <x-slot:selection>
            <div class="admin-data-control-group admin-selection" x-data="{ open: false }">
                <span class="admin-data-control-label">Selection</span>
                <div class="admin-selection__anchor">
                    <button
                        class="admin-action admin-selection__trigger"
                        type="button"
                        x-on:click="open = ! open"
                        x-bind:aria-expanded="open"
                        aria-haspopup="menu"
                        @disabled($selectedCount === 0)
                    >
                        <span>Selected</span>
                        <span class="admin-selection__count">{{ $selectedCount }}</span>
                    </button>
                    <div class="admin-selection__menu" x-cloak x-show="open" x-on:click.outside="open = false" role="menu">
                        <button class="admin-action" type="button" role="menuitem" wire:click="bulkPin" @disabled(! $selectionCapabilities['has_selection'])>Pin selected</button>
                        <button class="admin-action" type="button" role="menuitem" wire:click="bulkUnpin" @disabled(! $selectionCapabilities['has_selection'])>Unpin selected</button>
                        <button class="admin-action" type="button" role="menuitem" wire:click="bulkMarkRead" @disabled(! $selectionCapabilities['all_mutable'])>Mark read</button>
                        <button class="admin-action" type="button" role="menuitem" wire:click="bulkMarkUnread" @disabled(! $selectionCapabilities['all_mutable'])>Mark unread</button>
                        @if ($deleteWithoutConfirmation)
                            <button class="admin-action is-danger" type="button" role="menuitem" wire:click="bulkDelete" @disabled(! $selectionCapabilities['all_mutable'])>Delete selected</button>
                        @else
                            <button class="admin-action is-danger" type="button" role="menuitem" wire:click="mountAction('deleteSelected')" @disabled(! $selectionCapabilities['all_mutable'])>Delete selected</button>
                        @endif
                    </div>
                </div>
            </div>
        </x-slot:selection>
    </x-admin.controls>

    <x-admin.table class="admin-data-table admin-table--ranked admin-dashboard__feed-table">
        <table class="admin-table--six-grid">
            <colgroup>
                <col class="admin-table__col-quarter-unit">
                <col class="admin-table__col-quarter-unit">
                <col class="admin-table__col-three-quarter-unit">
                <col class="admin-table__col-three-quarter-unit">
                <col class="admin-table__col-one-half-units">
                <col class="admin-table__col-half-unit">
                <col class="admin-table__col-two-units-minus-selection">
                <col class="admin-table__selection-col">
            </colgroup>
            <thead>
                <tr>
                    <th scope="colgroup" colspan="2" class="admin-table__ordering-heading">Position</th>
                    <th scope="col">Type</th>
                    <th scope="col">Date</th>
                    <th scope="col">Title</th>
                    <th scope="col">Sender</th>
                    <th scope="col" class="admin-table__actions">Actions</th>
                    <th scope="col" class="admin-table__selection admin-table__selection--trailing">
                        <input
                            type="checkbox"
                            aria-label="Select all visible feed entries"
                            aria-checked="{{ $selectionIndeterminate ? 'mixed' : ($allVisibleSelected ? 'true' : 'false') }}"
                            wire:click="toggleSelectAll"
                            @checked($allVisibleSelected)
                            @disabled($selectableFeedKeys === [])
                            x-data
                            x-effect="$el.indeterminate = {{ $selectionIndeterminate ? 'true' : 'false' }}"
                        >
                    </th>
                </tr>
            </thead>

            @if ($pinnedFeed->isNotEmpty())
                <tbody class="admin-dashboard__pinned-feed" wire:sort="reorderPinnedFeed">
                    @foreach ($pinnedFeed as $item)
                        @include('filament.pages.partials.dashboard-feed-row', ['item' => $item, 'pinned' => true])
                    @endforeach
                </tbody>
            @endif

            <tbody class="admin-dashboard__regular-feed">
                @foreach ($regularFeed as $item)
                    @include('filament.pages.partials.dashboard-feed-row', ['item' => $item, 'pinned' => false])
                @endforeach
            </tbody>
        </table>

        @if ($feed === [])
            @if ($feedHasRecords)
                <x-admin.empty-state title="No matching feed entries" minimal>
                    <x-slot:actions>
                        <button class="admin-action" type="button" wire:click="resetFeed">Clear filters</button>
                    </x-slot:actions>
                </x-admin.empty-state>
            @else
                <x-admin.empty-state title="No feed entries yet" minimal />
            @endif
        @endif
    </x-admin.table>

    <footer class="admin-pager">
        <x-admin.page-size-picker :value="$feedPageSize" wire-model="feedPageSize" />
        <span class="admin-pager__range">
            @if ($feedPagination['total'] === 0)
                0 of 0
            @else
                {{ $feedPagination['start'] }}–{{ $feedPagination['end'] }} of {{ $feedPagination['total'] }}
            @endif
        </span>
        <div class="admin-pager__actions admin-toolbar">
            <button class="admin-action" type="button" wire:click="previousFeedPage" @disabled($feedPagination['page'] <= 1)>Previous</button>
            <button class="admin-action" type="button" wire:click="nextFeedPage" @disabled($feedPagination['page'] >= $feedPagination['pages'])>Next</button>
        </div>
    </footer>
</x-admin.section>
