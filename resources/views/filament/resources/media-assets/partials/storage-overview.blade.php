@php
    $storageTargets = is_array($storageAttention['targets'] ?? null) ? $storageAttention['targets'] : [];
    $storageSegments = is_array($storageAttention['capacity_segments'] ?? null) ? $storageAttention['capacity_segments'] : [];

    $usageByLabel = [];
    foreach ($usageGroups ?? [] as $usageGroup) {
        foreach (($usageGroup['options'] ?? []) as $option) {
            $label = trim((string) ($option['label'] ?? ''));
            $value = trim((string) ($option['value'] ?? ''));
            if ($label !== '' && $value !== '') {
                $usageByLabel[mb_strtolower($label)] = $value;
            }
        }
    }

    $storageTargets = array_map(static function (array $target) use ($usageByLabel): array {
        $area = (string) ($target['area'] ?? 'referenced');
        $label = trim((string) ($target['label'] ?? 'Reference'));
        $exactUsage = $usageByLabel[mb_strtolower($label)] ?? null;
        $usageFilter = is_string($exactUsage) ? $exactUsage : match ($area) {
            'home' => 'home',
            'cv' => 'cv',
            'site-identity' => 'site-identity',
            default => null,
        };

        $target['display_label'] = $area === 'site-identity' ? 'Site icon' : $label;
        $target['display_area'] = match ($area) {
            'galleries' => 'Gallery',
            'journal' => 'Journal',
            'custom-pages' => 'Custom page',
            'site-identity' => 'Appearance',
            'unassigned' => 'Unassigned',
            'uncatalogued' => 'Uncatalogued',
            default => (string) ($target['area_label'] ?? ucfirst(str_replace('-', ' ', $area))),
        };
        $target['usage_filter'] = $usageFilter;

        return $target;
    }, $storageTargets);
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
    aria-label="Storage upload, total capacity and media distribution"
    x-data="{
        selectedTarget: null,
        selectedArea: null,
        usageFilter: $wire.entangle('usage', true),
        targets: @js($storageTargets),
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
        syncUsage(value) {
            const exact = this.targets.find((target) => target.usage_filter === value) ?? null
            this.selectedTarget = exact?.key ?? null
            this.selectedArea = exact ? null : this.areaFromUsage(value)
        },
        selectTarget(target) {
            if (! target?.usage_filter) return
            this.usageFilter = this.selectedTarget === target.key ? 'all' : target.usage_filter
        },
        visibleTargets() {
            const rows = this.selectedArea
                ? this.targets.filter((target) => target.area === this.selectedArea)
                : this.targets

            return rows
        },
        targetWidth(bytes) {
            const rows = this.visibleTargets()
            const max = Math.max(1, ...rows.map((row) => Number(row.bytes) || 0))

            return Math.max(2, Math.round(((Number(bytes) || 0) / max) * 100))
        },
        toneIndex(target) {
            const index = this.targets.findIndex((row) => row.key === target?.key)
            return ((index < 0 ? 0 : index) % 8) + 1
        },
        emptyDetail() {
            if (this.selectedArea === 'unassigned') return 'No unassigned files are present in the measured storage.'
            if (this.selectedArea === 'uncatalogued') return 'No uncatalogued files are present in the measured storage.'
            return 'No measured distribution is available for this filter.'
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
                <p class="admin-storage__eyebrow">Total Capacity</p>
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
                :segments="$storageSegments"
            />
        </div>
    </div>

    <div class="admin-storage__distribution admin-visual-stage__pane">
        <div class="admin-storage__visual-heading">
            <p class="admin-storage__eyebrow">Media Distribution</p>
        </div>

        <div class="admin-storage__target-plot" aria-live="polite">
            <div class="admin-storage__distribution-columns" aria-hidden="true">
                <span>Area</span>
                <span>Files</span>
                <span>Storage</span>
            </div>

            <template x-for="target in visibleTargets()" x-bind:key="target.key">
                <button
                    class="admin-storage__target-row"
                    type="button"
                    x-bind:style="`--storage-row-color: var(--storage-target-${toneIndex(target)})`"
                    x-on:click="selectTarget(target)"
                    x-bind:disabled="! target.usage_filter"
                    x-bind:aria-pressed="target.usage_filter ? (selectedTarget === target.key).toString() : null"
                    x-bind:aria-label="`${target.display_label}, ${Number(target.files) || 0} files, ${target.display_bytes}`"
                    x-bind:class="{
                        'is-active': selectedTarget === target.key,
                        'is-context': selectedArea === target.area,
                        'is-static': ! target.usage_filter,
                    }"
                >
                    <span class="admin-storage__target-label">
                        <strong x-text="target.display_label"></strong>
                        <small x-text="target.display_area"></small>
                    </span>
                    <span class="admin-storage__target-files" x-text="Number(target.files) || 0"></span>
                    <span class="admin-storage__target-track" aria-hidden="true">
                        <i x-bind:style="`width: ${targetWidth(target.bytes)}%`"></i>
                    </span>
                    <strong class="admin-storage__target-value" x-text="target.display_bytes"></strong>
                </button>
            </template>

            <div class="admin-storage__target-empty" x-show="visibleTargets().length === 0" x-cloak>
                <p x-text="emptyDetail()"></p>
            </div>
        </div>

        <div class="admin-storage__attention" aria-label="Storage attention">
            @if (($storageAttention['unreferenced_files'] ?? 0) > 0)
                <div class="admin-storage__attention-row">
                    <span>Unused storage</span>
                    <strong>{{ $storageAttention['unreferenced_display_bytes'] }} · {{ number_format($storageAttention['unreferenced_files']) }} {{ ($storageAttention['unreferenced_files'] ?? 0) === 1 ? 'file' : 'files' }}</strong>
                </div>
            @endif

            @if (($storageAttention['uncatalogued_files'] ?? 0) > 0)
                <div class="admin-storage__attention-row is-warning">
                    <span>Uncatalogued storage</span>
                    <strong>{{ $storageAttention['uncatalogued_display_bytes'] }} · {{ number_format($storageAttention['uncatalogued_files']) }} {{ ($storageAttention['uncatalogued_files'] ?? 0) === 1 ? 'file' : 'files' }}</strong>
                </div>
            @endif

            @if (is_array($storageAttention['largest_file'] ?? null))
                <div class="admin-storage__attention-row">
                    <span>Largest file</span>
                    <strong title="{{ $storageAttention['largest_file']['filename'] }}">{{ $storageAttention['largest_file']['filename'] }} · {{ $storageAttention['largest_file']['display_bytes'] }}</strong>
                </div>
            @endif
        </div>

        <div class="admin-storage__reclaim-footer">
            <livewire:admin.site-storage-reclaim-control />
            <x-admin.help
                label="About freeing storage"
                text="Permanently clears Undo history, releases older publication restore snapshots, and removes rebuildable generated thumbnails. Activity remains available. The current LIVE restore snapshot and any restore or revert source currently in use stay protected."
            />
        </div>
    </div>
</section>