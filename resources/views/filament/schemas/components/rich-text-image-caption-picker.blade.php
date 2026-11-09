<div
    x-ref="richCaptionPanel"
    class="admin-rich-text-popover"
    hidden
    x-bind:hidden="activeRichPanel !== 'caption'"
    x-bind:style="{ top: richPanelTop + 'px', left: richPanelLeft + 'px' }"
    x-on:change.stop
    x-on:click.outside="applyCaption(); closeRichPanel('caption')"
    x-on:keydown.escape.stop.prevent="closeRichPanel('caption')"
    role="group"
    aria-label="{{ __('Image caption') }}"
>
    @include('filament.schemas.components.rich-text-popover-header', [
        'title' => __('Image caption'),
        'closeAction' => "applyCaption(); closeRichPanel('caption')",
    ])
    <div class="admin-rich-text-popover__form">
        <input
            x-ref="richCaptionInput" x-model="captionText"
            x-on:blur="applyCaptionOnExit($event)"
            x-on:keydown.enter.prevent="applyCaption(); closeRichPanel('caption')"
            type="text" maxlength="500" class="admin-form-control"
            aria-label="{{ __('Image caption') }}"
        />
    </div>
</div>
