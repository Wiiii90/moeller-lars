<x-filament-panels::page>
    <x-admin.workspace title="Storage" class="admin-storage">
        <x-slot:status>
            <x-admin.status :tone="$capacity['status_tone'] ?? 'neutral'">
                {{ $capacity['status_label'] ?? 'Storage unavailable' }}
            </x-admin.status>
        </x-slot:status>

        <x-admin.metrics :columns="6" aria-label="Storage statistics">
            <x-admin.metric label="Assets" :value="number_format($availableAssets)">Available files in Media Files</x-admin.metric>
            <x-admin.metric label="Unused" :value="number_format($unusedAssets)">Unreferenced by canonical media rules</x-admin.metric>
            <x-admin.metric label="Original media" :value="$capacity['authoritative'] ?? '—'">Authoritative · counts against allowance</x-admin.metric>
            <x-admin.metric label="Generated derivatives" :value="$capacity['generated'] ?? '—'">Rebuildable · outside allowance</x-admin.metric>
            <x-admin.metric label="Remaining" :value="$capacity['remaining'] ?? '—'">Configured allowance remaining</x-admin.metric>
            <x-admin.metric label="Allowance" :value="$capacity['allowance'] ?? '—'">Operator-controlled · read only</x-admin.metric>
        </x-admin.metrics>

        <section class="admin-storage__visual-stage" aria-label="Storage capacity and distribution">
            <div class="admin-storage__visual-main">
                <div class="admin-storage__capacity-group">
                    <div
                        @class([
                            'admin-storage__capacity-orbit',
                            'is-unconfigured' => ! ($capacity['configured'] ?? false),
                            'is-unavailable' => ! ($capacity['measurement_available'] ?? false),
                        ])
                        style="--capacity-used: {{ $capacity['percent'] ?? 0 }}%"
                        role="img"
                        aria-label="@if ($capacity['percent'] !== null) {{ $capacity['percent'] }} percent of the configured allowance is used @elseif ($capacity['measurement_available'] ?? false) Authoritative usage is measured but no allowance is configured @else Authoritative storage measurement is unavailable @endif"
                    >
                        <div class="admin-storage__capacity-core">
                            @if ($capacity['percent'] !== null)
                                <strong>{{ $capacity['percent'] }}%</strong>
                                <span>Allowance used</span>
                            @elseif ($capacity['measurement_available'] ?? false)
                                <strong>{{ $capacity['authoritative'] ?? '—' }}</strong>
                                <span>Authoritative used</span>
                            @else
                                <strong>—</strong>
                                <span>Measurement unavailable</span>
                            @endif
                        </div>
                    </div>

                    <div class="admin-storage__capacity-copy">
                        <p class="admin-storage__eyebrow">Capacity</p>
                        <strong>{{ $capacity['authoritative'] ?? '—' }} authoritative</strong>
                        <span>
                            @if ($capacity['configured'] ?? false)
                                {{ $capacity['remaining'] ?? '—' }} remaining of {{ $capacity['allowance'] ?? '—' }}
                            @else
                                No operator allowance configured
                            @endif
                        </span>
                        <small>{{ $capacity['generated'] ?? '—' }} generated · rebuildable and excluded from allowance</small>
                    </div>
                </div>

                <div class="admin-storage__distribution" aria-label="Authoritative storage distribution">
                    <div class="admin-storage__visual-heading">
                        <div>
                            <p class="admin-storage__eyebrow">Distribution</p>
                            <strong>Originals by actual use</strong>
                        </div>
                        @if ($areaFilter !== 'all' || $referenceState !== 'all' || $referenceFilter !== 'all')
                            <button class="admin-action" type="button" wire:click="resetTableFilters">Clear selection</button>
                        @endif
                    </div>

                    <div class="admin-storage__segments">
                        @forelse (array_slice($breakdown, 0, 7) as $row)
                            <button
                                type="button"
                                wire:click="selectArea('{{ $row['key'] }}')"
                                @class(['admin-storage__segment', 'is-active' => $areaFilter === $row['key']])
                                aria-pressed="{{ $areaFilter === $row['key'] ? 'true' : 'false' }}"
                            >
                                <span class="admin-storage__segment-label">
                                    <strong>{{ $row['label'] }}</strong>
                                    <small>{{ number_format($row['files']) }} {{ $row['files'] === 1 ? 'original' : 'originals' }}</small>
                                </span>
                                <span class="admin-storage__segment-track" aria-hidden="true">
                                    <i style="width: {{ min(100, max(0, $row['percent'])) }}%"></i>
                                </span>
                                <span class="admin-storage__segment-value">{{ $row['display_bytes'] }} · {{ number_format($row['percent'], 1) }}%</span>
                            </button>
                        @empty
                            <p class="admin-storage__empty">No authoritative originals are currently measurable.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <aside class="admin-storage__context" aria-label="Storage context and attention">
                <div class="admin-storage__context-block">
                    <p class="admin-storage__eyebrow">Capacity context</p>
                    <dl class="admin-storage__facts">
                        <div><dt>Used</dt><dd>{{ $capacity['authoritative'] ?? '—' }}</dd></div>
                        <div><dt>Remaining</dt><dd>{{ $capacity['remaining'] ?? '—' }}</dd></div>
                        <div><dt>Allowance</dt><dd>{{ $capacity['allowance'] ?? '—' }}</dd></div>
                        <div><dt>Warning threshold</dt><dd>{{ $capacity['warning_threshold'] ?? '—' }}</dd></div>
                    </dl>
                    <p class="admin-storage__guidance">
                        Authoritative originals count against the allowance. Generated derivatives are rebuildable and do not.
                    </p>
                    @if (! empty($capacity['action']))
                        <p class="admin-storage__guidance">{{ $capacity['action'] }}</p>
                    @endif
                </div>

                <div class="admin-storage__context-block">
                    <p class="admin-storage__eyebrow">Attention</p>
                    <div class="admin-storage__attention">
                        @if (($attention['unreferenced_files'] ?? 0) > 0)
                            <button type="button" wire:click="selectReferenceState('unreferenced')" class="admin-storage__attention-row">
                                <span>Unused originals</span>
                                <strong>{{ number_format($attention['unreferenced_files']) }} · {{ $attention['unreferenced_display_bytes'] }}</strong>
                            </button>
                        @endif

                        @if (is_array($attention['largest_gallery'] ?? null))
                            <button type="button" wire:click="selectReference('{{ $attention['largest_gallery']['key'] }}')" class="admin-storage__attention-row">
                                <span>Most storage-heavy gallery</span>
                                <strong>{{ $attention['largest_gallery']['label'] }} · {{ $attention['largest_gallery']['display_bytes'] }}</strong>
                            </button>
                        @endif

                        @if (is_array($attention['largest_file'] ?? null))
                            <div class="admin-storage__attention-row is-static">
                                <span>Largest original</span>
                                <strong title="{{ $attention['largest_file']['filename'] }}">{{ $attention['largest_file']['filename'] }} · {{ $attention['largest_file']['display_bytes'] }}</strong>
                            </div>
                        @endif
                    </div>
                </div>
            </aside>
        </section>

        <form wire:submit.prevent class="admin-storage__data-surface">
            <x-admin.controls class="admin-storage__controls" aria-label="Storage file controls">
                <x-slot:search>
                    <label class="admin-data-field">
                        <span>Search</span>
                        <input
                            type="search"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Search filename or reference"
                            autocomplete="off"
                        >
                    </label>
                </x-slot:search>

                <x-slot:filters>
                    <label class="admin-data-field">
                        <span>Area</span>
                        <select wire:model.live="areaFilter">
                            <option value="all">All areas</option>
                            @foreach ($breakdown as $row)
                                <option value="{{ $row['key'] }}">{{ $row['label'] }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="admin-data-field">
                        <span>Reference state</span>
                        <select wire:model.live="referenceState">
                            <option value="all">All states</option>
                            <option value="referenced">Referenced</option>
                            <option value="unreferenced">Unused</option>
                            <option value="uncatalogued">Uncatalogued</option>
                        </select>
                    </label>

                    <label class="admin-data-field">
                        <span>Reference</span>
                        <select wire:model.live="referenceFilter">
                            <option value="all">All references</option>
                            @foreach ($referenceOptions as $reference)
                                <option value="{{ $reference['key'] }}">{{ $reference['area_label'] }} · {{ $reference['label'] }}</option>
                            @endforeach
                        </select>
                    </label>
                </x-slot:filters>

                <x-slot:reset>
                    <div class="admin-data-control-group">
                        <span class="admin-data-control-label">Filter</span>
                        <button class="admin-action" type="button" wire:click="resetTableFilters">Reset</button>
                    </div>
                </x-slot:reset>
            </x-admin.controls>
        </form>

        <x-admin.table class="admin-table--data admin-storage__table">
            @if ($files !== [])
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Original</th>
                            <th scope="col">Use</th>
                            <th scope="col">References</th>
                            <th scope="col">Type</th>
                            <th scope="col">Original size</th>
                            <th scope="col">Share</th>
                            <th scope="col">State</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($files as $row)
                            <tr>
                                <td class="admin-table__identity">
                                    <strong title="{{ $row['filename'] }}">{{ $row['filename'] }}</strong>
                                    <small>{{ $row['asset_id'] === null ? 'Measured original without Media Files record' : 'Authoritative original' }}</small>
                                </td>
                                <td>
                                    <span class="admin-storage__use">{{ implode(' + ', $row['use_labels']) }}</span>
                                </td>
                                <td class="admin-storage__references">
                                    @if ($row['references'] === [])
                                        <span>—</span>
                                    @else
                                        @foreach (array_slice($row['references'], 0, 2) as $reference)
                                            <span class="admin-storage__reference">
                                                @if (! empty($reference['url']))
                                                    <a href="{{ $reference['url'] }}">{{ $reference['target_label'] }}</a>
                                                @else
                                                    <strong>{{ $reference['target_label'] }}</strong>
                                                @endif
                                                <small>{{ $reference['label'] }}</small>
                                            </span>
                                        @endforeach
                                        @if (count($row['references']) > 2)
                                            <details class="admin-storage__reference-more">
                                                <summary>+ {{ count($row['references']) - 2 }} more</summary>
                                                <div>
                                                    @foreach (array_slice($row['references'], 2) as $reference)
                                                        <span class="admin-storage__reference">
                                                            @if (! empty($reference['url']))
                                                                <a href="{{ $reference['url'] }}">{{ $reference['target_label'] }}</a>
                                                            @else
                                                                <strong>{{ $reference['target_label'] }}</strong>
                                                            @endif
                                                            <small>{{ $reference['label'] }}</small>
                                                        </span>
                                                    @endforeach
                                                </div>
                                            </details>
                                        @endif
                                    @endif
                                </td>
                                <td>{{ $row['type_label'] }}</td>
                                <td class="admin-storage__number">{{ $row['display_bytes'] }}</td>
                                <td class="admin-storage__number">{{ $row['display_share'] }}</td>
                                <td>
                                    <span @class([
                                        'admin-storage__state',
                                        'is-unused' => $row['state'] === 'unreferenced',
                                        'is-uncatalogued' => $row['state'] === 'uncatalogued',
                                    ])>{{ $row['state_label'] }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <x-admin.empty-state kicker="No matches" title="No originals match these filters">
                    <p>Change search, area or reference filters to widen the storage view.</p>
                </x-admin.empty-state>
            @endif
        </x-admin.table>

        <nav class="admin-pager" aria-label="Storage pagination">
            <label class="admin-pager__size">
                <span>Rows</span>
                <select wire:model.live="pageSize" aria-label="Rows per page">
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </label>

            <span class="admin-pager__range">
                @if ($total > 0)
                    {{ number_format((($page - 1) * $pageSize) + 1) }}–{{ number_format(min($total, $page * $pageSize)) }} of {{ number_format($total) }}
                @else
                    0 of 0
                @endif
            </span>

            <x-admin.toolbar class="admin-pager__actions" aria-label="Storage pages">
                <button class="admin-action" type="button" wire:click="previousPage" @disabled($page <= 1)>Previous</button>
                <button class="admin-action" type="button" wire:click="nextPage" @disabled($page >= $pages)>Next</button>
            </x-admin.toolbar>
        </nav>
    </x-admin.workspace>
</x-filament-panels::page>
