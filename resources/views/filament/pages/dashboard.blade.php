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
                    <x-admin.storage-capacity-visual
                        :capacity="$storage"
                        :breakdown="$storage['breakdown']"
                        :segments="$storage['segments']"
                    />
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

                <x-admin.activity-clock-visual
                    class="admin-dashboard__activity-visual"
                    :activity="$activity['clock_activity']"
                    :peak-count="$activity['clock_peak_count']"
                    :peak-hour="$activity['clock_peak_hour']"
                    aria-context="activity distribution for the last 30 days"
                />
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