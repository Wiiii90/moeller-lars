@php
    $storageTargets = is_array($storageAttention['targets'] ?? null) ? $storageAttention['targets'] : [];
    $storageSliceAngle = 0.0;
    $storageTotalBytes = array_sum(array_map(
        static fn (array $row): int => (int) ($row['bytes'] ?? 0),
        $storageBreakdown,
    ));
    $capacityPercent = ($capacity['percent'] ?? null) !== null
        ? min(100, max(0, (float) $capacity['percent']))
        : null;
    $storageUsedAngle = $capacityPercent !== null ? ($capacityPercent / 100) * 360 : 0.0;

    $storagePolarPoint = static function (float $angle, float $radius): array {
        $radians = deg2rad($angle - 90);

        return [
            60 + (cos($radians) * $radius),
            60 + (sin($radians) * $radius),
        ];
    };

    $storageDonutPath = static function (float $startAngle, float $endAngle) use ($storagePolarPoint): string {
        $outerRadius = 43.0;
        $innerRadius = 29.5;
        [$outerStartX, $outerStartY] = $storagePolarPoint($startAngle, $outerRadius);
        [$outerEndX, $outerEndY] = $storagePolarPoint($endAngle, $outerRadius);
        [$innerEndX, $innerEndY] = $storagePolarPoint($endAngle, $innerRadius);
        [$innerStartX, $innerStartY] = $storagePolarPoint($startAngle, $innerRadius);
        $largeArc = ($endAngle - $startAngle) > 180 ? 1 : 0;

        return sprintf(
            'M %.3f %.3f A %.1f %.1f 0 %d 1 %.3f %.3f L %.3f %.3f A %.1f %.1f 0 %d 0 %.3f %.3f Z',
            $outerStartX,
            $outerStartY,
            $outerRadius,
            $outerRadius,
            $largeArc,
            $outerEndX,
            $outerEndY,
            $innerEndX,
            $innerEndY,
            $innerRadius,
            $innerRadius,
            $largeArc,
            $innerStartX,
            $innerStartY,
        );
    };
@endphp

<x-admin.metrics :columns="6" aria-label="Storage statistics">
    <x-admin.metric label="Files" :value="number_format($libraryFiles)">Available</x-admin.metric>
    <x-admin.metric label="Images" :value="number_format($libraryImages)">Available images</x-admin.metric>
    <x-admin.metric label="Unreferenced" :value="number_format($libraryUnreferenced)">No canonical consumer</x-admin.metric>
    <x-admin.metric label="Original storage" :value="$capacity['authoritative'] ?? '—'">Counts against allowance</x-admin.metric>
    <x-admin.metric label="Generated" :value="$capacity['generated'] ?? '—'">Rebuildable derivatives</x-admin.metric>
    <x-admin.metric label="Remaining" :value="$capacity['remaining'] ?? '—'">{{ $capacity['remaining_detail'] ?? 'Storage allowance' }}</x-admin.metric>
</x-admin.metrics>

