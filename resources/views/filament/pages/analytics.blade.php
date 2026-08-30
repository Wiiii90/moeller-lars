<x-filament-panels::page>
    @php
        $status = $matomo['status'] ?? null;
        $reportingAvailable = in_array($status, ['available', 'stale'], true);
        $reportAvailability = \App\Domain\Analytics\AnalyticsReportAvailability::fromReport($matomo);
        $countriesAvailable = $reportingAvailable && $reportAvailability->isAvailable('countries');
        $centroids = config('analytics-country-centroids', []);
        $countryRows = $countriesAvailable
            ? array_values(array_filter(
                $matomo['countries'] ?? [],
                static fn (mixed $row): bool => is_array($row)
                    && is_string($row['label'] ?? null)
                    && trim($row['label']) !== '',
            ))
            : [];

        usort($countryRows, static function (array $a, array $b): int {
            $aVisits = is_numeric($a['nb_visits'] ?? null) ? (float) $a['nb_visits'] : -1.0;
            $bVisits = is_numeric($b['nb_visits'] ?? null) ? (float) $b['nb_visits'] : -1.0;

            return $bVisits <=> $aVisits;
        });

        $totalVisits = is_numeric($matomo['metrics']['nb_visits'] ?? null)
            ? (float) $matomo['metrics']['nb_visits']
            : null;
        $positiveVisits = array_values(array_filter(
            array_map(
                static fn (array $row): ?float => is_numeric($row['nb_visits'] ?? null) ? (float) $row['nb_visits'] : null,
                $countryRows,
            ),
            static fn (?float $value): bool => $value !== null && $value > 0,
        ));
        $countryMax = $positiveVisits === [] ? 1.0 : max($positiveVisits);
        $mapPoints = [];
        $countryContext = [];
        $rank = 0;

        foreach ($countryRows as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            $visits = is_numeric($row['nb_visits'] ?? null) ? (float) $row['nb_visits'] : null;
            $rankLabel = '—';
            if ($visits !== null) {
                $rank++;
                $rankLabel = number_format($rank);
            }

            $share = null;
            if ($visits !== null && $totalVisits !== null) {
                $share = $totalVisits > 0 ? ($visits / $totalVisits) * 100 : 0.0;
            }

            $countryContext[$label] = [
                'visits' => $visits === null ? '—' : number_format((int) round($visits)),
                'share' => $share === null ? '—' : number_format($share, 1).'%',
                'rank' => $rankLabel,
            ];

            $coords = $centroids[$label] ?? null;
            if (! is_array($coords) || count($coords) < 2 || $visits === null || $visits <= 0) {
                continue;
            }

            $lat = (float) $coords[0];
            $lon = (float) $coords[1];
            $mapPoints[] = [
                'label' => $label,
                'visits' => (int) round($visits),
                'x' => min(99, max(1, (($lon + 180.0) / 360.0) * 100.0)),
                'y' => min(98, max(2, ((90.0 - $lat) / 180.0) * 100.0)),
                'size' => 9.0 + (22.0 * sqrt($visits / $countryMax)),
            ];
        }

        $topCountries = array_slice($countryRows, 0, 6);
        $initialCountry = $topCountries[0]['label'] ?? null;
        $mapViewExists = view()->exists('filament.generated.analytics-world-map');
        $stageMessage = match (true) {
            $status === 'disabled' => 'No reporting data for this environment.',
            $status === 'unavailable' => 'Reporting data is currently unavailable.',
            ! $countriesAvailable => 'Country-level reporting is unavailable.',
            $countryRows === [] => 'No country-level visits in this period.',
            default => null,
        };
        $stageHighlights = collect($audienceHighlights)
            ->filter(static fn (array $highlight): bool => in_array(
                $highlight['label'] ?? null,
                ['Leading source', 'Most viewed content', 'AI referrals'],
                true,
            ))
            ->filter(static fn (array $highlight): bool => ($highlight['value'] ?? '—') !== '—')
            ->take(2)
            ->values()
            ->all();
        $workspaceStatusTone = match ($status) {
            'available' => 'success',
            'stale' => 'warning',
            'disabled', 'unavailable' => 'danger',
            default => 'neutral',
        };
        $detailTable = $this->detailTable();
        $detailReportOptions = $this->detailReportOptions();
    @endphp

    <x-admin.workspace title="Analytics" class="analytics-dashboard">
        <x-slot:status>
            <x-admin.status :tone="$workspaceStatusTone">
                @if ($status === 'available') Live Matomo
                @elseif ($status === 'stale') Cached Matomo
                @elseif ($status === 'disabled') Reporting disabled
                @else Reporting unavailable
                @endif
            </x-admin.status>
        </x-slot:status>

        <x-admin.metrics :columns="6" aria-label="Traffic summary">
            @foreach ($kpis as $kpi)
                <x-admin.metric
                    :label="$kpi['label']"
                    :value="$kpi['value']"
                    :description="$kpi['comparison']"
                    class="{{ is_numeric($kpi['delta']) && $kpi['delta'] > 0 ? 'is-up' : (is_numeric($kpi['delta']) && $kpi['delta'] < 0 ? 'is-down' : '') }}"
                />
            @endforeach
        </x-admin.metrics>

        <section
            class="analytics-visual-stage"
            aria-label="Analytics Visual Stage"
            x-data="{
                selectedCountry: @js($initialCountry),
                activeCountry: @js($initialCountry),
                countries: @js($countryContext),
                previewCountry(country) { this.activeCountry = country },
                restoreCountry() { this.activeCountry = this.selectedCountry },
                selectCountry(country) { this.selectedCountry = country; this.activeCountry = country },
            }"
        >
            <figure class="analytics-world" aria-label="World visitor map">
                <div class="analytics-world__canvas">
                    @if ($mapViewExists)
                        @include('filament.generated.analytics-world-map')
                    @else
                        <div class="analytics-map-build-warning" role="status">
                            Map geometry is unavailable in this build.
                        </div>
                    @endif

                    @foreach ($mapPoints as $point)
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

            <aside class="analytics-stage-rail" aria-label="Geography context">
                <div class="analytics-stage-rail__heading">
                    <div>
                        <span>Human visits</span>
                        <strong>Geography</strong>
                    </div>
                    <small>Matomo</small>
                </div>

                @if ($countryContext !== [])
                    <dl class="analytics-country-context">
                        <div class="analytics-country-context__country">
                            <dt>Country</dt>
                            <dd x-text="activeCountry ?? '—'">{{ $initialCountry ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt>Visits</dt>
                            <dd x-text="activeCountry && countries[activeCountry] ? countries[activeCountry].visits : '—'">{{ $initialCountry ? $countryContext[$initialCountry]['visits'] : '—' }}</dd>
                        </div>
                        <div>
                            <dt>Share</dt>
                            <dd x-text="activeCountry && countries[activeCountry] ? countries[activeCountry].share : '—'">{{ $initialCountry ? $countryContext[$initialCountry]['share'] : '—' }}</dd>
                        </div>
                        <div>
                            <dt>Rank</dt>
                            <dd x-text="activeCountry && countries[activeCountry] ? countries[activeCountry].rank : '—'">{{ $initialCountry ? $countryContext[$initialCountry]['rank'] : '—' }}</dd>
                        </div>
                    </dl>
                @else
                    <div class="analytics-stage-empty">
                        <strong>Geography context</strong>
                        <p>{{ $stageMessage }}</p>
                    </div>
                @endif

                <div class="analytics-stage-ranking">
                    <div class="analytics-stage-ranking__head">
                        <strong>Top countries</strong>
                        <span>Visits</span>
                    </div>

                    @if ($topCountries !== [])
                        @foreach ($topCountries as $row)
                            @php
                                $label = (string) $row['label'];
                                $visits = is_numeric($row['nb_visits'] ?? null) ? (int) round((float) $row['nb_visits']) : null;
                            @endphp
                            <button
                                type="button"
                                class="analytics-country-rank"
                                x-on:mouseenter="previewCountry(@js($label))"
                                x-on:mouseleave="restoreCountry()"
                                x-on:focus="previewCountry(@js($label))"
                                x-on:blur="restoreCountry()"
                                x-on:click="selectCountry(@js($label))"
                                x-bind:class="selectedCountry === @js($label) ? 'is-selected' : ''"
                                x-bind:aria-pressed="(selectedCountry === @js($label)).toString()"
                            >
                                <span>{{ $label }}</span>
                                <small>{{ $countryContext[$label]['share'] ?? '—' }}</small>
                                <strong>{{ $visits === null ? '—' : number_format($visits) }}</strong>
                            </button>
                        @endforeach
                    @else
                        <p class="analytics-stage-ranking__empty">{{ $stageMessage }}</p>
                    @endif
                </div>

                @if ($stageHighlights !== [] || $applicationSignals !== [])
                    <div class="analytics-stage-signals">
                        @if ($stageHighlights !== [])
                            <div>
                                <span class="analytics-stage-signals__source">Human analytics · Matomo</span>
                                @foreach ($stageHighlights as $highlight)
                                    <div class="analytics-stage-signal">
                                        <span>{{ $highlight['label'] }}</span>
                                        <strong>{{ $highlight['value'] }}</strong>
                                        <small>{{ $highlight['detail'] }}</small>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if ($applicationSignals !== [])
                            <div>
                                <span class="analytics-stage-signals__source">Application signals</span>
                                @foreach ($applicationSignals as $signal)
                                    <div class="analytics-stage-signal">
                                        <span>{{ $signal['label'] }}</span>
                                        <strong>{{ $signal['value'] }}</strong>
                                        <small>{{ $signal['detail'] }}</small>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            </aside>
        </section>

        <section class="analytics-detail-surface" aria-label="Analytics detail table">
            <x-admin.controls class="analytics-controls" aria-label="Analytics report controls">
                <x-slot:search>
                    <label class="admin-data-field">
                        <span>Search</span>
                        <input
                            type="search"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Search current report"
                            autocomplete="off"
                        >
                    </label>
                </x-slot:search>

                <x-slot:filters>
                    <label class="admin-data-field">
                        <span>Report</span>
                        <select wire:model.live="detailReport">
                            @foreach ($detailReportOptions as $report => $label)
                                <option value="{{ $report }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </x-slot:filters>

                <x-slot:reset>
                    <div class="admin-data-control-group">
                        <span class="admin-data-control-label">Filter</span>
                        <button class="admin-action" type="button" wire:click="$set('search', '')" @disabled(trim($search) === '')>Reset</button>
                    </div>
                </x-slot:reset>

                <x-slot:actions>
                    <div class="admin-data-control-group analytics-range-control">
                        <span class="admin-data-control-label">Range</span>
                        <x-admin.toolbar aria-label="Analytics date range">
                            @foreach (['today' => 'Today', '7d' => '7d', '30d' => '30d', '12m' => '12m'] as $preset => $label)
                                <button
                                    type="button"
                                    wire:click="setRange('{{ $preset }}')"
                                    class="admin-action {{ $range === $preset ? 'is-primary' : '' }}"
                                >{{ $label }}</button>
                            @endforeach
                        </x-admin.toolbar>
                    </div>
                </x-slot:actions>
            </x-admin.controls>

            <x-admin.table class="admin-table--data analytics-detail-table">
                @if ($detailTable['rows'] !== [])
                    <table>
                        @if ($detailTable['partial'])
                            <caption>{{ $detailTable['partial'] }}</caption>
                        @endif
                        <thead>
                            <tr>
                                @foreach ($detailTable['columns'] as $column)
                                    <th scope="col">{{ $column }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($detailTable['rows'] as $row)
                                <tr>
                                    @foreach ($row as $cell)
                                        <td class="{{ $loop->first ? 'admin-table__identity' : '' }}">{{ $cell }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <x-admin.empty-state title="No analytics detail rows">
                        <p>{{ $detailTable['message'] }}</p>
                    </x-admin.empty-state>
                @endif
            </x-admin.table>

            @if ($detailTable['total'] > 12)
                <div class="analytics-pager-boundary">
                    <footer class="admin-pager" aria-label="Analytics detail pagination">
                        <label class="admin-pager__size">
                            <span>Per page</span>
                            <select wire:model.live.number="detailPageSize">
                                <option value="12">12</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                            </select>
                        </label>

                        <span class="admin-pager__range">
                            {{ $detailTable['start'] }}–{{ $detailTable['end'] }} of {{ $detailTable['total'] }}
                        </span>

                        <div class="admin-pager__actions admin-toolbar">
                            <button class="admin-action" type="button" wire:click="previousDetailPage" @disabled($detailTable['page'] <= 1)>Previous</button>
                            <button class="admin-action" type="button" wire:click="nextDetailPage" @disabled($detailTable['page'] >= $detailTable['pages'])>Next</button>
                        </div>
                    </footer>
                </div>
            @endif
        </section>
    </x-admin.workspace>
</x-filament-panels::page>
