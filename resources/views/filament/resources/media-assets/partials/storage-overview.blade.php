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

<x-admin.metrics :columns="6" class="storage-status-metrics admin-metrics--six-state" aria-label="Storage statistics">
    <x-admin.metric class="storage-metric storage-metric--files" label="Files" :value="number_format($libraryFiles)">{{ __('Available') }}</x-admin.metric>
    <x-admin.metric class="storage-metric storage-metric--images" label="Images" :value="number_format($libraryImages)">{{ __('Available images') }}</x-admin.metric>
    <x-admin.metric class="storage-metric storage-metric--unreferenced" label="Unreferenced" :value="number_format($libraryUnreferenced)">{{ __('No canonical consumer') }}</x-admin.metric>
    <x-admin.metric class="storage-metric storage-metric--used" label="Original storage" :value="$capacity['authoritative'] ?? '—'">{{ __('Counts against allowance') }}</x-admin.metric>
    <x-admin.metric class="storage-metric storage-metric--generated" label="Generated" :value="$capacity['generated'] ?? '—'">{{ __('Rebuildable derivatives') }}</x-admin.metric>
    <x-admin.metric class="storage-metric storage-metric--remaining" label="Remaining" :value="$capacity['remaining'] ?? '—'">{{ $capacity['remaining_detail'] ?? 'Storage allowance' }}</x-admin.metric>
</x-admin.metrics>

