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
            $activitySourceExists = $paginator->total() > 0 || \App\Models\AuditEvent::query()
                ->where('occurred_at', '>=', now()->subDays(\App\Filament\Support\AdminActivityFeed::ACTIVITY_WINDOW_DAYS))
                ->exists();
        @endphp

        <section
            class="activity-atlas"
            aria-label="Activity visualization"
            x-data="{
                mode: 'clock',
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
            <div class="activity-atlas__grid admin-visual-stage admin-visual-stage--stackable" aria-label="Activity visualization">
                <div class="activity-atlas__visual admin-visual-stage__pane">
                    <header class="activity-atlas__visual-header">
                        <div>
                            <strong x-text="mode === 'clock' ? 'Clock' : 'Calendar'">Clock</strong>
                            <span>{{ $selectedPeriodLabel }} · {{ number_format($activityMetrics['changes']) }} matching changes</span>
                        </div>

                        <x-admin.toolbar class="activity-atlas__mode" aria-label="Activity visualization mode">
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

                    <div class="activity-atlas__view activity-clock" x-show="mode === 'clock'">
                        <div class="activity-clock__figure">
                            <svg
                                class="activity-clock__dial"
                                viewBox="0 0 320 320"
                                role="img"
                                x-bind:aria-label="`Current local time ${timeLabel()}`"
                            >
                                <circle class="activity-clock__activity-track" cx="160" cy="160" r="134" />
                                @foreach ($clockActivity as $bucket)
                                    @php
                                        $activityRatio = $clockPeakCount > 0 ? sqrt($bucket['count'] / $clockPeakCount) : 0;
                                        $activityOpacity = $bucket['count'] > 0 ? 0.28 + (0.72 * $activityRatio) : 0.12;
                                    @endphp
                                    <line
                                        class="activity-clock__activity-tick {{ $bucket['hour'] === $clockPeakHour ? 'is-peak' : '' }}"
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

                        <div class="activity-clock__meta" aria-label="Clock context">
                            <div>
                                <span>Current time</span>
                                <strong x-text="timeLabel()">—</strong>
                            </div>
                            <div>
                                <span>Peak activity</span>
                                <strong>
                                    @if ($clockPeakHour !== null)
                                        {{ str_pad((string) $clockPeakHour, 2, '0', STR_PAD_LEFT) }}:00 · {{ number_format($clockPeakCount) }}
                                    @else
                                        —
                                    @endif
                                </strong>
                            </div>
                        </div>
                    </div>

                    <div class="activity-atlas__view activity-calendar" x-show="mode === 'calendar'" x-cloak>
                        <div class="activity-calendar__summary">
                            <span>{{ $calendarLabel }}</span>
                            <strong>{{ number_format($calendarActiveDays) }} active days · peak {{ number_format($calendarMaximum) }}</strong>
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
                            <a class="admin-action" href="{{ $activityUrl(['period' => $period]) }}">Reset</a>
                        </div>
                    </x-slot:reset>

                    <x-slot:actions>
                        <div class="admin-data-control-group">
                            <span class="admin-data-control-label">Range</span>
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
