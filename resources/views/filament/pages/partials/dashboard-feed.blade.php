<x-admin.section class="admin-dashboard__feed-section admin-visual-stage-followup" aria-label="Dashboard feed">
    @php
        $selectedCount = count($selectedFeedKeys);
        $selectableFeedKeys = collect($feed)->pluck('key')->values()->all();
        $feedHasRecords = $feedPagination['total'] > 0 || (trim($feedSearch) !== '' || $feedType !== 'all' || $notificationFilter !== 'all')
            && app(\App\Domain\Admin\DashboardFeed::class)->paginate('', 'all', 1, 25)['total'] > 0;
        $pinnedFeed = collect($feed)->filter(fn (array $item): bool => ($item['pinned'] ?? false) === true)->values();
        $regularFeed = collect($feed)->reject(fn (array $item): bool => ($item['pinned'] ?? false) === true)->values();
    @endphp

    <x-admin.controls
        class="admin-dashboard__feed-controls"
        aria-label="Dashboard feed filters"
        metric-grid
        :filter-count="1"
    >
        <x-slot:search>
            <label class="admin-data-field">
                <span>{{ __('Search') }}</span>
                <x-admin.search-input model="feedSearch" placeholder="Title, sender or message" />
            </label>
        </x-slot:search>

        <x-slot:filters>
            <label class="admin-data-field">
                <span>{{ __('Type') }}</span>
                <select wire:model.live="feedType">
                    @foreach ($feedTypes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </x-slot:filters>

        <x-slot:reset>
            <div class="admin-data-control-group admin-dashboard__feed-clear">
                <x-admin.clear-filters wire:click="resetFeed" :disabled="trim($feedSearch) === '' && $feedType === 'all' && $notificationFilter === 'all'" />
            </div>
        </x-slot:reset>

        <x-slot:actions>
            <div class="admin-data-control-group">
                <span class="admin-data-control-label">{{ __('Dashboard') }}</span>
                <button class="admin-action admin-action--with-icon admin-dashboard__settings-trigger" type="button" wire:click="mountAction('dashboardSettings')" aria-label="Dashboard settings">
                    <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Settings->mini()" class="admin-action__icon" />
                    <span class="admin-action__label">{{ __('Settings') }}</span>
                </button>
            </div>
        </x-slot:actions>

        <x-slot:selection>
            <div class="admin-data-control-group admin-selection admin-dashboard__selection" x-data="{ open: false }">
                <div class="admin-selection__anchor">
                    <button
                        class="admin-action admin-selection__trigger"
                        type="button"
                        x-on:click="open = ! open"
                        x-bind:aria-expanded="open"
                        aria-haspopup="menu"
                        aria-label="Selection actions; {{ $selectedCount }} selected"
                        @disabled($selectedCount === 0)
                    >
                        <x-admin.selection-trigger-label>{{ __('Selected') }}</x-admin.selection-trigger-label>
                    </button>
                    <div class="admin-selection__menu" x-cloak x-show="open" x-on:click.outside="open = false" role="menu">
                        <button class="admin-action" type="button" role="menuitem" wire:click="bulkPin" @disabled(! $selectionCapabilities['can_pin'])>{{ __('Pin selected') }}</button>
                        <button class="admin-action" type="button" role="menuitem" wire:click="bulkUnpin" @disabled(! $selectionCapabilities['can_unpin'])>{{ __('Unpin selected') }}</button>
                        <button class="admin-action" type="button" role="menuitem" wire:click="bulkMarkRead" @disabled(! $selectionCapabilities['all_readable'])>{{ __('Mark read') }}</button>
                        <button class="admin-action" type="button" role="menuitem" wire:click="bulkMarkUnread" @disabled(! $selectionCapabilities['all_readable'])>{{ __('Mark unread') }}</button>
                        @if ($deleteWithoutConfirmation)
                            <button class="admin-action is-danger" type="button" role="menuitem" wire:click="bulkDelete" @disabled(! $selectionCapabilities['all_mutable'])>{{ __('Delete selected') }}</button>
                        @else
                            <button class="admin-action is-danger" type="button" role="menuitem" wire:click="mountAction('deleteSelected')" @disabled(! $selectionCapabilities['all_mutable'])>{{ __('Delete selected') }}</button>
                        @endif
                    </div>
                </div>
                <span class="admin-selection__count" aria-label="{{ $selectedCount }} selected">{{ $selectedCount }}</span>
            </div>
        </x-slot:selection>
    </x-admin.controls>

    <x-admin.table class="admin-data-table admin-table--ranked admin-dashboard__feed-table">
        <table class="admin-table--six-grid">
            <colgroup>
                <col class="admin-table__col-quarter-unit admin-dashboard__col-position">
                <col class="admin-table__col-quarter-unit admin-dashboard__col-drag">
                <col class="admin-dashboard__col-type">
                <col class="admin-dashboard__col-date">
                <col class="admin-dashboard__col-time">
                <col class="admin-dashboard__col-title">
                <col class="admin-dashboard__col-sender">
                <col class="admin-dashboard__col-actions">
                <col class="admin-table__selection-col">
            </colgroup>
            <thead>
                <tr>
                    <th scope="colgroup" colspan="2" class="admin-table__ordering-heading">{{ __('Position') }}</th>
                    <th scope="col" class="admin-dashboard__col-type">{{ __('Type') }}</th>
                    <th scope="col" class="admin-dashboard__col-date">{{ __('Date') }}</th>
                    <th scope="col" class="admin-dashboard__col-time">{{ __('Time') }}</th>
                    <th scope="col" class="admin-dashboard__col-title">{{ __('Title') }}</th>
                    <th scope="col" class="admin-dashboard__col-sender">{{ __('Sender') }}</th>
                    <th scope="col" class="admin-table__actions"><span class="admin-row-actions-heading">{{ __('Actions') }}</span></th>
                    <th scope="col" class="admin-table__selection admin-table__selection--trailing">
                        <input
                            type="checkbox"
                            aria-label="Select all visible feed entries"
                            wire:click.prevent="toggleSelectAll"
                            data-visible-keys='@json($selectableFeedKeys)'
                            x-data="{}"
                            x-effect="
                                const visibleKeys = JSON.parse($el.dataset.visibleKeys);
                                const selectedKeys = $wire.selectedFeedKeys;
                                const count = visibleKeys.filter((key) => selectedKeys.includes(key)).length;
                                $el.checked = visibleKeys.length > 0 && count === visibleKeys.length;
                                $el.indeterminate = count > 0 && count < visibleKeys.length;
                                $el.disabled = visibleKeys.length === 0;
                                $el.setAttribute('aria-checked', $el.indeterminate ? 'mixed' : ($el.checked ? 'true' : 'false'));
                            "
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
                        <button class="admin-action" type="button" wire:click="resetFeed">{{ __('Clear filters') }}</button>
                    </x-slot:actions>
                </x-admin.empty-state>
            @else
                <x-admin.empty-state title="No feed entries yet" minimal />
            @endif
        @endif
    </x-admin.table>

    <x-admin.pager
        :start="$feedPagination['start']"
        :end="$feedPagination['end']"
        :total="$feedPagination['total']"
        :page="$feedPagination['page']"
        :pages="$feedPagination['pages']"
        :page-size="$feedPageSize"
        page-size-wire-model="feedPageSize"
        previous-wire-action="previousFeedPage"
        next-wire-action="nextFeedPage"
        aria-label="Dashboard feed pagination"
    />
</x-admin.section>
