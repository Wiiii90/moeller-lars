@props([
    'url' => null,
    'frameTitle' => 'Interactive public preview',
    'heading' => true,
    'refreshOnAppearance' => false,
])

<aside
    {{ $attributes->class(['admin-live-preview']) }}
    aria-label="Live public preview"
    x-bind:data-preview-device="previewDevice"
    data-preview-profiles='@json(\App\Domain\Content\PreviewDeviceProfiles::all())'
    data-preview-url="{{ $url }}"
    data-preview-render-url="{{ route('admin.live-preview.render') }}"
    x-data="adminLivePreview($el)"
    @if ($refreshOnAppearance) x-on:general-appearance-updated.window="refreshPreview()" @endif
    x-on:gallery-preview-updated.window="refreshPreview($event.detail); scheduleFit()"
    x-on:resize.window="scheduleFit()"
    x-on:keydown.window="handleSpaceDown($event)"
    x-on:keyup.window="handleSpaceUp($event)"
    x-on:blur.window="cancelSpacePan()"
>
    @if ($heading)
        <h2 class="admin-section__kicker admin-live-preview__title admin-visual-stage__heading">Live preview</h2>
    @endif
    <x-admin.stage-tool-row label="Live preview controls">
        <div class="admin-stage-tool-group admin-live-preview__device-buttons" role="group" aria-label="Device class">
            <button type="button" class="admin-action admin-stage-tool-icon"
                x-on:click="setDevice('desktop')"
                x-bind:class="{ 'is-primary': previewDevice === 'desktop' }"
                x-bind:aria-pressed="(previewDevice === 'desktop').toString()"
                title="Desktop devices" aria-label="Desktop devices">
                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::DeviceDesktop->mini()" />
            </button>
            <button type="button" class="admin-action admin-stage-tool-icon"
                x-on:click="setDevice('tablet')"
                x-bind:class="{ 'is-primary': previewDevice === 'tablet' }"
                x-bind:aria-pressed="(previewDevice === 'tablet').toString()"
                title="Tablet devices" aria-label="Tablet devices">
                <x-filament::icon icon="heroicon-o-device-tablet" />
            </button>
            <button type="button" class="admin-action admin-stage-tool-icon"
                x-on:click="setDevice('mobile')"
                x-bind:class="{ 'is-primary': previewDevice === 'mobile' }"
                x-bind:aria-pressed="(previewDevice === 'mobile').toString()"
                title="Phone devices" aria-label="Phone devices">
                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::DeviceMobile->mini()" />
            </button>
        </div>
        <div class="admin-stage-tool-group admin-live-preview__device-profile" role="group" aria-label="Responsive device profile">
            <select class="admin-live-preview__width-select" x-model="selectedProfile"
                x-on:change="selectProfile($event.target.value)"
                aria-label="Device and viewport resolution"
                title="True CSS viewport width and height">
                @foreach (['desktop' => 'Desktop', 'tablet' => 'Tablet', 'mobile' => 'Phone'] as $category => $label)
                    <optgroup label="{{ $label }}">
                        @foreach (\App\Domain\Content\PreviewDeviceProfiles::all() as $id => $profile)
                            @if ($profile['category'] === $category)
                                <option value="{{ $id }}">{{ $profile['label'] }}</option>
                            @endif
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
            <button class="admin-action admin-stage-tool-icon" type="button"
                x-on:click="rotateViewport()"
                x-bind:aria-label="rotated ? 'Restore device orientation' : 'Rotate device'"
                x-bind:title="rotated ? 'Restore orientation' : 'Rotate device'">
                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Refresh->mini()" />
            </button>
        </div>
        @if (filled($url))
            <div class="admin-stage-tool-group admin-live-preview__engine-group">
                <select class="admin-live-preview__engine-select"
                    x-model="engine" x-on:change="setEngine($event.target.value)"
                    title="Live browser or isolated rendering engine"
                    aria-label="Rendering engine">
                    <option value="interactive">Live</option>
                    <option value="chromium">Chromium</option>
                    <option value="webkit">WebKit</option>
                    <option value="firefox">Firefox</option>
                </select>
            </div>
        @endif
        <div class="admin-stage-tool-group admin-live-preview__zoom-group" role="group" aria-label="Preview zoom">
            <button class="admin-action admin-stage-tool-icon" type="button"
                x-on:click="changeZoom(-0.25)" x-bind:disabled="zoom <= minZoom"
                title="Zoom out" aria-label="Zoom out preview">
                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::PreviewZoomOut->mini()" />
            </button>
            <button class="admin-live-preview__zoom-value" type="button"
                x-on:click="resetZoom()" x-bind:disabled="zoom <= minZoom"
                x-bind:class="{ 'is-active': zoom > minZoom }"
                title="Reset to fitted preview" aria-label="Reset zoom" x-text="zoomPercent()"></button>
            <button class="admin-action admin-stage-tool-icon" type="button"
                x-on:click="changeZoom(0.25)" x-bind:disabled="zoom >= maxZoom"
                title="Zoom in" aria-label="Zoom in preview">
                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Inspect->mini()" />
            </button>
        </div>
        <div class="admin-stage-tool-group admin-live-preview__actions-group" role="group" aria-label="Preview actions">
            <button class="admin-action admin-stage-tool-icon" type="button"
                x-on:click="togglePanMode()" x-bind:disabled="zoom <= minZoom"
                x-bind:class="{ 'is-primary': panMode }" x-bind:aria-pressed="panMode.toString()"
                title="Pan (or hold Space and drag)" aria-label="Toggle preview pan mode">
                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::PreviewPan->mini()" />
            </button>
            @if (filled($url))
                <a class="admin-action admin-stage-tool-icon" href="{{ $url }}"
                    x-bind:href="currentUrl || '{{ $url }}'"
                    target="_blank" rel="noopener noreferrer"
                    title="Open interactive preview" aria-label="Open interactive preview">
                    <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Preview->mini()" />
                </a>
            @else
                <button class="admin-action admin-stage-tool-icon" disabled type="button"
                    title="No preview URL" aria-label="Open preview unavailable">
                    <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Preview->mini()" />
                </button>
            @endif
        </div>
    </x-admin.stage-tool-row>


    <div x-ref="viewport" class="admin-live-preview__viewport" tabindex="0"
        x-on:pointerenter="pointerInside = true"
        x-on:pointerleave="pointerInside = false; cancelSpacePan()"
        x-on:wheel="handleSnapshotWheel($event)"
        x-on:keydown="handleSnapshotKeydown($event)">
        <div x-ref="surface" wire:ignore.self class="admin-live-preview__surface">
            <div x-ref="page" wire:ignore.self class="admin-live-preview__page"
                x-bind:class="{
                    'is-zoomed': zoom > minZoom,
                    'is-pan-enabled': zoom > minZoom && (panMode || spacePan),
                    'is-dragging': dragging,
                }">
                @if (isset($content))
                    <div x-ref="frame" wire:ignore.self class="admin-live-preview__frame admin-live-preview__frame--content">
                        {{ $content }}
                    </div>
                @elseif (filled($url))
                    <iframe x-ref="frame" wire:ignore class="admin-live-preview__frame"
                        x-bind:class="{ 'is-snapshot-source': engine !== 'interactive' }"
                        src="{{ $url }}" title="{{ $frameTitle }}"
                        loading="eager" tabindex="0" allowfullscreen
                        x-on:load="$nextTick(() => { scheduleFit(); bindFrameShortcuts(); updateViewportDiagnostics() })"></iframe>
                    <img x-ref="snapshotFrame" class="admin-live-preview__snapshot"
                        x-show="engine !== 'interactive' && snapshotUrl !== ''" x-cloak
                        x-bind:src="snapshotUrl || null"
                        alt="Rendered public page in the selected browser engine">
                @else
                    <div x-ref="frame" wire:ignore.self class="admin-live-preview__frame admin-live-preview__frame--content">
                        <x-admin.empty-state title="Preview unavailable" minimal />
                    </div>
                @endif
                <div x-ref="panLayer" class="admin-live-preview__pan-layer"
                    x-show="zoom > minZoom && (panMode || spacePan)" x-cloak
                    aria-hidden="true" x-on:pointerdown="startPan($event)"
                    x-on:pointermove="movePan($event)" x-on:pointerup="endPan($event)"
                    x-on:pointercancel="endPan($event)" x-on:wheel.prevent.stop></div>
            </div>
        </div>
        <div class="admin-live-preview__status" role="status" aria-live="polite"
            x-show="snapshotLoading || snapshotError !== '' || horizontalOverflow" x-cloak>
            <span x-show="snapshotLoading">Rendering browser view…</span>
            <span x-show="snapshotError !== ''" x-text="snapshotError"></span>
            <span x-show="!snapshotLoading && !snapshotError && horizontalOverflow">
                Horizontal page overflow detected
            </span>
        </div>
    </div>
</aside>