{{-- The single out-of-flow panel owner; the Storage Select remains a native Filament child schema. --}}
<div class="admin-rich-text-panel-layer">
    @include('filament.schemas.components.rich-text-link-picker')
    @include('filament.schemas.components.rich-text-color-picker')
    @include('filament.schemas.components.rich-text-spacing-picker')

    @if ($allowEmbeddedMedia)
        @include('filament.schemas.components.rich-text-image-caption-picker')
        <div
            x-ref="richImagePicker"
            data-rich-text-popover-key="{{ $getKey() }}"
            class="admin-rich-text-popover admin-rich-text-popover--storage"
            hidden
            x-bind:hidden="activeRichPanel !== 'image'"
            x-bind:style="{ top: richPanelTop + 'px', left: richPanelLeft + 'px' }"
            x-on:change.stop
            x-on:click.outside="closeRichPanel('image')"
            x-on:keydown.escape.capture.stop.prevent="closeRichPanel('image')"
            role="group"
            aria-label="{{ __('Image from Storage') }}"
        >
            @include('filament.schemas.components.rich-text-popover-header', [
                'title' => __('Image from Storage'),
                'closeAction' => "closeRichPanel('image')",
            ])
            {{ $getChildSchema() }}
        </div>
    @endif
</div>
