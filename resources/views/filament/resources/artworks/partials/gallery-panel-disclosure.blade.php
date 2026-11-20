<button
    type="button"
    class="gallery-stage__panel-disclosure"
    x-on:click.stop="expanded = ! expanded"
    x-bind:aria-expanded="expanded.toString()"
    x-bind:aria-label="expanded ? 'Collapse options' : 'Expand options'"
    title="Expand or collapse options"
>
    <span class="gallery-stage__panel-chevron" x-bind:class="{ 'is-collapsed': !expanded }" aria-hidden="true">▾</span>
</button>
