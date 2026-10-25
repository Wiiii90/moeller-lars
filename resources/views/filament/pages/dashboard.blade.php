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
                    $isPublishedPagesMetric = strtolower((string) $metric['label']) === 'published pages';
                @endphp
                <x-admin.metric
                    :label="$metric['label']"
                    :value="$metric['value']"
                    class="admin-dashboard__metric admin-dashboard__metric--{{ $metricRole }}"
                >
                    @if ($isPublishedPagesMetric)
                        <span class="admin-dashboard__metric-detail--long">{{ $metric['detail'] }}</span>
                        <span class="admin-dashboard__metric-detail--short">{{ __('Nav groups excluded') }}</span>
                    @else
                        {{ $metric['detail'] }}
                    @endif
                </x-admin.metric>
            @endforeach
        </x-admin.metrics>

        <section
            class="admin-dashboard__overview admin-visual-stage admin-visual-stage--triptych"
            aria-label="Storage, Activity and Analytics overview"
        >
            <input class="admin-dashboard__overview-panel-toggle" type="radio" name="dashboard-overview-panel" id="dashboard-overview-storage" checked>
            <input class="admin-dashboard__overview-panel-toggle" type="radio" name="dashboard-overview-panel" id="dashboard-overview-activity">
            <input class="admin-dashboard__overview-panel-toggle" type="radio" name="dashboard-overview-panel" id="dashboard-overview-analytics">

            <div class="admin-stage-tabs admin-dashboard__overview-switcher" aria-label="Dashboard overview">
                <label class="admin-action" for="dashboard-overview-storage">{{ __('Storage') }}</label>
                <label class="admin-action" for="dashboard-overview-activity">{{ __('Activity') }}</label>
                <label class="admin-action" for="dashboard-overview-analytics">{{ __('Analytics') }}</label>
            </div>

            <article class="admin-dashboard__overview-column admin-dashboard__overview-column--storage">
                <header class="admin-dashboard__overview-head">
                    <span class="admin-section__kicker">{{ __('Storage') }}</span>
                    <a class="admin-action" href="{{ $storage['url'] }}" wire:navigate>{{ __('Open') }}</a>
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
                            <span class="admin-dashboard__fact-label admin-dashboard__fact-label--long" aria-hidden="true">{{ __('Used') }}</span>
                            <span class="admin-dashboard__fact-label admin-dashboard__fact-label--short" aria-hidden="true">U:</span>
                            <strong>{{ $storage['authoritative'] }}</strong>
                        </span>
                        <span aria-label="Remaining {{ $storage['remaining'] }}">
                            <span class="admin-dashboard__fact-label admin-dashboard__fact-label--long" aria-hidden="true">{{ __('Remaining') }}</span>
                            <span class="admin-dashboard__fact-label admin-dashboard__fact-label--short" aria-hidden="true">R:</span>
                            <strong>{{ $storage['remaining'] }}</strong>
                        </span>
                        <span aria-label="Allowance {{ $storage['allowance'] }}">
                            <span class="admin-dashboard__fact-label admin-dashboard__fact-label--long" aria-hidden="true">{{ __('Allowance') }}</span>
                            <span class="admin-dashboard__fact-label admin-dashboard__fact-label--short" aria-hidden="true">A:</span>
                            <strong>{{ $storage['allowance'] }}</strong>
                        </span>
                    </p>
                </div>
            </article>

            <article class="admin-dashboard__overview-column admin-dashboard__overview-column--activity">
                <header class="admin-dashboard__overview-head">
                    <span class="admin-section__kicker">{{ __('Activity') }}</span>
                    <a class="admin-action" href="{{ $activity['url'] }}" wire:navigate>{{ __('Open') }}</a>
                </header>

                <x-admin.activity-clock-visual
                    class="admin-dashboard__activity-visual"
                    :activity="$activity['clock_activity']"
                    :peak-count="$activity['clock_peak_count']"
                    :peak-hour="$activity['clock_peak_hour']"
                    :caption-label="\App\Domain\Content\RegionalFormats::adminDate(now())"
                    aria-context="activity distribution for the last 30 days"
                />
            </article>

            <article class="admin-dashboard__overview-column admin-dashboard__overview-column--analytics">
                <header class="admin-dashboard__overview-head">
                    <span class="admin-section__kicker">{{ __('Analytics') }}</span>
                    <a class="admin-action" href="{{ $analytics['url'] }}" wire:navigate>{{ __('Open') }}</a>
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
                            <span class="admin-dashboard__analytics-caption--long">{{ __('No reporting data for this environment.') }}</span>
                            <span class="admin-dashboard__analytics-caption--short">{{ __('Reporting disabled.') }}</span>
                        @elseif ($analytics['status'] === 'unavailable')
                            <span class="admin-dashboard__analytics-caption--long">{{ __('Reporting data is currently unavailable.') }}</span>
                            <span class="admin-dashboard__analytics-caption--short">{{ __('Reporting unavailable.') }}</span>
                        @elseif ($analytics['country_state'] === 'unavailable')
                            <span class="admin-dashboard__analytics-caption--long">{{ __('Country-level reporting is unavailable.') }}</span>
                            <span class="admin-dashboard__analytics-caption--short">{{ __('Country data unavailable.') }}</span>
                        @elseif ($analytics['country_state'] === 'empty')
                            <span class="admin-dashboard__analytics-caption--long">{{ __('No country-level visits in this period.') }}</span>
                            <span class="admin-dashboard__analytics-caption--short">{{ __('No country visits.') }}</span>
                        @else
                            <span class="admin-dashboard__analytics-caption--long">{{ __('Country markers follow aggregate visit volume.') }}</span>
                            <span class="admin-dashboard__analytics-caption--short">{{ __('Visit volume by country.') }}</span>
                        @endif
                    </figcaption>
                </figure>
                <p class="admin-dashboard__facts">
                    <span>{{ __('Visits') }} <strong>{{ $analytics['visits_display'] }}</strong></span>
                    <span>
                        <span class="admin-dashboard__analytics-fact-label--long">{{ __('Unique visitors') }}</span>
                        <span class="admin-dashboard__analytics-fact-label--short">{{ __('Visitors') }}</span>
                        <strong>{{ $analytics['visitors_display'] }}</strong>
                    </span>
                    <span>
                        <span class="admin-dashboard__analytics-fact-label--long">{{ __('last 30 days') }}</span>
                        <span class="admin-dashboard__analytics-fact-label--short">30 days</span>
                    </span>
                </p>
            </article>
        </section>

        @include('filament.pages.partials.dashboard-feed')
    </x-admin.workspace>
</x-filament-panels::page>
