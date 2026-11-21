<x-filament-panels::page>
    @php
        $reorderEnabled = trim($search) === ''
            && $statusFilter === 'any'
            && $readinessFilter === 'any';
        $reorderDisabledReason = 'Clear filters to reorder';
        $selectedIds = array_map('intval', $selectedArtworkIds);
        $selectedArtworks = collect($artworkDataset)->filter(static fn (array $row): bool =>
            in_array((int) $row['id'], $selectedIds, true)
        );
        $selectedHasPublished = $selectedArtworks->contains(static fn (array $row): bool => $row['state'] === 'published');
        $selectedCanPublish = $galleryContext['state'] === 'published'
            && $selectedArtworks->contains(static fn (array $row): bool => $row['state'] === 'draft')
            && $selectedArtworks->every(static fn (array $row): bool => $row['is_ready']);
        $hasPublishedMoveTarget = collect($moveTargets)->contains(static fn (array $target): bool => $target['state'] === 'published');
        $selectedCanMoveToGallery = $selectedArtworks->isNotEmpty() && $moveTargets !== []
            && (! $selectedHasPublished || $hasPublishedMoveTarget);
        $selectedCanMoveUp = $reorderEnabled
            && $selectedArtworks->contains(static fn (array $row): bool => $row['can_move_up']);
        $selectedCanMoveDown = $reorderEnabled
            && $selectedArtworks->contains(static fn (array $row): bool => $row['can_move_down']);
    @endphp
    <x-admin.workspace :title="$galleryContext['name']">
        <x-admin.metrics :columns="6" class="gallery-status-metrics admin-metrics--six-state" aria-label="Gallery overview">
            @foreach ($metrics as $metric)
                @php
                    $metricRole = match ($metric['label']) {
                        'Artworks' => 'artworks',
                        'Published' => 'published',
                        'Visits' => 'visits',
                        'Views' => 'views',
                        'Artwork opens' => 'opens',
                        'Attention' => 'attention',
                        default => 'secondary',
                    };
                @endphp
                <x-admin.metric class="gallery-metric gallery-metric--{{ $metricRole }}" :label="$metric['label']" :value="$metric['value']">{{ $metric['description'] }}</x-admin.metric>
            @endforeach
        </x-admin.metrics>

        @php
            $stagePublicUrl = $this->galleryStagePreviewUrl();
            $backgroundPreview = $this->galleryBackgroundPreviewTarget();
            $stagedPreviewUrl = route('preview.gallery.staged-artwork', [
                'gallery' => (int) $galleryContext['id'],
                'token' => $this->getId(),
            ]);
        @endphp
        <section class="gallery-workspace__stage admin-visual-stage admin-visual-stage--split admin-focus-stage"
            x-ref="galleryStageRoot"
            aria-label="Gallery editor and interactive preview"
            data-gallery-background-preview-kind="{{ $backgroundPreview['kind'] }}"
            data-gallery-background-preview-url="{{ $backgroundPreview['url'] }}"
            data-gallery-background-preview-artwork-id="{{ $backgroundPreview['artwork_id'] }}"
            data-gallery-staged-preview-url="{{ $stagedPreviewUrl }}"
            x-data="{
                panel: $wire.entangle('galleryStagePanel'),
                previewKind: $wire.entangle('galleryStagePreview'),
                previewArtworkId: $wire.entangle('galleryStagePreviewArtworkId'),
                previewAutoFocus: $wire.entangle('galleryPreviewAutoFocus'),
                selectPanel(next) {
                    if (this.panel === next || (next === 'queue' && this.$refs.galleryQueueTab?.disabled)) return
                    this.panel = next
                    if (!this.previewAutoFocus) return

                    // Only the visible pane is browser-local; the canonical
                    // preview targets are resolved by the Gallery workspace.
                    const background = this.$refs.galleryStageRoot.dataset
                    const kind = next === 'queue' ? 'staged' : background.galleryBackgroundPreviewKind
                    const artworkId = next === 'queue'
                        ? null : (Number(background.galleryBackgroundPreviewArtworkId) || null)
                    if (this.previewKind === kind && this.previewArtworkId === artworkId) return

                    const url = next === 'queue'
                        ? background.galleryStagedPreviewUrl : background.galleryBackgroundPreviewUrl
                    this.previewKind = kind
                    this.previewArtworkId = artworkId
                    if (url) this.$dispatch('gallery-preview-updated', {
                        url, title: kind === 'staged' ? 'Interactive staged Artwork preview'
                            : (kind === 'artwork' ? 'Interactive Artwork preview' : 'Interactive Gallery preview'),
                    })
                },
            }">
            <x-admin.stage-switcher
                storage-key="admin-stage.gallery-merged"
                default-pane="editor"
                :panes="['editor' => 'Editor', 'preview' => 'Preview']"
                aria-label="Gallery Stage view"
            />
            <div class="gallery-stage__workspace admin-visual-stage__pane" data-admin-stage-pane="editor" data-admin-stage-slot="1"
                x-data="{
                    phase: 'idle', progress: 0, processingStep: '', transferDetail: '', fileCount: 0, completed: 0, result: '',
                    pendingUploads: [], uploadRunning: false,
                    async uploadFiles(input) {
                        await window.adminMediaUploader(this, input, $wire, 'gallery')
                    },
                }">
                <header class="gallery-stage__workspace-header">
                    <h2 class="admin-section__kicker admin-visual-stage__heading">Gallery editor</h2>
                    @php($stagedPosition = $this->stagedArtworkPosition())
                    @php($stagedTotal = count($pendingBatchArtworkMedia))
                    <div class="admin-stage-tabs gallery-stage__workspace-tabs" role="group" aria-label="Gallery editor mode and staged artwork navigation">
                        <button class="admin-action" type="button"
                            x-on:click="selectPanel('upload')"
                            x-bind:class="{ 'is-primary': panel === 'upload' }"
                            x-bind:aria-pressed="(panel === 'upload').toString()">Upload<span x-show="uploadRunning" x-cloak x-text="'(' + progress.toFixed(1) + '%)'" class="gallery-stage__upload-tab-progress"></span></button>
                        <div class="admin-action gallery-stage__queue-control"
                            x-bind:class="{ 'is-primary': panel === 'queue' }"
                            role="group" aria-label="Staged Artwork navigation">
                            <button class="gallery-stage__queue-arrow" type="button"
                                wire:click="navigateStagedArtwork(-1)"
                                @disabled($stagedTotal <= 1)
                                aria-label="Previous staged artwork" title="Previous staged artwork">
                                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Previous->mini()" />
                            </button>
                            <button class="gallery-stage__queue-tab" type="button" x-ref="galleryQueueTab"
                                x-on:click="selectPanel('queue')"
                                x-bind:aria-pressed="(panel === 'queue').toString()"
                                @disabled($stagedTotal === 0)>Staged (<span aria-live="polite" aria-atomic="true">{{ $stagedPosition }}/{{ $stagedTotal }}</span>)</button>
                            <button class="gallery-stage__queue-arrow" type="button"
                                wire:click="navigateStagedArtwork(1)"
                                @disabled($stagedTotal <= 1)
                                aria-label="Next staged artwork" title="Next staged artwork">
                                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Next->mini()" />
                            </button>
                        </div>
                        <button class="admin-action" type="button"
                            x-on:click="selectPanel('settings')"
                            x-bind:class="{ 'is-primary': panel === 'settings' }"
                            x-bind:aria-pressed="(panel === 'settings').toString()">Settings</button>
                    </div>
                </header>

            <div class="gallery-stage__upload" x-show="panel === 'upload'" x-cloak
                x-bind:aria-busy="uploadRunning.toString()">
                <div class="gallery-stage__upload-status" role="status" aria-live="polite" aria-atomic="true">
                    <span x-text="
                        phase === 'uploading' ? 'Uploading to Storage'
                        : phase === 'processing' ? processingStep
                        : phase === 'result' ? 'Upload finished'
                        : 'Ready for new files'
                    ">Ready for new files</span>
                    <span class="gallery-stage__upload-status-value"
                        x-text="
                            phase === 'uploading' ? (completed + 1) + ' / ' + fileCount + ' (' + progress.toFixed(1) + '%) ' + transferDetail
                            : phase === 'processing' ? (completed + 1) + ' / ' + fileCount + ' (' + progress.toFixed(1) + '%)'
                            : phase === 'result' ? 'Review Staged'
                            : @js(count($pendingBatchArtworkMedia)) + ' staged'
                        ">{{ count($pendingBatchArtworkMedia) }} staged</span>
                </div>
                <div class="admin-gallery-upload__progress"
                    x-bind:role="uploadRunning ? 'progressbar' : null"
                    aria-label="Media upload status"
                    aria-valuemin="0" aria-valuemax="100"
                    x-bind:aria-valuenow="uploadRunning ? progress : null"
                    x-bind:aria-valuetext="phase === 'processing' ? processingStep + ' (' + progress.toFixed(1) + '%)' : null">
                    <span class="admin-gallery-upload__progress-track" aria-hidden="true">
                        <span class="admin-gallery-upload__progress-fill" x-bind:style="'width: ' + progress + '%'"></span>
                    </span>
                </div>
                <div class="admin-gallery-upload" aria-label="Add artwork from media">
                    <div class="admin-gallery-upload__dropzone">
                        <input class="admin-gallery-upload__input" type="file"
                            x-on:change="uploadFiles($el)"
                            accept="{{ implode(',', [...\App\Domain\Media\MediaTypePolicy::IMAGE_MIME_TYPES, ...\App\Domain\Media\MediaTypePolicy::VIDEO_MIME_TYPES]) }}"
                            aria-label="Upload primary images or videos for new artworks"
                            multiple>
                        <div class="admin-gallery-upload__copy">
                            <x-filament::icon :icon="\App\Filament\Support\AdminIcon::UploadDropzone->value"
                                class="admin-gallery-upload__icon" aria-hidden="true" />
                            <strong>Drop images or videos here, or choose files</strong>
                            <span x-show="uploadRunning" x-cloak
                                x-text="'Batch progress (' + progress.toFixed(1) + '%)'"></span>
                            <span x-show="phase === 'idle'">
                                JPEG, PNG, WebP (max {{ \App\Domain\Content\RegionalFormats::adminNumber(\App\Domain\Media\MediaIngestService::MAX_PIXELS / 1000000, 0) }} megapixels); MP4 and WebM. Interrupted transfers can be resumed by selecting the same file.
                            </span>
                            <span x-show="phase === 'result'" x-text="result" x-cloak></span>
                        </div>
                    </div>
                </div>
            </div>
                @if ($pendingBatchArtworkMedia !== [] && $selectedStagedMediaAssetId !== null)
                        <div class="gallery-stage__form-scroll" x-show="panel === 'queue'" x-cloak
                            wire:change="syncStagedArtworkPreview" wire:key="gallery-staged-editor-{{ $selectedStagedMediaAssetId }}">
                            {{ $this->stagedArtworkForm }}
                        </div>
                        <div class="gallery-stage__editor-actions admin-toolbar"
                            x-show="panel === 'queue'" x-cloak aria-label="Staged Artwork actions">
                            <div class="gallery-stage__action-group">
                                <button class="admin-action is-danger gallery-stage__action-discard" type="button"
                                    title="Discard staged file and delete it from Storage" aria-label="Discard staged file from Storage"
                                    wire:click="discardStagedMedia({{ $selectedStagedMediaAssetId }})"
                                    wire:loading.attr="disabled" wire:target="discardStagedMedia,discardAllStagedMedia">
                                    <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Clear->mini()" />
                                    <span class="gallery-stage__action-label">Discard</span>
                                </button>
                                <button class="admin-action is-success" type="button"
                                    title="Submit and publish Artwork in Working" aria-label="Submit and publish staged Artwork"
                                    wire:click="createSelectedStagedArtwork"
                                    wire:loading.attr="disabled" wire:target="createSelectedStagedArtwork,createAllStagedArtworks">
                                    <x-filament::icon :icon="\App\Filament\Support\AdminIcon::DialogSubmit->mini()" />
                                    <span class="gallery-stage__action-label">Submit</span>
                                </button>
                            </div>
                            <div class="gallery-stage__action-group gallery-stage__action-group--batch">
                                <button class="admin-action is-danger" type="button"
                                    title="Discard all staged files and delete them from Storage" aria-label="Discard all staged files from Storage"
                                    wire:click="discardAllStagedMedia"
                                    wire:loading.attr="disabled" wire:target="discardStagedMedia,discardAllStagedMedia">
                                    <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Clear->mini()" />
                                    <span class="gallery-stage__action-label">Discard All</span>
                                </button>
                                <button class="admin-action is-success" type="button"
                                    title="Submit and publish all staged Artworks" aria-label="Submit and publish all staged Artworks"
                                    wire:click="createAllStagedArtworks"
                                    wire:loading.attr="disabled" wire:target="createSelectedStagedArtwork,createAllStagedArtworks">
                                    <x-filament::icon :icon="\App\Filament\Support\AdminIcon::SubmitAll->value" />
                                    <span class="gallery-stage__action-label">Submit All</span>
                                </button>
                            </div>
                        </div>
                @endif
                    <div class="gallery-stage__settings" x-show="panel === 'settings'" x-cloak
                        aria-label="Gallery settings">
                        {{ $this->galleryStageSettingsForm }}
                    </div>
            </div>

            <div class="gallery-stage__preview admin-visual-stage__pane" data-admin-stage-pane="preview" data-admin-stage-slot="2">
                <header class="gallery-stage__preview-header">
                    <h2 class="admin-section__kicker admin-visual-stage__heading"
                        x-text="previewKind === 'staged' ? 'Staged Artwork preview' : 'Live preview'">{{ $galleryStagePreview === 'staged' ? 'Staged Artwork preview' : 'Live preview' }}</h2>

                </header>

                <x-admin.live-preview
                    class="gallery-stage__live-preview"
                    :heading="false"
                    :url="$stagePublicUrl"
                    :frame-title="$this->galleryStagePreviewTitle()"
                    wire:key="gallery-live-preview"
                >
                    <x-slot:focusAction>
                        <button class="admin-action admin-stage-tool-icon {{ $galleryPreviewAutoFocus ? 'is-primary' : '' }}"
                            type="button" wire:click="toggleGalleryPreviewAutoFocus"
                            aria-label="Auto-focus preview to selection"
                            aria-pressed="{{ $galleryPreviewAutoFocus ? 'true' : 'false' }}"
                            title="{{ $galleryPreviewAutoFocus ? 'Auto-focus selection on' : 'Auto-focus selection off' }}">
                            <x-filament::icon icon="heroicon-m-cursor-arrow-rays" />
                        </button>
                    </x-slot:focusAction>
                </x-admin.live-preview>
            </div>
        </section>

        <div class="gallery-workspace__result-surface admin-visual-stage-followup admin-view-switcher"
            data-admin-view-mode="{{ $viewMode }}"
            x-data="{ viewMode: $wire.entangle('viewMode') }"
            x-bind:data-admin-view-mode="viewMode">
            <x-admin.controls class="admin-data-controls--dashboard-responsive admin-data-controls--filters-compact gallery-workspace__controls" aria-label="Gallery controls" metric-grid :filter-count="2" :search-span="2">
                <x-slot:search>
                    <label class="admin-data-field">
                        <span>{{ __('Search') }}</span>
                        <x-admin.search-input model="search" placeholder="Title, material, dimensions" />
                    </label>
                </x-slot:search>

                <x-slot:filters>
                    <label class="admin-data-field">
                        <span>{{ __('Status') }}</span>
                        <select wire:model.live="statusFilter">
                            <option value="any">{{ __('Any') }}</option>
                            <option value="published">{{ __('Published') }}</option>
                            <option value="draft">{{ __('Unpublished') }}</option>
                        </select>
                    </label>

                    <label class="admin-data-field">
                        <span title="Ready to publish requires a published Gallery, a title, a public URL, one available primary image or video, ALT text, and its generated preview. Artwork Date, material, dimensions and description are optional.">{{ __('Publish readiness') }}</span>
                        <select wire:model.live="readinessFilter" aria-label="Filter by publish readiness">
                            <option value="any">{{ __('Any') }}</option>
                            <option value="ready">{{ __('Ready to publish') }}</option>
                            <option value="needs-attention">{{ __('Needs attention') }}</option>
                        </select>
                    </label>
                </x-slot:filters>

                <x-slot:reset>
                    <div class="admin-data-control-group">
                        <span class="admin-data-control-label">{{ __('Filter') }}</span>
                        <x-admin.clear-filters wire:click="resetFilters" :disabled="trim($search) === '' && $statusFilter === 'any' && $readinessFilter === 'any'" />
                    </div>
                </x-slot:reset>

                <x-slot:actions>
                    <div class="admin-data-control-group">
                        <span class="admin-data-control-label">{{ __('Gallery') }}</span>
                        <div class="admin-toolbar admin-editorial-actions gallery-workspace__gallery-actions">
                            <button class="admin-action admin-action--with-icon" type="button" wire:click="mountAction('gallerySettings')" aria-label="Gallery settings">
                                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Settings->mini()" class="admin-action__icon" />
                                <span class="admin-action__label">{{ __('Settings') }}</span>
                            </button>
                            <button class="admin-action admin-action--with-icon" type="button" wire:click="mountAction('addArtwork')" aria-label="Add artwork">
                                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Add->mini()" class="admin-action__icon" />
                                <span class="admin-action__label">{{ __('Add artwork') }}</span>
                            </button>
                            <button class="admin-action admin-action--with-icon" type="button" wire:click="mountAction('materialPresets')" aria-label="Materials">
                                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Materials->mini()" class="admin-action__icon" />
                                <span class="admin-action__label">{{ __('Materials') }}</span>
                            </button>
                            @if ($galleryContext['preview_url'])
                                <a class="admin-action admin-action--with-icon" href="{{ $galleryContext['preview_url'] }}" target="_blank" rel="noopener" aria-label="Preview Gallery">
                                    <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Preview->mini()" class="admin-action__icon" />
                                    <span class="admin-action__label">{{ __('Preview') }}</span>
                                </a>
                            @else
                                <button class="admin-action admin-action--with-icon" type="button" disabled title="A preview URL is unavailable for this Gallery" aria-label="Preview Gallery">
                                    <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Preview->mini()" class="admin-action__icon" />
                                    <span class="admin-action__label">{{ __('Preview') }}</span>
                                </button>
                            @endif
                        </div>
                    </div>
                    <div class="admin-data-control-group gallery-workspace__view-group">
                        <span class="admin-data-control-label">{{ __('View') }}</span>
                        <x-admin.view-options :view-mode="$viewMode" label="Artwork view" storage-key="admin-view.gallery" />
                    </div>
                </x-slot:actions>

                <x-slot:selection>
                    <div class="admin-data-control-group admin-selection"
                        x-data="{ open: false }" x-on:keydown.escape.window="open = false">
                        <span class="admin-data-control-label">{{ __('Selection') }}</span>
                        <div class="admin-selection__anchor">
                            <button
                                class="admin-action admin-selection__trigger"
                                type="button"
                                x-on:click="open = !open"
                                x-bind:aria-expanded="open.toString()"
                                aria-haspopup="menu"
                                @disabled(count($selectedArtworkIds) === 0)
                            >
                                <x-admin.selection-trigger-label>{{ __('Selected') }}</x-admin.selection-trigger-label>
                            </button>
                            <div class="admin-selection__menu" x-show="open" x-cloak x-on:click.outside="open = false" role="menu">
                                @if ($moveTargets !== [])
                                    <button class="admin-action" type="button" role="menuitem" x-on:click="open = false" wire:click="mountAction('moveSelectedToGallery')" @disabled(! $selectedCanMoveToGallery)>{{ __('Move to Gallery…') }}</button>
                                @endif
                                <button class="admin-action" type="button" role="menuitem" x-on:click="open = false" wire:click="moveSelectedArtworks('up')" @disabled(! $selectedCanMoveUp)>{{ __('Move selected up') }}</button>
                                <button class="admin-action" type="button" role="menuitem" x-on:click="open = false" wire:click="moveSelectedArtworks('down')" @disabled(! $selectedCanMoveDown)>{{ __('Move selected down') }}</button>
                                <button class="admin-action" type="button" role="menuitem" x-on:click="open = false" wire:click="mountAction('publishSelectedArtworks')" @disabled(! $selectedCanPublish)>{{ __('Publish selected') }}</button>
                                <button class="admin-action" type="button" role="menuitem" x-on:click="open = false" wire:click="mountAction('unpublishSelectedArtworks')" @disabled(! $selectedHasPublished)>{{ __('Unpublish selected') }}</button>
                                <button class="admin-action" type="button" role="menuitem" x-on:click="open = false" wire:click="mountAction('deleteSelectedArtworks')">{{ __('Delete selected') }}</button>
                            </div>
                        </div>
                        <span class="admin-selection__count" aria-label="{{ count($selectedArtworkIds) }} selected">{{ count($selectedArtworkIds) }}</span>
                    </div>
                </x-slot:selection>
            </x-admin.controls>

            @if ($artworks !== [])
                @include('filament.resources.artworks.partials.gallery-artwork-table')
                <section
                    class="admin-gallery-grid"
                    data-admin-view-panel="grid"
                    aria-label="Artwork sequence for {{ $galleryContext['name'] }}"
                    @if ($reorderEnabled) wire:sort="sortArtwork" @endif
                >
                    @foreach ($artworks as $artwork)
                        @php($selected = collect($selectedArtworkIds)->contains(fn ($id) => (int) $id === (int) $artwork['id']))
                        <article
                            class="admin-gallery-grid__item gallery-workspace__card {{ $selected ? 'is-selected' : '' }}"
                            wire:key="gallery-artwork-{{ $artwork['id'] }}"
                            @if ($reorderEnabled) wire:sort:item="{{ $artwork['id'] }}" @endif
                        >
                            <button
                                class="admin-gallery-grid__image gallery-workspace__visual"
                                type="button"
                                wire:key="gallery-artwork-media-{{ $artwork['id'] }}-{{ $artwork['primary_media_asset_id'] ?? 'empty' }}-{{ $artwork['primary_variant_id'] ?? 'none' }}"
                                wire:click="mountAction('previewArtwork', { artwork: {{ $artwork['id'] }} })"
                                aria-label="Preview {{ $artwork['title'] }}"
                            >
                                @if ($artwork['thumbnail_url'])
                                    <img src="{{ $artwork['thumbnail_url'] }}" alt="" loading="lazy" decoding="async" draggable="false">
                                @elseif ($artwork['primary_kind'] === 'video' && $artwork['primary_original_url'])
                                    <video src="{{ $artwork['primary_original_url'] }}" preload="metadata" muted playsinline aria-label="Video preview for {{ $artwork['title'] }}" draggable="false"></video>
                                @else
                                    <span>{{ __('No primary media') }}</span>
                                @endif
                                <span class="admin-gallery-grid__sequence">{{ str_pad((string) $artwork['sequence'], 2, '0', STR_PAD_LEFT) }}</span>
                            </button>

                            <div class="gallery-workspace__primary-actions" aria-label="Primary actions for {{ $artwork['title'] }}">
                                @if ($artwork['state'] === 'published')
                                    <button
                                        class="gallery-workspace__icon-action gallery-workspace__primary-action"
                                        type="button"
                                        wire:click="mountAction('unpublishArtwork', { artwork: {{ $artwork['id'] }} })"
                                        title="Unpublish artwork"
                                        aria-label="Unpublish {{ $artwork['title'] }}"
                                    >
                                        <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Unpublish->mini()" />
                                    </button>
                                @else
                                    <button
                                        class="gallery-workspace__icon-action gallery-workspace__primary-action"
                                        type="button"
                                        wire:click="mountAction('publishArtwork', { artwork: {{ $artwork['id'] }} })"
                                        title="Publish artwork"
                                        aria-label="Publish {{ $artwork['title'] }}"
                                    >
                                        <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Publish->mini()" />
                                    </button>
                                @endif
                                <button
                                    class="admin-drag-handle"
                                    type="button"
                                    @if ($reorderEnabled) wire:sort:handle @endif
                                    @disabled(! $reorderEnabled)
                                    aria-disabled="{{ $reorderEnabled ? 'false' : 'true' }}"
                                    title="{{ $reorderEnabled ? 'Drag to reorder' : $reorderDisabledReason }}"
                                    aria-label="{{ $reorderEnabled ? 'Drag '.$artwork['title'].' to reorder' : $reorderDisabledReason.' '.$artwork['title'] }}"
                                >⋮⋮</button>
                            </div>

                            <div class="admin-gallery-grid__caption">
                                <div class="admin-gallery-grid__identity">
                                    <x-admin.artwork-title-editor :artwork="$artwork" />
                                    @php($artworkMeta = implode(', ', array_filter([
                                        $artwork['year'] ? (string) $artwork['year'] : null,
                                        $artwork['dimensions'] ?: null,
                                        $artwork['medium'] ?: null,
                                    ])))
                                    <span title="{{ $artworkMeta }}">{{ $artworkMeta }}</span>
                                    @php($analyticsSummary = $artwork['analytics']['available']
                                        ? '30d: '.\App\Domain\Content\RegionalFormats::adminNumber($artwork['analytics']['views']).' views, '.\App\Domain\Content\RegionalFormats::adminNumber($artwork['analytics']['opens']).' opens, '.\App\Domain\Content\RegionalFormats::adminNumber($artwork['analytics']['zooms']).' zooms, '.$artwork['analytics']['attention'].' attention'
                                        : '30d: analytics unavailable')
                                    <small class="admin-gallery-grid__analytics" title="{{ $analyticsSummary }}">{{ $analyticsSummary }}</small>
                                </div>
                                <span class="admin-gallery-grid__state {{ $artwork['state'] === 'published' ? 'is-published' : '' }}">
                                    {{ $artwork['state_label'] }}
                                    @if ($artwork['state'] !== 'published')
                                        <span>, {{ $artwork['readiness_label'] }}</span>
                                    @endif
                                </span>
                            </div>

                            <div class="admin-gallery-grid__actions">
                                <label class="gallery-workspace__selection-checkbox">
                                    <input
                                        type="checkbox"
                                        value="{{ $artwork['id'] }}"
                                        wire:model.live="selectedArtworkIds"
                                    >
                                    <span>{{ __('Select') }}</span>
                                </label>
                                <div class="gallery-workspace__card-actions">
                                    <button
                                        class="gallery-workspace__icon-action"
                                        type="button"
                                        wire:click="mountAction('editArtwork', { artwork: {{ $artwork['id'] }} })"
                                        title="Edit artwork"
                                        aria-label="Edit {{ $artwork['title'] }}"
                                    >
                                        <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Edit->mini()" />
                                    </button>
                                    <button
                                        class="gallery-workspace__icon-action gallery-workspace__order-action"
                                        type="button"
                                        wire:click="moveArtwork({{ $artwork['id'] }}, 'up')"
                                        title="{{ $reorderEnabled ? 'Move artwork earlier' : $reorderDisabledReason }}"
                                        aria-label="{{ $reorderEnabled ? 'Move '.$artwork['title'].' earlier' : $reorderDisabledReason.' '.$artwork['title'] }}"
                                        @disabled(! $reorderEnabled || ! $artwork['can_move_up'])
                                    >
                                        <x-filament::icon :icon="\App\Filament\Support\AdminIcon::MoveUp->mini()" />
                                    </button>
                                    <button
                                        class="gallery-workspace__icon-action gallery-workspace__order-action"
                                        type="button"
                                        wire:click="moveArtwork({{ $artwork['id'] }}, 'down')"
                                        title="{{ $reorderEnabled ? 'Move artwork later' : $reorderDisabledReason }}"
                                        aria-label="{{ $reorderEnabled ? 'Move '.$artwork['title'].' later' : $reorderDisabledReason.' '.$artwork['title'] }}"
                                        @disabled(! $reorderEnabled || ! $artwork['can_move_down'])
                                    >
                                        <x-filament::icon :icon="\App\Filament\Support\AdminIcon::MoveDown->mini()" />
                                    </button>
                                    @if ($moveTargets !== [])
                                        <button class="gallery-workspace__icon-action" type="button" wire:click="mountAction('moveArtworkToGallery', { artwork: {{ $artwork['id'] }} })"
                                            @disabled($artwork['state'] === 'published' && ! $hasPublishedMoveTarget)
                                            title="{{ $artwork['state'] === 'published' && ! $hasPublishedMoveTarget ? 'Published artwork needs a published destination Gallery' : 'Move to Gallery' }}" aria-label="Move {{ $artwork['title'] }} to another Gallery">
                                            <x-filament::icon :icon="\App\Filament\Support\AdminIcon::MoveBetween->mini()" />
                                        </button>
                                    @endif
                                    <button
                                        class="gallery-workspace__icon-action"
                                        type="button"
                                        wire:click="mountAction('deleteArtwork', { artwork: {{ $artwork['id'] }} })"
                                        title="Delete Artwork (optionally with its Storage file)"
                                        aria-label="Delete artwork {{ $artwork['title'] }}"
                                    >
                                        <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Delete->mini()" />
                                    </button>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </section>
            @elseif ($unfilteredArtworkCount > 0)
                <x-admin.empty-state title="No matches" minimal>
                    <x-slot:actions>
                        <x-admin.clear-filters label="Clear filters" wire:click="resetFilters" />
                    </x-slot:actions>
                </x-admin.empty-state>
            @else
                <x-admin.empty-state title="Empty" minimal>
                    <x-slot:actions>
                        <button class="admin-action" type="button" wire:click="mountAction('addArtwork')">{{ __('Add artwork') }}</button>
                    </x-slot:actions>
                </x-admin.empty-state>
            @endif
        </div>
    </x-admin.workspace>

    <x-filament-actions::modals />
</x-filament-panels::page>