<div
    x-ref="richSpacingPanel"
    class="admin-rich-text-link-dropdown"
    x-show="spacingOpen"
    x-cloak
    x-on:change.stop
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
                <option value="1.0">1,0</option>
                <option value="1.15">1,15</option>
                <option value="1.5">1,5</option>
                <option value="2.0">2,0</option>
            </select>
        </label>
    </div>
</div>
