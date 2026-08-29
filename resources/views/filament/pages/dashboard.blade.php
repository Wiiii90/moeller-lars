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
                    <span>Storage</span>
                    <a class="admin-action" href="{{ $storage['url'] }}">Open</a>
                </header>

                <div class="admin-dashboard__storage-visual">
                    <div
                        class="admin-dashboard__storage-ring {{ $storage['percent'] === null ? 'is-empty' : '' }}"
                        @if ($storage['percent'] !== null) style="--dashboard-storage-used: {{ $storage['percent'] }}%" @endif
                        aria-label="{{ $storage['percent'] === null ? $storage['label'] : $storage['percent'].' percent of configured storage allowance used' }}"
                    >
                        <strong>{{ $storage['percent'] === null ? '—' : $storage['percent'].'%' }}</strong>
                    </div>
                </div>
                <p class="admin-dashboard__facts">
                    <span>Used <strong>{{ $storage['used'] }}</strong></span>
                    <span aria-hidden="true">·</span>
                    <span>Remaining <strong>{{ $storage['remaining'] }}</strong></span>
                    <span aria-hidden="true">·</span>
                    <span>Allowance <strong>{{ $storage['allowance'] }}</strong></span>
                </p>
            </article>

            <article class="admin-dashboard__overview-column">
                <header class="admin-dashboard__overview-head">
                    <span>Activity</span>
                    <a class="admin-action" href="{{ $activity['url'] }}">Open</a>
                </header>

                <div class="admin-dashboard__activity-visual" aria-label="Activity time preview">
                    <div class="activity-clock admin-dashboard__activity-clock" aria-label="24-hour activity clock for the last 30 days">
                        <div class="activity-clock__face">
                            <span class="activity-clock__label activity-clock__label--00">00</span>
                            <span class="activity-clock__label activity-clock__label--06">06</span>
                            <span class="activity-clock__label activity-clock__label--12">12</span>
                            <span class="activity-clock__label activity-clock__label--18">18</span>
                            <span class="activity-clock__axis activity-clock__axis--vertical" aria-hidden="true"></span>
                            <span class="activity-clock__axis activity-clock__axis--horizontal" aria-hidden="true"></span>
                            @foreach ($activity['clock_points'] as $point)
                                <span
                                    class="activity-clock__marker"
                                    style="--activity-x: {{ number_format($point['x'], 4, '.', '') }}%; --activity-y: {{ number_format($point['y'], 4, '.', '') }}%"
                                    role="img"
                                    aria-label="Change at {{ $point['label'] }}"
                                    title="{{ $point['label'] }}"
                                ></span>
                            @endforeach
                        </div>
                    </div>

                    <div class="activity-calendar admin-dashboard__activity-calendar" aria-label="Activity calendar for {{ $activity['calendar_label'] }}">
                        <strong class="activity-calendar__month">{{ $activity['calendar_label'] }}</strong>
                        <div class="activity-calendar__weekdays" aria-hidden="true">
                            @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday)
                                <span>{{ $weekday }}</span>
                            @endforeach
                        </div>
                        <div class="activity-calendar__grid">
                            @foreach ($activity['calendar_days'] as $day)
                                @if ($day === null)
                                    <span class="activity-calendar__day is-empty" aria-hidden="true"></span>
                                @else
                                    <span class="activity-calendar__day" aria-label="{{ $day['date'] }}: {{ $day['count'] }} changes">
                                        <span>{{ $day['day'] }}</span>
                                        <strong>{{ $day['count'] }}</strong>
                                    </span>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
                <p class="admin-dashboard__facts">{{ number_format($activity['recent_changes']) }} changes · last 30 days</p>
            </article>

            <article class="admin-dashboard__overview-column">
                <header class="admin-dashboard__overview-head">
                    <span>Analytics</span>
                    <a class="admin-action" href="{{ $analytics['url'] }}">Open</a>
                </header>

                @if (in_array($analytics['status'], ['available', 'stale'], true))
                    <figure class="admin-dashboard__map analytics-world">
                        <div class="analytics-world__canvas">
                            @if (view()->exists('filament.generated.analytics-world-map'))
                                @include('filament.generated.analytics-world-map')
                            @else
                                <div class="analytics-map-build-warning">Map geometry is generated by the frontend build.</div>
                            @endif

                            @foreach ($analytics['map_points'] as $point)
                                <span
                                    class="analytics-world__marker"
                                    style="left: {{ number_format($point['x'], 3, '.', '') }}%; top: {{ number_format($point['y'], 3, '.', '') }}%; width: {{ number_format($point['size'], 2, '.', '') }}px; height: {{ number_format($point['size'], 2, '.', '') }}px;"
                                    tabindex="0"
                                    aria-label="{{ $point['label'] }}: {{ number_format($point['visits']) }} visits"
                                    title="{{ $point['label'] }} · {{ number_format($point['visits']) }} visits"
                                ></span>
                            @endforeach
                        </div>
                        <figcaption>
                            @if ($analytics['country_state'] === 'unavailable')
                                Country-level visits unavailable.
                            @elseif ($analytics['country_state'] === 'empty')
                                No country-level visits in this period.
                            @else
                                Country markers follow aggregate visit volume.
                            @endif
                        </figcaption>
                    </figure>
                @else
                    <p class="admin-dashboard__overview-unavailable">{{ $analytics['message'] }}</p>
                @endif
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
            </x-admin.controls>

            <x-admin.table class="admin-data-table">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Type</th>
                            <th scope="col">Date</th>
                            <th scope="col">Title</th>
                            <th scope="col">Sender</th>
                            <th scope="col">Message</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($feed as $item)
                            <tr
                                class="admin-data-row"
                                wire:key="dashboard-feed-{{ $item['key'] }}"
                                wire:click="openFeedEntry('{{ $item['key'] }}')"
                                wire:keydown.enter="openFeedEntry('{{ $item['key'] }}')"
                                tabindex="0"
                                aria-label="Open {{ $item['type_label'] }} entry {{ $item['title'] }}"
                            >
                                <td class="admin-data-nowrap">{{ $item['type_label'] }}</td>
                                <td class="admin-data-nowrap"><time datetime="{{ $item['date'] }}">{{ $item['date_display'] }}</time></td>
                                <td class="admin-data-title">{{ $item['title'] }}</td>
                                <td class="admin-data-sender">
                                    @if ($item['type'] === 'contact')
                                        <strong>{{ $item['sender_name'] }}</strong>
                                        <small>{{ $item['sender_email'] }}</small>
                                    @else
                                        <span>—</span>
                                    @endif
                                </td>
                                <td class="admin-data-message">{{ $item['message_excerpt'] }}</td>
                                <td class="admin-data-status"><span>{{ $item['status'] }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @if ($feed === [])
                    <x-admin.empty-state title="No matching feed entries" minimal>
                        <x-slot:actions>
                            <button class="admin-action" type="button" wire:click="resetFeed">Clear filters</button>
                        </x-slot:actions>
                    </x-admin.empty-state>
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
