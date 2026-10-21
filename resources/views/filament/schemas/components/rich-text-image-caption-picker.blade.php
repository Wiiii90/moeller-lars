<div
    x-ref="richCaptionPanel"
    class="admin-rich-text-image-caption-dropdown"
    x-show="captionOpen"
    x-cloak
    x-bind:style="`top: ${captionTop}px; left: ${captionLeft}px`"
    x-on:change.stop
    x-on:keydown.escape.stop.prevent="captionOpen = false"
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
            <button type="button" x-on:click="captionOpen = false" class="admin-rich-text-link-dropdown__button">
                {{ __('Cancel') }}
            </button>
            <button type="button" x-on:click="applyCaption()" class="admin-rich-text-link-dropdown__button admin-rich-text-link-dropdown__button--apply">
                {{ __('Apply') }}
            </button>
        </div>
    </div>
</div>
