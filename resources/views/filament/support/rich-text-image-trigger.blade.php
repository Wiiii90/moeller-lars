<button
    class="admin-action admin-rich-text-image-trigger"
    type="button"
    x-on:click="open = ! open; source = 'media'; externalError = ''"
    x-bind:aria-expanded="open.toString()"
    aria-label="Insert image from Storage or URL"
>
    Insert image
</button>
