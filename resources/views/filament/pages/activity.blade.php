<x-filament-panels::page>
    <x-admin.workspace title="Activity" class="activity-workspace">
        @php
            $activityUrl = static function (array $values): string {
                $query = array_filter(
                    $values,
                    static fn (mixed $value): bool => $value !== null && $value !== '',
                );

                return request()->url().($query === [] ? '' : '?'.http_build_query($query));
            };
            $liveCommit = $publicationContext['latest'];
        @endphp

        <div class="activity-view-switch admin-toolbar" aria-label="Activity view">
            <a class="admin-action {{ $viewMode === 'activity' ? 'is-primary' : '' }}" href="{{ $activityUrl([]) }}" wire:navigate>Activity</a>
            <a class="admin-action {{ $viewMode === 'commits' ? 'is-primary' : '' }}" href="{{ $activityUrl(['view' => 'commits']) }}" wire:navigate>Commits</a>
        </div>

        @if ($viewMode === 'activity')
            <x-admin.metrics :columns="6" aria-label="Activity statistics">
                <x-admin.metric label="Changes" :value="number_format($activityMetrics['changes'])">Matching filters</x-admin.metric>
                <x-admin.metric label="Active days" :value="number_format($activityMetrics['active_days'])">Days with matching activity</x-admin.metric>
                <x-admin.metric label="Areas" :value="number_format($activityMetrics['areas'])">Matching editorial areas</x-admin.metric>
                <x-admin.metric label="Change types" :value="number_format($activityMetrics['families'])">Matching change families</x-admin.metric>
                <x-admin.metric label="Actors" :value="number_format($activityMetrics['actors'])">Matching admins</x-admin.metric>
                <x-admin.metric label="Latest" :value="$activityMetrics['latest_when']" :description="$activityMetrics['latest_at'] ?? 'No matching activity'" />
            </x-admin.metrics>

            @php
                $stageQuery = [
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
                $hourUrls = [];
                foreach (range(0, 23) as $hour) {
                    $hourUrls[$hour] = $activityUrl([
                        ...$stageQuery,
                        'calendar_year' => $calendarYear,
                        'calendar_date' => $selectedCalendarDate,
                        'hour' => $hour,
                    ]);
                }
                $visiblePublicationGroups = array_slice($publicationContext['staged_groups'], 0, 4);
                $hiddenPublicationGroups = max(0, count($publicationContext['staged_groups']) - count($visiblePublicationGroups));
            @endphp

            <section class="activity-atlas" aria-label="Activity visualization">
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
                                                        class="activity-calendar__day is-level-{{ $day['level'] }} {{ $day['selected'] ? 'is-selected' : '' }} {{ $day['filtered'] ? 'is-filtered' : '' }} {{ $day['today'] ? 'is-today' : '' }}"
                                                        href="{{ $calendarDateUrl($day['date']) }}"
                                                        wire:navigate
                                                        role="gridcell"
                                                        aria-current="{{ $day['filtered'] ? 'date' : 'false' }}"
                                                        aria-label="Filter {{ $day['label'] }}: {{ $day['count'] }} changes"
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

                        <x-admin.activity-clock-visual
                            class="activity-atlas__view"
                            :activity="$clockActivity"
                            :peak-count="$clockPeakCount"
                            :peak-hour="$clockPeakHour"
                            :selected-hour="$activeHour"
                            :hour-urls="$hourUrls"
                            :caption-label="$selectedCalendarLabel"
                            :aria-context="'activity distribution for '.$selectedCalendarLabel"
                        />
                    </div>

                    <aside class="activity-publication admin-visual-stage__pane" aria-label="Next publication">
                        <header class="activity-publication__header">
                            <strong>Next publication</strong>
                        </header>

                        <div class="activity-publication__staged">
                            <span>Pending changes</span>
                            <strong>{{ number_format($publicationContext['staged']) }}</strong>
                            <small>
                                Working state compared with the current live snapshot
                                @if ($publicationContext['staged_events'] > 0)
                                    · {{ number_format($publicationContext['staged_events']) }} related activity events
                                @endif
                            </small>
                        </div>

                        @if ($visiblePublicationGroups !== [])
                            <div class="activity-publication__recent activity-publication__groups">
                                <span>What will publish</span>
                                @foreach ($visiblePublicationGroups as $group)
                                    <article>
                                        <div>
                                            <strong>{{ $group['area'] }}</strong>
                                            <span>{{ number_format($group['count']) }}</span>
                                        </div>
                                        <small>{{ $group['entity'] }}</small>
                                    </article>
                                @endforeach
                                @if ($hiddenPublicationGroups > 0)
                                    <small>+ {{ number_format($hiddenPublicationGroups) }} more group{{ $hiddenPublicationGroups === 1 ? '' : 's' }}</small>
                                @endif
                            </div>
                        @endif

                        <div class="activity-publication__readiness is-{{ $publicationContext['preflight']['status'] }}">
                            <span>Preflight</span>
                            <strong>{{ $publicationContext['preflight']['label'] }}</strong>
                            @foreach (array_slice($publicationContext['preflight']['blockers'], 0, 2) as $blocker)
                                <small>{{ $blocker }}</small>
                            @endforeach
                            @if ($publicationContext['staged'] > 0)
                                <x-admin.toolbar>
                                    <button class="admin-action" type="button" wire:click="openPublicationReview">Review changes</button>
                                </x-admin.toolbar>
                            @endif
                        </div>

                        @if ($liveCommit)
                            <div class="activity-publication__latest">
                                <span>Current live version</span>
                                <strong>{{ $liveCommit['short_hash'] ?? '#'.$liveCommit['id'] }} · {{ $liveCommit['when'] }}</strong>
                                <small>{{ number_format($liveCommit['change_count']) }} changes · {{ $liveCommit['timestamp'] }}</small>
                                <p title="{{ $liveCommit['message'] ?? 'No commit message' }}">{{ $liveCommit['message'] ?? 'No commit message' }}</p>
                            </div>
                        @else
                            <div class="activity-publication__latest is-empty">
                                <span>Current live version</span>
                                <strong>No commits yet</strong>
                            </div>
                        @endif
                    </aside>
                </div>

                <form method="get" action="{{ request()->url() }}" class="admin-visual-stage-followup">
                    <input type="hidden" name="calendar_year" value="{{ $calendarYear }}">
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
                            <label class="admin-data-field">
                                <span>Date</span>
                                <input
                                    type="date"
                                    name="calendar_date"
                                    value="{{ $activeDate ?? '' }}"
                                    min="2000-01-01"
                                    max="{{ $todayDate }}"
                                    x-on:change="$el.form.requestSubmit()"
                                >
                            </label>
                            <label class="admin-data-field">
                                <span>Time</span>
                                <select name="hour" x-on:change="$el.form.requestSubmit()" @disabled($activeDate === null)>
                                    <option value="">All times</option>
                                    @foreach (range(0, 23) as $hour)
                                        <option value="{{ $hour }}" @selected($activeHour === $hour)>{{ str_pad((string) $hour, 2, '0', STR_PAD_LEFT) }}:00–{{ str_pad((string) (($hour + 1) % 24), 2, '0', STR_PAD_LEFT) }}:00</option>
                                    @endforeach
                                </select>
                            </label>
                        </x-slot:filters>

                        <x-slot:reset>
                            <div class="admin-data-control-group">
                                <span class="admin-data-control-label">Filter</span>
                                <a class="admin-action" href="{{ $activityUrl(['calendar_year' => $calendarYear]) }}">Reset</a>
                            </div>
                        </x-slot:reset>
                    </x-admin.controls>
                </form>
            </section>

            <x-admin.table class="admin-table--data activity-workspace__table">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Activity</th>
                            <th scope="col">Publication</th>
                            <th scope="col">Who / when</th>
                            <th scope="col" class="admin-table__actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($activity as $event)
                            <tr>
                                <td class="admin-table__identity activity-event-cell">
                                    <strong>{{ $event['action'] }}</strong>
                                    <span>{{ $event['target'] }}</span>
                                    <small>{{ $event['area'] }} · {{ $event['family'] }}</small>
                                </td>
                                <td class="activity-publication-cell">
                                    @if ($event['publication_status'] === 'committed')
                                        <span class="admin-status is-published">Committed</span>
                                        <small>Commit #{{ $event['checkpoint_id'] }} · {{ $event['checkpoint_at'] }}</small>
                                        @if ($event['checkpoint_message'])
                                            <small title="{{ $event['checkpoint_message'] }}">{{ $event['checkpoint_message'] }}</small>
                                        @endif
                                    @elseif ($event['publication_status'] === 'pending')
                                        <span class="admin-status">Staged</span>
                                        <small>Included in next publish</small>
                                    @elseif ($event['publication_status'] === 'not_pending')
                                        <span class="admin-status">No staged delta</span>
                                        <small>Later changes neutralized this event</small>
                                    @else
                                        <span class="activity-publication-cell__empty" aria-label="No publication state">—</span>
                                    @endif
                                </td>
                                <td class="activity-event-meta">
                                    <strong>{{ $event['actor'] }}</strong>
                                    <time datetime="{{ str_replace(' ', 'T', $event['timestamp']) }}" title="{{ $event['timestamp'] }}">{{ $event['when'] }}</time>
                                </td>
                                <td class="admin-table__actions">
                                    <x-admin.toolbar>
                                        <button class="admin-action" type="button" wire:click="openActivityDetails({{ $event['id'] }})">Details</button>
                                        @if ($event['url'] !== null)
                                            <a class="admin-action" href="{{ $event['url'] }}">Open record</a>
                                        @endif
                                    </x-admin.toolbar>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="admin-table__empty-cell" colspan="4">
                                    @if ($activitySourceExists)
                                        <x-admin.empty-state title="No matching activity" minimal>
                                            <x-slot:actions>
                                                <a class="admin-action" href="{{ $activityUrl(['calendar_year' => $calendarYear]) }}">Clear filters</a>
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
        @else
            <x-admin.metrics :columns="6" aria-label="Publication commit statistics">
                <x-admin.metric label="Staged" :value="number_format($publicationContext['staged'])">Working vs live</x-admin.metric>
                <x-admin.metric label="Commits" :value="number_format($commitPaginator?->total() ?? 0)">Permanent history</x-admin.metric>
                <x-admin.metric label="Live" :value="$liveCommit['short_hash'] ?? '—'">Current public version</x-admin.metric>
                <x-admin.metric label="Live changes" :value="number_format($liveCommit['change_count'] ?? 0)">Rows in latest commit</x-admin.metric>
                <x-admin.metric label="Last commit" :value="$liveCommit['when'] ?? '—'" :description="$liveCommit['timestamp'] ?? 'No commits yet'" />
                <x-admin.metric label="Preflight" :value="$publicationContext['preflight']['label']">Next publication</x-admin.metric>
            </x-admin.metrics>

            <section class="activity-commit-stage admin-visual-stage admin-visual-stage--stackable" aria-label="Working changes">
                <div class="admin-visual-stage__pane activity-commit-stage__summary">
                    <span class="activity-commit-stage__eyebrow">Working changes</span>
                    <strong>{{ number_format($publicationContext['staged']) }} staged change{{ $publicationContext['staged'] === 1 ? '' : 's' }}</strong>
                    <p>The working database is compared directly with the current LIVE snapshot.</p>

                    @if ($publicationContext['staged_groups'] !== [])
                        <div class="activity-commit-stage__groups">
                            @foreach ($publicationContext['staged_groups'] as $group)
                                <span><strong>{{ $group['area'] }}</strong> · {{ $group['entity'] }} · {{ number_format($group['count']) }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="admin-visual-stage__pane activity-commit-stage__actions">
                    <span class="activity-commit-stage__eyebrow">{{ $publicationContext['preflight']['label'] }}</span>
                    @foreach ($publicationContext['preflight']['blockers'] as $blocker)
                        <small>{{ $blocker }}</small>
                    @endforeach

                    <x-admin.toolbar>
                        @if ($publicationContext['staged'] > 0)
                            <button class="admin-action" type="button" wire:click="openPublicationReview">Review changes</button>
                            <button
                                class="admin-action"
                                type="button"
                                wire:click="resetStagedChanges"
                                wire:confirm="Reset all staged changes? The working state will be restored exactly to the current LIVE version. Activity history is preserved."
                            >Reset staged changes</button>
                        @endif
                        <button
                            class="admin-action is-primary"
                            type="button"
                            x-data
                            x-on:click="$dispatch('publication-commit')"
                            @disabled($publicationContext['staged'] < 1 || $publicationContext['preflight']['status'] !== 'ready')
                        >Commit</button>
                    </x-admin.toolbar>
                </div>
            </section>

            <x-admin.table class="admin-table--data activity-workspace__table activity-commits-table">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Commit</th>
                            <th scope="col">Publication</th>
                            <th scope="col">Who / when</th>
                            <th scope="col" class="admin-table__actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($commits as $commit)
                            <tr>
                                <td class="admin-table__identity activity-commit-cell">
                                    <div class="activity-commit-cell__heading">
                                        <code>{{ $commit['short_hash'] }}</code>
                                        @if ($commit['live'])
                                            <span class="admin-status is-published">LIVE</span>
                                        @endif
                                    </div>
                                    <strong>{{ $commit['message'] ?? 'No commit message' }}</strong>
                                    <small>
                                        {{ $commit['operation_label'] }}
                                        @if ($commit['parent_short_hash'])
                                            · parent {{ $commit['parent_short_hash'] }}
                                        @endif
                                    </small>
                                </td>
                                <td class="activity-publication-cell">
                                    <span class="admin-status {{ $commit['restorable'] ? 'is-published' : '' }}">
                                        {{ $commit['restorable'] ? 'Restorable' : ($commit['legacy'] ? 'Metadata only' : 'Schema changed') }}
                                    </span>
                                    <small>{{ number_format($commit['change_count']) }} changes · {{ number_format($commit['event_count']) }} activity events</small>
                                </td>
                                <td class="activity-event-meta">
                                    <strong>{{ $commit['actor'] }}</strong>
                                    <time datetime="{{ str_replace(' ', 'T', $commit['timestamp']) }}" title="{{ $commit['timestamp'] }}">{{ $commit['when'] }}</time>
                                </td>
                                <td class="admin-table__actions">
                                    <x-admin.toolbar>
                                        <button class="admin-action" type="button" wire:click="openCommitDetails({{ $commit['id'] }})">Details</button>
                                        @if ($commit['can_restore'])
                                            <button
                                                class="admin-action"
                                                type="button"
                                                wire:click="restoreVersion({{ $commit['id'] }})"
                                                wire:confirm="Restore version {{ $commit['short_hash'] }} to the working state? This replaces all current staged work. The LIVE site will not change until you commit."
                                            >Restore this version</button>
                                        @endif
                                        @if ($commit['can_revert'])
                                            <button
                                                class="admin-action"
                                                type="button"
                                                wire:click="revertCurrentCommit"
                                                wire:confirm="Revert the current LIVE commit {{ $commit['short_hash'] }}? Its parent version will be loaded into the working state for review. Nothing is published until you commit."
                                            >Revert commit</button>
                                        @endif
                                    </x-admin.toolbar>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="admin-table__empty-cell" colspan="4">
                                    <x-admin.empty-state title="No commits yet" minimal />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </x-admin.table>

            @if ($commitPaginator?->hasPages())
                <footer class="admin-pager" aria-label="Commit pagination">
                    <span class="admin-pager__meta">Page {{ $commitPaginator->currentPage() }} of {{ $commitPaginator->lastPage() }}</span>
                    <span class="admin-pager__range">{{ $commitPaginator->firstItem() ?? 0 }}–{{ $commitPaginator->lastItem() ?? 0 }} of {{ $commitPaginator->total() }}</span>
                    <div class="admin-pager__actions admin-toolbar">
                        @if ($commitPaginator->previousPageUrl())
                            <a class="admin-action" href="{{ $commitPaginator->previousPageUrl() }}">Previous</a>
                        @else
                            <button class="admin-action" type="button" disabled>Previous</button>
                        @endif
                        @if ($commitPaginator->nextPageUrl())
                            <a class="admin-action" href="{{ $commitPaginator->nextPageUrl() }}">Next</a>
                        @else
                            <button class="admin-action" type="button" disabled>Next</button>
                        @endif
                    </div>
                </footer>
            @endif
        @endif
    </x-admin.workspace>
</x-filament-panels::page>
