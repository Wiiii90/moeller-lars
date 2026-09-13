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

        @php
            $activityUrl = static function (array $values): string {
                $query = array_filter(
                    $values,
                    static fn (mixed $value): bool => $value !== null && $value !== '',
                );

                return request()->url().($query === [] ? '' : '?'.http_build_query($query));
            };
            $stageQuery = [
                'period' => $period,
                'search' => $search,
                'area' => $area,
                'family' => $family,
            ];
            $calendarYearUrl = static fn (int $year): string => $activityUrl([
                ...$stageQuery,
                'calendar_year' => $year,
            ]);
            $calendarDateUrl = static fn (string $date): string => $activityUrl([
                ...$stageQuery,
                'calendar_year' => $calendarYear,
                'calendar_date' => $date,
            ]);
            $activitySourceExists = $paginator->total() > 0 || \App\Models\AuditEvent::query()
                ->where('occurred_at', '>=', now()->subDays(\App\Filament\Support\AdminActivityFeed::ACTIVITY_WINDOW_DAYS))
                ->exists();
            $clockVizConfig = [
                'kind' => 'activity-clock',
                'selected_label' => $selectedCalendarLabel,
                'buckets' => array_map(
                    static fn (array $bucket): array => [
                        'hour' => (int) ($bucket['hour'] ?? 0),
                        'count' => (int) ($bucket['count'] ?? 0),
                    ],
                    $clockActivity,
                ),
            ];
        @endphp

        <section
            class="activity-atlas"
            aria-label="Activity visualization"
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
                    this.timer = window.setInterval(() => { this.now = new Date() }, 1000)
                },
                destroy() {
                    if (this.timer !== null) {
                        window.clearInterval(this.timer)
                        this.timer = null
                    }
                },
                timeLabel() {
                    return this.timeFormatter.format(this.now)
                },
            }"
        >
            <div class="activity-atlas__grid admin-visual-stage admin-visual-stage--stackable" aria-label="Activity visualization">
                <div class="activity-atlas__visual admin-visual-stage__pane">
                    <div class="activity-atlas__view activity-calendar">
                        <div class="activity-calendar__header">
                            <div class="activity-calendar__year-nav" aria-label="Calendar year">
                                @if ($calendarPreviousYear !== null)
                                    <a
                                        class="admin-icon-action"
                                        href="{{ $calendarYearUrl($calendarPreviousYear) }}"
                                        wire:navigate
                                        aria-label="Previous year"
                                        title="Previous year"
                                    ><x-filament::icon icon="heroicon-m-chevron-left" /></a>
                                @else
                                    <span class="admin-icon-action is-disabled" aria-hidden="true"><x-filament::icon icon="heroicon-m-chevron-left" /></span>
                                @endif

                                <strong>{{ $calendarYear }}</strong>

                                @if ($calendarNextYear !== null)
                                    <a
                                        class="admin-icon-action"
                                        href="{{ $calendarYearUrl($calendarNextYear) }}"
                                        wire:navigate
                                        aria-label="Next year"
                                        title="Next year"
                                    ><x-filament::icon icon="heroicon-m-chevron-right" /></a>
                                @else
                                    <span class="admin-icon-action is-disabled" aria-hidden="true"><x-filament::icon icon="heroicon-m-chevron-right" /></span>
                                @endif
                            </div>
                        </div>

                        <div class="activity-calendar__bands" role="grid" aria-label="Daily activity density for {{ $calendarYear }}">
                            @foreach ($calendarBands as $band)
                                @php
                                    $bandDays = array_merge([], ...$band);
                                    $monthMarkers = [];
                                    foreach ($band as $weekIndex => $week) {
                                        foreach ($week as $day) {
                                            if ($day === null || substr($day['date'], -2) !== '01') {
                                                continue;
                                            }
                                            $monthMarkers[] = [
                                                'week' => $weekIndex + 1,
                                                'label' => \Carbon\CarbonImmutable::parse($day['date'])->format('M'),
                                            ];
                                        }
                                    }
                                @endphp
                                <div class="activity-calendar__band" style="--activity-calendar-weeks: {{ count($band) }};">
                                    <span class="activity-calendar__month-spacer" aria-hidden="true"></span>
                                    <div class="activity-calendar__months" aria-hidden="true">
                                        @foreach ($monthMarkers as $month)
                                            <span style="grid-column: {{ $month['week'] }};">{{ $month['label'] }}</span>
                                        @endforeach
                                    </div>
                                    <div class="activity-calendar__weekdays" aria-hidden="true">
                                        @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday)
                                            <span>{{ $weekday }}</span>
                                        @endforeach
                                    </div>
                                    <div class="activity-calendar__cells">
                                        @foreach ($bandDays as $day)
                                            @if ($day === null)
                                                <span class="activity-calendar__day is-outside" aria-hidden="true"></span>
                                            @elseif ($day['future'])
                                                <span
                                                    class="activity-calendar__day is-level-0 is-future"
                                                    aria-label="{{ $day['label'] }}"
                                                    title="{{ $day['label'] }}"
                                                ></span>
                                            @else
                                                <a
                                                    class="activity-calendar__day is-level-{{ $day['level'] }} {{ $day['selected'] ? 'is-selected' : '' }} {{ $day['today'] ? 'is-today' : '' }}"
                                                    href="{{ $calendarDateUrl($day['date']) }}"
                                                    wire:navigate
                                                    role="gridcell"
                                                    aria-current="{{ $day['selected'] ? 'date' : 'false' }}"
                                                    aria-label="{{ $day['label'] }}: {{ $day['count'] }} changes"
                                                    title="{{ $day['label'] }} · {{ $day['count'] }} changes"
                                                ></a>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="activity-calendar__legend" aria-hidden="true">
                            <span>Less</span>
                            @foreach (range(0, 4) as $level)
                                <i class="activity-calendar__day is-level-{{ $level }}"></i>
                            @endforeach
                            <span>More</span>
                        </div>
                    </div>

                    <div class="activity-atlas__view activity-clock">
                        <div class="activity-clock__figure">
                            <div
                                class="activity-clock__chart"
                                data-admin-viz="activity-clock"
                                role="img"
                                aria-label="Live local clock and activity distribution for {{ $selectedCalendarLabel }}"
                            >
                                <script type="application/json" data-admin-viz-config>@json($clockVizConfig)</script>
                            </div>
                        </div>

                        <div class="activity-clock__caption" aria-label="Selected day and live local time">
                            <strong>{{ $selectedCalendarLabel }}</strong>
                            <span>Live local time · <time x-text="timeLabel()">—</time></span>
                        </div>
                    </div>
                </div>

                <aside class="activity-publication admin-visual-stage__pane" aria-label="Publication context">
                    <header class="activity-publication__header">
                        <strong>Publication</strong>
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
                </aside>
            </div>

            <form method="get" action="{{ request()->url() }}" class="admin-visual-stage-followup">
                <input type="hidden" name="period" value="{{ $period }}">
                <input type="hidden" name="calendar_year" value="{{ $calendarYear }}">
                <input type="hidden" name="calendar_date" value="{{ $selectedCalendarDate }}">
                <x-admin.controls class="activity-workspace__controls" aria-label="Activity controls">
                    <x-slot:search>
                        <label class="admin-data-field">
                            <span>Search</span>
                            <input
                                type="search"
                                name="search"
                                value="{{ $search }}"
                                placeholder="Search change or actor"
                                autocomplete="off"
                                x-data
                                x-on:input.debounce.350ms="$el.form.requestSubmit()"
                            >
                        </label>
                    </x-slot:search>

                    <x-slot:filters>
                        <label class="admin-data-field">
                            <span>Editorial area</span>
                            <select name="area" x-on:change="$el.form.requestSubmit()">
                                <option value="">All areas</option>
                                @foreach ($areaOptions as $value => $label)
                                    <option value="{{ $value }}" @selected($area === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="admin-data-field">
                            <span>Change type</span>
                            <select name="family" x-on:change="$el.form.requestSubmit()">
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
                            <a class="admin-action" href="{{ $activityUrl(['period' => $period, 'calendar_year' => $calendarYear, 'calendar_date' => $selectedCalendarDate]) }}">Reset</a>
                        </div>
                    </x-slot:reset>

                    <x-slot:actions>
                        <div class="admin-data-control-group">
                            <span class="admin-data-control-label">Range</span>
                            <x-admin.toolbar aria-label="Activity period">
                                @foreach ($periodOptions as $value => $label)
                                    <a
                                        class="admin-action {{ $period === $value ? 'is-primary' : '' }}"
                                        href="{{ $activityUrl(['period' => $value, 'search' => $search, 'area' => $area, 'family' => $family, 'calendar_year' => $calendarYear, 'calendar_date' => $selectedCalendarDate]) }}"
                                    >{{ $label }}</a>
                                @endforeach
                            </x-admin.toolbar>
                        </div>
                    </x-slot:actions>
                </x-admin.controls>
            </form>
        </section>

        <x-admin.table class="admin-table--data activity-workspace__table">
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
                    @forelse ($activity as $event)
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
                                    @else
                                        <button class="admin-action" type="button" disabled>Undo</button>
                                    @endif

                                    @if ($event['url'] !== null)
                                        <a class="admin-action" href="{{ $event['url'] }}">Open</a>
                                    @else
                                        <button class="admin-action" type="button" disabled>Open</button>
                                    @endif
                                </x-admin.toolbar>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="admin-table__empty-cell" colspan="7">
                                @if ($activitySourceExists)
                                    <x-admin.empty-state title="No matching activity" minimal>
                                        <x-slot:actions>
                                            <a class="admin-action" href="{{ $activityUrl([]) }}">Clear filters</a>
                                        </x-slot:actions>
                                    </x-admin.empty-state>
                                @else
                                    <x-admin.empty-state title="No activity yet" minimal />
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </x-admin.table>

        @if ($paginator->hasPages())
            <footer class="admin-pager" aria-label="Activity pagination">
                <span class="admin-pager__meta">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
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
