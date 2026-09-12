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
    $device = isset($generalPage) && $generalPage instanceof \App\Filament\Pages\General
        ? $generalPage->previewDevice
        : 'desktop';
    $previewWidth = $device === 'mobile' ? 390 : 1366;
    $previewHeight = $device === 'mobile' ? 844 : 768;
@endphp

<aside
    class="general-appearance-stage__preview admin-visual-stage__pane"
    aria-label="Live public preview"
    x-data="{
        refreshTimer: null,
        resizeObserver: null,
        init() {
            this.$nextTick(() => {
                this.fitPreview()
                this.resizeObserver = new ResizeObserver(() => this.fitPreview())
                this.resizeObserver.observe(this.$refs.page)
            })
        },
        destroy() {
            window.clearTimeout(this.refreshTimer)
            this.resizeObserver?.disconnect()
        },
        fitPreview() {
            const page = this.$refs.page
            const frame = this.$refs.frame
            const device = this.$refs.device
            if (! page || ! frame || ! device) return

            const targetWidth = Number(device.dataset.previewWidth)
            const targetHeight = Number(device.dataset.previewHeight)
            const availableWidth = page.clientWidth
            const availableHeight = page.clientHeight
            if (! targetWidth || ! targetHeight || ! availableWidth || ! availableHeight) return

            const scale = Math.min(availableWidth / targetWidth, availableHeight / targetHeight)
            frame.style.width = `${targetWidth}px`
            frame.style.height = `${targetHeight}px`
            frame.style.left = `${Math.max(0, (availableWidth - (targetWidth * scale)) / 2)}px`
            frame.style.top = '0px'
            frame.style.transform = `scale(${scale})`
        },
        refreshPreview() {
            window.clearTimeout(this.refreshTimer)
            this.refreshTimer = window.setTimeout(() => {
                const url = new URL(this.$refs.frame.src, window.location.origin)
                url.searchParams.set('_appearance', Date.now().toString())
                this.$refs.frame.src = url.toString()
                this.$nextTick(() => this.fitPreview())
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
                class="general-live-preview__mode {{ $device === 'desktop' ? 'is-active' : '' }}"
                type="button"
                wire:click="setPreviewDevice('desktop')"
                aria-pressed="{{ $device === 'desktop' ? 'true' : 'false' }}"
                title="Desktop preview"
            >
                <x-filament::icon icon="heroicon-m-computer-desktop" />
            </button>
            <button
                class="general-live-preview__mode {{ $device === 'mobile' ? 'is-active' : '' }}"
                type="button"
                wire:click="setPreviewDevice('mobile')"
                aria-pressed="{{ $device === 'mobile' ? 'true' : 'false' }}"
                title="Mobile preview"
            >
                <x-filament::icon icon="heroicon-m-device-phone-mobile" />
            </button>
        </div>
    </div>

    <div class="general-live-preview__viewport">
        <div
            x-ref="device"
            class="general-live-preview__device is-{{ $device }}"
            data-preview-width="{{ $previewWidth }}"
            data-preview-height="{{ $previewHeight }}"
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
            <div x-ref="page" class="general-live-preview__page">
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
