@php
    $storageTargets = is_array($storageAttention['targets'] ?? null) ? $storageAttention['targets'] : [];
    $storageSliceOffset = 0.0;
@endphp

<x-admin.metrics :columns="6" aria-label="Storage statistics">
    <x-admin.metric label="Files" :value="number_format($libraryFiles)">Available</x-admin.metric>
    <x-admin.metric label="Images" :value="number_format($libraryImages)">Available images</x-admin.metric>
    <x-admin.metric label="Unreferenced" :value="number_format($libraryUnreferenced)">No canonical consumer</x-admin.metric>
    <x-admin.metric label="Original storage" :value="$capacity['authoritative'] ?? '—'">Counts against allowance</x-admin.metric>
    <x-admin.metric label="Generated" :value="$capacity['generated'] ?? '—'">Rebuildable derivatives</x-admin.metric>
    <x-admin.metric label="Remaining" :value="$capacity['remaining'] ?? '—'">{{ $capacity['remaining_detail'] ?? 'Storage allowance' }}</x-admin.metric>
</x-admin.metrics>

<section class="admin-storage__visual-stage admin-visual-stage admin-visual-stage--triptych admin-visual-stage--stackable" aria-label="Storage capacity, distribution and upload">
    <div
        class="admin-storage__visual-main admin-visual-stage__pane"
        x-data="{
            selected: null,
            breakdown: @js($storageBreakdown),
            targets: @js($storageTargets),
            select(key) {
                this.selected = this.selected === key ? null : key
            },
            selectedRow() {
                return this.breakdown.find((row) => row.key === this.selected) ?? null
            },
            visibleTargets() {
                const rows = this.selected
                    ? this.targets.filter((target) => target.area === this.selected)
                    : this.targets

                return rows.slice(0, 5)
            },
            targetWidth(bytes) {
                const rows = this.visibleTargets()
                const max = Math.max(1, ...rows.map((row) => Number(row.bytes) || 0))

                return Math.max(4, Math.round(((Number(bytes) || 0) / max) * 100))
            },
            selectedMeta() {
                const row = this.selectedRow()
                if (! row) return 'Largest measured destinations'

                const files = Number(row.files) || 0
                const share = Number(row.percent) || 0

                return `${row.display_bytes} · ${files} ${files === 1 ? 'original' : 'originals'} · ${share.toFixed(1)}% of originals`
            },
            emptyDetail() {
                const row = this.selectedRow()
                if (! row) return 'No measured destination usage is available.'

                if (row.key === 'unassigned') return 'These originals are not referenced by a canonical consumer.'
                if (row.key === 'uncatalogued') return 'These originals exist on disk without a matching MediaAsset record.'
                if (row.key === 'shared') return 'These originals span more than one area, so destination totals overlap by design.'

                return 'No destination breakdown is available for this area.'
            },
        }"
    >
        <div class="admin-storage__capacity-group">
            <div class="admin-storage__visual-heading admin-storage__capacity-heading">
                <div>
                    <p class="admin-storage__eyebrow">Capacity + use</p>
                    <strong>Authoritative originals</strong>
                </div>
            </div>

            <div class="admin-storage__capacity-plot">
                <svg class="admin-storage__donut" viewBox="0 0 120 120" role="img" aria-label="Storage allowance and measured original distribution">
                    <circle class="admin-storage__capacity-track" cx="60" cy="60" r="53" pathLength="100" />
                    @if (($capacity['percent'] ?? null) !== null)
                        <circle
                            class="admin-storage__capacity-used"
                            cx="60"
                            cy="60"
                            r="53"
                            pathLength="100"
                            stroke-dasharray="{{ min(100, max(0, $capacity['percent'])) }} {{ max(0, 100 - min(100, max(0, $capacity['percent']))) }}"
                            transform="rotate(-90 60 60)"
                        />
                    @endif

                    <circle class="admin-storage__usage-track" cx="60" cy="60" r="40" pathLength="100" />
                    <g transform="rotate(-90 60 60)">
                        @foreach ($storageBreakdown as $row)
                            @php
                                $slicePercent = min(100, max(0, (float) ($row['percent'] ?? 0)));
                                $sliceMidpoint = $storageSliceOffset + ($slicePercent / 2);
                                $sliceAngle = deg2rad($sliceMidpoint * 3.6);
                                $sliceX = round(cos($sliceAngle) * 4, 2);
                                $sliceY = round(sin($sliceAngle) * 4, 2);
                                $sliceKey = (string) ($row['key'] ?? '');
                                $sliceIndex = ($loop->index % 8) + 1;
                            @endphp
                            <circle
                                class="admin-storage__usage-arc admin-storage__usage-arc--{{ $sliceIndex }}"
                                cx="60"
                                cy="60"
                                r="40"
                                pathLength="100"
                                stroke-dasharray="{{ $slicePercent }} {{ max(0, 100 - $slicePercent) }}"
                                stroke-dashoffset="{{ -$storageSliceOffset }}"
                                style="--storage-slice-x: {{ $sliceX }}px; --storage-slice-y: {{ $sliceY }}px"
                                role="button"
                                tabindex="0"
                                aria-label="{{ $row['label'] }}: {{ $row['display_bytes'] }}, {{ number_format((float) $row['percent'], 1) }} percent of authoritative originals"
                                x-bind:aria-pressed="(selected === @js($sliceKey)).toString()"
                                x-bind:class="{
                                    'is-selected': selected === @js($sliceKey),
                                    'is-muted': selected !== null && selected !== @js($sliceKey),
                                }"
                                x-on:click="select(@js($sliceKey))"
                                x-on:keydown.enter.prevent="select(@js($sliceKey))"
                                x-on:keydown.space.prevent="select(@js($sliceKey))"
                            >
                                <title>{{ $row['label'] }} — {{ $row['display_bytes'] }} · {{ number_format((float) $row['percent'], 1) }}%</title>
                            </circle>
                            @php $storageSliceOffset += $slicePercent; @endphp
                        @endforeach
                    </g>
                </svg>

                <div class="admin-storage__capacity-core" aria-live="polite">
                    <template x-if="selectedRow()">
                        <div>
                            <strong x-text="selectedRow().display_bytes"></strong>
                            <span x-text="selectedRow().label"></span>
                            <small x-text="`${Number(selectedRow().percent || 0).toFixed(1)}% of originals`"></small>
                        </div>
                    </template>
                    <template x-if="! selectedRow()">
                        <div>
                            @if (($capacity['percent'] ?? null) !== null)
                                <strong>{{ $capacity['percent'] }}%</strong>
                                <span>Allowance used</span>
                                <small>{{ $capacity['authoritative'] ?? '—' }} of {{ $capacity['allowance'] ?? '—' }}</small>
                            @elseif ($capacity['measurement_available'] ?? false)
                                <strong>{{ $capacity['authoritative'] ?? '—' }}</strong>
                                <span>Authoritative used</span>
                                <small>No operator allowance configured</small>
                            @else
                                <strong>—</strong>
                                <span>{{ ($capacity['status'] ?? null) === 'not_measured' ? 'Measure storage' : 'Measurement unavailable' }}</span>
                                <small>Refresh once to load current storage data</small>
                            @endif
                        </div>
                    </template>
                </div>
            </div>

            <div class="admin-storage__capacity-copy">
                @if (($capacity['status'] ?? null) === 'not_measured')
                    <span>Refresh explicitly when you need an authoritative filesystem measurement.</span>
                @elseif ($capacity['configured'] ?? false)
                    <strong>{{ $capacity['remaining'] ?? '—' }} remaining</strong>
                    <span>{{ $capacity['authoritative'] ?? '—' }} used of {{ $capacity['allowance'] ?? '—' }}</span>
                @else
                    <strong>{{ $capacity['authoritative'] ?? '—' }} authoritative</strong>
                    <span>No operator allowance configured</span>
                @endif
                <small>{{ $capacity['generated'] ?? '—' }} generated · rebuildable and excluded from allowance</small>
                <div class="admin-storage__capacity-actions">
                    <button class="admin-action" type="button" wire:click="refreshStorageMeasurement">Refresh measurement</button>
                </div>
            </div>
        </div>

        <div class="admin-storage__distribution">
            <div class="admin-storage__visual-heading">
                <div>
                    <p class="admin-storage__eyebrow">Destinations</p>
                    <strong x-text="selectedRow()?.label ?? 'Largest destinations'">Largest destinations</strong>
                    <small x-text="selectedMeta()">Largest measured destinations</small>
                </div>
                <div class="admin-storage__visual-actions" x-show="selectedRow()" x-cloak>
                    <button class="admin-action" type="button" x-on:click="selected = null">All</button>
                    <template x-if="selectedRow()?.usage_filter">
                        <button
                            class="admin-action"
                            type="button"
                            x-on:click="$wire.set('usage', selectedRow().usage_filter)"
                        >Filter library</button>
                    </template>
                </div>
            </div>

            <div class="admin-storage__target-plot" aria-live="polite">
                <template x-for="target in visibleTargets()" x-bind:key="target.key">
                    <div class="admin-storage__target-row">
                        <span class="admin-storage__target-label">
                            <strong x-text="target.label"></strong>
                            <small x-text="`${Number(target.files) || 0} ${(Number(target.files) || 0) === 1 ? 'original' : 'originals'}`"></small>
                        </span>
                        <span class="admin-storage__target-track" aria-hidden="true">
                            <i x-bind:style="`width: ${targetWidth(target.bytes)}%`"></i>
                        </span>
                        <strong class="admin-storage__target-value" x-text="target.display_bytes"></strong>
                    </div>
                </template>

                <div class="admin-storage__target-empty" x-show="visibleTargets().length === 0" x-cloak>
                    <p x-text="emptyDetail()"></p>
                    <template x-if="selectedRow()">
                        <strong x-text="`${selectedRow().display_bytes} · ${Number(selectedRow().files) || 0} ${(Number(selectedRow().files) || 0) === 1 ? 'original' : 'originals'}`"></strong>
                    </template>
                </div>
            </div>

            <div class="admin-storage__attention" aria-label="Storage attention">
                @if (($storageAttention['unreferenced_files'] ?? 0) > 0)
                    <div class="admin-storage__attention-row">
                        <span>Unused originals</span>
                        <strong>{{ number_format($storageAttention['unreferenced_files']) }} · {{ $storageAttention['unreferenced_display_bytes'] }}</strong>
                    </div>
                @endif

                @if (($storageAttention['uncatalogued_files'] ?? 0) > 0)
                    <div class="admin-storage__attention-row is-warning">
                        <span>Uncatalogued originals</span>
                        <strong>{{ number_format($storageAttention['uncatalogued_files']) }} · {{ $storageAttention['uncatalogued_display_bytes'] }}</strong>
                    </div>
                @endif

                @if (is_array($storageAttention['largest_gallery'] ?? null))
                    <div class="admin-storage__attention-row">
                        <span>Largest gallery</span>
                        <strong title="{{ $storageAttention['largest_gallery']['label'] }}">{{ $storageAttention['largest_gallery']['label'] }} · {{ $storageAttention['largest_gallery']['display_bytes'] }}</strong>
                    </div>
                @endif

                @if (is_array($storageAttention['largest_file'] ?? null))
                    <div class="admin-storage__attention-row">
                        <span>Largest original</span>
                        <strong title="{{ $storageAttention['largest_file']['filename'] }}">{{ $storageAttention['largest_file']['filename'] }} · {{ $storageAttention['largest_file']['display_bytes'] }}</strong>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="admin-storage__upload admin-visual-stage__pane">
        <div
            class="media-workspace__dropzone admin-storage__dropzone"
            x-data="{
                phase: 'idle',
                progress: 0,
                fileCount: 0,
                result: '',
                resultTimer: null,
                showResult(message) {
                    window.clearTimeout(this.resultTimer)
                    this.result = message
                    this.phase = 'result'
                    this.resultTimer = window.setTimeout(() => {
                        this.phase = 'idle'
                        this.progress = 0
                        this.fileCount = 0
                        this.result = ''
                    }, 3800)
                },
            }"
            x-bind:aria-busy="(phase === 'uploading' || phase === 'processing').toString()"
            x-on:livewire-upload-start="window.clearTimeout(resultTimer); phase = 'uploading'; progress = 0; result = ''"
            x-on:livewire-upload-progress="progress = $event.detail.progress"
            x-on:livewire-upload-finish="
                progress = 100
                phase = 'processing'
                $wire.processDirectMedia()
                    .then((response) => showResult(response?.summary ?? 'Upload complete'))
                    .catch(() => showResult('Upload failed'))
            "
            x-on:livewire-upload-error="$wire.set('directMedia', []); showResult('Upload failed')"
        >
            <input
                class="media-workspace__file-input"
                id="storage-upload"
                type="file"
                wire:model="directMedia"
                x-on:change="fileCount = $event.target.files.length"
                x-bind:disabled="phase === 'uploading' || phase === 'processing'"
                accept="{{ implode(',', \App\Domain\Media\MediaTypePolicy::uploadAcceptedMimeTypes()) }}"
                aria-label="Upload media files"
                multiple
            >
            <div class="media-workspace__dropzone-copy">
                <strong
                    x-text="
                        phase === 'uploading'
                            ? `Uploading ${fileCount} ${fileCount === 1 ? 'file' : 'files'} · ${progress}%`
                            : phase === 'processing'
                                ? `Processing ${fileCount} ${fileCount === 1 ? 'file' : 'files'}…`
                                : phase === 'result'
                                    ? result
                                    : 'Drop files here or choose from your device'
                    "
                >Drop files here or choose from your device</strong>
                <span x-show="phase === 'idle'">JPEG, PNG, WebP, H.264 MP4, VP8/VP9/AV1 WebM, MP3, M4A/AAC, Ogg audio, or WAV.</span>
            </div>
            <div
                class="media-workspace__upload-progress"
                x-show="phase === 'uploading'"
                x-cloak
                role="progressbar"
                aria-label="Upload progress"
                aria-valuemin="0"
                aria-valuemax="100"
                x-bind:aria-valuenow="progress"
            >
                <span class="media-workspace__upload-progress-track" aria-hidden="true">
                    <span class="media-workspace__upload-progress-fill" x-bind:style="`width: ${progress}%`"></span>
                </span>
            </div>
        </div>
    </div>
</section>
