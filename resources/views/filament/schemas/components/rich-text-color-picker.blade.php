<div
    x-ref="richColorPanel"
    class="admin-rich-text-popover"
    hidden
    x-bind:hidden="activeRichPanel !== 'color'"
    x-on:change.stop
    x-bind:style="{ top: richPanelTop + 'px', left: richPanelLeft + 'px' }"
    x-on:click.outside="closeRichPanel('color')"
    x-on:keydown.escape.stop.prevent="closeRichPanel('color')"
    role="group"
    tabindex="-1"
    aria-label="{{ __('Text color') }}"
>
    @include('filament.schemas.components.rich-text-popover-header', [
        'title' => __('Text color'),
        'closeAction' => "closeRichPanel('color')",
        'removeAction' => 'color',
    ])
    <div class="admin-rich-text-color-picker">
        <input
            type="color"
            x-model="colorValue"
            x-on:change.stop="applyTextColor()"
            class="admin-rich-text-color-picker__swatch"
            aria-label="{{ __('Choose text color') }}"
        />
        <label class="admin-rich-text-popover__label admin-rich-text-color-picker__value">
            {{ __('Hex color') }}
            <input type="text" x-model="colorValue"
                x-on:change.stop="applyTextColor()"
                x-on:keydown.enter.prevent="applyTextColor()"
                spellcheck="false" maxlength="7" placeholder="#333333"
                class="admin-form-control" />
        </label>
    </div>
</div>
