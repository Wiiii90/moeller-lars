<x-filament-panels::page>
    <x-admin.workspace kicker="Media capacity" title="Storage allowance" class="admin-storage">
        <x-slot:summary>
            <div><strong>{{ $availableAssets }}</strong><span>Assets</span></div>
            <div><strong>{{ $unusedAssets }}</strong><span>Unused</span></div>
        </x-slot:summary>

        @if (! $capacity['measurement_available'])
            <x-admin.empty-state :kicker="$capacity['status_label']" :title="$capacity['configuration_valid'] ? 'Current usage cannot be verified' : 'The configured allowance cannot be verified'">
                <p>{{ $capacity['action'] }}</p>
            </x-admin.empty-state>
        @else
            <x-admin.section kicker="Allowance" title="Current storage usage">
                <div class="admin-storage__composition">
                    <div class="admin-storage__ring" style="--capacity-used: {{ $capacity['percent'] }}%" aria-label="{{ $capacity['percent'] }} percent of configured allowance used">
                        <div><strong>{{ $capacity['configured'] ? $capacity['percent'].'%' : '—' }}</strong><span>{{ $capacity['status_label'] }}</span></div>
                    </div>

                    <div class="admin-storage__numbers">
                        <div class="admin-storage__primary"><span>Used · authoritative originals</span><strong>{{ $capacity['authoritative'] }}</strong><small>{{ $capacity['original_files'] }} files · counts against allowance</small></div>
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
            </x-admin.section>

            <x-admin.metrics :columns="2" aria-label="Storage classes">
                <x-admin.metric label="Original media" :value="$capacity['authoritative']" :detail="$capacity['original_files'].' authoritative files'" />
                <x-admin.metric label="Generated derivatives" :value="$capacity['generated']" :detail="$capacity['generated_files'].' rebuildable files · outside the allowance'" />
            </x-admin.metrics>

            @if ($breakdown !== [])
                <x-admin.section kicker="Breakdown" title="Originals by library use">
                    <x-admin.list aria-label="Authoritative originals by library use">
                        @foreach ($breakdown as $row)
                            <div class="admin-list__row">
                                <div class="admin-list__identity">
                                    <strong>{{ $row['label'] }}</strong>
                                    <span>{{ $row['files'] }} {{ $row['files'] === 1 ? 'file' : 'files' }} · {{ number_format($row['percent'], 1) }}% of authoritative originals</span>
                                </div>
                                <div></div>
                                <div class="admin-list__count"><strong>{{ $row['display_bytes'] }}</strong><span>Measured bytes</span></div>
                                <div></div>
                            </div>
                        @endforeach
                    </x-admin.list>
                    <p class="admin-workspace__footnote">Each measured original appears exactly once. Media reused by more than one content area is grouped as shared instead of double-counted.</p>
                </x-admin.section>
            @endif

            @if ($heavyConsumers !== [])
                <x-admin.section kicker="Details" title="Largest originals">
                    <details class="admin-storage__details">
                        <summary>Show largest authoritative originals</summary>
                        <x-admin.list>
                            @foreach ($heavyConsumers as $row)
                                <div class="admin-list__row">
                                    <div class="admin-list__identity">
                                        <strong>{{ $row['label'] }}</strong>
                                        <span>{{ $row['classification'] }} · authoritative original</span>
                                    </div>
                                    <div></div>
                                    <div class="admin-list__count"><strong>{{ $row['display_bytes'] }}</strong><span>Size</span></div>
                                    <div></div>
                                </div>
                            @endforeach
                        </x-admin.list>
                        <p class="admin-workspace__footnote">Largest files are derived from the authoritative measurement. Internal storage paths are intentionally not exposed.</p>
                    </details>
                </x-admin.section>
            @endif
        @endif

        <footer class="admin-workspace__footnote">
            The allowance is operator-controlled and read-only in artist admin. Host disks, other workloads and server-wide capacity are intentionally not exposed here.
        </footer>
    </x-admin.workspace>
</x-filament-panels::page>