<section
    class="admin-storage__visual-stage admin-visual-stage admin-visual-stage--triptych admin-focus-stage"
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
    <x-admin.stage-switcher
        storage-key="admin-stage.storage"
        default-pane="capacity"
        :panes="['upload' => 'Upload', 'capacity' => 'Capacity', 'distribution' => 'Distribution']"
        aria-label="Storage stage"
    />

    <div class="admin-storage__upload admin-visual-stage__pane" data-admin-stage-pane="upload" data-admin-stage-slot="1">
        <div class="admin-storage__visual-heading">
            <p class="admin-storage__eyebrow">{{ __('Upload Media Files') }}</p>
        </div>

        <div
            class="media-workspace__dropzone admin-storage__dropzone"
            x-data="{
                phase: 'idle',
                progress: 0,
                fileCount: 0,
                completed: 0,
                result: '',
                async uploadFiles(input) {
                    if (this.phase === 'uploading' || this.phase === 'processing') return

                    const files = Array.from(input.files ?? [])
                    if (files.length === 0) return

                    this.fileCount = files.length
                    this.completed = 0
                    this.progress = 0
                    this.result = ''
                    let added = 0
                    let duplicates = 0
                    let failed = 0
                    const errors = []

                    for (const file of files) {
                        this.phase = 'uploading'

                        const transferred = await new Promise((resolve) => {
                            $wire.uploadMultiple(
                                'directMedia',
                                [file],
                                () => resolve(true),
                                () => resolve(false),
                                (event) => {
                                    const percent = Number(event?.detail?.progress ?? 0)
                                    this.progress = Math.round(
                                        (this.completed + percent / 100) * 100 / this.fileCount
                                    )
                                },
                            )
                        })

                        if (!transferred) {
                            failed++
                            errors.push(file.name + ' — Transfer failed. Check the file size and connection.')
                        } else {
                            this.phase = 'processing'
                            try {
                                const outcome = await $wire.processDirectMedia(false)
                                added += Number(outcome?.added ?? 0)
                                duplicates += Number(outcome?.duplicates ?? 0)
                                failed += Number(outcome?.failed ?? 0)
                                for (const error of outcome?.errors ?? []) errors.push(error)
                            } catch (error) {
                                failed++
                                errors.push(file.name + ' — Processing failed.')
                                try { await $wire.set('directMedia', []) } catch {}
                            }
                        }

                        this.completed++
                        this.progress = Math.round(this.completed * 100 / this.fileCount)
                    }

                    input.value = ''
                    const summary = added + ' uploaded, ' + duplicates + ' already in Storage, ' +
                        failed + ' failed (' + this.fileCount + ' selected)'
                    this.result = errors.length ? summary + ' · ' + errors.slice(0, 2).join('; ') : summary
                    this.phase = 'result'
                },
            }"
            x-bind:aria-busy="(phase === 'uploading' || phase === 'processing').toString()"
        >
            <input
                class="media-workspace__file-input"
                id="storage-upload"
                type="file"
                x-on:change="uploadFiles($el)"
                x-bind:disabled="phase === 'uploading' || phase === 'processing'"
                accept="{{ implode(',', \App\Domain\Media\MediaTypePolicy::uploadAcceptedMimeTypes()) }}"
                aria-label="Upload media files"
                multiple
            >
            <div class="media-workspace__dropzone-copy">
                <strong
                    x-text="
                        phase === 'uploading'
                            ? `Uploading file ${completed + 1} of ${fileCount} · ${progress}%`
                            : phase === 'processing'
                                ? `Processing file ${completed + 1} of ${fileCount}…`
                                : phase === 'result'
                                    ? result
                                    : 'Drop files here or choose from your device'
                    "
                >{{ __('Drop files here or choose from your device') }}</strong>
                <span x-show="phase === 'idle'">JPEG, PNG, WebP · {{ number_format(\App\Domain\Media\MediaTypePolicy::imageMaxBytes() / 1048576, 0) }} MiB / {{ number_format(\App\Domain\Media\MediaIngestService::MAX_PIXELS / 1000000, 0) }} MP; MP4, WebM · {{ number_format(\App\Domain\Media\MediaTypePolicy::videoMaxBytes() / 1048576, 0) }} MiB; MP3, M4A/AAC, Ogg, WAV · {{ number_format(\App\Domain\Media\MediaTypePolicy::audioMaxBytes() / 1048576, 0) }} MiB.</span>
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

    <div class="admin-storage__capacity-group admin-visual-stage__pane" data-admin-stage-pane="capacity" data-admin-stage-slot="2">
        <div class="admin-storage__visual-heading">
            <p class="admin-storage__eyebrow">{{ __('Total Capacity') }}</p>
        </div>

        <x-admin.storage-capacity-visual
            :capacity="$capacity"
            :breakdown="$storageBreakdown"
            :segments="$storageSegments"
        />

        <div class="admin-storage__capacity-actions" aria-label="Storage capacity actions">
            <button
                class="admin-action admin-action--with-icon"
                type="button"
                wire:click="refreshStorageMeasurement"
                wire:loading.attr="disabled"
                wire:target="refreshStorageMeasurement"
                aria-label="Refresh storage measurement"
                title="Refresh storage measurement"
            >
                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Refresh->mini()" class="admin-action__icon" />
                <span class="admin-action__label">{{ __('Refresh') }}</span>
            </button>

            <livewire:admin.site-storage-reclaim-control />

        </div>
    </div>

    <div class="admin-storage__distribution admin-visual-stage__pane" data-admin-stage-pane="distribution" data-admin-stage-slot="3">
        <div class="admin-storage__visual-heading">
            <p class="admin-storage__eyebrow">{{ __('Media Distribution') }}</p>
        </div>

        <div class="admin-storage__target-plot" aria-live="polite">
            <div class="admin-storage__distribution-columns" aria-hidden="true">
                <span>{{ __('Area') }}</span>
                <span>{{ __('Files') }}</span>
                <span>{{ __('Storage') }}</span>
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
                    <span>{{ __('Unused storage') }}</span>
                    <strong>{{ $storageAttention['unreferenced_display_bytes'] }} · {{ number_format($storageAttention['unreferenced_files']) }} {{ ($storageAttention['unreferenced_files'] ?? 0) === 1 ? 'file' : 'files' }}</strong>
                </div>
            @endif

            @if (($storageAttention['uncatalogued_files'] ?? 0) > 0)
                <div class="admin-storage__attention-row is-warning">
                    <span>{{ __('Uncatalogued storage') }}</span>
                    <strong>{{ $storageAttention['uncatalogued_display_bytes'] }} · {{ number_format($storageAttention['uncatalogued_files']) }} {{ ($storageAttention['uncatalogued_files'] ?? 0) === 1 ? 'file' : 'files' }}</strong>
                </div>
            @endif

            @if (is_array($storageAttention['largest_file'] ?? null))
                <div class="admin-storage__attention-row">
                    <span>{{ __('Largest file') }}</span>
                    <strong title="{{ $storageAttention['largest_file']['filename'] }}">{{ $storageAttention['largest_file']['filename'] }} · {{ $storageAttention['largest_file']['display_bytes'] }}</strong>
                </div>
            @endif
        </div>
    </div>
</section>
