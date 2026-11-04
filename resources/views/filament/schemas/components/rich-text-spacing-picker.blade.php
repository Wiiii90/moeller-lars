<div
    x-ref="richSpacingPanel"
    class="admin-rich-text-popover"
    hidden
    x-bind:hidden="activeRichPanel !== 'spacing'"
    x-on:change.stop
    x-bind:style="{ top: richPanelTop + 'px', left: richPanelLeft + 'px' }"
    x-on:click.outside="closeRichPanel('spacing')"
    x-on:keydown.escape.stop.prevent="closeRichPanel('spacing')"
    role="group"
    tabindex="-1"
    aria-label="{{ __('Line spacing') }}"
>
    @include('filament.schemas.components.rich-text-popover-header', [
        'title' => __('Line spacing'),
        'closeAction' => "closeRichPanel('spacing')",
    ])
    <div class="admin-rich-text-popover__form">
        <select x-model="spacingValue" class="admin-form-control"
            aria-label="{{ __('Line spacing') }}" x-on:change="applySpacing()">
            <option value="1.0">1,0</option>
            <option value="1.15">1,15</option>
            <option value="1.5">1,5</option>
            <option value="2.0">2,0</option>
        </select>
    </div>
</div>
