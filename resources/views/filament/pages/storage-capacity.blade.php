<x-filament-panels::page>
    <div class="artist-workspace artist-storage">
        <header class="artist-workspace__head">
            <div>
                <p class="artist-workspace__kicker">Media capacity</p>
                <h2>Storage allowance</h2>
            </div>
            <div class="artist-workspace__summary">
                <div><strong>{{ $availableAssets }}</strong><span>Assets</span></div>
                <div><strong>{{ $unusedAssets }}</strong><span>Unused</span></div>
            </div>
        </header>

        @if (! $capacity['measurement_available'])
            <section class="artist-storage__unavailable">
                <p class="artist-workspace__kicker">{{ $capacity['status_label'] }}</p>
                <h3>{{ $capacity['configuration_valid'] ? 'Current usage cannot be verified' : 'The configured allowance cannot be verified' }}</h3>
                <p>{{ $capacity['action'] }}</p>
            </section>
        @else
            <section class="artist-storage__composition">
                <div class="artist-storage__ring" style="--capacity-used: {{ $capacity['percent'] }}%" aria-label="{{ $capacity['percent'] }} percent of configured allowance used">
                    <div><strong>{{ $capacity['configured'] ? $capacity['percent'].'%' : '—' }}</strong><span>{{ $capacity['status_label'] }}</span></div>
                </div>

                <div class="artist-storage__numbers">
                    <div class="artist-storage__primary"><span>Used · authoritative originals</span><strong>{{ $capacity['authoritative'] }}</strong><small>{{ $capacity['original_files'] }} files · counts against allowance</small></div>
                    <dl>
                        <div><dt>Remaining</dt><dd>{{ $capacity['remaining'] }}</dd></div>
                        <div><dt>Allowance</dt><dd>{{ $capacity['allowance'] }}</dd></div>
                        <div><dt>Generated previews</dt><dd>{{ $capacity['generated'] }}</dd></div>
                    </dl>
                    @if (filled($capacity['action']))
                        <div class="artist-workspace__footnote"><span>{{ $capacity['action'] }}</span></div>
                    @endif
                    <div class="artist-workspace__footnote">
                        <span>Warning begins at {{ $capacity['warning_threshold'] }} of the allowance.</span>
                        <span>{{ $capacity['unit_note'] }}</span>
                    </div>
                </div>
            </section>

            <section class="artist-storage__breakdown" aria-label="Storage classes">
                <div><span>Original media</span><strong>{{ $capacity['authoritative'] }}</strong><small>{{ $capacity['original_files'] }} authoritative files</small></div>
                <div><span>Generated derivatives</span><strong>{{ $capacity['generated'] }}</strong><small>{{ $capacity['generated_files'] }} rebuildable files · outside the allowance</small></div>
            </section>

            @if ($breakdown !== [])
                <section class="artist-dashboard__content" aria-label="Authoritative originals by library use">
                    <div class="artist-dashboard__section-head">
                        <span>Originals by library use</span>
                        <span>Measured bytes</span>
                    </div>
                    @foreach ($breakdown as $row)
                        <div class="artist-dashboard__row">
                            <span class="artist-dashboard__identity">
                                <strong>{{ $row['label'] }}</strong>
                                <small>{{ $row['files'] }} {{ $row['files'] === 1 ? 'file' : 'files' }} · {{ number_format($row['percent'], 1) }}% of authoritative originals</small>
                            </span>
                            <strong class="artist-dashboard__number">{{ $row['display_bytes'] }}</strong>
                        </div>
                    @endforeach
                    <div class="artist-workspace__footnote">
                        <span>Each measured original appears exactly once. Media reused by more than one content area is grouped as shared instead of double-counted.</span>
                    </div>
                </section>
            @endif

            @if ($heavyConsumers !== [])
                <details class="artist-dashboard__content" aria-label="Largest authoritative originals">
                    <summary class="artist-dashboard__section-head">
                        <span>Largest originals</span>
                        <span>Details</span>
                    </summary>
                    @foreach ($heavyConsumers as $row)
                        <div class="artist-dashboard__row">
                            <span class="artist-dashboard__identity">
                                <strong>{{ $row['label'] }}</strong>
                                <small>{{ $row['classification'] }} · authoritative original</small>
                            </span>
                            <strong class="artist-dashboard__number">{{ $row['display_bytes'] }}</strong>
                        </div>
                    @endforeach
                    <div class="artist-workspace__footnote">
                        <span>Largest files are derived from the authoritative measurement. Internal storage paths are intentionally not exposed.</span>
                    </div>
                </details>
            @endif
        @endif

        <footer class="artist-workspace__footnote">
            <span>The allowance is operator-controlled and read-only in artist admin.</span>
            <span>Host disks, other workloads and server-wide capacity are intentionally not exposed here.</span>
        </footer>
    </div>
</x-filament-panels::page>
