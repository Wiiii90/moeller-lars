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
    <div class="admin-rich-text-link-dropdown__form">
        <label class="admin-rich-text-link-dropdown__label">
            {{ __('Text color') }}
            <input type="color" x-model="colorValue" class="admin-rich-text-color-input" />
        </label>
        <div class="admin-rich-text-link-dropdown__actions">
            <button type="button" class="admin-rich-text-link-dropdown__button" x-on:click="applyTextColor(true)">
                {{ __('Remove color') }}
            </button>
            <button type="button" class="admin-rich-text-link-dropdown__button admin-rich-text-link-dropdown__button--apply" x-on:click="applyTextColor()">
                {{ __('Apply') }}
            </button>
        </div>
    </div>
</div>
