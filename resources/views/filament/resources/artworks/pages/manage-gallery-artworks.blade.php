<x-filament-panels::page>
    <x-admin.workspace :title="$galleryContext['name']">
        <x-admin.metrics :columns="6" aria-label="Gallery overview">
            @foreach ($metrics as $metric)
                <x-admin.metric :label="$metric['label']" :value="$metric['value']">{{ $metric['description'] }}</x-admin.metric>
            @endforeach
        </x-admin.metrics>

        <div class="admin-gallery-upload" aria-label="Add artwork from media">
            <div
                class="admin-gallery-upload__dropzone"
                x-data="{ uploading: false, progress: 0 }"
                x-on:livewire-upload-start="uploading = true; progress = 0"
                x-on:livewire-upload-finish="uploading = false; progress = 100"
                x-on:livewire-upload-error="uploading = false"
                x-on:livewire-upload-progress="progress = $event.detail.progress"
            >
                <input
                    class="admin-gallery-upload__input"
                    type="file"
                    wire:model="directPrimaryMedia"
                    accept="image/jpeg,image/png,image/webp,video/mp4,video/webm"
                    aria-label="Upload primary image or video for a new artwork"
                >
                <div class="admin-gallery-upload__copy">
                    <strong>Drop an image or video here, or choose a file</strong>
                    <span>Creates the Media File first, then opens Add Artwork with it selected.</span>
                </div>
                <div class="admin-gallery-upload__progress" x-show="uploading" x-cloak>
                    <progress max="100" x-bind:value="progress"></progress>
                    <span x-text="`${progress}%`"></span>
                </div>
            </div>
            <button class="admin-action" type="button" wire:click="chooseExistingMedia">Choose from Media Files</button>
        </div>
        @error('directPrimaryMedia')
            <p class="admin-gallery-upload__message is-error">{{ $message }}</p>
        @enderror
        @if ($directUploadMessage !== null)
            <p class="admin-gallery-upload__message">{{ $directUploadMessage }}</p>
        @endif

        <div class="admin-gallery-workspace-toolbar" aria-label="Gallery artwork actions">
            <div class="admin-toolbar">
                {{ $this->gallerySettingsAction }}
                {{ $this->addArtworkAction }}
                {{ $this->materialPresetsAction }}
            </div>

            @if (count($selectedArtworkIds) > 0)
                <div class="admin-toolbar admin-gallery-workspace-toolbar__batch">
                    <span class="admin-gallery-workspace-toolbar__selection">{{ count($selectedArtworkIds) }} selected</span>
                    @if ($moveTargets !== [])
                        <select wire:model="batchTargetGalleryId" aria-label="Move selected artworks to Gallery">
                            <option value="">Move to Gallery…</option>
                            @foreach ($moveTargets as $target)
                                <option value="{{ $target['id'] }}">
                                    {{ $target['name'] }}{{ $target['state'] === 'published' ? '' : ' · '.$target['state'] }}
                                </option>
                            @endforeach
                        </select>
                        <button class="admin-action" type="button" wire:click="reassignSelectedArtworks">Move</button>
                    @endif
                    <button class="admin-action" type="button" wire:click="moveSelectedArtworks('up')">Up</button>
                    <button class="admin-action" type="button" wire:click="moveSelectedArtworks('down')">Down</button>
                    {{ $this->publishSelectedArtworksAction }}
                    {{ $this->unpublishSelectedArtworksAction }}
                    {{ $this->removeSelectedArtworksAction }}
                    {{ $this->deleteSelectedArtworksAction }}
                </div>
            @endif
        </div>

        @if ($artworks !== [])
            <section class="admin-gallery-grid" aria-label="Artwork sequence for {{ $galleryContext['name'] }}">
                @foreach ($artworks as $artwork)
                    <article class="admin-gallery-grid__item" wire:key="gallery-artwork-{{ $artwork['id'] }}">
                        <div class="admin-gallery-grid__image">
                            @if ($artwork['thumbnail_url'])
                                <img src="{{ $artwork['thumbnail_url'] }}" alt="" loading="lazy" decoding="async">
                            @elseif ($artwork['primary_kind'] === 'video' && $artwork['primary_original_url'])
                                <video src="{{ $artwork['primary_original_url'] }}" preload="metadata" muted playsinline controls aria-label="Video preview for {{ $artwork['title'] }}"></video>
                            @else
                                <span>No primary media</span>
                            @endif
                            <span class="admin-gallery-grid__sequence">{{ str_pad((string) $artwork['sequence'], 2, '0', STR_PAD_LEFT) }}</span>
                        </div>

                        <div class="admin-gallery-grid__caption">
                            <div class="admin-gallery-grid__identity">
                                <strong>{{ $artwork['title'] }}</strong>
                                <span>
                                    @if ($artwork['year']){{ $artwork['year'] }}@endif
                                    @if ($artwork['medium']){{ $artwork['year'] ? ' · ' : '' }}{{ $artwork['medium'] }}@endif
                                    @if ($artwork['dimensions']){{ ($artwork['year'] || $artwork['medium']) ? ' · ' : '' }}{{ $artwork['dimensions'] }}@endif
                                </span>
                                <small class="admin-gallery-grid__analytics">
                                    @if ($artwork['analytics']['available'])
                                        30d · {{ number_format($artwork['analytics']['views']) }} views · {{ number_format($artwork['analytics']['opens']) }} opens · {{ number_format($artwork['analytics']['zooms']) }} zooms · {{ $artwork['analytics']['attention'] }} attention
                                    @else
                                        30d analytics unavailable
                                    @endif
                                </small>
                            </div>
                            <span class="admin-gallery-grid__state {{ $artwork['state'] === 'published' || $artwork['is_ready'] ? 'is-published' : '' }}">
                                {{ $artwork['state_label'] }} · {{ $artwork['readiness_label'] }}
                            </span>
                        </div>

                        <div class="admin-gallery-grid__actions admin-toolbar">
                            <label class="admin-action">
                                <input type="checkbox" wire:model.live="selectedArtworkIds" value="{{ $artwork['id'] }}">
                                Select
                            </label>
                            <button class="admin-action is-primary" type="button" wire:click="mountAction('editArtwork', { artwork: {{ $artwork['id'] }} })">Edit</button>
                            @if ($artwork['media_preview_url'])
                                <a class="admin-action" href="{{ $artwork['media_preview_url'] }}">Media</a>
                            @endif
                            @if ($artwork['public_url'])
                                <a class="admin-action" href="{{ $artwork['public_url'] }}" target="_blank" rel="noopener">View</a>
                            @endif
                            @if ($artwork['state'] === 'published')
                                <button class="admin-action" type="button" wire:click="mountAction('unpublishArtwork', { artwork: {{ $artwork['id'] }} })">Unpublish</button>
                            @else
                                <button class="admin-action" type="button" wire:click="mountAction('publishArtwork', { artwork: {{ $artwork['id'] }} })">Publish</button>
                            @endif
                            <button class="admin-action" type="button" wire:click="moveArtwork({{ $artwork['id'] }}, 'up')" aria-label="Move {{ $artwork['title'] }} earlier" @disabled(! $artwork['can_move_up'])>↑</button>
                            <button class="admin-action" type="button" wire:click="moveArtwork({{ $artwork['id'] }}, 'down')" aria-label="Move {{ $artwork['title'] }} later" @disabled(! $artwork['can_move_down'])>↓</button>
                            @if ($moveTargets !== [])
                                <select wire:model="moveTargetGalleryIds.{{ $artwork['id'] }}" aria-label="Move {{ $artwork['title'] }} to Gallery">
                                    <option value="">Move to Gallery…</option>
                                    @foreach ($moveTargets as $target)
                                        <option value="{{ $target['id'] }}">
                                            {{ $target['name'] }}{{ $target['state'] === 'published' ? '' : ' · '.$target['state'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <button class="admin-action" type="button" wire:click="reassignArtwork({{ $artwork['id'] }})">Move</button>
                            @endif
                            <button class="admin-action" type="button" wire:click="mountAction('removeArtwork', { artwork: {{ $artwork['id'] }} })">Remove</button>
                            @if ($artwork['state'] === 'draft')
                                <button class="admin-action is-danger" type="button" wire:click="mountAction('deleteArtwork', { artwork: {{ $artwork['id'] }} })">Delete</button>
                            @endif
                        </div>
                    </article>
                @endforeach
            </section>
        @else
            <x-admin.empty-state kicker="Empty Gallery" title="Add the first artwork">
                <p>This Gallery is ready. Add an artwork draft and select or upload an image or video as its primary media.</p>
                <x-slot:actions>{{ $this->addArtworkAction }}</x-slot:actions>
            </x-admin.empty-state>
        @endif
    </x-admin.workspace>

    <x-filament-actions::modals />
</x-filament-panels::page>