<div
    x-ref="richColorPanel"
    class="admin-rich-text-link-dropdown"
    x-show="colorOpen"
    x-cloak
    x-on:change.stop
    x-bind:style="`top: ${colorTop}px; left: ${colorLeft}px`"
    x-on:click.outside="colorOpen = false"
    x-on:keydown.escape.stop.prevent="colorOpen = false"
    role="group"
    aria-label="{{ __('Text color') }}"
>
    <div class="admin-rich-text-link-dropdown__header">
        <span>{{ __('Text color') }}</span>
        <div class="admin-rich-text-link-dropdown__header-actions">
            <x-filament::icon-button
                icon="heroicon-o-no-symbol"
                size="sm"
                color="gray"
                :label="__('Remove color')"
                x-on:click.stop="applyTextColor(true)"
            />
            <x-filament::icon-button
                icon="heroicon-o-x-mark"
                size="sm"
                color="gray"
                :label="__('Close')"
                x-on:click.stop="colorOpen = false"
            />
        </div>
    </div>
    <div class="admin-rich-text-color-picker">
        <input
            type="color"
            x-model="colorValue"
            x-on:change.stop="applyTextColor()"
            class="admin-rich-text-color-picker__swatch"
            aria-label="{{ __('Choose text color') }}"
        />
        <label class="admin-rich-text-link-dropdown__label admin-rich-text-color-picker__value">
            {{ __('Hex color') }}
            <input
                type="text"
                x-model="colorValue"
                x-on:change.stop="applyTextColor()"
                x-on:keydown.enter.prevent="applyTextColor()"
                spellcheck="false"
                maxlength="7"
                placeholder="#333333"
                class="admin-rich-text-link-dropdown__input"
            />
        </label>
    </div>
</div>