<div
    x-ref="richSpacingPanel"
    class="admin-rich-text-link-dropdown"
    x-show="spacingOpen"
    x-cloak
    x-bind:style="`top: ${spacingTop}px; left: ${spacingLeft}px`"
    x-on:click.outside="spacingOpen = false"
    x-on:keydown.escape.stop.prevent="spacingOpen = false"
    role="group"
    aria-label="{{ __('Line spacing') }}"
>
    <div class="admin-rich-text-link-dropdown__form">
        <label class="admin-rich-text-link-dropdown__label">
            {{ __('Line spacing') }}
            <select x-model="spacingValue" class="admin-rich-text-link-dropdown__input" x-on:change="applySpacing()">
                <option value="default">{{ __('Default') }}</option>
                <option value="none">{{ __('No spacing') }}</option>
                <option value="compact">{{ __('Compact') }}</option>
                <option value="relaxed">{{ __('Relaxed') }}</option>
            </select>
        </label>
    </div>
</div>
