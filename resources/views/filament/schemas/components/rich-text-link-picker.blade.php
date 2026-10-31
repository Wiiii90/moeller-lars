<div
    x-ref="richLinkPanel"
    class="admin-rich-text-link-dropdown"
    x-show="linkOpen"
    x-cloak
    x-bind:style="`top: ${linkTop}px; left: ${linkLeft}px`"
    x-on:change.stop
    x-on:click.outside="applyLink(); linkOpen = false"
    x-on:keydown.escape.stop.prevent="linkOpen = false"
    role="group"
    aria-label="{{ __('Link') }}"
>
    <div class="admin-rich-text-link-dropdown__header">
        <span>{{ __('Link') }}</span>
        <div class="admin-rich-text-link-dropdown__header-actions">
            <x-filament::icon-button
                icon="heroicon-o-link-slash"
                size="sm"
                color="gray"
                :label="__('Remove link')"
                x-show="linkIsExisting"
                x-on:click.stop="removeLink()"
            />
            <x-filament::icon-button
                icon="heroicon-o-x-mark"
                size="sm"
                color="gray"
                :label="__('Close')"
                x-on:click.stop="linkOpen = false"
            />
        </div>
    </div>
    <div class="admin-rich-text-link-dropdown__form">
        <label class="admin-rich-text-link-dropdown__label">
            {{ __('Link URL') }}
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
                class="admin-rich-text-link-dropdown__input"
                aria-required="true"
                x-bind:aria-invalid="linkInvalid.toString()"
            />
        </label>
        <p x-show="linkInvalid" x-cloak class="admin-rich-text-link-dropdown__error">
            {{ __('Enter a valid HTTP, HTTPS or email link.') }}
        </p>
        <label class="admin-rich-text-link-dropdown__label" x-show="!linkIsExisting && linkSelection?.from === linkSelection?.to">
            {{ __('Link text') }}
            <input
                x-model="linkText"
                x-on:blur="applyLinkOnExit($event)"
                x-on:keydown.enter.prevent="applyLink()"
                type="text"
                class="admin-rich-text-link-dropdown__input"
                placeholder="{{ __('Optional link label') }}"
            />
        </label>
        <label class="admin-rich-text-link-dropdown__checkbox">
            <input x-model="linkNewTab" type="checkbox" x-on:change.stop="applyLink()" />
            <span>{{ __('Open in new tab') }}</span>
        </label>
    </div>
</div>