<div class="admin-rich-text-image-insert__content">
    <div class="admin-rich-text-image-insert__sources" role="tablist" aria-label="Image source">
        <button
            class="admin-rich-text-image-insert__source"
            type="button"
            role="tab"
            data-rich-text-source="media"
            x-bind:class="{ 'is-active': source === 'media' }"
            x-bind:aria-selected="source === 'media'"
            x-on:click="source = 'media'; externalError = ''; $nextTick(() => $el.closest('[data-admin-rich-text-image-insert]').querySelector('[role=combobox]')?.focus())"
        >Media Files</button>
        <button
            class="admin-rich-text-image-insert__source"
            type="button"
            role="tab"
            data-rich-text-source="external"
            x-bind:class="{ 'is-active': source === 'external' }"
            x-bind:aria-selected="source === 'external'"
            x-on:click="source = 'external'; externalError = ''; $nextTick(() => $el.closest('[data-admin-rich-text-image-insert]').querySelector('[data-admin-rich-text-external-url]')?.focus())"
        >External URL</button>
    </div>

    <div class="admin-rich-text-image-insert__external" x-show="source === 'external'">
        <span class="admin-rich-text-image-insert__external-label">External URL</span>
        <div class="admin-rich-text-image-insert__external-row">
            <input
                class="fi-input admin-rich-text-image-insert__external-input"
                type="url"
                data-admin-rich-text-external-url
                x-model="externalUrl"
                x-on:input="externalError = ''"
                x-on:keydown.enter.prevent="submitExternal($el)"
                placeholder="https://example.com/image.jpg"
                inputmode="url"
                autocomplete="off"
                spellcheck="false"
                aria-label="External image URL"
            >
            <button class="admin-action" type="button" x-on:click="submitExternal($el)">Insert</button>
        </div>
        <p
            class="admin-rich-text-image-insert__error"
            x-show="externalError !== ''"
            x-text="externalError"
            role="alert"
        ></p>
    </div>
</div>