<section
    class="admin-storage__visual-stage admin-visual-stage admin-visual-stage--triptych admin-visual-stage--stackable"
    aria-label="Storage upload, destinations and capacity"
    x-data="{
        selected: null,
        breakdown: @js($storageBreakdown),
        targets: @js($storageTargets),
        capacityPercent: @js($capacityPercent),
        allowance: @js($capacity['allowance'] ?? '—'),
        select(key) {
            if (! this.breakdown.some((row) => row.key === key)) return
            this.selected = this.selected === key ? null : key
        },
        selectedRow() {
            return this.breakdown.find((row) => row.key === this.selected) ?? null
        },
        capacityShare(row) {
            const used = Number(this.capacityPercent)
            const shareOfUsed = Number(row?.percent)
            if (! Number.isFinite(used) || ! Number.isFinite(shareOfUsed)) return null

            return (used * shareOfUsed) / 100
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
            if (! row) return ''

            const files = Number(row.files) || 0
            const capacityShare = this.capacityShare(row)
            const capacityCopy = capacityShare === null
                ? ''
                : ` · ${capacityShare.toFixed(capacityShare < 0.1 ? 2 : 1)}% of allowance`

            return `${row.display_bytes} · ${files} ${files === 1 ? 'original' : 'originals'}${capacityCopy}`
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
    <div class="admin-storage__visual-main admin-visual-stage__pane">
        <div class="admin-storage__upload">
            <div class="admin-storage__visual-heading">
                <p class="admin-storage__eyebrow">Upload</p>
            </div>

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

        <div class="admin-storage__distribution">
            <div class="admin-storage__visual-heading">
                <p class="admin-storage__eyebrow">Destinations</p>
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
                <div class="admin-storage__selection-summary" x-show="selectedRow()" x-cloak>
                    <strong x-text="selectedRow()?.label"></strong>
                    <span x-text="selectedMeta()"></span>
                </div>

                <template x-for="target in visibleTargets()" x-bind:key="target.key">
                    <button
                        class="admin-storage__target-row"
                        type="button"
                        x-bind:data-area="target.area"
                        x-on:click="select(target.area)"
                        x-bind:aria-pressed="(selected === target.area).toString()"
                        x-bind:class="{ 'is-active': selected === target.area }"
                    >
                        <span class="admin-storage__target-label">
                            <strong x-text="target.label"></strong>
                            <small x-text="`${Number(target.files) || 0} ${(Number(target.files) || 0) === 1 ? 'original' : 'originals'}`"></small>
                        </span>
                        <span class="admin-storage__target-track" aria-hidden="true">
                            <i x-bind:style="`width: ${targetWidth(target.bytes)}%`"></i>
                        </span>
                        <strong class="admin-storage__target-value" x-text="target.display_bytes"></strong>
                    </button>
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

    <div class="admin-storage__capacity-group admin-visual-stage__pane">
        <div class="admin-storage__visual-heading">
            <p class="admin-storage__eyebrow">Capacity</p>
        </div>

        <div class="admin-storage__capacity-plot">
            <svg class="admin-storage__donut" viewBox="0 0 120 120" role="img" aria-label="Storage allowance split into used categories and remaining capacity">
                <circle class="admin-storage__capacity-base" cx="60" cy="60" r="36.25" />

                @if (($capacity['configured'] ?? false) && ($capacity['measurement_available'] ?? false) && $capacityPercent !== null)
                    @foreach ($storageBreakdown as $row)
                        @php
                            $sliceBytes = max(0, (int) ($row['bytes'] ?? 0));
                            $sliceAngle = $storageTotalBytes > 0
                                ? ($sliceBytes / $storageTotalBytes) * $storageUsedAngle
                                : 0.0;
                            $sliceGap = $sliceAngle >= 3
                                ? min(0.9, $sliceAngle * 0.08)
                                : min(0.18, $sliceAngle * 0.08);
                            $sliceStart = $storageSliceAngle + ($sliceGap / 2);
                            $sliceEnd = $storageSliceAngle + $sliceAngle - ($sliceGap / 2);
                            $sliceMidpoint = $storageSliceAngle + ($sliceAngle / 2);
                            $sliceRadians = deg2rad($sliceMidpoint - 90);
                            $sliceX = round(cos($sliceRadians) * 4.5, 2);
                            $sliceY = round(sin($sliceRadians) * 4.5, 2);
                            $sliceKey = (string) ($row['key'] ?? '');
                            $sliceClass = preg_replace('/[^a-z0-9-]+/', '-', strtolower($sliceKey)) ?: 'referenced';
                            $slicePath = $sliceEnd > $sliceStart
                                ? $storageDonutPath($sliceStart, $sliceEnd)
                                : '';
                            $sliceCapacityPercent = $storageTotalBytes > 0
                                ? $capacityPercent * ($sliceBytes / $storageTotalBytes)
                                : 0.0;
                        @endphp
                        @if ($slicePath !== '')
                            <path
                                class="admin-storage__usage-segment admin-storage__usage-segment--{{ $sliceClass }}"
                                d="{{ $slicePath }}"
                                style="--storage-slice-x: {{ $sliceX }}px; --storage-slice-y: {{ $sliceY }}px"
                                role="button"
                                tabindex="0"
                                aria-label="{{ $row['label'] }}: {{ $row['display_bytes'] }}, {{ number_format($sliceCapacityPercent, $sliceCapacityPercent < 0.1 ? 2 : 1) }} percent of storage allowance"
                                x-bind:aria-pressed="(selected === @js($sliceKey)).toString()"
                                x-bind:class="{
                                    'is-selected': selected === @js($sliceKey),
                                    'is-muted': selected !== null && selected !== @js($sliceKey),
                                }"
                                x-on:click="select(@js($sliceKey))"
                                x-on:keydown.enter.prevent="select(@js($sliceKey))"
                                x-on:keydown.space.prevent="select(@js($sliceKey))"
                            >
                                <title>{{ $row['label'] }} — {{ $row['display_bytes'] }} · {{ number_format($sliceCapacityPercent, $sliceCapacityPercent < 0.1 ? 2 : 1) }}% of allowance</title>
                            </path>
                        @endif
                        @php $storageSliceAngle += $sliceAngle; @endphp
                    @endforeach
                @endif
            </svg>

            <div class="admin-storage__capacity-core" aria-live="polite">
                <template x-if="selectedRow()">
                    <div>
                        <strong x-text="selectedRow().display_bytes"></strong>
                        <span x-text="selectedRow().label"></span>
                        <small x-text="capacityShare(selectedRow()) === null ? '' : `${capacityShare(selectedRow()).toFixed(capacityShare(selectedRow()) < 0.1 ? 2 : 1)}% of ${allowance}`"></small>
                    </div>
                </template>
                <template x-if="! selectedRow()">
                    <div>
                        @if (($capacity['measurement_available'] ?? false) && ($capacity['configured'] ?? false))
                            <strong>{{ $capacity['allowance'] ?? '—' }}</strong>
                            <span>Total capacity</span>
                            <small>{{ $capacity['authoritative'] ?? '—' }} used · {{ $capacity['remaining'] ?? '—' }} free</small>
                        @elseif ($capacity['measurement_available'] ?? false)
                            <strong>—</strong>
                            <span>No allowance configured</span>
                            <small>{{ $capacity['authoritative'] ?? '—' }} authoritative</small>
                        @else
                            <strong>—</strong>
                            <span>{{ ($capacity['status'] ?? null) === 'not_measured' ? 'Awaiting measurement' : 'Unavailable' }}</span>
                        @endif
                    </div>
                </template>
            </div>
        </div>

        <div class="admin-storage__capacity-copy">
            @if (($capacity['configured'] ?? false) && ($capacity['measurement_available'] ?? false))
                <strong>{{ $capacity['percent'] ?? '—' }}% used</strong>
                <span>{{ $capacity['remaining'] ?? '—' }} remaining</span>
            @elseif ($capacity['measurement_available'] ?? false)
                <strong>{{ $capacity['authoritative'] ?? '—' }} authoritative</strong>
            @else
                <span>Measure once to load the current storage snapshot.</span>
            @endif
            <small>{{ $capacity['generated'] ?? '—' }} generated · excluded from allowance</small>
            <button class="admin-action admin-storage__refresh" type="button" wire:click="refreshStorageMeasurement">Refresh</button>
        </div>
    </div>
</section>