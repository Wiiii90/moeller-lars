<x-admin.metrics :columns="6" aria-label="Storage statistics">
    <x-admin.metric label="Files" :value="number_format($libraryFiles)">Available</x-admin.metric>
    <x-admin.metric label="Images" :value="number_format($libraryImages)">Available images</x-admin.metric>
    <x-admin.metric label="Unreferenced" :value="number_format($libraryUnreferenced)">No canonical consumer</x-admin.metric>
    <x-admin.metric label="Original storage" :value="$capacity['authoritative'] ?? '—'">Counts against allowance</x-admin.metric>
    <x-admin.metric label="Generated" :value="$capacity['generated'] ?? '—'">Rebuildable derivatives</x-admin.metric>
    <x-admin.metric label="Remaining" :value="$capacity['remaining'] ?? '—'">{{ $capacity['remaining_detail'] ?? 'Storage allowance' }}</x-admin.metric>
</x-admin.metrics>

<section class="admin-storage__visual-stage admin-visual-stage admin-visual-stage--triptych admin-visual-stage--stackable" aria-label="Storage capacity, distribution and upload">
    <div class="admin-storage__visual-main admin-visual-stage__pane">
        <div class="admin-storage__capacity-group">
            <div
                @class([
                    'admin-storage__capacity-orbit',
                    'is-unconfigured' => ! ($capacity['configured'] ?? false),
                    'is-unavailable' => ! ($capacity['measurement_available'] ?? false),
                ])
                style="--capacity-used: {{ $capacity['percent'] ?? 0 }}%"
                role="img"
                aria-label="@if (($capacity['percent'] ?? null) !== null) {{ $capacity['percent'] }} percent of the configured allowance is used @elseif ($capacity['measurement_available'] ?? false) Authoritative usage is measured but no allowance is configured @else Authoritative storage measurement is unavailable @endif"
            >
                <div class="admin-storage__capacity-core">
                    @if (($capacity['percent'] ?? null) !== null)
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

        <div class="admin-storage__distribution">
            <div class="admin-storage__visual-heading">
                <div>
                    <p class="admin-storage__eyebrow">Distribution</p>
                    <strong>Originals by actual use</strong>
                </div>
            </div>

            <div class="admin-storage__segments" aria-label="Authoritative storage distribution">
                @forelse ($storageBreakdown as $row)
                    <div class="admin-storage__segment">
                        <span class="admin-storage__segment-label">
                            <strong>{{ $row['label'] }}</strong>
                            <small>{{ number_format($row['files']) }} {{ $row['files'] === 1 ? 'original' : 'originals' }}</small>
                        </span>
                        <span class="admin-storage__segment-track" aria-hidden="true">
                            <i style="width: {{ min(100, max(0, $row['percent'])) }}%"></i>
                        </span>
                        <span class="admin-storage__segment-value">{{ $row['display_bytes'] }} · {{ number_format($row['percent'], 1) }}%</span>
                    </div>
                @empty
                    <p class="admin-storage__empty">No authoritative originals are currently measurable.</p>
                @endforelse
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
