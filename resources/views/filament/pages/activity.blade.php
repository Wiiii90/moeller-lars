<x-filament-panels::page>
    <x-admin.workspace title="Activity" class="activity-workspace">
        <x-admin.metrics :columns="6" aria-label="Activity statistics">
            <x-admin.metric label="Changes" :value="number_format($activityMetrics['changes'])">Matching changes</x-admin.metric>
            <x-admin.metric label="On page" :value="number_format($activityMetrics['on_page'])">Visible changes</x-admin.metric>
            <x-admin.metric label="Areas" :value="number_format($activityMetrics['areas'])">Visible editorial areas</x-admin.metric>
            <x-admin.metric label="Change types" :value="number_format($activityMetrics['families'])">Visible change families</x-admin.metric>
            <x-admin.metric label="Undoable" :value="number_format($activityMetrics['undoable'])">Undo available</x-admin.metric>
            <x-admin.metric
                label="Latest"
                :value="$activityMetrics['latest']['when'] ?? '—'"
                :description="$activityMetrics['latest']['timestamp'] ?? 'No visible activity'"
            />
        </x-admin.metrics>

        <section class="activity-time-visual" aria-label="Activity time visualization">
            <div class="activity-clock" aria-label="24-hour activity clock">
                <div class="activity-clock__face">
                    <span class="activity-clock__label activity-clock__label--00">00</span>
                    <span class="activity-clock__label activity-clock__label--06">06</span>
                    <span class="activity-clock__label activity-clock__label--12">12</span>
                    <span class="activity-clock__label activity-clock__label--18">18</span>
                    <span class="activity-clock__axis activity-clock__axis--vertical" aria-hidden="true"></span>
                    <span class="activity-clock__axis activity-clock__axis--horizontal" aria-hidden="true"></span>
                    @foreach ($clockActivity as $event)
                        <span
                            class="activity-clock__marker"
                            style="--activity-x: {{ number_format($event['clock_x'], 4, '.', '') }}%; --activity-y: {{ number_format($event['clock_y'], 4, '.', '') }}%"
                            role="img"
                            aria-label="{{ $event['action'] }} · {{ $event['timestamp'] }}"
                            title="{{ $event['action'] }} · {{ $event['timestamp'] }}"
                        ></span>
                    @endforeach
                </div>
            </div>

            <div class="activity-calendar" aria-label="Activity calendar for {{ $calendarLabel }}">
                <strong class="activity-calendar__month">{{ $calendarLabel }}</strong>
                <div class="activity-calendar__weekdays" aria-hidden="true">
                    @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday)
                        <span>{{ $weekday }}</span>
                    @endforeach
                </div>
                <div class="activity-calendar__grid">
                    @foreach ($calendarDays as $day)
                        @if ($day === null)
                            <span class="activity-calendar__day is-empty" aria-hidden="true"></span>
                        @else
                            <span class="activity-calendar__day" aria-label="{{ $day['date'] }}: {{ $day['count'] }} activities">
                                <span>{{ $day['day'] }}</span>
                                <strong>{{ $day['count'] }}</strong>
                            </span>
                        @endif
                    @endforeach
                </div>
            </div>
        </section>

        @php
            $activityUrl = static function (array $values): string {
                $query = array_filter(
                    $values,
                    static fn (mixed $value): bool => $value !== null && $value !== '',
                );

                return request()->url().($query === [] ? '' : '?'.http_build_query($query));
            };
        @endphp

        <form method="get" action="{{ request()->url() }}">
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

        <x-admin.table class="admin-table--data activity-workspace__table">
            @if ($activity !== [])
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Area</th>
                            <th scope="col">Change</th>
                            <th scope="col">Target</th>
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
                                <td>{{ $event['target'] }}</td>
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
            <footer class="admin-workspace__footnote">
                {{ $paginator->links() }}
            </footer>
        @endif
    </x-admin.workspace>
</x-filament-panels::page>
