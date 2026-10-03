<x-filament-panels::page>
    @php
        $status = $matomo['status'] ?? null;
        $reportingAvailable = in_array($status, ['available', 'stale'], true);
        $reportAvailability = \App\Domain\Analytics\AnalyticsReportAvailability::fromReport($matomo);
        $countriesAvailable = $reportingAvailable && $reportAvailability->isAvailable('countries');
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
        $mapPoints = app(\App\Domain\Analytics\AnalyticsWorldMap::class)->points($countryRows);
        $previewCountryCodes = [
            'Germany' => 'de',
            'Netherlands' => 'nl',
            'Denmark' => 'dk',
            'France' => 'fr',
            'United Kingdom' => 'gb',
            'United States' => 'us',
            'Austria' => 'at',
            'Switzerland' => 'ch',
            'Sweden' => 'se',
            'Japan' => 'jp',
            'Spain' => 'es',
            'Italy' => 'it',
        ];
        $countryPresentation = [];
        $rank = 0;

        foreach ($countryRows as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            $visits = is_numeric($row['nb_visits'] ?? null) ? (float) $row['nb_visits'] : null;
            $share = $visits !== null && $totalVisits !== null
                ? ($totalVisits > 0 ? ($visits / $totalVisits) * 100 : 0.0)
                : null;
            $code = is_string($row['code'] ?? null) && preg_match('/^[a-z]{2}$/i', trim($row['code'])) === 1
                ? strtolower(trim($row['code']))
                : ($previewCountryCodes[$label] ?? null);
            $flagUrl = $code !== null && is_file(public_path("vendor/pixel-flags/svg/{$code}.svg"))
                ? asset("vendor/pixel-flags/svg/{$code}.svg")
                : null;

            $rankLabel = '—';
            if ($visits !== null) {
                $rank++;
                $rankLabel = number_format($rank);
            }

            $countryPresentation[$label] = [
                'visits' => $visits === null ? '—' : number_format((int) round($visits)),
                'share' => $share === null ? '—' : number_format($share, 1).'%',
                'rank' => $rankLabel,
                'flag_url' => $flagUrl,
                'code' => $code,
            ];
        }

        $rankedCountries = array_slice($countryRows, 0, 30);
        $initialCountry = $rankedCountries[0]['label'] ?? null;
        $leadingVisits = is_numeric($rankedCountries[0]['nb_visits'] ?? null)
            ? (float) $rankedCountries[0]['nb_visits']
            : null;
        $leadingShare = $leadingVisits !== null && $totalVisits !== null
            ? ($totalVisits > 0 ? ($leadingVisits / $totalVisits) * 100 : 0.0)
            : null;
        $outsideLeaderVisits = $leadingVisits !== null && $totalVisits !== null
            ? max(0.0, $totalVisits - $leadingVisits)
            : null;
        $outsideLeaderShare = $leadingShare !== null ? max(0.0, 100.0 - $leadingShare) : null;
        $geographyContext = [
            [
                'label' => 'Leading country',
                'value' => $initialCountry === null
                    ? '—'
                    : $initialCountry.($leadingShare === null ? '' : ' ('.number_format($leadingShare, 1).'%)'),
            ],
            [
                'label' => 'Visits outside leader',
                'value' => $outsideLeaderVisits === null
                    ? '—'
                    : number_format((int) round($outsideLeaderVisits)).($outsideLeaderShare === null ? '' : ' ('.number_format($outsideLeaderShare, 1).'%)'),
            ],
        ];
        $stageMessage = match (true) {
            $status === 'disabled' => 'No reporting data for this environment.',
            $status === 'unavailable' => 'Reporting data is currently unavailable.',
            ! $countriesAvailable => 'Country-level reporting is unavailable.',
            $countryRows === [] => 'No country-level visits in this period.',
            default => null,
        };
        $detailTable = $this->detailTable();
        $detailLayout = $this->detailTableLayout();
        $detailReportOptions = $this->detailReportOptions();
        $detailReportLabel = $detailReportOptions[$detailReport] ?? 'Analytics';
        $detailEmptyTitle = trim($search) !== ''
            ? 'No matching '.$detailReportLabel.' rows'
            : ($detailTable['state'] === 'unavailable' ? $detailReportLabel.' unavailable' : 'No '.$detailReportLabel.' data');
    @endphp

    <x-admin.workspace title="Analytics" class="analytics-dashboard">
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
            class="analytics-visual-stage admin-visual-stage admin-visual-stage--stackable"
            aria-label="Analytics Visual Stage"
            x-data="{
                selectedCountry: @js($initialCountry),
                selectCountry(country) { this.selectedCountry = country },
            }"
        >
            <x-admin.analytics-world-map
                class="admin-visual-stage__pane"
                :points="$mapPoints"
                :animate-arrival="true"
            />

            <aside class="analytics-stage-rail admin-visual-stage__pane" aria-label="Geography">
                <div class="analytics-stage-rail__heading">
                    <strong>Geography</strong>
                </div>

                @if ($rankedCountries !== [])
                    <div class="analytics-stage-ranking">
                        <div class="analytics-stage-ranking__head" aria-hidden="true">
                            <span>Country</span>
                            <span>Share</span>
                            <span>Visits</span>
                        </div>

                        <div class="analytics-stage-ranking__scroll" tabindex="0" aria-label="Country ranking">
                            @foreach ($rankedCountries as $row)
                                @php
                                    $label = (string) $row['label'];
                                    $presentation = $countryPresentation[$label] ?? [];
                                @endphp
                                <button
                                    type="button"
                                    class="analytics-country-rank"
                                    x-on:click="selectCountry(@js($label))"
                                    x-bind:class="selectedCountry === @js($label) ? 'is-selected' : ''"
                                    x-bind:aria-pressed="(selectedCountry === @js($label)).toString()"
                                >
                                    <span class="analytics-country-rank__identity">
                                        @if (is_string($presentation['flag_url'] ?? null))
                                            <img
                                                class="analytics-country-rank__flag {{ in_array($presentation['code'] ?? null, ['ch', 'va'], true) ? 'analytics-country-rank__flag--square' : '' }}"
                                                src="{{ $presentation['flag_url'] }}"
                                                data-country-code="{{ $presentation['code'] ?? '' }}"
                                                width="16"
                                                height="12"
                                                alt=""
                                                loading="lazy"
                                            >
                                        @elseif (is_string($presentation['code'] ?? null))
                                            <span class="analytics-country-rank__flag-code" aria-hidden="true">{{ strtoupper($presentation['code']) }}</span>
                                        @endif
                                        <span>{{ $label }}</span>
                                    </span>
                                    <small>{{ $presentation['share'] ?? '—' }}</small>
                                    <strong>{{ $presentation['visits'] ?? '—' }}</strong>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="analytics-stage-empty">
                        <p>{{ $stageMessage }}</p>
                    </div>
                @endif

                @if ($countryRows !== [])
                    <div class="analytics-stage-context" aria-label="Geography context">
                        @foreach ($geographyContext as $context)
                            <div class="analytics-stage-context__row">
                                <span>{{ $context['label'] }}</span>
                                <strong>{{ $context['value'] }}</strong>
                            </div>
                        @endforeach
                    </div>
                @endif
            </aside>
        </section>

        <section class="analytics-detail-surface admin-visual-stage-followup" aria-label="Analytics detail table">
            <x-admin.controls :metric-grid="true" :search-span="4" aria-label="Analytics report controls">
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

                    <label class="admin-data-field">
                        <span>Range</span>
                        <select wire:change="setRange($event.target.value)" aria-label="Analytics date range">
                            @foreach (['today' => 'Today', '7d' => '7 days', '30d' => '30 days', '12m' => '12 months'] as $preset => $label)
                                <option value="{{ $preset }}" @selected($range === $preset)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </x-slot:filters>
            </x-admin.controls>

            <x-admin.table class="admin-table--data analytics-detail-table">
                <table class="admin-table--six-grid analytics-detail-table__table">
                    <colgroup>
                        @for ($column = 0; $column < 12; $column++)
                            <col class="admin-table__col-half-unit">
                        @endfor
                    </colgroup>
                    @if ($detailTable['partial'])
                        <caption>{{ $detailTable['partial'] }}</caption>
                    @endif
                    <thead>
                        <tr>
                            @foreach ($detailLayout['columns'] as $column)
                                <th
                                    scope="col"
                                    colspan="{{ $column['span'] }}"
                                    class="{{ $column['numeric'] ? 'analytics-detail-table__numeric' : '' }}"
                                >{{ $detailTable['columns'][$column['index']] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @if ($detailTable['state'] === 'unavailable')
                            <tr>
                                <td class="admin-table__empty-cell" colspan="12">
                                    {{ $detailTable['message'] ?? $detailReportLabel.' unavailable' }}
                                </td>
                            </tr>
                        @else
                            @forelse ($detailTable['rows'] as $row)
                                <tr>
                                    @foreach ($detailLayout['columns'] as $column)
                                        @php
                                            $columnIndex = $column['index'];
                                            $cellValue = (string) ($row[$columnIndex] ?? '—');
                                            $isIdentity = $loop->first;
                                            $metaIndex = $detailLayout['meta_index'];
                                            $metaValue = $metaIndex === null ? null : (string) ($row[$metaIndex] ?? '');
                                        @endphp
                                        <td
                                            colspan="{{ $column['span'] }}"
                                            class="{{ $isIdentity ? 'admin-table__identity ' : '' }}{{ $column['numeric'] ? 'analytics-detail-table__numeric' : '' }}"
                                        >
                                            @if ($detailReport === 'geography' && $isIdentity)
                                                @php
                                                    $presentation = $countryPresentation[$cellValue] ?? [];
                                                @endphp
                                                <span class="analytics-country-rank__identity">
                                                    @if (is_string($presentation['flag_url'] ?? null))
                                                        <img
                                                            class="analytics-country-rank__flag {{ in_array($presentation['code'] ?? null, ['ch', 'va'], true) ? 'analytics-country-rank__flag--square' : '' }}"
                                                            src="{{ $presentation['flag_url'] }}"
                                                            data-country-code="{{ $presentation['code'] ?? '' }}"
                                                            width="16"
                                                            height="12"
                                                            alt=""
                                                            loading="lazy"
                                                        >
                                                    @elseif (is_string($presentation['code'] ?? null))
                                                        <span class="analytics-country-rank__flag-code" aria-hidden="true">{{ strtoupper($presentation['code']) }}</span>
                                                    @endif
                                                    <strong title="{{ $cellValue }}">{{ $cellValue }}</strong>
                                                </span>
                                            @elseif ($isIdentity)
                                                <strong title="{{ $cellValue }}">{{ $cellValue }}</strong>
                                                @if ($metaValue !== null && trim($metaValue) !== '')
                                                    <small title="{{ $metaValue }}">{{ $metaValue }}</small>
                                                @endif
                                            @else
                                                {{ $cellValue }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td class="admin-table__empty-cell" colspan="12">
                                        <x-admin.empty-state :title="$detailEmptyTitle" minimal>
                                            @if (trim($search) !== '')
                                                <x-slot:actions>
                                                    <button class="admin-action" type="button" wire:click="$set('search', '')">Clear search</button>
                                                </x-slot:actions>
                                            @endif
                                        </x-admin.empty-state>
                                    </td>
                                </tr>
                            @endforelse
                        @endif
                    </tbody>
                </table>
            </x-admin.table>

            @if (($detailTable['total'] ?? 0) > 0)
                <x-admin.pager
                    :start="$detailTable['start']"
                    :end="$detailTable['end']"
                    :total="$detailTable['total']"
                    :page="$detailTable['page']"
                    :pages="$detailTable['pages']"
                    :page-size="$detailPageSize"
                    page-size-wire-model="detailPageSize"
                    page-size-aria-label="Analytics rows per page"
                    previous-wire-action="previousDetailPage"
                    next-wire-action="nextDetailPage"
                    aria-label="Analytics detail pagination"
                />
            @endif
        </section>
    </x-admin.workspace>
</x-filament-panels::page>
