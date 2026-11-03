<div
    x-ref="richLinkPanel"
    class="admin-rich-text-popover"
    x-show="activeRichPanel === 'link'"
    x-cloak
    x-bind:style="{ top: richPanelTop + 'px', left: richPanelLeft + 'px' }"
    x-on:change.stop
    x-on:click.outside="applyLink(); closeRichPanel('link')"
    x-on:keydown.escape.stop.prevent="closeRichPanel('link')"
    role="group"
    aria-label="{{ __('Link') }}"
>
    @include('filament.schemas.components.rich-text-popover-header', [
        'title' => __('Link'),
        'closeAction' => "closeRichPanel('link')",
        'removeAction' => 'link',
    ])
    <div class="admin-rich-text-popover__form">
        <input
            x-ref="richLinkUrl"
            x-model="linkUrl"
            x-on:input="linkInvalid = false"
            x-on:blur="applyLinkOnExit($event)"
            x-on:keydown.enter.prevent="applyLink()"
            type="text"
            inputmode="url"
            spellcheck="false"
            autocomplete="url"
            placeholder="https://example.com"
            class="admin-form-control"
            aria-label="{{ __('Link URL') }}"
            aria-required="true"
            x-bind:aria-invalid="linkInvalid.toString()"
        />
        <p x-show="linkInvalid" x-cloak class="admin-rich-text-popover__error">
            {{ __('Enter a valid HTTP, HTTPS or email link.') }}
        </p>
        <label class="admin-rich-text-popover__label" x-show="!linkIsExisting && linkSelection?.from === linkSelection?.to">
            {{ __('Link text') }}
            <input
                x-model="linkText"
                x-on:blur="applyLinkOnExit($event)"
                x-on:keydown.enter.prevent="applyLink()"
                type="text"
                class="admin-form-control"
                placeholder="{{ __('Optional link label') }}"
            />
        </label>
        <label class="admin-rich-text-popover__checkbox">
            <input x-model="linkNewTab" type="checkbox" x-on:change.stop="applyLink()" />
            <span>{{ __('Open in new tab') }}</span>
        </label>
    </div>
</div>
