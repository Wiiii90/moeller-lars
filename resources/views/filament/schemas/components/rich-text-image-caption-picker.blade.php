<div
    x-ref="richCaptionPanel"
    class="admin-rich-text-link-dropdown"
    x-show="activeRichPanel === 'caption'"
    x-cloak
    x-bind:style="{ top: richPanelTop + 'px', left: richPanelLeft + 'px' }"
    x-on:change.stop
    x-on:click.outside="applyCaption(); closeRichPanel('caption')"
    x-on:keydown.escape.stop.prevent="closeRichPanel('caption')"
    role="group"
    aria-label="{{ __('Image caption') }}"
>
    <div class="admin-rich-text-link-dropdown__header">
        <span>{{ __('Image caption') }}</span>
        <div class="admin-rich-text-link-dropdown__header-actions">
            <x-filament::icon-button
                icon="heroicon-o-x-mark"
                size="sm"
                color="gray"
                :label="__('Close')"
                x-on:click.stop="applyCaption(); closeRichPanel('caption')"
            />
        </div>
    </div>
    <div class="admin-rich-text-link-dropdown__form">
        <label class="admin-rich-text-link-dropdown__label">
            {{ __('Image caption') }}
            <input
                x-ref="richCaptionInput"
                x-model="captionText"
                x-on:blur="applyCaptionOnExit($event)"
                x-on:keydown.enter.prevent="applyCaption(); closeRichPanel('caption')"
                type="text"
                maxlength="500"
                class="admin-rich-text-link-dropdown__input"
            />
        </label>
    </div>
</div>
