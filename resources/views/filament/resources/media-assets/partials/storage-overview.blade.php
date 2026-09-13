@php
    $storageTargets = is_array($storageAttention['targets'] ?? null) ? $storageAttention['targets'] : [];
    $capacityPercent = ($capacity['percent'] ?? null) !== null
        ? min(100, max(0, (float) $capacity['percent']))
        : null;
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
    aria-label="Storage upload, capacity and destinations"
    x-on:admin-viz:select="select($event.detail.key)"
    x-data="{
        selected: null,
        usageFilter: $wire.entangle('usage', true),
        breakdown: @js($storageBreakdown),
        targets: @js($storageTargets),
        capacityPercent: @js($capacityPercent),
        init() {
            this.$watch('usageFilter', (value) => this.syncUsage(value))
            this.$nextTick(() => this.syncUsage(this.usageFilter))
        },
        areaFromUsage(value) {
            const normalized = String(value ?? '').toLowerCase()
            if (normalized === '' || normalized === 'all' || normalized === 'in-use') return null
            if (normalized === 'unreferenced') return 'unassigned'
            if (normalized === 'kind:gallery') return 'galleries'
            if (normalized === 'kind:journal') return 'journal'
            if (normalized === 'kind:custom') return 'custom-pages'
            if (normalized === 'site-identity') return 'site-identity'
            if (normalized === 'home') return 'home'
            if (normalized === 'cv') return 'cv'
            return null
        },
        usageFromArea(key) {
            return ({
                galleries: 'kind:gallery',
                journal: 'kind:journal',
                'custom-pages': 'kind:custom',
                home: 'home',
                cv: 'cv',
                'site-identity': 'site-identity',
                unassigned: 'unreferenced',
            })[key] ?? null
        },
        setSelection(key) {
            const next = key && this.breakdown.some((row) => row.key === key) ? key : null
            this.selected = next
            this.$nextTick(() => {
                this.$refs.capacityViz?.dispatchEvent(new CustomEvent('admin-viz:state', {
                    detail: { selected: this.selected },
                }))
            })
        },
        select(key) {
            if (! this.breakdown.some((row) => row.key === key)) return

            const next = this.selected === key ? null : key
            this.setSelection(next)

            const usage = next === null ? 'all' : this.usageFromArea(next)
            if (usage !== null) this.usageFilter = usage
        },
        syncUsage(value) {
            this.setSelection(this.areaFromUsage(value))
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
                <p class="admin-storage__eyebrow">Upload Media Files</p>
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

        <div class="admin-storage__capacity-group">
            <div class="admin-storage__visual-heading">
                <p class="admin-storage__eyebrow">Capacity</p>
                <button
                    class="admin-icon-action"
                    type="button"
                    wire:click="refreshStorageMeasurement"
                    wire:loading.attr="disabled"
                    wire:target="refreshStorageMeasurement"
                    aria-label="Refresh storage measurement"
                    title="Refresh storage measurement"
                >
                    <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Refresh->mini()" />
                </button>
            </div>

            <x-admin.storage-capacity-visual
                :capacity="$capacity"
                :breakdown="$storageBreakdown"
                linked
                x-ref="capacityViz"
            />
        </div>
    </div>

    <div class="admin-storage__distribution admin-visual-stage__pane">
        <div class="admin-storage__visual-heading">
            <p class="admin-storage__eyebrow">Destinations</p>
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
</section>
