<x-filament-panels::page>
    @php
        $status = $matomo['status'] ?? null;
        $available = in_array($status, ['available', 'stale'], true);
        $metricValue = static fn (array $row, string $metric = 'nb_visits'): int => (int) round((float) ($row[$metric] ?? 0));
        $maxMetric = static function (array $rows, string $metric = 'nb_visits'): float {
            $max = 0.0;
            foreach ($rows as $row) {
                $max = max($max, (float) ($row[$metric] ?? 0));
            }

            return max(1.0, $max);
        };
        $percent = static fn (array $row, float $max, string $metric = 'nb_visits'): float => min(100, max(0, ((float) ($row[$metric] ?? 0) / $max) * 100));

        $donutPalette = ['#92400e', '#d97706', '#f59e0b', '#fbbf24', '#fed7aa', '#d1d5db'];
        $buildDonut = static function (array $rows, string $metric = 'nb_visits') use ($donutPalette): array {
            $rows = array_values(array_filter($rows, static fn (array $row): bool => (float) ($row[$metric] ?? 0) > 0));
            $rows = array_slice($rows, 0, 6);
            $total = array_sum(array_map(static fn (array $row): float => (float) ($row[$metric] ?? 0), $rows));
            if ($total <= 0) {
                return ['gradient' => 'conic-gradient(#e5e7eb 0 100%)', 'total' => 0, 'rows' => []];
            }

            $cursor = 0.0;
            $segments = [];
            $legend = [];
            foreach ($rows as $index => $row) {
                $share = ((float) ($row[$metric] ?? 0) / $total) * 100;
                $end = $cursor + $share;
                $color = $donutPalette[$index] ?? end($donutPalette);
                $segments[] = sprintf('%s %.3f%% %.3f%%', $color, $cursor, $end);
                $legend[] = [
                    'label' => (string) ($row['label'] ?? 'Unknown'),
                    'value' => (int) round((float) ($row[$metric] ?? 0)),
                    'share' => $share,
                    'color' => $color,
                ];
                $cursor = $end;
            }

            return [
                'gradient' => 'conic-gradient('.implode(', ', $segments).')',
                'total' => (int) round($total),
                'rows' => $legend,
            ];
        };

        $sourceRows = $matomo['referrers'] ?? [];
        $deviceRows = $matomo['devices'] ?? [];
        $sourceDonut = $buildDonut($sourceRows);
        $deviceDonut = $buildDonut($deviceRows);

        $countryRows = $matomo['countries'] ?? [];
        $countryMax = $maxMetric($countryRows);
        $centroids = config('analytics-country-centroids', []);
        $mapPoints = [];
        foreach ($countryRows as $row) {
            $label = (string) ($row['label'] ?? '');
            $coords = $centroids[$label] ?? null;
            if (! is_array($coords) || count($coords) < 2) {
                continue;
            }

            $visits = max(0, $metricValue($row));
            $lat = (float) $coords[0];
            $lon = (float) $coords[1];
            $mapPoints[] = [
                'label' => $label,
                'visits' => $visits,
                'x' => min(98, max(2, (($lon + 180.0) / 360.0) * 100.0)),
                'y' => min(96, max(4, ((90.0 - $lat) / 180.0) * 100.0)),
                'size' => 10.0 + (24.0 * sqrt($visits / $countryMax)),
            ];
        }
    @endphp

    <div class="analytics-dashboard">
        <header class="analytics-hero">
            <div>
                <div class="analytics-hero__eyebrow">Self-hosted artist intelligence</div>
                <h2 class="analytics-hero__title">Human analytics</h2>
                <p class="analytics-hero__copy">
                    Audience, acquisition, content, behaviour and artist-specific interactions from Matomo Core. Human analytics stay aggregate and separate from local bot, error and performance telemetry.
                </p>
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
            </div>

            <div class="analytics-range" role="group" aria-label="Analytics date range">
                @foreach (['today' => 'Today', '7d' => '7 days', '30d' => '30 days', '12m' => '12 months'] as $preset => $label)
                    <button
                        type="button"
                        wire:click="setRange('{{ $preset }}')"
                        class="analytics-range__button {{ $range === $preset ? 'is-active' : '' }}"
                    >{{ $label }}</button>
                @endforeach
            </div>
        </header>

        <div wire:loading.flex wire:target="setRange" class="analytics-loading">
            Updating Matomo reports…
        </div>

        @if ($status === 'disabled')
            <div class="analytics-notice is-warning">
                <strong>Matomo reporting is not enabled for this application runtime.</strong>
                <div>{{ $matomo['message'] ?? 'Matomo reporting is disabled.' }}</div>
                <div>Browser tracking can remain disabled here while the read-only Reporting API is enabled for validation.</div>
            </div>
        @elseif ($status === 'unavailable')
            <div class="analytics-notice is-danger">
                <strong>Matomo reporting is temporarily unavailable.</strong>
                <div>{{ $matomo['message'] ?? 'The Reporting API could not be read.' }}</div>
            </div>
        @elseif ($status === 'stale')
            <div class="analytics-notice is-warning">
                <strong>Live Matomo is unavailable.</strong> {{ $matomo['message'] ?? 'Showing cached aggregate analytics.' }}
            </div>
        @endif

        @if ($available)
            <div class="analytics-meta">
                <strong>{{ $matomo['range']['label'] ?? 'Selected range' }}</strong>
                @if (filled($matomo['range']['start'] ?? null) && filled($matomo['range']['end'] ?? null))
                    <span>{{ $matomo['range']['start'] }} – {{ $matomo['range']['end'] }}</span>
                @endif
                <span>Source: Matomo Reporting API</span>
                @if (filled($matomo['generated_at'] ?? null))
                    <span>Generated {{ $matomo['generated_at'] }}</span>
                @endif
            </div>

            <div class="analytics-kpis">
                @foreach ($kpis as $kpi)
                    <article class="analytics-card">
                        <div class="analytics-card__label">{{ $kpi['label'] }}</div>
                        <div class="analytics-card__value">{{ $kpi['value'] }}</div>
                        <div @class([
                            'analytics-card__delta',
                            'is-up' => is_numeric($kpi['delta']) && $kpi['delta'] > 0,
                            'is-down' => is_numeric($kpi['delta']) && $kpi['delta'] < 0,
                        ])>{{ $kpi['comparison'] }}</div>
                    </article>
                @endforeach
            </div>

            <section class="analytics-section" id="overview">
                <div class="analytics-section__header">
                    <div>
                        <h3 class="analytics-section__title">Traffic & engagement</h3>
                        <p class="analytics-section__copy">Visits and tracked actions over time, with acquisition and device mix at a glance.</p>
                    </div>
                </div>

                <div class="analytics-grid-wide">
                    <article class="analytics-panel">
                        <div class="analytics-panel__heading-row">
                            <div>
                                <h4 class="analytics-panel__title">Visits over time</h4>
                                <p class="analytics-panel__copy">Human visits versus tracked public actions.</p>
                            </div>
                        </div>

                        @if ($trendChart === [])
                            <div class="analytics-empty">No time-series data is available for this period.</div>
                        @else
                            <div class="analytics-chart">
                                <svg viewBox="0 0 1000 260" role="img" aria-label="Visits and tracked actions trend">
                                    <line x1="22" y1="238" x2="978" y2="238" class="analytics-chart__grid" stroke-width="1" />
                                    <line x1="22" y1="130" x2="978" y2="130" class="analytics-chart__grid" stroke-width="1" />
                                    <polyline points="{{ $trendChart['actions_points'] }}" fill="none" stroke="currentColor" class="analytics-chart__actions" stroke-width="3" vector-effect="non-scaling-stroke" />
                                    <polyline points="{{ $trendChart['visits_points'] }}" fill="none" stroke="currentColor" class="analytics-chart__visits" stroke-width="4" vector-effect="non-scaling-stroke" />
                                </svg>
                            </div>
                            <div class="analytics-chart__legend">
                                <span><i class="analytics-legend__dot" style="background:#d97706"></i>Visits</span>
                                <span><i class="analytics-legend__dot" style="background:#9ca3af"></i>Tracked actions</span>
                                <span>{{ $trendChart['start'] }} → {{ $trendChart['end'] }}</span>
                            </div>
                        @endif
                    </article>

                    <div class="analytics-grid-2">
                        <article class="analytics-panel">
                            <h4 class="analytics-panel__title">Acquisition mix</h4>
                            <div class="analytics-donut-wrap">
                                <div class="analytics-donut" style="--donut: {{ $sourceDonut['gradient'] }}" data-total="{{ number_format($sourceDonut['total']) }}&#10;visits"></div>
                                <div class="analytics-list">
                                    @forelse ($sourceDonut['rows'] as $row)
                                        <div class="analytics-list__row">
                                            <span class="analytics-list__label"><i class="analytics-legend__dot" style="background:{{ $row['color'] }}"></i>{{ $row['label'] }}</span>
                                            <span class="analytics-list__value">{{ number_format($row['value']) }}</span>
                                        </div>
                                    @empty
                                        <div class="analytics-empty">No acquisition data.</div>
                                    @endforelse
                                </div>
                            </div>
                        </article>

                        <article class="analytics-panel">
                            <h4 class="analytics-panel__title">Device mix</h4>
                            <div class="analytics-donut-wrap">
                                <div class="analytics-donut" style="--donut: {{ $deviceDonut['gradient'] }}" data-total="{{ number_format($deviceDonut['total']) }}&#10;visits"></div>
                                <div class="analytics-list">
                                    @forelse ($deviceDonut['rows'] as $row)
                                        <div class="analytics-list__row">
                                            <span class="analytics-list__label"><i class="analytics-legend__dot" style="background:{{ $row['color'] }}"></i>{{ $row['label'] }}</span>
                                            <span class="analytics-list__value">{{ number_format($row['value']) }}</span>
                                        </div>
                                    @empty
                                        <div class="analytics-empty">No device data.</div>
                                    @endforelse
                                </div>
                            </div>
                        </article>
                    </div>
                </div>

                <div class="analytics-grid-4">
                    @foreach ($audienceHighlights as $highlight)
                        <article class="analytics-card">
                            <div class="analytics-card__label">{{ $highlight['label'] }}</div>
                            <div class="analytics-card__value" style="font-size:1rem">{{ $highlight['value'] }}</div>
                            <div class="analytics-card__detail">{{ $highlight['detail'] }}</div>
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="analytics-section" id="geography">
                <div class="analytics-section__header">
                    <div>
                        <h3 class="analytics-section__title">Audience & geography</h3>
                        <p class="analytics-section__copy">Country-level visitor origins only. No raw visitor identity, precise address or city list is mirrored into the application.</p>
                    </div>
                </div>

                <div class="analytics-grid-wide">
                    <article class="analytics-panel">
                        <h4 class="analytics-panel__title">Visitor origin map</h4>
                        <p class="analytics-panel__copy">Bubble size represents aggregate visits for the selected period.</p>
                        <div class="analytics-world" role="img" aria-label="World map of aggregate visitor origins">
                            <svg class="analytics-world__svg" viewBox="0 0 1000 500" preserveAspectRatio="none" aria-hidden="true">
                                <path class="analytics-world__grid" d="M0 125H1000M0 250H1000M0 375H1000M250 0V500M500 0V500M750 0V500" />
                                <path class="analytics-world__land" d="M61 129L111 83L198 61L285 77L337 119L310 164L266 184L237 224L188 220L162 194L119 180L84 157Z" />
                                <path class="analytics-world__land" d="M259 225L306 237L340 279L329 333L306 392L276 449L253 400L247 339L232 286Z" />
                                <path class="analytics-world__land" d="M455 106L492 82L548 88L578 112L619 110L657 124L715 115L778 132L846 121L912 151L899 187L841 194L815 225L760 220L717 198L675 206L633 185L598 188L561 168L520 175L489 152L458 149Z" />
                                <path class="analytics-world__land" d="M488 181L548 180L589 206L607 258L583 320L548 371L508 348L482 301L467 244Z" />
                                <path class="analytics-world__land" d="M783 330L830 311L881 323L915 356L894 394L845 404L802 383Z" />
                                <path class="analytics-world__land" d="M418 92L435 63L468 55L483 78L461 105Z" />
                            </svg>
                            @forelse ($mapPoints as $point)
                                <span
                                    class="analytics-world__marker"
                                    tabindex="0"
                                    title="{{ $point['label'] }}: {{ number_format($point['visits']) }} visits"
                                    style="--x:{{ number_format($point['x'], 3, '.', '') }}%;--y:{{ number_format($point['y'], 3, '.', '') }}%;--size:{{ number_format($point['size'], 2, '.', '') }}px"
                                ></span>
                            @empty
                                <div class="analytics-empty" style="position:absolute;left:1rem;right:1rem;bottom:1rem">No country-level Matomo data is available for this period.</div>
                            @endforelse
                        </div>
                    </article>

                    <div class="analytics-grid-2">
                        <article class="analytics-panel">
                            <h4 class="analytics-panel__title">Top countries</h4>
                            @php($countryBarMax = $maxMetric($countryRows))
                            <div class="analytics-list">
                                @forelse (array_slice($countryRows, 0, 12) as $row)
                                    <div>
                                        <div class="analytics-list__row"><span class="analytics-list__label">{{ $row['label'] }}</span><span class="analytics-list__value">{{ number_format($metricValue($row)) }}</span></div>
                                        <div class="analytics-bar"><div class="analytics-bar__fill" style="width:{{ $percent($row, $countryBarMax) }}%"></div></div>
                                    </div>
                                @empty
                                    <div class="analytics-empty">No country report.</div>
                                @endforelse
                            </div>
                        </article>

                        <article class="analytics-panel">
                            <h4 class="analytics-panel__title">Continents</h4>
                            @php($continentRows = $matomo['continents'] ?? [])
                            @php($continentMax = $maxMetric($continentRows))
                            <div class="analytics-list">
                                @forelse ($continentRows as $row)
                                    <div>
                                        <div class="analytics-list__row"><span class="analytics-list__label">{{ $row['label'] }}</span><span class="analytics-list__value">{{ number_format($metricValue($row)) }}</span></div>
                                        <div class="analytics-bar"><div class="analytics-bar__fill" style="width:{{ $percent($row, $continentMax) }}%"></div></div>
                                    </div>
                                @empty
                                    <div class="analytics-empty">No continent report.</div>
                                @endforelse
                            </div>
                        </article>
                    </div>
                </div>

                <div class="analytics-grid-2">
                    @foreach (['day_of_week' => 'Visits by weekday', 'local_time' => 'Visits by local hour'] as $key => $title)
                        @php($rows = $matomo[$key] ?? [])
                        @php($rowMax = $maxMetric($rows))
                        <article class="analytics-panel">
                            <h4 class="analytics-panel__title">{{ $title }}</h4>
                            <div class="analytics-list">
                                @forelse ($rows as $row)
                                    <div>
                                        <div class="analytics-list__row"><span class="analytics-list__label">{{ $row['label'] }}</span><span class="analytics-list__value">{{ number_format($metricValue($row)) }}</span></div>
                                        <div class="analytics-bar"><div class="analytics-bar__fill" style="width:{{ $percent($row, $rowMax) }}%"></div></div>
                                    </div>
                                @empty
                                    <div class="analytics-empty">No timing data.</div>
                                @endforelse
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="analytics-section" id="acquisition">
                <div class="analytics-section__header">
                    <div>
                        <h3 class="analytics-section__title">Acquisition</h3>
                        <p class="analytics-section__copy">Where visitors came from: referring sites, social networks, search engines, campaigns and AI assistants.</p>
                    </div>
                </div>

                <div class="analytics-grid-3">
                    @foreach ([
                        'referrer_websites' => ['Referring websites', 'No referring websites.'],
                        'socials' => ['Social networks', 'No social referrals.'],
                        'search_engines' => ['Search engines', 'No search referrals.'],
                        'ai_assistants' => ['AI assistants', 'No AI-assistant referrals.'],
                        'campaigns' => ['Campaigns', 'No tracked campaigns.'],
                    ] as $key => [$title, $empty])
                        <article class="analytics-panel">
                            <h4 class="analytics-panel__title">{{ $title }}</h4>
                            <div class="analytics-list">
                                @forelse (($matomo[$key] ?? []) as $row)
                                    <div class="analytics-list__row"><span class="analytics-list__label">{{ $row['label'] }}</span><span class="analytics-list__value">{{ number_format($metricValue($row)) }}</span></div>
                                @empty
                                    <div class="analytics-empty">{{ $empty }}</div>
                                @endforelse
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="analytics-section" id="content">
                <div class="analytics-section__header">
                    <div>
                        <h3 class="analytics-section__title">Content & journeys</h3>
                        <p class="analytics-section__copy">What visitors viewed, where sessions began and ended, and which downloads or external destinations followed.</p>
                    </div>
                </div>

                <div class="analytics-grid-wide">
                    <article class="analytics-panel">
                        <h4 class="analytics-panel__title">Most-viewed content</h4>
                        <div class="analytics-table-wrap">
                            <table class="analytics-table">
                                <thead><tr><th>Content</th><th>Views</th><th>Visits</th></tr></thead>
                                <tbody>
                                @forelse (($matomo['content'] ?? []) as $row)
                                    <tr><td>{{ $row['label'] }}</td><td>{{ number_format((int) (($row['nb_hits'] ?? 0) ?: ($row['nb_actions'] ?? 0))) }}</td><td>{{ number_format($metricValue($row)) }}</td></tr>
                                @empty
                                    <tr><td colspan="3">No content report.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </article>

                    <div class="analytics-grid-2">
                        @foreach (['downloads' => 'Downloads', 'outlinks' => 'Outbound destinations'] as $key => $title)
                            <article class="analytics-panel">
                                <h4 class="analytics-panel__title">{{ $title }}</h4>
                                <div class="analytics-list">
                                    @forelse (($matomo[$key] ?? []) as $row)
                                        <div class="analytics-list__row"><span class="analytics-list__label">{{ $row['label'] }}</span><span class="analytics-list__value">{{ number_format((int) (($row['nb_hits'] ?? 0) ?: ($row['nb_visits'] ?? 0))) }}</span></div>
                                    @empty
                                        <div class="analytics-empty">No activity.</div>
                                    @endforelse
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>

                <div class="analytics-grid-2">
                    @foreach (['entry_pages' => ['Entry pages', 'nb_entrances'], 'exit_pages' => ['Exit pages', 'nb_exits']] as $key => [$title, $metric])
                        <article class="analytics-panel">
                            <h4 class="analytics-panel__title">{{ $title }}</h4>
                            <div class="analytics-table-wrap">
                                <table class="analytics-table">
                                    <thead><tr><th>Path</th><th>Count</th></tr></thead>
                                    <tbody>
                                    @forelse (($matomo[$key] ?? []) as $row)
                                        <tr><td>{{ $row['label'] }}</td><td>{{ number_format((int) (($row[$metric] ?? 0) ?: ($row['nb_visits'] ?? 0))) }}</td></tr>
                                    @empty
                                        <tr><td colspan="2">No data.</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="analytics-section" id="interactions">
                <div class="analytics-section__header">
                    <div>
                        <h3 class="analytics-section__title">Artist interaction analytics</h3>
                        <p class="analytics-section__copy">Meaningful public interactions such as artwork opens, zooms, navigation, exhibition interest, blog reads and contact conversions.</p>
                    </div>
                </div>

                <div class="analytics-signal-grid">
                    @foreach ($interactionSignals as $label => $value)
                        <article class="analytics-card">
                            <div class="analytics-card__label">{{ $label }}</div>
                            <div class="analytics-card__value">{{ number_format((int) $value) }}</div>
                        </article>
                    @endforeach
                </div>

                <div class="analytics-grid-3">
                    @foreach (['events' => 'Event actions', 'event_categories' => 'Event categories', 'event_names' => 'Works / subjects engaged'] as $key => $title)
                        <article class="analytics-panel">
                            <h4 class="analytics-panel__title">{{ $title }}</h4>
                            <div class="analytics-list">
                                @forelse (($matomo[$key] ?? []) as $row)
                                    <div class="analytics-list__row"><span class="analytics-list__label">{{ $row['label'] }}</span><span class="analytics-list__value">{{ number_format((int) ($row['nb_events'] ?? 0)) }}</span></div>
                                @empty
                                    <div class="analytics-empty">No matching events yet.</div>
                                @endforelse
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="analytics-section" id="engagement-technology">
                <div class="analytics-section__header">
                    <div>
                        <h3 class="analytics-section__title">Engagement & technology</h3>
                        <p class="analytics-section__copy">Visit depth and aggregate compatibility context without visitor-level fingerprinting.</p>
                    </div>
                </div>

                <div class="analytics-grid-2">
                    @foreach (['visit_duration' => 'Visit duration', 'pages_per_visit' => 'Pages / actions per visit'] as $key => $title)
                        @php($rows = $matomo[$key] ?? [])
                        @php($rowMax = $maxMetric($rows))
                        <article class="analytics-panel">
                            <h4 class="analytics-panel__title">{{ $title }}</h4>
                            <div class="analytics-list">
                                @forelse ($rows as $row)
                                    <div>
                                        <div class="analytics-list__row"><span class="analytics-list__label">{{ $row['label'] }}</span><span class="analytics-list__value">{{ number_format($metricValue($row)) }}</span></div>
                                        <div class="analytics-bar"><div class="analytics-bar__fill" style="width:{{ $percent($row, $rowMax) }}%"></div></div>
                                    </div>
                                @empty
                                    <div class="analytics-empty">No engagement distribution.</div>
                                @endforelse
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="analytics-grid-3">
                    @foreach (['devices' => 'Device classes', 'browsers' => 'Browsers', 'operating_systems' => 'Operating systems'] as $key => $title)
                        @php($rows = $matomo[$key] ?? [])
                        @php($rowMax = $maxMetric($rows))
                        <article class="analytics-panel">
                            <h4 class="analytics-panel__title">{{ $title }}</h4>
                            <div class="analytics-list">
                                @forelse ($rows as $row)
                                    <div>
                                        <div class="analytics-list__row"><span class="analytics-list__label">{{ $row['label'] }}</span><span class="analytics-list__value">{{ number_format($metricValue($row)) }}</span></div>
                                        <div class="analytics-bar"><div class="analytics-bar__fill" style="width:{{ $percent($row, $rowMax) }}%"></div></div>
                                    </div>
                                @empty
                                    <div class="analytics-empty">No aggregate data.</div>
                                @endforelse
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>

            @if (($matomo['warnings'] ?? []) !== [])
                <div class="analytics-notice is-warning">
                    <strong>Partial Matomo reporting</strong>
                    <ul class="analytics-warning-list">
                        @foreach ($matomo['warnings'] as $warning)
                            <li>{{ $warning }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endif

        <section class="analytics-section" id="operational-health">
            <div class="analytics-section__header">
                <div>
                    <h3 class="analytics-section__title">Operational health</h3>
                    <p class="analytics-section__copy">Application-owned aggregates for errors, bots, request performance and admin traffic. These remain intentionally separate from human Matomo analytics.</p>
                </div>
            </div>

            <div class="analytics-summary-grid">
                @foreach ($operationalSummary as $label => $value)
                    <article class="analytics-card">
                        <div class="analytics-card__label">{{ $label }}</div>
                        <div class="analytics-card__value">{{ $value }}</div>
                    </article>
                @endforeach
            </div>

            <article class="analytics-panel">
                <h4 class="analytics-panel__title">Recent operational aggregates</h4>
                <div class="analytics-table-wrap">
                    <table class="analytics-table">
                        <thead><tr><th>Date</th><th>Metric</th><th>Value</th></tr></thead>
                        <tbody>
                        @forelse ($operational as $row)
                            <tr><td>{{ $row['date'] }}</td><td>{{ $row['label'] }}</td><td>{{ $row['display_value'] }}</td></tr>
                        @empty
                            <tr><td colspan="3">No operational aggregates recorded for this period.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </article>
        </section>
    </div>
</x-filament-panels::page>
