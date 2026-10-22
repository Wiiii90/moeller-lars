<dialog
    id="artwork-viewer"
    class="artwork-viewer"
    data-artwork-viewer
    aria-labelledby="artwork-viewer-title"
>
    <div class="artwork-viewer__surface">
        <div class="artwork-viewer__topbar" aria-label="Media viewer actions">
            <a
                class="artwork-viewer__icon-button artwork-viewer__page-link"
                data-viewer-page-link
                aria-label="Open artwork page"
                title="Open artwork page"
            ><x-public-icon name="arrow-right" /></a>
            <button
                type="button"
                class="artwork-viewer__icon-button artwork-viewer__close"
                data-viewer-close
                aria-label="Close media viewer"
                title="Close"
            ><x-public-icon name="close" /></button>
        </div>

        <div class="artwork-viewer__stage" data-viewer-stage>
            <div class="artwork-viewer__loading" data-viewer-loading role="status" aria-live="polite">Loading media…</div>
            <img class="artwork-viewer__image" data-viewer-image alt="" draggable="false" hidden>
            <video class="artwork-viewer__video" data-viewer-video controls playsinline preload="metadata" hidden></video>
            <div class="artwork-viewer__missing" data-viewer-missing role="img" aria-label="Media unavailable" hidden>Media unavailable</div>
        </div>

        <div class="artwork-viewer__controls" aria-label="Artwork viewer controls">
            <button type="button" data-viewer-previous aria-label="Previous media" title="Previous media"><x-public-icon name="chevron-left" /></button>
            <button type="button" data-viewer-zoom-out aria-label="Zoom out" title="Zoom out"><x-public-icon name="minus" /></button>
            <button type="button" class="artwork-viewer__reset" data-viewer-reset aria-label="Reset zoom" title="Reset zoom">100%</button>
            <button type="button" data-viewer-zoom-in aria-label="Zoom in" title="Zoom in"><x-public-icon name="plus" /></button>
            <button type="button" data-viewer-next aria-label="Next media" title="Next media"><x-public-icon name="chevron-right" /></button>
        </div>

        <p id="artwork-viewer-title" class="artwork-viewer__title" data-viewer-title aria-live="polite"></p>
    </div>
</dialog>
