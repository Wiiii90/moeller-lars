@php
    $filteredBreakdown = $this->filteredBreakdown();
    $filteredHeavyConsumers = $this->filteredHeavyConsumers();
    $measurementAvailable = $capacity['measurement_available'];
@endphp

<x-filament-panels::page>
    <x-admin.workspace title="Storage" class="admin-storage">
        <x-slot:status>
            <span>{{ $capacity['status_label'] }}</span>
        </x-slot:status>

        <x-admin.metrics :columns="6" aria-label="Storage metrics">
            <x-admin.metric label="Assets" :value="$availableAssets" description="Available media" />
            <x-admin.metric label="Unused" :value="$unusedAssets" description="Unreferenced media" />
            <x-admin.metric label="Original media" :value="$capacity['authoritative']" :description="$capacity['original_files'].' authoritative files'" />
            <x-admin.metric label="Generated derivatives" :value="$capacity['generated']" :description="$capacity['generated_files'].' rebuildable files · outside the allowance'" />
            <x-admin.metric label="Remaining" :value="$capacity['remaining']" />
            <x-admin.metric label="Allowance" :value="$capacity['allowance']" />
        </x-admin.metrics>

        <section class="admin-section" aria-label="Current storage usage">
            <div class="admin-storage__composition">
                <div
                    class="admin-storage__ring"
                    style="--capacity-used: {{ $measurementAvailable ? $capacity['percent'] : 0 }}%"
                    aria-label="{{ $measurementAvailable ? $capacity['percent'].' percent of configured allowance used' : 'Storage usage measurement unavailable' }}"
                >
                    <div>
                        <strong>{{ $measurementAvailable && $capacity['configured'] ? $capacity['percent'].'%' : '—' }}</strong>
                        <span>{{ $capacity['status_label'] }}</span>
                    </div>
                </div>

                <div class="admin-storage__numbers">
                    <div class="admin-storage__primary">
                        <span>Used · authoritative originals</span>
                        <strong>{{ $capacity['authoritative'] }}</strong>
                        <small>{{ $capacity['original_files'] }} files · counts against allowance</small>
                    </div>
                    <dl>
                        <div><dt>Remaining</dt><dd>{{ $capacity['remaining'] }}</dd></div>
                        <div><dt>Allowance</dt><dd>{{ $capacity['allowance'] }}</dd></div>
                        <div><dt>Generated previews</dt><dd>{{ $capacity['generated'] }}</dd></div>
                    </dl>
                    @if (filled($capacity['action']))
                        <p class="admin-workspace__footnote">{{ $capacity['action'] }}</p>
                    @endif
                    <p class="admin-workspace__footnote">Warning begins at {{ $capacity['warning_threshold'] }} of the allowance. {{ $capacity['unit_note'] }}</p>
                </div>
            </div>
        </section>

        <x-admin.section kicker="Breakdown" title="Originals by library use">
            <x-admin.controls class="admin-control-bar admin-toolbar" aria-label="Storage table controls">
                <x-slot:search>
                    <label class="admin-data-field admin-control-bar__search">
                        <span>Search</span>
                        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search storage">
                    </label>
                </x-slot:search>

                <x-slot:filters>
                    <label class="admin-data-field">
                        <span>Use</span>
                        <select wire:model.live="useFilter" aria-label="Filter storage by use">
                            <option value="">All uses</option>
                            <option value="artworks">Artworks</option>
                            <option value="exhibitions">Exhibitions</option>
                            <option value="vita">Vita / CV</option>
                            <option value="blog">Blog</option>
                            <option value="shared">Shared across sections</option>
                            <option value="unassigned">Unassigned library media</option>
                            <option value="uncatalogued">Uncatalogued originals</option>
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

            <x-admin.table class="admin-table--data" aria-label="Authoritative originals by library use">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Use</th>
                            <th scope="col">Files</th>
                            <th scope="col">Share</th>
                            <th scope="col">Storage</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($filteredBreakdown as $row)
                            <tr>
                                <td class="admin-table__identity"><strong>{{ $row['label'] }}</strong></td>
                                <td>{{ $row['files'] }}</td>
                                <td>{{ number_format($row['percent'], 1) }}%</td>
                                <td>{{ $row['display_bytes'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">No storage uses match the current filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </x-admin.table>
            <p class="admin-workspace__footnote">Each measured original appears exactly once. Media reused by more than one content area is grouped as shared instead of double-counted.</p>
        </x-admin.section>

        @if ($heavyConsumers !== [])
            <x-admin.section kicker="Details" title="Largest originals">
                <details class="admin-storage__details">
                    <summary>Show largest authoritative originals</summary>
                    <x-admin.table class="admin-table--data" aria-label="Largest authoritative originals">
                        <table>
                            <thead>
                                <tr>
                                    <th scope="col">Original</th>
                                    <th scope="col">Classification</th>
                                    <th scope="col">Size</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($filteredHeavyConsumers as $row)
                                    <tr>
                                        <td class="admin-table__identity"><strong>{{ $row['label'] }}</strong></td>
                                        <td>{{ $row['classification'] }} · authoritative original</td>
                                        <td>{{ $row['display_bytes'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3">No originals match the current search.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </x-admin.table>
                    <p class="admin-workspace__footnote">Largest files are derived from the authoritative measurement. Internal storage paths are intentionally not exposed.</p>
                </details>
            </x-admin.section>
        @endif

        <footer class="admin-workspace__footnote">
            The allowance is operator-controlled and read-only in artist admin. Host disks, other workloads and server-wide capacity are intentionally not exposed here.
        </footer>
    </x-admin.workspace>
</x-filament-panels::page>
