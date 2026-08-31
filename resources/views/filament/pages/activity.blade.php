<x-filament-panels::page>
    <x-admin.workspace title="Activity" class="activity-workspace">
        <x-admin.metrics :columns="6" aria-label="Activity statistics">
            <x-admin.metric label="Changes" :value="number_format($activityMetrics['changes'])">Matching filters</x-admin.metric>
            <x-admin.metric label="Active days" :value="number_format($activityMetrics['active_days'])">Days with matching activity</x-admin.metric>
            <x-admin.metric label="Areas" :value="number_format($activityMetrics['areas'])">Matching editorial areas</x-admin.metric>
            <x-admin.metric label="Change types" :value="number_format($activityMetrics['families'])">Matching change families</x-admin.metric>
            <x-admin.metric label="Actors" :value="number_format($activityMetrics['actors'])">Matching admins</x-admin.metric>
            <x-admin.metric label="Latest" :value="$activityMetrics['latest_when']" :description="$activityMetrics['latest_at'] ?? 'No matching activity'" />
        </x-admin.metrics>

        <section class="activity-atlas" aria-label="Activity atlas" x-data="{ mode: 'clock' }">
            <header class="activity-atlas__header">
                <div class="activity-atlas__heading">
                    <span class="activity-atlas__kicker">{{ $selectedPeriodLabel }} · {{ number_format($activityMetrics['changes']) }} matching changes</span>
                    <strong>Activity atlas</strong>
                </div>
                <x-admin.toolbar aria-label="Activity visualization mode">
                    <button
                        class="admin-action"
                        type="button"
                        x-on:click="mode = 'clock'"
                        x-bind:class="{ 'is-primary': mode === 'clock' }"
                        x-bind:aria-pressed="(mode === 'clock').toString()"
                    >Clock</button>
                    <button
                        class="admin-action"
                        type="button"
                        x-on:click="mode = 'calendar'"
                        x-bind:class="{ 'is-primary': mode === 'calendar' }"
                        x-bind:aria-pressed="(mode === 'calendar').toString()"
                    >Calendar</button>
                </x-admin.toolbar>
            </header>

            <div class="activity-atlas__grid admin-visual-stage admin-visual-stage--stackable" aria-label="Activity visualization">
                <div class="activity-atlas__visual admin-visual-stage__pane">
                    <div class="activity-atlas__panel activity-rhythm" x-show="mode === 'clock'">
                        <div class="activity-atlas__panel-heading">
                            <div>
                                <strong>24-hour rhythm</strong>
                                <span>Aggregated by recorded hour across the selected filters.</span>
                            </div>
                            @if ($clockPeakHour !== null)
                                <span class="activity-atlas__summary">Peak {{ str_pad((string) $clockPeakHour, 2, '0', STR_PAD_LEFT) }}:00 · {{ number_format($clockPeakCount) }}</span>
                            @else
                                <span class="activity-atlas__summary">No matching activity</span>
                            @endif
                        </div>

                        <div class="activity-rhythm__figure">
                            <svg class="activity-rhythm__chart" viewBox="0 0 240 240" role="img" aria-label="24-hour rhythm for matching Activity events">
                                <circle class="activity-rhythm__ring" cx="120" cy="120" r="79" />
                                <circle class="activity-rhythm__inner-ring" cx="120" cy="120" r="60" />
                                @foreach ($clockActivity as $bucket)
                                    <line
                                        class="activity-rhythm__bar {{ $bucket['count'] > 0 ? 'is-active' : 'is-empty' }}"
                                        x1="{{ number_format($bucket['x1'], 3, '.', '') }}"
                                        y1="{{ number_format($bucket['y1'], 3, '.', '') }}"
                                        x2="{{ number_format($bucket['x2'], 3, '.', '') }}"
                                        y2="{{ number_format($bucket['y2'], 3, '.', '') }}"
                                    >
                                        <title>{{ str_pad((string) $bucket['hour'], 2, '0', STR_PAD_LEFT) }}:00 · {{ $bucket['count'] }} changes</title>
                                    </line>
                                @endforeach
                                <text class="activity-rhythm__label activity-rhythm__label--00" x="120" y="24">00</text>
                                <text class="activity-rhythm__label activity-rhythm__label--06" x="216" y="124">06</text>
                                <text class="activity-rhythm__label activity-rhythm__label--12" x="120" y="222">12</text>
                                <text class="activity-rhythm__label activity-rhythm__label--18" x="24" y="124">18</text>
                                <text class="activity-rhythm__total" x="120" y="116">{{ number_format($clockTotal) }}</text>
                                <text class="activity-rhythm__total-label" x="120" y="135">changes</text>
                            </svg>
                        </div>

                        <p class="activity-atlas__caption">Bar length represents event density for each hour of day, not a rolling last-24-hours window.</p>
                    </div>

                    <div class="activity-atlas__panel activity-calendar" x-show="mode === 'calendar'" x-cloak>
                        <div class="activity-atlas__panel-heading">
                            <div>
                                <strong>Calendar density</strong>
                                <span>{{ $calendarLabel }}</span>
                            </div>
                            <span class="activity-atlas__summary">{{ number_format($calendarActiveDays) }} active days · peak {{ number_format($calendarMaximum) }}</span>
                        </div>

                        <div class="activity-calendar__plot">
                            <div class="activity-calendar__weekdays" aria-hidden="true">
                                @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday)
                                    <span>{{ $weekday }}</span>
                                @endforeach
                            </div>
                            <div class="activity-calendar__cells" role="img" aria-label="Daily Activity density for {{ $calendarLabel }}">
                                @foreach ($calendarDays as $day)
                                    @if ($day === null)
                                        <span class="activity-calendar__day is-outside" aria-hidden="true"></span>
                                    @else
                                        <span
                                            class="activity-calendar__day is-level-{{ $day['level'] }}"
                                            aria-label="{{ $day['label'] }}: {{ $day['count'] }} changes"
                                            title="{{ $day['label'] }} · {{ $day['count'] }} changes"
                                        ></span>
                                    @endif
                                @endforeach
                            </div>
                        </div>

                        <div class="activity-calendar__legend" aria-hidden="true">
                            <span>Less</span>
                            @foreach (range(0, 4) as $level)
                                <i class="activity-calendar__day is-level-{{ $level }}"></i>
                            @endforeach
                            <span>More</span>
                        </div>
                    </div>
                </div>

                <aside class="activity-publication admin-visual-stage__pane" aria-label="Publication context">
                    <header class="activity-publication__header">
                        <span>Publication</span>
                        <strong>Current state</strong>
                    </header>

                    <div class="activity-publication__staged">
                        <span>Staged activity</span>
                        <strong>{{ number_format($publicationContext['staged']) }}</strong>
                        <small>Pending audit events not yet checkpointed</small>
                    </div>

                    @if ($publicationContext['latest'])
                        <div class="activity-publication__latest">
                            <span>Latest checkpoint</span>
                            <strong>#{{ $publicationContext['latest']['id'] }} · {{ $publicationContext['latest']['when'] }}</strong>
                            <small>{{ number_format($publicationContext['latest']['change_count']) }} changes · {{ $publicationContext['latest']['timestamp'] }}</small>
                            <p title="{{ $publicationContext['latest']['message'] ?? 'No checkpoint message' }}">{{ $publicationContext['latest']['message'] ?? 'No checkpoint message' }}</p>
                        </div>
                    @else
                        <div class="activity-publication__latest is-empty">
                            <span>Latest checkpoint</span>
                            <strong>No checkpoints yet</strong>
                        </div>
                    @endif

                    @if ($publicationContext['recent'] !== [])
                        <div class="activity-publication__recent">
                            <span>Recent checkpoints</span>
                            @foreach ($publicationContext['recent'] as $checkpoint)
                                <article>
                                    <div>
                                        <strong>#{{ $checkpoint['id'] }}</strong>
                                        <span>{{ $checkpoint['when'] }}</span>
                                    </div>
                                    <small>{{ number_format($checkpoint['change_count']) }} changes · {{ $checkpoint['message'] ?? 'No message' }}</small>
                                </article>
                            @endforeach
                        </div>
                    @endif

                    <p class="activity-publication__scope">Publication context is global current state. Activity filters apply to the atlas and table.</p>
                </aside>
            </div>

            @php
                $activityUrl = static function (array $values): string {
                    $query = array_filter(
                        $values,
                        static fn (mixed $value): bool => $value !== null && $value !== '',
                    );

                    return request()->url().($query === [] ? '' : '?'.http_build_query($query));
                };
            @endphp

            <form method="get" action="{{ request()->url() }}" class="admin-visual-stage-followup">
                <input type="hidden" name="period" value="{{ $period }}">
                <x-admin.controls class="activity-workspace__controls" aria-label="Activity controls">
                    <x-slot:search>
                        <label class="admin-data-field">
                            <span>Search</span>
                            <input type="search" name="search" value="{{ $search }}" placeholder="Search change or actor">
                        </label>
                    </x-slot:search>

                    <x-slot:filters>
                        <label class="admin-data-field">
                            <span>Editorial area</span>
                            <select name="area" onchange="this.form.submit()">
                                <option value="">All areas</option>
                                @foreach ($areaOptions as $value => $label)
                                    <option value="{{ $value }}" @selected($area === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="admin-data-field">
                            <span>Change type</span>
                            <select name="family" onchange="this.form.submit()">
                                <option value="">All changes</option>
                                @foreach ($familyOptions as $value => $label)
                                    <option value="{{ $value }}" @selected($family === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                    </x-slot:filters>

                    <x-slot:reset>
                        <div class="admin-data-control-group">
                            <span class="admin-data-control-label">Filter</span>
                            <a class="admin-action" href="{{ $activityUrl(['period' => $period]) }}">Reset</a>
                        </div>
                    </x-slot:reset>

                    <x-slot:actions>
                        <div class="admin-data-control-group">
                            <span class="admin-data-control-label">Activity</span>
                            <x-admin.toolbar aria-label="Activity period">
                                @foreach ($periodOptions as $value => $label)
                                    <a
                                        class="admin-action {{ $period === $value ? 'is-primary' : '' }}"
                                        href="{{ $activityUrl(['period' => $value, 'search' => $search, 'area' => $area, 'family' => $family]) }}"
                                    >{{ $label }}</a>
                                @endforeach
                            </x-admin.toolbar>
                        </div>
                    </x-slot:actions>
                </x-admin.controls>
            </form>
        </section>

        <x-admin.table class="admin-table--data activity-workspace__table">
            @if ($activity !== [])
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Area</th>
                            <th scope="col">Change</th>
                            <th scope="col">Target</th>
                            <th scope="col">Publication</th>
                            <th scope="col">Actor</th>
                            <th scope="col">Time</th>
                            <th scope="col" class="admin-table__actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($activity as $event)
                            <tr>
                                <td>{{ $event['area'] }}</td>
                                <td>{{ $event['action'] }}</td>
                                <td class="admin-table__identity"><strong>{{ $event['target'] }}</strong></td>
                                <td class="activity-publication-cell">
                                    @if ($event['publication_status'] === 'committed')
                                        <span class="admin-status is-published">Committed</span>
                                        <small>Checkpoint #{{ $event['checkpoint_id'] }} · {{ $event['checkpoint_at'] }}</small>
                                        @if ($event['checkpoint_message'])
                                            <small title="{{ $event['checkpoint_message'] }}">{{ $event['checkpoint_message'] }}</small>
                                        @endif
                                    @elseif ($event['publication_status'] === 'pending')
                                        <span class="admin-status">Staged</span>
                                        <small>Pending checkpoint</small>
                                    @elseif ($event['publication_status'] === 'not_pending')
                                        <span class="admin-status">No pending delta</span>
                                        <small>Not checkpointed</small>
                                    @else
                                        <span class="activity-publication-cell__empty" aria-label="No publication state">—</span>
                                    @endif
                                </td>
                                <td>{{ $event['actor'] }}</td>
                                <td>
                                    <time datetime="{{ str_replace(' ', 'T', $event['timestamp']) }}" title="{{ $event['timestamp'] }}">{{ $event['when'] }}</time>
                                </td>
                                <td class="admin-table__actions">
                                    <x-admin.toolbar>
                                        @if ($event['undo'] !== null)
                                            <button
                                                class="admin-action"
                                                type="button"
                                                wire:click="undo({{ $event['undo']['id'] }})"
                                                wire:confirm="{{ $event['undo']['confirmation'] }}"
                                            >Undo</button>
                                        @endif
                                        @if ($event['url'] !== null)
                                            <a class="admin-action" href="{{ $event['url'] }}">Open</a>
                                        @endif
                                    </x-admin.toolbar>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <x-admin.empty-state kicker="No matches" title="No activity matches these filters">
                    <p>Change the period, search, editorial area or change type to widen the activity scope.</p>
                </x-admin.empty-state>
            @endif
        </x-admin.table>

        @if ($paginator->hasPages())
            <footer class="admin-pager" aria-label="Activity pagination">
                <span class="admin-pager__size">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
                <span class="admin-pager__range">{{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }}</span>
                <div class="admin-pager__actions admin-toolbar">
                    @if ($paginator->previousPageUrl())
                        <a class="admin-action" href="{{ $paginator->previousPageUrl() }}">Previous</a>
                    @else
                        <button class="admin-action" type="button" disabled>Previous</button>
                    @endif
                    @if ($paginator->nextPageUrl())
                        <a class="admin-action" href="{{ $paginator->nextPageUrl() }}">Next</a>
                    @else
                        <button class="admin-action" type="button" disabled>Next</button>
                    @endif
                </div>
            </footer>
        @endif
    </x-admin.workspace>
</x-filament-panels::page>
