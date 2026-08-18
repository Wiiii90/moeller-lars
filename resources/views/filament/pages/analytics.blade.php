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
    @endphp

    <div class="space-y-10">
        <section class="space-y-5">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div class="max-w-3xl">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-2xl font-semibold tracking-tight text-gray-950 dark:text-white">Human analytics</h2>
                        @if ($status === 'available')
                            <span class="rounded-full bg-success-50 px-2.5 py-1 text-xs font-semibold text-success-700 ring-1 ring-inset ring-success-600/20 dark:bg-success-400/10 dark:text-success-300">LIVE MATOMO</span>
                        @elseif ($status === 'stale')
                            <span class="rounded-full bg-warning-50 px-2.5 py-1 text-xs font-semibold text-warning-700 ring-1 ring-inset ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-300">CACHED</span>
                        @endif
                    </div>
                    <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-400">
                        Matomo Core reporting for audience, acquisition, content, behaviour and artist-specific interactions. Data is aggregate; raw visitor identities are not mirrored into this application.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2" role="group" aria-label="Analytics date range">
                    @foreach (['today' => 'Today', '7d' => '7 days', '30d' => '30 days', '12m' => '12 months'] as $preset => $label)
                        <button
                            type="button"
                            wire:click="setRange('{{ $preset }}')"
                            @class([
                                'rounded-lg border px-3 py-2 text-sm font-medium transition',
                                'border-primary-600 bg-primary-600 text-white shadow-sm' => $range === $preset,
                                'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10' => $range !== $preset,
                            ])
                        >{{ $label }}</button>
                    @endforeach
                </div>
            </div>

            <div wire:loading.flex wire:target="setRange" class="items-center gap-2 rounded-lg border border-primary-200 bg-primary-50 px-4 py-3 text-sm text-primary-700 dark:border-primary-400/20 dark:bg-primary-400/10 dark:text-primary-300">
                Updating Matomo reports…
            </div>

            @if ($status === 'disabled')
                <div class="rounded-xl border border-gray-200 bg-white p-6 text-sm text-gray-700 shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
                    <strong>Matomo reporting is not enabled for this application runtime.</strong>
                    <span class="mt-1 block">{{ $matomo['message'] ?? 'Reporting API access is disabled.' }}</span>
                </div>
            @elseif ($status === 'unavailable')
                <div class="rounded-xl border border-danger-200 bg-danger-50 p-6 text-sm text-danger-800 dark:border-danger-400/20 dark:bg-danger-400/10 dark:text-danger-200">
                    <strong>Matomo reporting is temporarily unavailable.</strong>
                    <span class="mt-1 block">{{ $matomo['message'] ?? 'The Reporting API could not be read.' }}</span>
                </div>
            @elseif ($available)
                @if ($status === 'stale')
                    <div class="rounded-xl border border-warning-200 bg-warning-50 p-4 text-sm text-warning-800 dark:border-warning-400/20 dark:bg-warning-400/10 dark:text-warning-200">
                        {{ $matomo['message'] ?? 'Showing cached analytics because live reporting is unavailable.' }}
                    </div>
                @endif

                <div class="flex flex-wrap items-center gap-x-5 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ $matomo['range']['label'] ?? 'Selected range' }}</span>
                    @if (filled($matomo['range']['start'] ?? null) && filled($matomo['range']['end'] ?? null))
                        <span>{{ $matomo['range']['start'] }} – {{ $matomo['range']['end'] }}</span>
                    @endif
                    <span>Source: self-hosted Matomo Reporting API</span>
                    @if (filled($matomo['generated_at'] ?? null))
                        <span>Generated {{ $matomo['generated_at'] }}</span>
                    @endif
                </div>

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
                    @foreach ($kpis as $kpi)
                        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
                            <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $kpi['label'] }}</div>
                            <div class="mt-2 text-3xl font-semibold tracking-tight text-gray-950 dark:text-white">{{ $kpi['value'] }}</div>
                            <div @class([
                                'mt-3 text-xs font-medium',
                                'text-success-600 dark:text-success-400' => is_numeric($kpi['delta']) && $kpi['delta'] > 0,
                                'text-danger-600 dark:text-danger-400' => is_numeric($kpi['delta']) && $kpi['delta'] < 0,
                                'text-gray-500 dark:text-gray-400' => !is_numeric($kpi['delta']) || $kpi['delta'] == 0,
                            ])>{{ $kpi['comparison'] }}</div>
                        </div>
                    @endforeach
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/5">
                    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-950 dark:text-white">Traffic & engagement over time</h3>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Visits versus tracked actions across the selected period.</p>
                        </div>
                        @if ($trendChart !== [])
                            <div class="flex gap-4 text-xs font-medium text-gray-600 dark:text-gray-300">
                                <span class="text-primary-600 dark:text-primary-400">● Visits</span>
                                <span>● Actions</span>
                            </div>
                        @endif
                    </div>

                    @if ($trendChart === [])
                        <p class="mt-6 text-sm text-gray-500 dark:text-gray-400">No time-series data is available for this period.</p>
                    @else
                        <div class="mt-6 overflow-hidden rounded-xl border border-gray-100 bg-gray-50/60 p-3 dark:border-white/5 dark:bg-black/10">
                            <svg viewBox="0 0 1000 260" class="h-64 w-full" role="img" aria-label="Visits and tracked actions trend">
                                <line x1="22" y1="238" x2="978" y2="238" class="stroke-gray-300 dark:stroke-white/10" stroke-width="1" />
                                <line x1="22" y1="130" x2="978" y2="130" class="stroke-gray-200 dark:stroke-white/5" stroke-width="1" />
                                <polyline points="{{ $trendChart['actions_points'] }}" fill="none" stroke="currentColor" class="text-gray-400 dark:text-gray-500" stroke-width="3" vector-effect="non-scaling-stroke" />
                                <polyline points="{{ $trendChart['visits_points'] }}" fill="none" stroke="currentColor" class="text-primary-600 dark:text-primary-400" stroke-width="4" vector-effect="non-scaling-stroke" />
                            </svg>
                        </div>
                        <div class="mt-3 flex justify-between text-xs text-gray-500 dark:text-gray-400">
                            <span>{{ $trendChart['start'] }}</span>
                            <span>{{ $trendChart['points'] }} data point{{ $trendChart['points'] === 1 ? '' : 's' }}</span>
                            <span>{{ $trendChart['end'] }}</span>
                        </div>
                    @endif
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                    @foreach ($audienceHighlights as $highlight)
                        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
                            <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $highlight['label'] }}</div>
                            <div class="mt-2 break-words text-lg font-semibold text-gray-950 dark:text-white">{{ $highlight['value'] }}</div>
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $highlight['detail'] }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        @if ($available)
            <section class="space-y-5 border-t border-gray-200 pt-9 dark:border-white/10" id="acquisition">
                <div>
                    <h2 class="text-xl font-semibold text-gray-950 dark:text-white">Acquisition</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">How people discovered the site: direct, websites, social networks, search, campaigns and AI assistants.</p>
                </div>

                <div class="grid gap-6 xl:grid-cols-2">
                    @php($sourceRows = $matomo['referrers'] ?? [])
                    @php($sourceMax = $maxMetric($sourceRows))
                    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
                        <h3 class="font-semibold text-gray-950 dark:text-white">Traffic source mix</h3>
                        @forelse ($sourceRows as $row)
                            <div class="mt-4">
                                <div class="flex items-center justify-between gap-4 text-sm">
                                    <span class="font-medium text-gray-800 dark:text-gray-200">{{ $row['label'] }}</span>
                                    <span class="text-gray-500 dark:text-gray-400">{{ number_format($metricValue($row)) }} visits</span>
                                </div>
                                <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10"><div class="h-full rounded-full bg-primary-500" style="width: {{ $percent($row, $sourceMax) }}%"></div></div>
                            </div>
                        @empty
                            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">No referrer summary is available.</p>
                        @endforelse
                    </div>

                    @php($websiteRows = $matomo['referrer_websites'] ?? [])
                    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
                        <h3 class="font-semibold text-gray-950 dark:text-white">Referring websites</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">External websites that sent visitors here.</p>
                        @if ($websiteRows === [])
                            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">No referring websites in this period.</p>
                        @else
                            <div class="mt-4 overflow-x-auto">
                                <table class="w-full text-left text-sm">
                                    <thead class="text-xs uppercase text-gray-500 dark:text-gray-400"><tr><th class="pb-2 pr-4">Website</th><th class="pb-2 text-right">Visits</th></tr></thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                    @foreach ($websiteRows as $row)
                                        <tr><td class="py-2.5 pr-4 font-medium text-gray-900 dark:text-gray-100">{{ $row['label'] }}</td><td class="py-2.5 text-right text-gray-600 dark:text-gray-300">{{ number_format($metricValue($row)) }}</td></tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                    @foreach ([
                        'socials' => ['Social networks', 'No social-network referrals.'],
                        'search_engines' => ['Search engines', 'No search-engine referrals.'],
                        'ai_assistants' => ['AI assistants', 'No AI-assistant referrals.'],
                        'campaigns' => ['Campaigns', 'No tracked campaigns.'],
                    ] as $key => [$title, $empty])
                        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
                            <h3 class="font-semibold text-gray-950 dark:text-white">{{ $title }}</h3>
                            @forelse (($matomo[$key] ?? []) as $row)
                                <div class="mt-3 flex items-center justify-between gap-3 text-sm">
                                    <span class="truncate text-gray-700 dark:text-gray-200">{{ $row['label'] }}</span>
                                    <span class="shrink-0 font-semibold text-gray-950 dark:text-white">{{ number_format($metricValue($row)) }}</span>
                                </div>
                            @empty
                                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">{{ $empty }}</p>
                            @endforelse
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="space-y-5 border-t border-gray-200 pt-9 dark:border-white/10" id="audience">
                <div>
                    <h2 class="text-xl font-semibold text-gray-950 dark:text-white">Audience & geography</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Country and continent aggregates plus returning-visitor and visit-time context. No visitor-level map or precise location list is exposed.</p>
                </div>

                <div class="grid gap-6 xl:grid-cols-3">
                    @php($countryRows = $matomo['countries'] ?? [])
                    @php($countryMax = $maxMetric($countryRows))
                    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm xl:col-span-2 dark:border-white/10 dark:bg-white/5">
                        <h3 class="font-semibold text-gray-950 dark:text-white">Countries</h3>
                        <div class="mt-4 grid gap-x-8 gap-y-3 md:grid-cols-2">
                            @forelse ($countryRows as $row)
                                <div>
                                    <div class="flex justify-between gap-3 text-sm"><span class="truncate text-gray-700 dark:text-gray-200">{{ $row['label'] }}</span><span class="font-semibold text-gray-950 dark:text-white">{{ number_format($metricValue($row)) }}</span></div>
                                    <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10"><div class="h-full rounded-full bg-primary-500" style="width: {{ $percent($row, $countryMax) }}%"></div></div>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500 dark:text-gray-400">No country data is available.</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="space-y-5">
                        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
                            <h3 class="font-semibold text-gray-950 dark:text-white">Continents</h3>
                            @forelse (($matomo['continents'] ?? []) as $row)
                                <div class="mt-3 flex items-center justify-between gap-3 text-sm"><span class="text-gray-700 dark:text-gray-200">{{ $row['label'] }}</span><span class="font-semibold text-gray-950 dark:text-white">{{ number_format($metricValue($row)) }}</span></div>
                            @empty
                                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">No continent data.</p>
                            @endforelse
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
                            <h3 class="font-semibold text-gray-950 dark:text-white">Returning visitors</h3>
                            @php($returning = $matomo['returning'] ?? [])
                            <div class="mt-4 grid grid-cols-2 gap-3">
                                <div><div class="text-xs text-gray-500 dark:text-gray-400">Returning visits</div><div class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ number_format((int) ($returning['nb_visits_returning'] ?? 0)) }}</div></div>
                                <div><div class="text-xs text-gray-500 dark:text-gray-400">Returning actions</div><div class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ number_format((int) ($returning['nb_actions_returning'] ?? 0)) }}</div></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid gap-6 xl:grid-cols-2">
                    @foreach (['day_of_week' => 'Visits by weekday', 'local_time' => 'Visits by local hour'] as $key => $title)
                        @php($rows = $matomo[$key] ?? [])
                        @php($rowMax = $maxMetric($rows))
                        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
                            <h3 class="font-semibold text-gray-950 dark:text-white">{{ $title }}</h3>
                            <div class="mt-4 space-y-3">
                                @forelse ($rows as $row)
                                    <div>
                                        <div class="flex justify-between gap-3 text-xs"><span class="text-gray-600 dark:text-gray-300">{{ $row['label'] }}</span><span class="font-medium text-gray-900 dark:text-white">{{ number_format($metricValue($row)) }}</span></div>
                                        <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10"><div class="h-full rounded-full bg-primary-500" style="width: {{ $percent($row, $rowMax) }}%"></div></div>
                                    </div>
                                @empty
                                    <p class="text-sm text-gray-500 dark:text-gray-400">No timing data is available.</p>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="space-y-5 border-t border-gray-200 pt-9 dark:border-white/10" id="content">
                <div>
                    <h2 class="text-xl font-semibold text-gray-950 dark:text-white">Content & journeys</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">What people looked at, where visits began and ended, and which external/download actions followed.</p>
                </div>

                <div class="grid gap-6 xl:grid-cols-3">
                    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm xl:col-span-2 dark:border-white/10 dark:bg-white/5">
                        <h3 class="font-semibold text-gray-950 dark:text-white">Most-viewed content</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Public paths only; URL query strings are removed before display.</p>
                        @if (($matomo['content'] ?? []) === [])
                            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">No content report is available.</p>
                        @else
                            <div class="mt-4 overflow-x-auto">
                                <table class="w-full text-left text-sm">
                                    <thead class="text-xs uppercase text-gray-500 dark:text-gray-400"><tr><th class="pb-2 pr-4">Content</th><th class="pb-2 text-right">Views</th><th class="pb-2 pl-4 text-right">Visits</th></tr></thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                    @foreach ($matomo['content'] as $row)
                                        <tr><td class="py-2.5 pr-4 font-medium text-gray-900 dark:text-gray-100">{{ $row['label'] }}</td><td class="py-2.5 text-right text-gray-700 dark:text-gray-300">{{ number_format((int) ($row['nb_hits'] ?: $row['nb_actions'])) }}</td><td class="py-2.5 pl-4 text-right text-gray-500 dark:text-gray-400">{{ number_format($metricValue($row)) }}</td></tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
                        <h3 class="font-semibold text-gray-950 dark:text-white">Downloads</h3>
                        @forelse (($matomo['downloads'] ?? []) as $row)
                            <div class="mt-3 flex items-start justify-between gap-3 text-sm"><span class="break-all text-gray-700 dark:text-gray-200">{{ $row['label'] }}</span><span class="shrink-0 font-semibold text-gray-950 dark:text-white">{{ number_format((int) ($row['nb_hits'] ?: $row['nb_visits'])) }}</span></div>
                        @empty
                            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">No tracked downloads.</p>
                        @endforelse
                    </div>
                </div>

                <div class="grid gap-6 xl:grid-cols-2">
                    @foreach (['entry_pages' => ['Entry pages', 'nb_entrances', 'Entrances'], 'exit_pages' => ['Exit pages', 'nb_exits', 'Exits']] as $key => [$title, $metric, $metricLabel])
                        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
                            <h3 class="font-semibold text-gray-950 dark:text-white">{{ $title }}</h3>
                            <div class="mt-4 overflow-x-auto">
                                <table class="w-full text-left text-sm">
                                    <thead class="text-xs uppercase text-gray-500 dark:text-gray-400"><tr><th class="pb-2 pr-4">Path</th><th class="pb-2 text-right">{{ $metricLabel }}</th></tr></thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                    @forelse (($matomo[$key] ?? []) as $row)
                                        <tr><td class="py-2.5 pr-4 font-medium text-gray-900 dark:text-gray-100">{{ $row['label'] }}</td><td class="py-2.5 text-right text-gray-600 dark:text-gray-300">{{ number_format((int) (($row[$metric] ?? 0) ?: ($row['nb_visits'] ?? 0))) }}</td></tr>
                                    @empty
                                        <tr><td colspan="2" class="py-4 text-gray-500 dark:text-gray-400">No data available.</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="grid gap-6 xl:grid-cols-3">
                    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
                        <h3 class="font-semibold text-gray-950 dark:text-white">Outbound destinations</h3>
                        @forelse (($matomo['outlinks'] ?? []) as $row)
                            <div class="mt-3 flex items-start justify-between gap-3 text-sm"><span class="break-all text-gray-700 dark:text-gray-200">{{ $row['label'] }}</span><span class="shrink-0 font-semibold text-gray-950 dark:text-white">{{ number_format((int) ($row['nb_hits'] ?: $row['nb_visits'])) }}</span></div>
                        @empty
                            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">No outbound clicks.</p>
                        @endforelse
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
                        <h3 class="font-semibold text-gray-950 dark:text-white">Site searches</h3>
                        @forelse (($matomo['site_searches'] ?? []) as $row)
                            <div class="mt-3 flex items-center justify-between gap-3 text-sm"><span class="text-gray-700 dark:text-gray-200">{{ $row['label'] }}</span><span class="font-semibold text-gray-950 dark:text-white">{{ number_format($metricValue($row)) }}</span></div>
                        @empty
                            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">No internal search activity.</p>
                        @endforelse
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
                        <h3 class="font-semibold text-gray-950 dark:text-white">Searches with no result</h3>
                        @forelse (($matomo['site_search_no_results'] ?? []) as $row)
                            <div class="mt-3 flex items-center justify-between gap-3 text-sm"><span class="text-gray-700 dark:text-gray-200">{{ $row['label'] }}</span><span class="font-semibold text-gray-950 dark:text-white">{{ number_format($metricValue($row)) }}</span></div>
                        @empty
                            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">None recorded.</p>
                        @endforelse
                    </div>
                </div>
            </section>

            <section class="space-y-5 border-t border-gray-200 pt-9 dark:border-white/10" id="interactions">
                <div>
                    <h2 class="text-xl font-semibold text-gray-950 dark:text-white">Artist interaction analytics</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Events emitted by the public experience show which works and editorial surfaces visitors actively engage with rather than merely loading.</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($interactionSignals as $label => $value)
                        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
                            <div class="text-sm text-gray-600 dark:text-gray-400">{{ $label }}</div>
                            <div class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ number_format((int) $value) }}</div>
                        </div>
                    @endforeach
                </div>

                <div class="grid gap-6 xl:grid-cols-3">
                    @foreach (['events' => 'Event actions', 'event_categories' => 'Event categories', 'event_names' => 'Works / subjects engaged'] as $key => $title)
                        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
                            <h3 class="font-semibold text-gray-950 dark:text-white">{{ $title }}</h3>
                            @forelse (($matomo[$key] ?? []) as $row)
                                <div class="mt-3 flex items-center justify-between gap-3 text-sm"><span class="truncate text-gray-700 dark:text-gray-200">{{ $row['label'] }}</span><span class="shrink-0 font-semibold text-gray-950 dark:text-white">{{ number_format((int) ($row['nb_events'] ?? 0)) }}</span></div>
                            @empty
                                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">No matching events yet.</p>
                            @endforelse
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="space-y-5 border-t border-gray-200 pt-9 dark:border-white/10" id="engagement">
                <div>
                    <h2 class="text-xl font-semibold text-gray-950 dark:text-white">Engagement depth</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">How long visits last and how many pages/actions visitors consume.</p>
                </div>

                <div class="grid gap-6 xl:grid-cols-2">
                    @foreach (['visit_duration' => 'Visit duration', 'pages_per_visit' => 'Pages / actions per visit'] as $key => $title)
                        @php($rows = $matomo[$key] ?? [])
                        @php($rowMax = $maxMetric($rows))
                        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
                            <h3 class="font-semibold text-gray-950 dark:text-white">{{ $title }}</h3>
                            <div class="mt-4 space-y-3">
                                @forelse ($rows as $row)
                                    <div>
                                        <div class="flex justify-between gap-3 text-sm"><span class="text-gray-700 dark:text-gray-200">{{ $row['label'] }}</span><span class="font-semibold text-gray-950 dark:text-white">{{ number_format($metricValue($row)) }}</span></div>
                                        <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10"><div class="h-full rounded-full bg-primary-500" style="width: {{ $percent($row, $rowMax) }}%"></div></div>
                                    </div>
                                @empty
                                    <p class="text-sm text-gray-500 dark:text-gray-400">No engagement distribution is available.</p>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="space-y-5 border-t border-gray-200 pt-9 dark:border-white/10" id="technology">
                <div>
                    <h2 class="text-xl font-semibold text-gray-950 dark:text-white">Technology</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Aggregate device, browser and operating-system mix for compatibility decisions.</p>
                </div>

                <div class="grid gap-6 lg:grid-cols-3">
                    @foreach (['devices' => 'Device classes', 'browsers' => 'Browsers', 'operating_systems' => 'Operating systems'] as $key => $title)
                        @php($rows = $matomo[$key] ?? [])
                        @php($rowMax = $maxMetric($rows))
                        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
                            <h3 class="font-semibold text-gray-950 dark:text-white">{{ $title }}</h3>
                            <div class="mt-4 space-y-3">
                                @forelse ($rows as $row)
                                    <div>
                                        <div class="flex justify-between gap-3 text-sm"><span class="truncate text-gray-700 dark:text-gray-200">{{ $row['label'] }}</span><span class="font-semibold text-gray-950 dark:text-white">{{ number_format($metricValue($row)) }}</span></div>
                                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10"><div class="h-full rounded-full bg-primary-500" style="width: {{ $percent($row, $rowMax) }}%"></div></div>
                                    </div>
                                @empty
                                    <p class="text-sm text-gray-500 dark:text-gray-400">No aggregate data is available.</p>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            @if (($matomo['warnings'] ?? []) !== [])
                <div class="rounded-xl border border-warning-200 bg-warning-50 p-4 text-sm text-warning-800 dark:border-warning-400/20 dark:bg-warning-400/10 dark:text-warning-200">
                    <strong>Partial Matomo reporting:</strong>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @foreach ($matomo['warnings'] as $warning)
                            <li>{{ $warning }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endif

        <section class="space-y-5 border-t border-gray-200 pt-9 dark:border-white/10">
            <div>
                <h2 class="text-xl font-semibold text-gray-950 dark:text-white">Operational health</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Application-owned health aggregates only. These remain intentionally separate from human Matomo analytics.</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($operationalSummary as $label => $value)
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
                        <div class="text-sm text-gray-600 dark:text-gray-400">{{ $label }}</div>
                        <div class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ is_numeric($value) ? number_format((float) $value) : $value }}</div>
                    </div>
                @endforeach
            </div>

            @if ($operational === [])
                <div class="rounded-xl border border-gray-200 bg-white p-5 text-sm text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">No operational aggregate data is available for this period.</div>
            @else
                <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/5">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-white/5 dark:text-gray-400"><tr><th class="px-4 py-3">Date</th><th class="px-4 py-3">Metric</th><th class="px-4 py-3 text-right">Value</th></tr></thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($operational as $row)
                                <tr><td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $row['date'] }}</td><td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $row['label'] }}</td><td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ $row['display_value'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-filament-panels::page>
