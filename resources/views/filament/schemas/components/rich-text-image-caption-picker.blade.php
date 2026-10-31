<div
    x-ref="richCaptionPanel"
    class="admin-rich-text-image-caption-dropdown"
    x-show="activeRichPanel === 'caption'"
    x-cloak
    x-bind:style="`top: ${richPanelTop}px; left: ${richPanelLeft}px`"
    x-on:change.stop
    x-on:click.outside="closeRichPanel('caption')"
    x-on:keydown.escape.stop.prevent="closeRichPanel('caption')"
    role="group"
    aria-label="{{ __('Image caption') }}"
>
    <div class="admin-rich-text-link-dropdown__form">
        <label class="admin-rich-text-link-dropdown__label">
            {{ __('Image caption') }}
            <input
                x-ref="richCaptionInput"
                x-model="captionText"
                x-on:keydown.enter.prevent="applyCaption()"
                type="text"
                maxlength="500"
                class="admin-rich-text-link-dropdown__input"
            />
        </label>
        <div class="admin-rich-text-link-dropdown__actions">
            <button type="button" x-on:click="closeRichPanel('caption')" class="admin-rich-text-link-dropdown__button">
                {{ __('Cancel') }}
            </button>
            <button type="button" x-on:click="applyCaption()" class="admin-rich-text-link-dropdown__button admin-rich-text-link-dropdown__button--apply">
                {{ __('Apply') }}
            </button>
        </div>
    </div>
</div>
