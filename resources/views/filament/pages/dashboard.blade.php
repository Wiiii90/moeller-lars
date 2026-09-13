<x-filament-panels::page>
    <x-admin.workspace title="Dashboard" class="admin-dashboard">
        <x-admin.metrics :columns="6" aria-label="Dashboard summary">
            @foreach ($metrics as $metric)
                <x-admin.metric :label="$metric['label']" :value="$metric['value']">{{ $metric['detail'] }}</x-admin.metric>
            @endforeach
        </x-admin.metrics>

        <section class="admin-dashboard__overview" aria-label="Storage, Activity and Analytics overview">
            <article class="admin-dashboard__overview-column">
                <header class="admin-dashboard__overview-head">
                    <span class="admin-section__kicker">Storage</span>
                    <a class="admin-action" href="{{ $storage['url'] }}">Open</a>
                </header>

                <div class="admin-dashboard__storage-visual" aria-label="Storage capacity preview">
                    <div class="admin-storage__capacity-plot">
                        <svg
                            class="admin-storage__donut"
                            viewBox="0 0 120 120"
                            role="img"
                            aria-label="@if (($storage['percent'] ?? null) !== null) {{ $storage['percent'] }} percent of the configured allowance is used @elseif ($storage['measurement_available'] ?? false) Authoritative usage is measured but no allowance is configured @else Authoritative storage measurement is unavailable @endif"
                        >
                            <circle class="admin-storage__capacity-track" cx="60" cy="60" r="53" pathLength="100" />
                            @if (($storage['percent'] ?? null) !== null)
                                <circle
                                    class="admin-storage__capacity-used"
                                    cx="60"
                                    cy="60"
                                    r="53"
                                    pathLength="100"
                                    stroke-dasharray="{{ min(100, max(0, $storage['percent'])) }} {{ max(0, 100 - min(100, max(0, $storage['percent']))) }}"
                                    transform="rotate(-90 60 60)"
                                />
                            @endif
                        </svg>

                        <div class="admin-storage__capacity-core">
                            <div>
                                @if (($storage['percent'] ?? null) !== null)
                                    <strong>{{ $storage['percent'] }}%</strong>
                                    <span>Allowance used</span>
                                    <small>{{ $storage['authoritative'] ?? '—' }} of {{ $storage['allowance'] ?? '—' }}</small>
                                @elseif ($storage['measurement_available'] ?? false)
                                    <strong>{{ $storage['authoritative'] ?? '—' }}</strong>
                                    <span>Authoritative used</span>
                                    <small>No operator allowance configured</small>
                                @else
                                    <strong>—</strong>
                                    <span>Measurement unavailable</span>
                                    <small>No cached measurement</small>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <p class="admin-dashboard__facts">
                    <span>Used <strong>{{ $storage['authoritative'] }}</strong></span>
                    <span aria-hidden="true">·</span>
                    <span>Remaining <strong>{{ $storage['remaining'] }}</strong></span>
                    <span aria-hidden="true">·</span>
                    <span>Allowance <strong>{{ $storage['allowance'] }}</strong></span>
                </p>
            </article>

            <article class="admin-dashboard__overview-column">
                <header class="admin-dashboard__overview-head">
                    <span class="admin-section__kicker">Activity</span>
                    <a class="admin-action" href="{{ $activity['url'] }}">Open</a>
                </header>

                <div
                    class="admin-dashboard__activity-visual activity-clock"
                    aria-label="Activity clock for the last 30 days"
                    x-data="{
                        now: new Date(),
                        timer: null,
                        timeFormatter: new Intl.DateTimeFormat(undefined, {
                            hour: '2-digit',
                            minute: '2-digit',
                            second: '2-digit',
                        }),
                        init() {
                            this.now = new Date()
                            if (this.timer !== null) window.clearInterval(this.timer)
                            this.timer = window.setInterval(() => { this.now = new Date() }, 1000)
                        },
                        destroy() {
                            if (this.timer !== null) {
                                window.clearInterval(this.timer)
                                this.timer = null
                            }
                        },
                        hourAngle() {
                            return ((this.now.getHours() % 12) + (this.now.getMinutes() / 60) + (this.now.getSeconds() / 3600)) * 30
                        },
                        minuteAngle() {
                            return (this.now.getMinutes() + (this.now.getSeconds() / 60)) * 6
                        },
                        secondAngle() {
                            return this.now.getSeconds() * 6
                        },
                        timeLabel() {
                            return this.timeFormatter.format(this.now)
                        },
                    }"
                >
                    <div class="activity-clock__figure">
                        <svg
                            class="activity-clock__dial"
                            viewBox="0 0 320 320"
                            role="img"
                            x-bind:aria-label="`Current local time ${timeLabel()}`"
                        >
                            <circle class="activity-clock__activity-track" cx="160" cy="160" r="134" />
                            @foreach ($activity['clock_activity'] as $bucket)
                                @php
                                    $activityRatio = $activity['clock_peak_count'] > 0 ? sqrt($bucket['count'] / $activity['clock_peak_count']) : 0;
                                    $activityOpacity = $bucket['count'] > 0 ? 0.28 + (0.72 * $activityRatio) : 0.12;
                                @endphp
                                <line
                                    class="activity-clock__activity-tick {{ $bucket['hour'] === $activity['clock_peak_hour'] ? 'is-peak' : '' }}"
                                    x1="160"
                                    y1="33"
                                    x2="160"
                                    y2="19"
                                    transform="rotate({{ $bucket['hour'] * 15 }} 160 160)"
                                    opacity="{{ number_format($activityOpacity, 3, '.', '') }}"
                                >
                                    <title>{{ str_pad((string) $bucket['hour'], 2, '0', STR_PAD_LEFT) }}:00 · {{ number_format($bucket['count']) }} changes</title>
                                </line>
                            @endforeach

                            <circle class="activity-clock__face" cx="160" cy="160" r="112" />

                            @for ($minute = 0; $minute < 60; $minute++)
                                <line
                                    class="activity-clock__tick {{ $minute % 5 === 0 ? 'is-hour' : '' }}"
                                    x1="160"
                                    y1="{{ $minute % 5 === 0 ? 59 : 54 }}"
                                    x2="160"
                                    y2="48"
                                    transform="rotate({{ $minute * 6 }} 160 160)"
                                />
                            @endfor

                            @foreach (range(1, 12) as $hour)
                                <g transform="rotate({{ $hour * 30 }} 160 160)">
                                    <text
                                        class="activity-clock__number"
                                        x="160"
                                        y="74"
                                        transform="rotate({{ $hour * -30 }} 160 74)"
                                    >{{ $hour }}</text>
                                </g>
                            @endforeach

                            <line
                                class="activity-clock__hand activity-clock__hand--hour"
                                x1="160"
                                y1="170"
                                x2="160"
                                y2="98"
                                x-bind:transform="`rotate(${hourAngle()} 160 160)`"
                            />
                            <line
                                class="activity-clock__hand activity-clock__hand--minute"
                                x1="160"
                                y1="174"
                                x2="160"
                                y2="82"
                                x-bind:transform="`rotate(${minuteAngle()} 160 160)`"
                            />
                            <line
                                class="activity-clock__hand activity-clock__hand--second"
                                x1="160"
                                y1="178"
                                x2="160"
                                y2="74"
                                x-bind:transform="`rotate(${secondAngle()} 160 160)`"
                            />
                            <circle class="activity-clock__pin" cx="160" cy="160" r="4.5" />
                        </svg>
                    </div>
                </div>
                <p class="admin-dashboard__facts">{{ number_format($activity['recent_changes']) }} changes · last 30 days</p>
            </article>

            <article class="admin-dashboard__overview-column">
                <header class="admin-dashboard__overview-head">
                    <span class="admin-section__kicker">Analytics</span>
                    <a class="admin-action" href="{{ $analytics['url'] }}">Open</a>
                </header>

                <figure class="admin-dashboard__analytics-visual">
                    <div
                        class="admin-dashboard__analytics-map analytics-visual-stage"
                        x-data="{
                            selectedCountry: @js($analytics['map_points'][0]['label'] ?? null),
                            activeCountry: @js($analytics['map_points'][0]['label'] ?? null),
                            previewCountry(country) { this.activeCountry = country },
                            restoreCountry() { this.activeCountry = this.selectedCountry },
                            selectCountry(country) { this.selectedCountry = country; this.activeCountry = country },
                        }"
                    >
                        <figure class="analytics-world" aria-label="World visitor map">
                            <div class="analytics-world__canvas">
                                @if (view()->exists('filament.generated.analytics-world-map'))
                                    @include('filament.generated.analytics-world-map')
                                @else
                                    <div class="analytics-map-build-warning" role="status">
                                        Map geometry unavailable in this build.
                                    </div>
                                @endif

                                @foreach ($analytics['map_points'] as $point)
                                    <button
                                        class="analytics-world__marker"
                                        type="button"
                                        style="left: {{ number_format($point['x'], 3, '.', '') }}%; top: {{ number_format($point['y'], 3, '.', '') }}%; width: {{ number_format($point['size'], 2, '.', '') }}px; height: {{ number_format($point['size'], 2, '.', '') }}px;"
                                        x-on:mouseenter="previewCountry(@js($point['label']))"
                                        x-on:mouseleave="restoreCountry()"
                                        x-on:focus="previewCountry(@js($point['label']))"
                                        x-on:blur="restoreCountry()"
                                        x-on:click="selectCountry(@js($point['label']))"
                                        x-bind:class="selectedCountry === @js($point['label']) ? 'is-selected' : ''"
                                        x-bind:aria-pressed="(selectedCountry === @js($point['label'])).toString()"
                                        aria-label="{{ $point['label'] }}: {{ number_format($point['visits']) }} visits"
                                        title="{{ $point['label'] }} · {{ number_format($point['visits']) }} visits"
                                    ></button>
                                @endforeach
                            </div>
                        </figure>
                    </div>
                    <figcaption class="admin-dashboard__analytics-caption">
                        @if ($analytics['status'] === 'disabled')
                            No reporting data for this environment.
                        @elseif ($analytics['status'] === 'unavailable')
                            Reporting data is currently unavailable.
                        @elseif ($analytics['country_state'] === 'unavailable')
                            Country-level reporting is unavailable.
                        @elseif ($analytics['country_state'] === 'empty')
                            No country-level visits in this period.
                        @else
                            Country markers follow aggregate visit volume.
                        @endif
                    </figcaption>
                </figure>
                <p class="admin-dashboard__facts">
                    <span>Visits <strong>{{ $analytics['visits_display'] }}</strong></span>
                    <span aria-hidden="true">·</span>
                    <span>Unique visitors <strong>{{ $analytics['visitors_display'] }}</strong></span>
                    <span aria-hidden="true">·</span>
                    <span>last 30 days</span>
                </p>
            </article>
        </section>

        <x-admin.section class="admin-dashboard__feed-section" aria-label="Dashboard feed">
            @php
                $selectedCount = count($selectedFeedKeys);
                $selectableFeedKeys = collect($feed)
                    ->filter(fn (array $item): bool => is_int($item['contact_id'] ?? null) || is_int($item['notification_id'] ?? null))
                    ->pluck('key')
                    ->values()
                    ->all();
                $selectedVisibleKeys = array_values(array_intersect($selectableFeedKeys, $selectedFeedKeys));
                $allVisibleSelected = $selectableFeedKeys !== [] && count($selectedVisibleKeys) === count($selectableFeedKeys);
                $selectionIndeterminate = count($selectedVisibleKeys) > 0 && ! $allVisibleSelected;
                $feedHasRecords = $feedPagination['total'] > 0 || (trim($feedSearch) !== '' || $feedType !== 'all' || $notificationFilter !== 'all')
                    && app(\App\Domain\Admin\DashboardFeed::class)->paginate('', 'all', 1, 25)['total'] > 0;
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
                    <div
                        class="admin-data-control-group admin-dashboard__settings"
                        x-data="{ open: false }"
                        x-on:click.outside="open = false"
                    >
                        <span class="admin-data-control-label">Dashboard</span>
                        <button
                            class="admin-action"
                            type="button"
                            x-on:click="open = ! open"
                            x-bind:aria-expanded="open.toString()"
                        >Settings</button>
                        <div class="admin-dashboard__settings-popover" x-show="open" x-cloak>
                            <label class="admin-data-field">
                                <span>Notification history</span>
                                <select wire:model.live="notificationFilter">
                                    @foreach ($notificationFilters as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
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
                                <button class="admin-action" type="button" role="menuitem" wire:click="bulkMarkRead" @disabled($selectedCount === 0)>Mark read</button>
                                <button class="admin-action" type="button" role="menuitem" wire:click="bulkMarkUnread" @disabled($selectedCount === 0)>Mark unread</button>
                                <button
                                    class="admin-action is-danger"
                                    type="button"
                                    role="menuitem"
                                    wire:click="bulkDelete"
                                    wire:confirm="Delete the selected contact messages and notifications?"
                                    @disabled($selectedCount === 0)
                                >Delete selected</button>
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
                        <col class="admin-table__col-half-unit">
                        <col class="admin-table__col-three-quarter-unit">
                        <col class="admin-table__col-one-unit">
                        <col class="admin-table__col-three-quarter-unit">
                        <col class="admin-table__col-one-unit">
                        <col class="admin-table__col-five-quarter-units">
                        <col class="admin-table__col-quarter-unit">
                    </colgroup>
                    <thead>
                        <tr>
                            <th scope="colgroup" colspan="2" class="admin-table__ordering-heading">Position</th>
                            <th scope="col">Type</th>
                            <th scope="col">Date</th>
                            <th scope="col">Title</th>
                            <th scope="col">Sender</th>
                            <th scope="col">Message</th>
                            <th scope="col" class="admin-table__actions">Actions</th>
                            <th scope="col" class="admin-table__selection admin-table__selection--trailing">
                                <input
                                    type="checkbox"
                                    aria-label="Select all visible contact messages and notifications"
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
                    <tbody>
                        @foreach ($feed as $item)
                            @php
                                $position = $feedPagination['start'] + $loop->index;
                                $mutable = is_int($item['contact_id'] ?? null) || is_int($item['notification_id'] ?? null);
                                $read = $mutable ? str_starts_with((string) $item['status'], 'Read') : null;
                                $selected = in_array((string) $item['key'], $selectedFeedKeys, true);
                            @endphp
                            <tr @class(['admin-data-row', 'is-selected' => $selected]) wire:key="dashboard-feed-{{ $item['key'] }}">
                                <td class="admin-table__position">
                                    <span class="admin-position">{{ $position }}</span>
                                </td>
                                <td class="admin-table__drag">
                                    <button
                                        class="admin-drag-handle"
                                        type="button"
                                        disabled
                                        title="Chronological feed"
                                        aria-label="Dashboard feed order follows newest first"
                                    >⋮⋮</button>
                                </td>
                                <td class="admin-data-nowrap">{{ $item['type_label'] }}</td>
                                <td class="admin-data-nowrap"><time datetime="{{ $item['date'] }}">{{ $item['date_display'] }}</time></td>
                                <td class="admin-data-title">{{ $item['title'] }}</td>
                                <td class="admin-data-sender">
                                    @if ($item['type'] === 'contact')
                                        <strong>{{ $item['sender_name'] }}</strong>
                                        <small>{{ $item['sender_email'] }}</small>
                                    @elseif ($item['type'] === 'notification')
                                        <strong>Admin</strong>
                                    @else
                                        <span>—</span>
                                    @endif
                                </td>
                                <td class="admin-data-message">{{ $item['message_excerpt'] }}</td>
                                <td class="admin-table__actions">
                                    <x-admin.toolbar class="admin-row-actions admin-row-actions--canonical admin-dashboard__feed-actions">
                                        <button
                                            class="admin-action admin-action--with-icon"
                                            type="button"
                                            wire:click="openFeedEntry('{{ $item['key'] }}')"
                                            aria-label="Open {{ $item['type_label'] }} entry {{ $item['title'] }}"
                                        >
                                            <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Inspect->mini()" class="admin-action__icon" />
                                            <span class="admin-action__label">Open</span>
                                        </button>

                                        @if ($mutable)
                                            <button
                                                class="admin-action admin-action--with-icon"
                                                type="button"
                                                wire:click="{{ $read ? 'markFeedUnread' : 'markFeedRead' }}('{{ $item['key'] }}')"
                                                aria-label="{{ $read ? 'Mark unread' : 'Mark read' }}"
                                                title="{{ $read ? 'Mark unread' : 'Mark read' }}"
                                            >
                                                <x-filament::icon
                                                    :icon="$read ? \App\Filament\Support\AdminIcon::MarkUnread->mini() : \App\Filament\Support\AdminIcon::MarkRead->mini()"
                                                    class="admin-action__icon"
                                                />
                                                <span class="admin-action__label">{{ $read ? 'Mark unread' : 'Mark read' }}</span>
                                            </button>
                                            <button
                                                class="admin-action admin-action--with-icon is-danger"
                                                type="button"
                                                wire:click="deleteFeedEntry('{{ $item['key'] }}')"
                                                wire:confirm="Delete this dashboard feed entry?"
                                            >
                                                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Delete->mini()" class="admin-action__icon" />
                                                <span class="admin-action__label">Delete</span>
                                            </button>
                                        @endif
                                    </x-admin.toolbar>
                                </td>
                                <td class="admin-table__selection admin-table__selection--trailing">
                                    @if ($mutable)
                                        <input
                                            type="checkbox"
                                            wire:model.live="selectedFeedKeys"
                                            value="{{ $item['key'] }}"
                                            aria-label="Select {{ $item['type_label'] }} entry {{ $item['title'] }}"
                                            @checked($selected)
                                        >
                                    @else
                                        <span aria-hidden="true">—</span>
                                    @endif
                                </td>
                            </tr>
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
                <label class="admin-pager__size">
                    <span>Per page</span>
                    <select wire:model.live.number="feedPageSize">
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </label>
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
    </x-admin.workspace>
</x-filament-panels::page>
