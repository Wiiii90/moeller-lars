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
        <div class="admin-stage-tool-group admin-live-preview__device-profile" role="group" aria-label="Device preview">
            <x-filament::dropdown placement="bottom-start">
                <x-slot name="trigger">
                    <button class="admin-action admin-stage-tool-icon" type="button"
                        x-bind:title="'Device: ' + selectedProfileLabel()"
                        x-bind:aria-label="'Choose preview device, current: ' + selectedProfileLabel()">
                        <x-filament::icon :icon="\App\Filament\Support\AdminIcon::DeviceDesktop->mini()"
                            x-show="previewDevice === 'desktop'" x-cloak aria-hidden="true" />
                        <x-filament::icon icon="heroicon-m-device-tablet"
                            x-show="previewDevice === 'tablet'" x-cloak aria-hidden="true" />
                        <x-filament::icon :icon="\App\Filament\Support\AdminIcon::DeviceMobile->mini()"
                            x-show="previewDevice === 'mobile'" x-cloak aria-hidden="true" />
                    </button>
                </x-slot>
                <x-filament::dropdown.list>
                    @foreach (\App\Domain\Content\PreviewDeviceProfiles::all() as $id => $profile)
                        <x-filament::dropdown.list.item
                            x-on:click="selectProfile(@js($id))"
                            x-bind:aria-current="selectedProfile === @js($id) ? 'true' : null"
                        >{{ $profile['label'] }}</x-filament::dropdown.list.item>
                    @endforeach
                </x-filament::dropdown.list>
            </x-filament::dropdown>
            <button class="admin-action admin-stage-tool-icon" type="button"
                x-on:click="rotateViewport()"
                x-bind:disabled="selectedProfile === 'current-window'"
                x-bind:aria-label="selectedProfile === 'current-window' ? 'Current window orientation' : (rotated ? 'Restore device orientation' : 'Rotate device')"
                x-bind:title="selectedProfile === 'current-window' ? 'Uses current window orientation' : (rotated ? 'Restore orientation' : 'Rotate device')">
                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Refresh->mini()" />
            </button>
        </div>
        <div class="admin-stage-tool-group admin-live-preview__zoom-group" role="group" aria-label="Preview zoom">
            <button class="admin-action admin-stage-tool-icon" type="button"
                x-on:click="changeZoom(-0.25)" x-bind:disabled="zoom <= minZoom"
                title="Zoom out" aria-label="Zoom out preview">
                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::PreviewZoomOut->mini()" />
            </button>
            <button class="admin-live-preview__zoom-value" type="button"
                x-on:click="resetZoom()" x-bind:disabled="zoom <= minZoom"
                x-bind:class="{ 'is-active': zoom > minZoom }"
                title="Reset to fitted preview (100%)" aria-label="Reset zoom to fitted size" x-text="zoomPercent()"></button>
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
            @isset($focusAction)
                {{ $focusAction }}
            @endisset
            @if (filled($url))
                <a class="admin-action admin-stage-tool-icon" href="{{ $url }}"
                    x-bind:href="currentUrl || '{{ $url }}'"
                    target="_blank" rel="noopener noreferrer"
                    title="Open preview" aria-label="Open preview">
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
        x-on:pointerleave="pointerInside = false; cancelSpacePan()">
        <span class="admin-live-preview__issue" x-show="horizontalOverflow" x-cloak
            title="The page is wider than the selected device width" aria-label="Horizontal page overflow" role="status">
            <x-filament::icon icon="heroicon-o-exclamation-circle" />
        </span>
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
                        src="{{ $url }}" title="{{ $frameTitle }}"
                        loading="eager" tabindex="0" allowfullscreen
                        x-on:load="$nextTick(() => onFrameLoad())"></iframe>
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
        <span class="admin-live-preview__sr-only" role="status" aria-live="polite"
            x-text="horizontalOverflow ? 'Horizontal page overflow detected' : ''"></span>
    </div>
</aside>