@php
    $settings = \App\Models\PublicContentSetting::general();
    $assetId = $settings->getAttribute('favicon_media_asset_id');
    $faviconAsset = is_numeric($assetId)
        ? \App\Models\MediaAsset::query()
            ->where('state', 'available')
            ->whereIn('mime_type', \App\Domain\Media\MediaTypePolicy::IMAGE_MIME_TYPES)
            ->with('variants')
            ->find((int) $assetId)
        : null;
    $faviconThumbnail = $faviconAsset?->getRelationValue('variants')->first(static function (\App\Models\MediaVariant $variant): bool {
        return $variant->getAttribute('variant_kind') === \App\Domain\Media\MediaIngestService::THUMBNAIL_KIND
            && $variant->getAttribute('transform_profile') === \App\Domain\Media\MediaIngestService::TRANSFORM_PROFILE
            && $variant->getAttribute('state') === 'available';
    });
    $previewUrl = route('preview.home', ['appearance' => now()->timestamp]);
    $previewHost = parse_url($previewUrl, PHP_URL_HOST) ?: 'preview';
    $previewPath = parse_url($previewUrl, PHP_URL_PATH) ?: '/preview';
@endphp

<aside
    class="general-appearance-stage__preview admin-visual-stage__pane"
    aria-label="Live public preview"
    x-data="{
        device: 'desktop',
        refreshTimer: null,
        init() {
            this.$nextTick(() => this.syncStageMode())
        },
        setDevice(next) {
            this.device = next
            this.$nextTick(() => this.syncStageMode())
        },
        syncStageMode() {
            const stage = this.$el.closest('.general-appearance-stage')
            if (! stage) return

            stage.setAttribute('data-preview-device', this.device)

            const controls = stage.querySelector('.general-appearance-stage__controls')
            if (! controls) return

            const grid = [...stage.querySelectorAll('.fi-sc-grid')].find((candidate) => {
                const children = [...candidate.children]
                const ownsControls = children.some((child) => child === controls || child.contains(controls))
                const ownsPreview = children.some((child) => child === this.$el || child.contains(this.$el))

                return ownsControls && ownsPreview
            })

            if (! grid) return

            const controlsItem = [...grid.children].find((child) => child === controls || child.contains(controls))
            const previewItem = [...grid.children].find((child) => child === this.$el || child.contains(this.$el))
            if (! controlsItem || ! previewItem) return

            grid.style.setProperty('grid-template-columns', 'repeat(3, minmax(0, 1fr))', 'important')
            grid.style.setProperty('grid-template-rows', 'repeat(3, minmax(0, 1fr))', 'important')
            grid.style.setProperty('gap', '0', 'important')
            grid.style.height = '100%'

            if (this.device === 'desktop') {
                controlsItem.style.setProperty('grid-column', '1', 'important')
                controlsItem.style.setProperty('grid-row', '1 / span 3', 'important')
                previewItem.style.setProperty('grid-column', '2 / span 2', 'important')
                previewItem.style.setProperty('grid-row', '1 / span 2', 'important')
            } else {
                controlsItem.style.setProperty('grid-column', '1 / span 2', 'important')
                controlsItem.style.setProperty('grid-row', '1 / span 3', 'important')
                previewItem.style.setProperty('grid-column', '3', 'important')
                previewItem.style.setProperty('grid-row', '1 / span 3', 'important')
            }
        },
        refreshPreview() {
            window.clearTimeout(this.refreshTimer)
            this.refreshTimer = window.setTimeout(() => {
                const url = new URL(this.$refs.frame.src, window.location.origin)
                url.searchParams.set('_appearance', Date.now().toString())
                this.$refs.frame.src = url.toString()
            }, 700)
        },
    }"
    x-on:general-appearance-updated.window="refreshPreview()"
>
    <div class="general-live-preview__toolbar">
        <span class="general-live-preview__title">
            <x-filament::icon icon="heroicon-m-rectangle-group" />
            <span>Live preview</span>
        </span>
        <div class="general-live-preview__modes" role="group" aria-label="Preview device">
            <button
                class="general-live-preview__mode"
                type="button"
                x-bind:class="device === 'desktop' ? 'is-active' : ''"
                x-on:click="setDevice('desktop')"
                x-bind:aria-pressed="(device === 'desktop').toString()"
                title="Desktop preview"
            >
                <x-filament::icon icon="heroicon-m-computer-desktop" />
            </button>
            <button
                class="general-live-preview__mode"
                type="button"
                x-bind:class="device === 'mobile' ? 'is-active' : ''"
                x-on:click="setDevice('mobile')"
                x-bind:aria-pressed="(device === 'mobile').toString()"
                title="Mobile preview"
            >
                <x-filament::icon icon="heroicon-m-device-phone-mobile" />
            </button>
        </div>
    </div>

    <div class="general-live-preview__viewport">
        <div
            class="general-live-preview__device"
            x-bind:class="device === 'desktop' ? 'is-desktop' : 'is-mobile'"
        >
            <div class="general-live-preview__browser" aria-hidden="true">
                <span class="general-live-preview__browser-dots">
                    <i></i><i></i><i></i>
                </span>
                <span class="general-live-preview__address">
                    @if ($faviconThumbnail instanceof \App\Models\MediaVariant)
                        <img src="{{ route('admin.media.variant', $faviconThumbnail) }}" alt="">
                    @endif
                    <span>{{ $previewHost }}{{ $previewPath }}</span>
                </span>
            </div>
            <div class="general-live-preview__page">
                <iframe
                    x-ref="frame"
                    src="{{ $previewUrl }}"
                    title="Live preview of the public homepage"
                    loading="eager"
                ></iframe>
            </div>
        </div>
    </div>
</aside>
