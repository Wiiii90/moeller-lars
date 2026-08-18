<x-filament-panels::page>
    <div class="space-y-8">
        <section class="space-y-4">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-950 dark:text-white">Human analytics</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Aggregate Matomo reporting only. No visitor-level identifiers are displayed here.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2" role="group" aria-label="Analytics date range">
                    @foreach (['today' => 'Today', '7d' => '7 days', '30d' => '30 days', '12m' => '12 months'] as $preset => $label)
                        <button
                            type="button"
                            wire:click="setRange('{{ $preset }}')"
                            @class([
                                'rounded-lg border px-3 py-2 text-sm font-medium transition',
                                'border-primary-600 bg-primary-600 text-white' => $range === $preset,
                                'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10' => $range !== $preset,
                            ])
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div wire:loading.flex wire:target="setRange" class="items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                <span>Updating analytics…</span>
            </div>

            @if (($matomo['status'] ?? null) === 'disabled')
                <div class="rounded-xl border border-gray-200 bg-white p-5 text-sm text-gray-700 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
                    {{ $matomo['message'] ?? 'Matomo analytics are disabled.' }}
                </div>
            @elseif (($matomo['status'] ?? null) === 'unavailable')
                <div class="rounded-xl border border-danger-200 bg-danger-50 p-5 text-sm text-danger-800 dark:border-danger-400/20 dark:bg-danger-400/10 dark:text-danger-200">
                    <strong>Human analytics are temporarily unavailable.</strong>
                    <span class="mt-1 block">{{ $matomo['message'] ?? 'The Reporting API could not be read.' }}</span>
                </div>
            @else
                @if (($matomo['status'] ?? null) === 'stale')
                    <div class="rounded-xl border border-warning-200 bg-warning-50 p-4 text-sm text-warning-800 dark:border-warning-400/20 dark:bg-warning-400/10 dark:text-warning-200">
                        {{ $matomo['message'] ?? 'Showing cached analytics because live reporting is unavailable.' }}
                        @if (filled($matomo['generated_at'] ?? null))
                            <span class="block">Cached report generated {{ $matomo['generated_at'] }}.</span>
                        @endif
                    </div>
                @endif

                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                    <span>{{ $matomo['range']['label'] ?? 'Selected range' }}</span>
                    @if (filled($matomo['range']['start'] ?? null) && filled($matomo['range']['end'] ?? null))
                        <span>{{ $matomo['range']['start'] }} – {{ $matomo['range']['end'] }}</span>
                    @endif
                    <span>Source: Matomo Reporting API</span>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($kpis as $kpi)
                        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
                            <div class="text-sm text-gray-600 dark:text-gray-400">{{ $kpi['label'] }}</div>
                            <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $kpi['value'] }}</div>
                            <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $kpi['comparison'] }}</div>
                        </div>
                    @endforeach
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="font-semibold text-gray-950 dark:text-white">Traffic and engagement trend</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Visits and tracked page views/actions over the selected period.</p>
                        </div>
                        @if ($trendChart !== [])
                            <div class="flex gap-4 text-xs text-gray-600 dark:text-gray-400">
                                <span class="font-medium text-primary-600 dark:text-primary-400">Visits</span>
                                <span class="font-medium text-gray-500 dark:text-gray-300">Actions</span>
                            </div>
                        @endif
                    </div>

                    @if ($trendChart === [])
                        <p class="mt-5 text-sm text-gray-500 dark:text-gray-400">No time-series data is available for this period.</p>
                    @else
                        <div class="mt-5 overflow-hidden rounded-lg border border-gray-100 bg-gray-50/60 p-2 dark:border-white/5 dark:bg-black/10">
                            <svg viewBox="0 0 1000 240" class="h-56 w-full" role="img" aria-label="Visits and actions trend">
                                <line x1="18" y1="222" x2="982" y2="222" class="stroke-gray-300 dark:stroke-white/10" stroke-width="1" />
                                <line x1="18" y1="120" x2="982" y2="120" class="stroke-gray-200 dark:stroke-white/5" stroke-width="1" />
                                <polyline points="{{ $trendChart['actions_points'] }}" fill="none" stroke="currentColor" class="text-gray-400 dark:text-gray-500" stroke-width="3" vector-effect="non-scaling-stroke" />
                                <polyline points="{{ $trendChart['visits_points'] }}" fill="none" stroke="currentColor" class="text-primary-600 dark:text-primary-400" stroke-width="4" vector-effect="non-scaling-stroke" />
                            </svg>
                        </div>
                        <div class="mt-2 flex justify-between text-xs text-gray-500 dark:text-gray-400">
                            <span>{{ $trendChart['start'] }}</span>
                            <span>{{ $trendChart['points'] }} data point{{ $trendChart['points'] === 1 ? '' : 's' }}</span>
                            <span>{{ $trendChart['end'] }}</span>
                        </div>
                    @endif
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
                    <h3 class="font-semibold text-gray-950 dark:text-white">Artist interaction signals</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Only configured aggregate event actions are counted. Missing event tracking remains zero rather than being inferred.</p>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($interactionSignals as $label => $value)
                            <div class="rounded-lg border border-gray-100 p-4 dark:border-white/5">
                                <div class="text-sm text-gray-600 dark:text-gray-400">{{ $label }}</div>
                                <div class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ number_format((int) $value) }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="grid gap-6 xl:grid-cols-2">
                    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
                        <h3 class="font-semibold text-gray-950 dark:text-white">Most-viewed content</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Top page paths; query strings are not displayed.</p>
                        @if (($matomo['content'] ?? []) === [])
                            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">No content report is available.</p>
                        @else
                            <div class="mt-4 overflow-x-auto">
                                <table class="w-full text-left text-sm">
                                    <thead class="text-xs uppercase text-gray-500 dark:text-gray-400"><tr><th class="pb-2 pr-4">Content</th><th class="pb-2 text-right">Actions</th></tr></thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                    @foreach ($matomo['content'] as $row)
                                        <tr><td class="py-2 pr-4 font-medium text-gray-900 dark:text-gray-100">{{ $row['label'] }}</td><td class="py-2 text-right text-gray-600 dark:text-gray-300">{{ number_format((int) ($row['nb_hits'] ?: $row['nb_actions'])) }}</td></tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
                        <h3 class="font-semibold text-gray-950 dark:text-white">Tracked events</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Aggregate event actions from Matomo.</p>
                        @if (($matomo['events'] ?? []) === [])
                            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">No event data is available for this period.</p>
                        @else
                            <div class="mt-4 overflow-x-auto">
                                <table class="w-full text-left text-sm">
                                    <thead class="text-xs uppercase text-gray-500 dark:text-gray-400"><tr><th class="pb-2 pr-4">Event action</th><th class="pb-2 text-right">Events</th></tr></thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                    @foreach ($matomo['events'] as $row)
                                        <tr><td class="py-2 pr-4 font-medium text-gray-900 dark:text-gray-100">{{ $row['label'] }}</td><td class="py-2 text-right text-gray-600 dark:text-gray-300">{{ number_format((int) $row['nb_events']) }}</td></tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="grid gap-6 xl:grid-cols-2">
                    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
                        <h3 class="font-semibold text-gray-950 dark:text-white">Traffic sources</h3>
                        @if (($matomo['referrers'] ?? []) === [])
                            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">No referrer summary is available.</p>
                        @else
                            <div class="mt-4 space-y-2">
                                @foreach ($matomo['referrers'] as $row)
                                    <div class="flex items-center justify-between gap-4 text-sm"><span class="text-gray-700 dark:text-gray-200">{{ $row['label'] }}</span><span class="font-medium text-gray-950 dark:text-white">{{ number_format((int) $row['nb_visits']) }} visits</span></div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
                        <h3 class="font-semibold text-gray-950 dark:text-white">Countries</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Country-level aggregate only; no city-level visitor view.</p>
                        @if (($matomo['countries'] ?? []) === [])
                            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">No country summary is available.</p>
                        @else
                            <div class="mt-4 space-y-2">
                                @foreach ($matomo['countries'] as $row)
                                    <div class="flex items-center justify-between gap-4 text-sm"><span class="text-gray-700 dark:text-gray-200">{{ $row['label'] }}</span><span class="font-medium text-gray-950 dark:text-white">{{ number_format((int) $row['nb_visits']) }} visits</span></div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-3">
                    @foreach (['devices' => 'Device classes', 'browsers' => 'Browsers', 'operating_systems' => 'Operating systems'] as $key => $title)
                        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
                            <h3 class="font-semibold text-gray-950 dark:text-white">{{ $title }}</h3>
                            @if (($matomo[$key] ?? []) === [])
                                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">No aggregate data is available.</p>
                            @else
                                <div class="mt-4 space-y-2">
                                    @foreach ($matomo[$key] as $row)
                                        <div class="flex items-center justify-between gap-3 text-sm"><span class="truncate text-gray-700 dark:text-gray-200">{{ $row['label'] }}</span><span class="shrink-0 font-medium text-gray-950 dark:text-white">{{ number_format((int) $row['nb_visits']) }}</span></div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if (($matomo['warnings'] ?? []) !== [])
                    <div class="rounded-xl border border-warning-200 bg-warning-50 p-4 text-sm text-warning-800 dark:border-warning-400/20 dark:bg-warning-400/10 dark:text-warning-200">
                        <strong>Partial reporting:</strong>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            @foreach ($matomo['warnings'] as $warning)
                                <li>{{ $warning }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endif
        </section>

        <section class="space-y-4 border-t border-gray-200 pt-8 dark:border-white/10">
            <div>
                <h2 class="text-xl font-semibold text-gray-950 dark:text-white">Operational health</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Local application aggregates only. These are intentionally separate from human Matomo analytics and contain no IP or visitor-level user-agent list.
                </p>
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
                <div class="rounded-xl border border-gray-200 bg-white p-5 text-sm text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
                    No operational aggregate data is available for this period.
                </div>
            @else
                <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/5">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-white/5 dark:text-gray-400">
                            <tr><th class="px-4 py-3">Date</th><th class="px-4 py-3">Metric</th><th class="px-4 py-3 text-right">Value</th><th class="px-4 py-3 text-right">Samples</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($operational as $metric)
                            <tr>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $metric['date'] }}</td>
                                <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $metric['label'] }}</td>
                                <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-200">{{ $metric['display_value'] }}</td>
                                <td class="px-4 py-3 text-right text-gray-500 dark:text-gray-400">{{ $metric['sample_count'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-filament-panels::page>
