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
            {{-- A real single-select uses the shared Admin native picker grammar.
                 The device glyph decorates its trigger without intercepting input. --}}
            <div class="admin-live-preview__device-picker">
                <select class="admin-live-preview__device-select"
                    x-model="selectedProfile"
                    x-on:change="selectProfile($event.target.value)"
                    x-bind:aria-label="'Device and viewport resolution: ' + selectedProfileLabel()"
                    x-bind:title="selectedProfileLabel()">
                    @foreach (['desktop' => 'Computers', 'tablet' => 'Tablets', 'mobile' => 'Phones'] as $category => $label)
                        <optgroup label="{{ $label }}">
                            {{-- Native optgroup labels remain available in classic browsers.
                                 Customizable Select renders this real legend for styling. --}}
                            <legend>{{ $label }}</legend>
                            @foreach (\App\Domain\Content\PreviewDeviceProfiles::all() as $id => $profile)
                                @if ($profile['category'] === $category)
                                    <option value="{{ $id }}">{{ $profile['label'] }}</option>
                                @endif
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                <span class="admin-live-preview__device-glyph" aria-hidden="true">
                    <x-filament::icon :icon="\App\Filament\Support\AdminIcon::DeviceDesktop->mini()"
                        x-show="previewDevice === 'desktop'" x-cloak />
                    <x-filament::icon icon="heroicon-m-device-tablet"
                        x-show="previewDevice === 'tablet'" x-cloak />
                    <x-filament::icon :icon="\App\Filament\Support\AdminIcon::DeviceMobile->mini()"
                        x-show="previewDevice === 'mobile'" x-cloak />
                </span>
            </div>
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
        x-on:pointerleave="pointerInside = false; if (!dragging) cancelSpacePan()"
        x-on:pointerdown.capture="startPan($event)"
        x-on:auxclick="if (zoom > minZoom && $event.button === 1) $event.preventDefault()"
        x-on:touchstart="startTouchPan($event)"
        x-on:touchmove="moveTouchPan($event)"
        x-on:touchend="endTouchPan($event)"
        x-on:touchcancel="endTouchPan($event)">
        <span class="admin-live-preview__issue" x-show="horizontalOverflow" x-cloak
            title="The page is wider than the selected device width" aria-label="Horizontal page overflow" role="status">
            <x-filament::icon icon="heroicon-o-exclamation-circle" />
        </span>
        <div x-ref="surface" wire:ignore.self class="admin-live-preview__surface">
            <div x-ref="page" wire:ignore.self class="admin-live-preview__page"
                x-bind:class="{
                    'is-zoomed': zoom > minZoom,
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
                    x-show="zoom > minZoom && (dragging || spacePan)" x-cloak
                    aria-hidden="true"></div>
            </div>
        </div>
        <span class="admin-live-preview__sr-only" role="status" aria-live="polite"
            x-text="horizontalOverflow ? 'Horizontal page overflow detected' : ''"></span>
    </div>
</aside>