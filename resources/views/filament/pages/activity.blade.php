<x-filament-panels::page>
    <x-admin.workspace title="Activity" class="activity-workspace">
        @php
            $activityBaseUrl = \App\Filament\Pages\Activity::getUrl();
            $currentQuery = [
                'view' => $viewMode === 'commits' ? 'commits' : null,
                'search' => $search,
                'area' => $area,
                'family' => $family,
                'calendar_year' => $calendarYear,
                'calendar_date' => $activeDate,
                'hour' => $activeHour,
                'per_page' => $perPage,
            ];
            $activityUrl = static function (array $values = []) use ($currentQuery, $activityBaseUrl): string {
                $query = array_filter(
                    array_replace($currentQuery, $values),
                    static fn (mixed $value): bool => $value !== null && $value !== '',
                );

                return $activityBaseUrl.($query === [] ? '' : '?'.http_build_query($query));
            };
            $liveCommit = $publicationContext['latest'];
            $calendarYearUrl = static fn (int $year): string => $activityUrl([
                'calendar_year' => $year,
                'calendar_date' => null,
                'page' => null,
                'commits_page' => null,
            ]);
            $calendarDateUrl = static fn (string $date): string => $activityUrl([
                'calendar_year' => $calendarYear,
                'calendar_date' => $date,
                'page' => null,
                'commits_page' => null,
            ]);
            $hourUrls = [];
            foreach (range(0, 23) as $hour) {
                $hourUrls[$hour] = $activityUrl([
                    'calendar_year' => $calendarYear,
                    'calendar_date' => $selectedCalendarDate,
                    'hour' => $hour,
                    'page' => null,
                    'commits_page' => null,
                ]);
            }
            $pageSizeUrls = [];
            foreach ($pageSizes as $sizeOption) {
                $pageSizeUrls[$sizeOption] = $activityUrl([
                    'per_page' => $sizeOption,
                    'page' => null,
                    'commits_page' => null,
                ]);
            }
            $visiblePublicationGroups = array_slice($publicationContext['staged_groups'], 0, 4);
            $hiddenPublicationGroups = max(0, count($publicationContext['staged_groups']) - count($visiblePublicationGroups));
            $resetUrl = $activityUrl([
                'search' => null,
                'area' => null,
                'family' => null,
                'calendar_date' => null,
                'hour' => null,
                'page' => null,
                'commits_page' => null,
            ]);
        @endphp

        <x-admin.metrics :columns="6" aria-label="{{ $viewMode === 'commits' ? 'Commit statistics' : 'Activity statistics' }}">
            @foreach ($workspaceMetrics as $metric)
                <x-admin.metric :label="$metric['label']" :value="$metric['value']" :description="$metric['description']" />
            @endforeach
        </x-admin.metrics>

        <section class="activity-atlas" aria-label="{{ $viewMode === 'commits' ? 'Commit timeline' : 'Activity timeline' }}">
            <div class="activity-atlas__grid admin-visual-stage admin-visual-stage--stackable" aria-label="{{ $viewMode === 'commits' ? 'Commit timeline' : 'Activity timeline' }}">
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

                        <div class="activity-calendar__bands" role="grid" aria-label="Daily {{ $timelineItemLabel }} density for {{ $calendarYear }}">
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
                                                    aria-label="Filter {{ $day['label'] }}: {{ $day['count'] }} {{ $timelineItemLabel }}"
                                                    title="{{ $day['label'] }} · {{ $day['count'] }} {{ $timelineItemLabel }}"
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
                        :aria-context="$timelineItemLabel.' distribution for '.$selectedCalendarLabel"
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

                    <div class="activity-publication__actions">
                        <x-admin.toolbar>
                            @if ($publicationContext['staged'] > 0)
                                <button class="admin-action" type="button" wire:click="openPublicationReview">Review changes</button>
                                <button
                                    class="admin-action"
                                    type="button"
                                    wire:click="resetStagedChanges"
                                    wire:confirm="Reset all staged changes? The working state will be restored exactly to the current LIVE version. Activity history is preserved."
                                >Reset</button>
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
                </aside>
            </div>

            <form method="get" action="{{ $activityBaseUrl }}" class="admin-visual-stage-followup">
                @if ($viewMode === 'commits')
                    <input type="hidden" name="view" value="commits">
                @endif
                <input type="hidden" name="calendar_year" value="{{ $calendarYear }}">

                <x-admin.controls class="activity-workspace__controls" aria-label="Activity controls">
                    <x-slot:search>
                        <label class="admin-data-field activity-control--search">
                            <span>Search</span>
                            <input
                                type="search"
                                name="search"
                                value="{{ $search }}"
                                placeholder="{{ $viewMode === 'commits' ? 'Hash, message, change or actor' : 'Change or actor' }}"
                                autocomplete="off"
                                x-data
                                x-on:input.debounce.350ms="$el.form.requestSubmit()"
                            >
                        </label>
                    </x-slot:search>

                    <x-slot:filters>
                        <label class="admin-data-field activity-control--area">
                            <span>Editorial area</span>
                            <select name="area" x-on:change="$el.form.requestSubmit()">
                                <option value="">All areas</option>
                                @foreach ($areaOptions as $value => $label)
                                    <option value="{{ $value }}" @selected($area === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="admin-data-field activity-control--type">
                            <span>Change type</span>
                            <select name="family" x-on:change="$el.form.requestSubmit()">
                                <option value="">All changes</option>
                                @foreach ($familyOptions as $value => $label)
                                    <option value="{{ $value }}" @selected($family === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="admin-data-field activity-control--date">
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
                        <label class="admin-data-field activity-control--time">
                            <span>Time</span>
                            <select name="hour" x-on:change="$el.form.requestSubmit()">
                                <option value="">All times</option>
                                @foreach (range(0, 23) as $hour)
                                    <option value="{{ $hour }}" @selected($activeHour === $hour)>{{ str_pad((string) $hour, 2, '0', STR_PAD_LEFT) }}:00–{{ str_pad((string) (($hour + 1) % 24), 2, '0', STR_PAD_LEFT) }}:00</option>
                                @endforeach
                            </select>
                        </label>
                    </x-slot:filters>

                    <x-slot:reset>
                        <div class="admin-data-control-group activity-control--reset">
                            <span class="admin-data-control-label">Filter</span>
                            <a class="admin-action" href="{{ $resetUrl }}">Reset</a>
                        </div>
                    </x-slot:reset>

                    <x-slot:actions>
                        <div class="admin-data-control-group activity-control--view">
                            <span class="admin-data-control-label">View</span>
                            <div class="admin-toolbar" role="group" aria-label="Activity view">
                                <a
                                    class="admin-action {{ $viewMode === 'activity' ? 'is-primary' : '' }}"
                                    href="{{ $activityUrl(['view' => null, 'page' => null, 'commits_page' => null]) }}"
                                >Activity</a>
                                <a
                                    class="admin-action {{ $viewMode === 'commits' ? 'is-primary' : '' }}"
                                    href="{{ $activityUrl(['view' => 'commits', 'page' => null, 'commits_page' => null]) }}"
                                >Commits</a>
                            </div>
                        </div>
                    </x-slot:actions>
                </x-admin.controls>
            </form>
        </section>

        @if ($viewMode === 'activity')
            <x-admin.table class="admin-table--data activity-workspace__table activity-events-table">
                <table>
                    <colgroup>
                        <col class="activity-col--change">
                        <col class="activity-col--who">
                        <col class="activity-col--when">
                        <col class="activity-col--target">
                        <col class="activity-col--area">
                        <col class="activity-col--type">
                        <col class="activity-col--publication">
                        <col class="activity-col--actions">
                    </colgroup>
                    <thead>
                        <tr>
                            <th scope="col">Change</th>
                            <th scope="col">Who</th>
                            <th scope="col">When</th>
                            <th scope="col">Target</th>
                            <th scope="col">Area</th>
                            <th scope="col">Type</th>
                            <th scope="col">Publication</th>
                            <th scope="col" class="admin-table__actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($activity as $event)
                            <tr>
                                <td class="activity-change-cell" title="{{ $event['action'] }}">
                                    <strong>{{ $event['action'] }}</strong>
                                </td>
                                <td class="activity-who-cell" title="{{ $event['actor'] }}">
                                    <strong>{{ $event['actor'] }}</strong>
                                </td>
                                <td class="activity-when-cell">
                                    <time datetime="{{ str_replace(' ', 'T', $event['timestamp']) }}" title="{{ $event['timestamp'] }}">{{ $event['when'] }}</time>
                                    <small>{{ $event['timestamp'] }}</small>
                                </td>
                                <td class="activity-target-cell" title="{{ $event['target'] }}">
                                    <strong>{{ $event['target'] }}</strong>
                                </td>
                                <td class="activity-area-cell">
                                    <span>{{ $event['area'] }}</span>
                                </td>
                                <td class="activity-type-cell">
                                    <span>{{ $familyOptions[$event['family']] ?? ucfirst($event['family']) }}</span>
                                </td>
                                <td class="activity-publication-cell">
                                    @if ($event['publication_status'] === 'committed')
                                        <div class="activity-publication-cell__stack" title="Commit {{ $event['checkpoint_short_hash'] ?? '#'.$event['checkpoint_id'] }} · {{ $event['checkpoint_at'] }}{{ $event['checkpoint_message'] ? ' · '.$event['checkpoint_message'] : '' }}">
                                            <span class="admin-status is-published">Committed</span>
                                            <code>{{ $event['checkpoint_short_hash'] ?? '#'.$event['checkpoint_id'] }}</code>
                                        </div>
                                    @elseif ($event['publication_status'] === 'pending')
                                        <span class="admin-status" title="Included in next publish">Staged</span>
                                    @elseif ($event['publication_status'] === 'not_pending')
                                        <span class="admin-status" title="Later changes neutralized this event">No staged delta</span>
                                    @else
                                        <span class="activity-publication-cell__empty" aria-label="No publication state">—</span>
                                    @endif
                                </td>
                                <td class="admin-table__actions activity-actions-cell">
                                    <x-admin.toolbar>
                                        <button class="admin-action" type="button" wire:click="mountAction('activityDetails', { id: {{ $event['id'] }} })">
                                            <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Details->mini()" class="admin-action__icon" />
                                            <span class="admin-action__label">Details</span>
                                        </button>
                                        @if (is_array($event['undo'] ?? null))
                                            <button
                                                class="admin-action"
                                                type="button"
                                                wire:click="undo({{ $event['undo']['id'] }})"
                                                wire:confirm="{{ $event['undo']['confirmation'] }}"
                                            >
                                                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Refresh->mini()" class="admin-action__icon" />
                                                <span class="admin-action__label">Undo</span>
                                            </button>
                                        @endif
                                    </x-admin.toolbar>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="admin-table__empty-cell" colspan="8">
                                    @if ($activitySourceExists)
                                        <x-admin.empty-state title="No matching activity" minimal>
                                            <x-slot:actions>
                                                <a class="admin-action" href="{{ $resetUrl }}">Clear filters</a>
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

            @if ($paginator !== null)
                <footer class="admin-pager" aria-label="Activity pagination">
                    <div class="admin-pager__leading">
                        <span class="admin-pager__meta">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
                        <x-admin.page-size-picker :value="$perPage" :options="$pageSizes" :urls="$pageSizeUrls" />
                    </div>
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
            <x-admin.table class="admin-table--data activity-workspace__table activity-commits-table">
                <table>
                    <colgroup>
                        <col class="activity-commit-col--commit">
                        <col class="activity-commit-col--who">
                        <col class="activity-commit-col--when">
                        <col class="activity-commit-col--summary">
                        <col class="activity-commit-col--publication">
                        <col class="activity-commit-col--actions">
                    </colgroup>
                    <thead>
                        <tr>
                            <th scope="col">Commit</th>
                            <th scope="col">Who</th>
                            <th scope="col">When</th>
                            <th scope="col">Summary</th>
                            <th scope="col">Publication</th>
                            <th scope="col" class="admin-table__actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($commits as $commit)
                            <tr>
                                <td class="activity-commit-cell">
                                    <div class="activity-commit-cell__heading">
                                        <code>{{ $commit['short_hash'] }}</code>
                                        @if ($commit['live'])
                                            <span class="admin-status is-published">LIVE</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="activity-who-cell">
                                    <strong>{{ $commit['actor'] }}</strong>
                                </td>
                                <td class="activity-when-cell">
                                    <time datetime="{{ str_replace(' ', 'T', $commit['timestamp']) }}" title="{{ $commit['timestamp'] }}">{{ $commit['when'] }}</time>
                                    <small>{{ $commit['timestamp'] }}</small>
                                </td>
                                <td class="activity-commit-summary">
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
                                    <small>{{ number_format($commit['change_count']) }} changes · {{ number_format($commit['event_count']) }} events</small>
                                </td>
                                <td class="admin-table__actions activity-actions-cell">
                                    <x-admin.toolbar>
                                        <button class="admin-action" type="button" wire:click="openCommitDetails({{ $commit['id'] }})">
                                            <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Details->mini()" class="admin-action__icon" />
                                            <span class="admin-action__label">Details</span>
                                        </button>
                                        @if ($commit['can_restore'])
                                            <button
                                                class="admin-action"
                                                type="button"
                                                wire:click="restoreVersion({{ $commit['id'] }})"
                                                wire:confirm="Restore version {{ $commit['short_hash'] }} to the working state? This replaces all current staged work. The LIVE site will not change until you commit."
                                            >Restore</button>
                                        @endif
                                        @if ($commit['can_revert'])
                                            <button
                                                class="admin-action"
                                                type="button"
                                                wire:click="revertCurrentCommit"
                                                wire:confirm="Revert the current LIVE commit {{ $commit['short_hash'] }}? Its parent version will be loaded into the working state for review. Nothing is published until you commit."
                                            >Revert</button>
                                        @endif
                                    </x-admin.toolbar>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="admin-table__empty-cell" colspan="6">
                                    @if ($activitySourceExists)
                                        <x-admin.empty-state title="No matching commits" minimal>
                                            <x-slot:actions>
                                                <a class="admin-action" href="{{ $resetUrl }}">Clear filters</a>
                                            </x-slot:actions>
                                        </x-admin.empty-state>
                                    @else
                                        <x-admin.empty-state title="No commits yet" minimal />
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </x-admin.table>

            @if ($commitPaginator !== null)
                <footer class="admin-pager" aria-label="Commit pagination">
                    <div class="admin-pager__leading">
                        <span class="admin-pager__meta">Page {{ $commitPaginator->currentPage() }} of {{ $commitPaginator->lastPage() }}</span>
                        <x-admin.page-size-picker :value="$perPage" :options="$pageSizes" :urls="$pageSizeUrls" />
                    </div>
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
