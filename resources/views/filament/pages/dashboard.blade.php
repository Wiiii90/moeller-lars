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
                    <x-admin.storage-capacity-visual :capacity="$storage" />
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

        @include('filament.pages.partials.dashboard-feed')
    </x-admin.workspace>
</x-filament-panels::page>