<div
    class="admin-rich-text-link-dropdown"
    x-show="linkOpen"
    x-cloak
    x-bind:style="`top: ${linkTop}px; left: ${linkLeft}px`"
    x-on:change.stop
    x-on:click.outside="linkOpen = false"
    x-on:keydown.escape.stop.prevent="linkOpen = false"
    role="group"
    aria-label="{{ __('Link') }}"
>
    <div class="admin-rich-text-link-dropdown__form">
        <label class="admin-rich-text-link-dropdown__label">
            {{ __('Link URL') }}
            <input
            x-ref="richLinkUrl"
            x-model="linkUrl"
            x-on:input="linkInvalid = false"
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
                x-on:keydown.enter.prevent="applyLink()"
                type="text"
                class="admin-rich-text-link-dropdown__input"
                placeholder="{{ __('Optional link label') }}"
            />
        </label>
        <label class="admin-rich-text-link-dropdown__checkbox">
            <input x-model="linkNewTab" type="checkbox" />
            <span>{{ __('Open in new tab') }}</span>
        </label>
        <div class="admin-rich-text-link-dropdown__actions">
            <button
                type="button"
                x-show="linkIsExisting"
                x-on:click="removeLink()"
                class="admin-rich-text-link-dropdown__button admin-rich-text-link-dropdown__button--remove"
            >{{ __('Remove link') }}</button>
            <button type="button" x-on:click="linkOpen = false" class="admin-rich-text-link-dropdown__button">
                {{ __('Cancel') }}
            </button>
            <button type="button" x-on:click="applyLink()" class="admin-rich-text-link-dropdown__button admin-rich-text-link-dropdown__button--apply">
                {{ __('Apply') }}
            </button>
        </div>
    </div>
</div>
