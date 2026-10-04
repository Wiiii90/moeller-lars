<x-filament-panels::page>
    <x-admin.workspace title="Dashboard" class="admin-dashboard">
        <x-admin.metrics :columns="6" aria-label="Dashboard summary">
            @foreach ($metrics as $metric)
                @php
                    $metricRole = match (strtolower((string) $metric['label'])) {
                        'visits' => 'traffic',
                        'published artworks' => 'artworks',
                        'recent changes' => 'changes',
                        default => 'supportive',
                    };
                @endphp
                <x-admin.metric
                    :label="$metric['label']"
                    :value="$metric['value']"
                    class="admin-dashboard__metric admin-dashboard__metric--{{ $metricRole }}"
                >{{ $metric['detail'] }}</x-admin.metric>
            @endforeach
        </x-admin.metrics>

        <section
            class="admin-dashboard__overview"
            aria-label="Storage, Activity and Analytics overview"
            x-data="{ compactPanel: 'storage' }"
        >
            <div class="admin-dashboard__overview-switcher" role="tablist" aria-label="Dashboard overview">
                <button
                    class="admin-action"
                    type="button"
                    role="tab"
                    x-on:click="compactPanel = 'storage'"
                    x-bind:aria-selected="(compactPanel === 'storage').toString()"
                    x-bind:class="{ 'is-active': compactPanel === 'storage' }"
                >Storage</button>
                <button
                    class="admin-action"
                    type="button"
                    role="tab"
                    x-on:click="compactPanel = 'activity'"
                    x-bind:aria-selected="(compactPanel === 'activity').toString()"
                    x-bind:class="{ 'is-active': compactPanel === 'activity' }"
                >Activity</button>
                <button
                    class="admin-action"
                    type="button"
                    role="tab"
                    x-on:click="compactPanel = 'analytics'"
                    x-bind:aria-selected="(compactPanel === 'analytics').toString()"
                    x-bind:class="{ 'is-active': compactPanel === 'analytics' }"
                >Analytics</button>
            </div>

            <article
                class="admin-dashboard__overview-column"
                x-bind:class="{ 'is-compact-active': compactPanel === 'storage' }"
            >
                <header class="admin-dashboard__overview-head">
                    <span class="admin-section__kicker">Storage</span>
                    <a class="admin-action" href="{{ $storage['url'] }}" wire:navigate>Open</a>
                </header>

                <div class="admin-dashboard__storage-stage">
                    <div class="admin-dashboard__storage-visual" aria-label="Storage capacity preview">
                        <x-admin.storage-capacity-visual
                            :capacity="$storage"
                            :breakdown="$storage['breakdown']"
                            :segments="$storage['segments']"
                        />
                    </div>
                    <p class="admin-dashboard__facts">
                        <span aria-label="Used {{ $storage['authoritative'] }}">
                            <span class="admin-dashboard__fact-label admin-dashboard__fact-label--long" aria-hidden="true">Used</span>
                            <span class="admin-dashboard__fact-label admin-dashboard__fact-label--short" aria-hidden="true">U:</span>
                            <strong>{{ $storage['authoritative'] }}</strong>
                        </span>
                        <span aria-label="Remaining {{ $storage['remaining'] }}">
                            <span class="admin-dashboard__fact-label admin-dashboard__fact-label--long" aria-hidden="true">Remaining</span>
                            <span class="admin-dashboard__fact-label admin-dashboard__fact-label--short" aria-hidden="true">R:</span>
                            <strong>{{ $storage['remaining'] }}</strong>
                        </span>
                        <span aria-label="Allowance {{ $storage['allowance'] }}">
                            <span class="admin-dashboard__fact-label admin-dashboard__fact-label--long" aria-hidden="true">Allowance</span>
                            <span class="admin-dashboard__fact-label admin-dashboard__fact-label--short" aria-hidden="true">A:</span>
                            <strong>{{ $storage['allowance'] }}</strong>
                        </span>
                    </p>
                </div>
            </article>

            <article
                class="admin-dashboard__overview-column"
                x-bind:class="{ 'is-compact-active': compactPanel === 'activity' }"
            >
                <header class="admin-dashboard__overview-head">
                    <span class="admin-section__kicker">Activity</span>
                    <a class="admin-action" href="{{ $activity['url'] }}" wire:navigate>Open</a>
                </header>

                <x-admin.activity-clock-visual
                    class="admin-dashboard__activity-visual"
                    :activity="$activity['clock_activity']"
                    :peak-count="$activity['clock_peak_count']"
                    :peak-hour="$activity['clock_peak_hour']"
                    :caption-label="now()->format('M j, Y')"
                    aria-context="activity distribution for the last 30 days"
                />
            </article>

            <article
                class="admin-dashboard__overview-column"
                x-bind:class="{ 'is-compact-active': compactPanel === 'analytics' }"
            >
                <header class="admin-dashboard__overview-head">
                    <span class="admin-section__kicker">Analytics</span>
                    <a class="admin-action" href="{{ $analytics['url'] }}" wire:navigate>Open</a>
                </header>

                <figure class="admin-dashboard__analytics-visual">
                    <div
                        class="admin-dashboard__analytics-map"
                        x-data="{
                            selectedCountry: @js($analytics['map_points'][0]['label'] ?? null),
                            selectCountry(country) { this.selectedCountry = country },
                        }"
                    >
                        <x-admin.analytics-world-map :points="$analytics['map_points']" />
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
                    <span>Unique visitors <strong>{{ $analytics['visitors_display'] }}</strong></span>
                    <span>last 30 days</span>
                </p>
            </article>
        </section>

        @include('filament.pages.partials.dashboard-feed')
    </x-admin.workspace>
</x-filament-panels::page>
