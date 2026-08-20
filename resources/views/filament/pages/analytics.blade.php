<x-filament-panels::page>
    @php
        $status = $matomo['status'] ?? null;
        $available = in_array($status, ['available', 'stale'], true);
        $metricValue = static function (array $row, string $metric = 'nb_visits'): ?int {
            $value = $row[$metric] ?? null;

            return is_numeric($value) ? (int) round((float) $value) : null;
        };
        $metricDisplay = static function (array $row, string $metric = 'nb_visits') use ($metricValue): string {
            $value = $metricValue($row, $metric);

            return $value === null ? '—' : number_format($value);
        };
        $maxMetric = static function (array $rows, string $metric = 'nb_visits') use ($metricValue): float {
            $max = 0.0;
            foreach ($rows as $row) {
                $value = $metricValue($row, $metric);
                if ($value !== null) {
                    $max = max($max, $value);
                }
            }

            return max(1.0, $max);
        };
        $percent = static function (array $row, float $max, string $metric = 'nb_visits') use ($metricValue): float {
            $value = $metricValue($row, $metric);
            if ($value === null) {
                return 0.0;
            }

            return min(100, max(0, ($value / $max) * 100));
        };

        $countryRows = $matomo['countries'] ?? [];
        $continentRows = $matomo['continents'] ?? [];
        $countryMax = $maxMetric($countryRows);
        $centroids = config('analytics-country-centroids', []);
        $mapPoints = [];
        foreach ($countryRows as $row) {
            $label = (string) ($row['label'] ?? '');
            $coords = $centroids[$label] ?? null;
            if (! is_array($coords) || count($coords) < 2) {
                continue;
            }

            $visits = $metricValue($row);
            if ($visits === null || $visits <= 0) {
                continue;
            }

            $lat = (float) $coords[0];
            $lon = (float) $coords[1];
            $mapPoints[] = [
                'label' => $label,
                'visits' => $visits,
                'x' => min(99, max(1, (($lon + 180.0) / 360.0) * 100.0)),
                'y' => min(98, max(2, ((90.0 - $lat) / 180.0) * 100.0)),
                'size' => 9.0 + (22.0 * sqrt($visits / $countryMax)),
            ];
        }

        $acquisitionGroups = [
            'Referring websites' => $matomo['referrer_websites'] ?? [],
            'Social networks' => $matomo['socials'] ?? [],
            'Search engines' => $matomo['search_engines'] ?? [],
            'AI assistants' => $matomo['ai_assistants'] ?? [],
            'Campaigns' => $matomo['campaigns'] ?? [],
        ];
        $hasAcquisition = collect($acquisitionGroups)->contains(static fn (array $rows): bool => $rows !== []);

        $contentRows = $matomo['content'] ?? [];
        $entryRows = $matomo['entry_pages'] ?? [];
        $exitRows = $matomo['exit_pages'] ?? [];
        $downloadRows = $matomo['downloads'] ?? [];
        $outlinkRows = $matomo['outlinks'] ?? [];
        $searchRows = $matomo['site_searches'] ?? [];
        $hasJourneys = $contentRows !== [] || $entryRows !== [] || $exitRows !== [] || $downloadRows !== [] || $outlinkRows !== [] || $searchRows !== [];

        $eventRows = $matomo['events'] ?? [];
        $eventCategoryRows = $matomo['event_categories'] ?? [];
        $measuredInteractionSignals = collect($interactionSignals)->filter(static fn ($value): bool => is_numeric($value));
        $hasInteractions = $measuredInteractionSignals->contains(static fn ($value): bool => (float) $value > 0)
            || $eventRows !== [] || $eventCategoryRows !== [] || $artworkAttention !== [];
        $allInteractionSignalsMeasured = $interactionSignals !== []
            && $measuredInteractionSignals->count() === count($interactionSignals);
        $allMeasuredInteractionSignalsZero = $allInteractionSignalsMeasured
            && $measuredInteractionSignals->every(static fn ($value): bool => (float) $value === 0.0);

        $technologyGroups = [
            'Devices' => $matomo['devices'] ?? [],
            'Browsers' => $matomo['browsers'] ?? [],
            'Operating systems' => $matomo['operating_systems'] ?? [],
            'Visit duration' => $matomo['visit_duration'] ?? [],
            'Pages / actions per visit' => $matomo['pages_per_visit'] ?? [],
        ];
        $hasTechnology = collect($technologyGroups)->contains(static fn (array $rows): bool => $rows !== []);

        // Matomo's dedicated day-of-week report has produced contradictory range aggregates in Validation.
        // Derive this distribution from the already-authoritative daily series instead, so it must reconcile with
        // the same selected range used by the headline visit metrics.
        $weekdayBuckets = array_fill_keys(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'], 0);
        foreach (($matomo['series'] ?? []) as $point) {
            $date = $point['date'] ?? null;
            if (! is_string($date)) {
                continue;
            }

            try {
                $weekday = \Carbon\CarbonImmutable::createFromFormat('Y-m-d', $date)->format('l');
            } catch (\Throwable) {
                continue;
            }

            if (array_key_exists($weekday, $weekdayBuckets)) {
                $weekdayBuckets[$weekday] += (int) round((float) ($point['visits'] ?? 0));
            }
        }
        $weekdayRows = [];
        foreach ($weekdayBuckets as $label => $visits) {
            if ($visits > 0) {
                $weekdayRows[] = ['label' => $label, 'nb_visits' => $visits];
            }
        }

        $localTimeRows = $matomo['local_time'] ?? [];
        $weekdayMax = $maxMetric($weekdayRows);
        $localTimeMax = $maxMetric($localTimeRows);
    @endphp

    <div class="analytics-dashboard">
        <header class="analytics-head">
            <div class="analytics-head__intro">
                <p class="analytics-kicker">Site intelligence</p>
                <h2>Human analytics</h2>
                <p>Aggregate Matomo reporting for audience, content and meaningful interactions. Operational telemetry is kept separate below.</p>
            </div>

            <div class="analytics-head__controls">
                <span @class([
                    'analytics-status',
                    'is-live' => $status === 'available',
                    'is-stale' => $status === 'stale',
                    'is-unavailable' => in_array($status, ['disabled', 'unavailable'], true),
                ])>
                    @if ($status === 'available') Live Matomo
                    @elseif ($status === 'stale') Cached Matomo
                    @elseif ($status === 'disabled') Reporting disabled
                    @else Reporting unavailable
                    @endif
                </span>

                <div class="analytics-range" role="group" aria-label="Analytics date range">
                    @foreach (['today' => 'Today', '7d' => '7 days', '30d' => '30 days', '12m' => '12 months'] as $preset => $label)
                        <button
                            type="button"
                            wire:click="setRange('{{ $preset }}')"
                            class="analytics-range__button {{ $range === $preset ? 'is-active' : '' }}"
                        >{{ $label }}</button>
                    @endforeach
                </div>
            </div>
        </header>

        <div wire:loading.flex wire:target="setRange" class="analytics-loading">Updating reports…</div>

        @if ($status === 'disabled')
            <div class="analytics-notice is-warning">
                <strong>Matomo reporting is disabled.</strong>
                <span>{{ $matomo['message'] ?? 'The read-only Reporting API is not enabled for this runtime.' }}</span>
            </div>
        @elseif ($status === 'unavailable')
            <div class="analytics-notice is-danger">
                <strong>Matomo reporting is unavailable.</strong>
                <span>{{ $matomo['message'] ?? 'The Reporting API could not be read.' }}</span>
            </div>
        @elseif ($status === 'stale')
            <div class="analytics-notice is-warning">
                <strong>Showing cached Matomo aggregates.</strong>
                <span>{{ $matomo['message'] ?? 'Live reporting is temporarily unavailable.' }}</span>
            </div>
        @endif

        @if ($available)
            <div class="analytics-context">
                <strong>{{ $matomo['range']['label'] ?? 'Selected range' }}</strong>
                @if (filled($matomo['range']['start'] ?? null) && filled($matomo['range']['end'] ?? null))
                    <span>{{ $matomo['range']['start'] }} – {{ $matomo['range']['end'] }}</span>
                @endif
                <span>Matomo Reporting API</span>
                @if (filled($matomo['generated_at'] ?? null))
                    <span>Updated {{ $matomo['generated_at'] }}</span>
                @endif
            </div>

            <section class="analytics-kpi-strip" aria-label="Traffic summary">
                @foreach ($kpis as $kpi)
                    <div class="analytics-kpi">
                        <span>{{ $kpi['label'] }}</span>
                        <strong>{{ $kpi['value'] }}</strong>
                        <small @class([
                            'is-up' => is_numeric($kpi['delta']) && $kpi['delta'] > 0,
                            'is-down' => is_numeric($kpi['delta']) && $kpi['delta'] < 0,
                        ])>{{ $kpi['comparison'] }}</small>
                    </div>
                @endforeach
            </section>

            <section class="analytics-section analytics-overview">
                <div class="analytics-section__head">
                    <div>
                        <p class="analytics-kicker">Traffic</p>
                        <h3>Visits over time</h3>
                    </div>
                    <div class="analytics-legend" aria-label="Chart legend">
                        <span><i class="is-visits"></i>Visits</span>
                        <span><i class="is-actions"></i>Tracked actions</span>
                    </div>
                </div>

                <div class="analytics-overview__grid">
                    <div class="analytics-trend">
                        @if ($trendChart === [])
                            <p class="analytics-empty">No time-series data is available for this period.</p>
                        @else
                            <svg viewBox="0 0 1000 260" role="img" aria-label="Visits and tracked actions trend">
                                <line x1="22" y1="238" x2="978" y2="238" class="analytics-chart__grid" />
                                <line x1="22" y1="130" x2="978" y2="130" class="analytics-chart__grid" />
                                <line x1="22" y1="22" x2="978" y2="22" class="analytics-chart__grid" />
                                <polyline points="{{ $trendChart['actions_points'] }}" class="analytics-chart__line is-actions" />
                                <polyline points="{{ $trendChart['visits_points'] }}" class="analytics-chart__line is-visits" />
                            </svg>
                            <div class="analytics-axis"><span>{{ $trendChart['start'] }}</span><span>{{ $trendChart['end'] }}</span></div>
                        @endif
                    </div>

                    <dl class="analytics-highlights">
                        @foreach ($audienceHighlights as $highlight)
                            <div>
                                <dt>{{ $highlight['label'] }}</dt>
                                <dd>{{ $highlight['value'] }}</dd>
                                <small>{{ $highlight['detail'] }}</small>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </section>

            <section class="analytics-section analytics-geography">
                <div class="analytics-section__head">
                    <div>
                        <p class="analytics-kicker">Audience</p>
                        <h3>Geography</h3>
                    </div>
                    <p>Country-level aggregates only.</p>
                </div>

                <div class="analytics-geography__grid">
                    <figure class="analytics-world">
                        <div class="analytics-world__canvas">
                            @if (view()->exists('filament.generated.analytics-world-map'))
                                @include('filament.generated.analytics-world-map')
                            @else
                                <div class="analytics-map-build-warning">Map geometry is generated by the frontend build.</div>
                            @endif

                            @foreach ($mapPoints as $point)
                                <span
                                    class="analytics-world__marker"
                                    style="left: {{ number_format($point['x'], 3, '.', '') }}%; top: {{ number_format($point['y'], 3, '.', '') }}%; width: {{ number_format($point['size'], 2, '.', '') }}px; height: {{ number_format($point['size'], 2, '.', '') }}px;"
                                    tabindex="0"
                                    aria-label="{{ $point['label'] }}: {{ number_format($point['visits']) }} visits"
                                    title="{{ $point['label'] }} · {{ number_format($point['visits']) }} visits"
                                ></span>
                            @endforeach
                        </div>
                        <figcaption>
                            @if ($countryRows === [])
                                No country-level visits in this period.
                            @else
                                Marker area follows aggregate visit volume. Natural Earth country geometry is generated at build time.
                            @endif
                        </figcaption>
                    </figure>

                    <div class="analytics-ranking">
                        <div>
                            <h4>Top countries</h4>
                            @forelse (array_slice($countryRows, 0, 8) as $row)
                                <div class="analytics-rank-row">
                                    <span>{{ $row['label'] }}</span>
                                    <i><b style="width: {{ number_format($percent($row, $countryMax), 2, '.', '') }}%"></b></i>
                                    <strong>{{ $metricDisplay($row) }}</strong>
                                </div>
                            @empty
                                <p class="analytics-empty">No country report.</p>
                            @endforelse
                        </div>

                        @if ($continentRows !== [])
                            <div>
                                <h4>Continents</h4>
                                @foreach ($continentRows as $row)
                                    <div class="analytics-plain-row"><span>{{ $row['label'] }}</span><strong>{{ $metricDisplay($row) }}</strong></div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                @if ($weekdayRows !== [] || $localTimeRows !== [])
                    <div class="analytics-time-grid">
                        @if ($weekdayRows !== [])
                            <div>
                                <h4>Visits by weekday</h4>
                                @foreach ($weekdayRows as $row)
                                    <div class="analytics-rank-row is-compact">
                                        <span>{{ $row['label'] }}</span>
                                        <i><b style="width: {{ number_format($percent($row, $weekdayMax), 2, '.', '') }}%"></b></i>
                                        <strong>{{ $metricDisplay($row) }}</strong>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if ($localTimeRows !== [])
                            <div>
                                <h4>Visits by local hour</h4>
                                @foreach ($localTimeRows as $row)
                                    <div class="analytics-rank-row is-compact">
                                        <span>{{ $row['label'] }}</span>
                                        <i><b style="width: {{ number_format($percent($row, $localTimeMax), 2, '.', '') }}%"></b></i>
                                        <strong>{{ $metricDisplay($row) }}</strong>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            </section>

            <section class="analytics-section">
                <div class="analytics-section__head">
                    <div>
                        <p class="analytics-kicker">Discovery</p>
                        <h3>Acquisition</h3>
                    </div>
                    <p>How visitors reached the site.</p>
                </div>

                @if ($hasAcquisition)
                    <div class="analytics-data-columns">
                        @foreach ($acquisitionGroups as $label => $rows)
                            @if ($rows !== [])
                                <div>
                                    <h4>{{ $label }}</h4>
                                    @foreach (array_slice($rows, 0, 8) as $row)
                                        <div class="analytics-plain-row"><span>{{ $row['label'] }}</span><strong>{{ $metricDisplay($row) }}</strong></div>
                                    @endforeach
                                </div>
                            @endif
                        @endforeach
                    </div>
                @else
                    <p class="analytics-empty analytics-empty--section">No acquisition activity in this period.</p>
                @endif
            </section>

            <section class="analytics-section">
                <div class="analytics-section__head">
                    <div>
                        <p class="analytics-kicker">Editorial</p>
                        <h3>Content & journeys</h3>
                    </div>
                    <p>What was viewed and where sessions moved.</p>
                </div>

                @if ($hasJourneys)
                    <div class="analytics-journey-grid">
                        <div class="analytics-table-wrap">
                            <h4>Most-viewed content</h4>
                            <table class="analytics-table">
                                <thead><tr><th>Content</th><th>Views</th><th>Visits</th></tr></thead>
                                <tbody>
                                @foreach (array_slice($contentRows, 0, 12) as $row)
                                    <tr><td>{{ $row['label'] }}</td><td>{{ $metricDisplay($row, 'nb_hits') }}</td><td>{{ $metricDisplay($row) }}</td></tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="analytics-journey-side">
                            @foreach (['Entry pages' => $entryRows, 'Exit pages' => $exitRows, 'Downloads' => $downloadRows, 'Outbound destinations' => $outlinkRows, 'Site search' => $searchRows] as $label => $rows)
                                @if ($rows !== [])
                                    <div>
                                        <h4>{{ $label }}</h4>
                                        @foreach (array_slice($rows, 0, 6) as $row)
                                            <div class="analytics-plain-row">
                                                <span>{{ $row['label'] }}</span>
                                                <strong>{{ $metricDisplay($row, in_array($label, ['Downloads', 'Outbound destinations'], true) ? 'nb_hits' : ($label === 'Exit pages' ? 'nb_exits' : ($label === 'Entry pages' ? 'nb_entrances' : 'nb_visits'))) }}</strong>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @else
                    <p class="analytics-empty analytics-empty--section">No content or journey activity in this period.</p>
                @endif
            </section>

            <section class="analytics-section">
                <div class="analytics-section__head">
                    <div>
                        <p class="analytics-kicker">Artwork</p>
                        <h3>Artist interactions</h3>
                    </div>
                    <p>Events that describe meaningful public engagement.</p>
                </div>

                <div class="analytics-interaction-strip">
                    @foreach ($interactionSignals as $label => $value)
                        <div><span>{{ $label }}</span><strong>{{ is_numeric($value) ? number_format((int) $value) : '—' }}</strong></div>
                    @endforeach
                </div>

                @include('filament.pages.partials.artwork-attention')

                @if ($hasInteractions)
                    <div class="analytics-data-columns analytics-data-columns--events">
                        @foreach (['Event actions' => $eventRows, 'Event categories' => $eventCategoryRows] as $label => $rows)
                            @if ($rows !== [])
                                <div>
                                    <h4>{{ $label }}</h4>
                                    @foreach (array_slice($rows, 0, 10) as $row)
                                        <div class="analytics-plain-row"><span>{{ $row['label'] }}</span><strong>{{ $metricDisplay($row, 'nb_events') }}</strong></div>
                                    @endforeach
                                </div>
                            @endif
                        @endforeach
                    </div>
                @elseif ($allMeasuredInteractionSignalsZero)
                    <p class="analytics-empty analytics-empty--section">No artist interaction events in this period.</p>
                @endif
            </section>

            <section class="analytics-section">
                <div class="analytics-section__head">
                    <div>
                        <p class="analytics-kicker">Context</p>
                        <h3>Engagement & technology</h3>
                    </div>
                    <p>Aggregate compatibility and session-depth context.</p>
                </div>

                @if ($hasTechnology)
                    <div class="analytics-data-columns">
                        @foreach ($technologyGroups as $label => $rows)
                            @if ($rows !== [])
                                @php($groupMax = $maxMetric($rows))
                                <div>
                                    <h4>{{ $label }}</h4>
                                    @foreach (array_slice($rows, 0, 8) as $row)
                                        <div class="analytics-rank-row is-compact">
                                            <span>{{ $row['label'] }}</span>
                                            <i><b style="width: {{ number_format($percent($row, $groupMax), 2, '.', '') }}%"></b></i>
                                            <strong>{{ $metricDisplay($row) }}</strong>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        @endforeach
                    </div>
                @else
                    <p class="analytics-empty analytics-empty--section">No engagement or technology distribution is available for this period.</p>
                @endif
            </section>

            @if (($matomo['warnings'] ?? []) !== [])
                <aside class="analytics-warning-line">
                    <strong>Partial reporting</strong>
                    <ul>
                        @foreach ($matomo['warnings'] as $warning)<li>{{ $warning }}</li>@endforeach
                    </ul>
                </aside>
            @endif
        @endif

        <section class="analytics-section analytics-operations">
            <div class="analytics-section__head">
                <div>
                    <p class="analytics-kicker">Application</p>
                    <h3>Operational health</h3>
                </div>
                <p>Local aggregate errors, bots, request performance and admin traffic.</p>
            </div>

            <div class="analytics-operational-strip">
                @foreach ($operationalSummary as $label => $value)
                    <div><span>{{ $label }}</span><strong>{{ $value }}</strong></div>
                @endforeach
            </div>

            @if ($operational !== [])
                <div class="analytics-table-wrap analytics-table-wrap--operations">
                    <table class="analytics-table">
                        <thead><tr><th>Date</th><th>Metric</th><th>Value</th></tr></thead>
                        <tbody>
                            @foreach ($operational as $row)
                                <tr><td>{{ $row['date'] }}</td><td>{{ $row['label'] }}</td><td>{{ $row['display_value'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="analytics-empty analytics-empty--section">No local operational aggregates yet.</p>
            @endif
        </section>
    </div>
</x-filament-panels::page>
