<dialog
    id="artwork-viewer"
    class="artwork-viewer"
    data-artwork-viewer
    aria-labelledby="artwork-viewer-title"
>
    <div class="artwork-viewer__surface">
        <div class="artwork-viewer__topbar">
            <a class="artwork-viewer__page-link" data-viewer-page-link>Open artwork page</a>
            <button type="button" class="artwork-viewer__close" data-viewer-close aria-label="Close artwork viewer">×</button>
        </div>
        <div class="artwork-viewer__stage" data-viewer-stage>
            <div class="artwork-viewer__loading" data-viewer-loading role="status" aria-live="polite">Loading image…</div>
            <img class="artwork-viewer__image" data-viewer-image alt="" draggable="false" hidden>
            <div class="artwork-viewer__missing" data-viewer-missing role="img" aria-label="Media unavailable" hidden>Media unavailable</div>
        </div>
        <div class="artwork-viewer__controls" aria-label="Artwork viewer controls">
            <button type="button" data-viewer-previous aria-label="Previous artwork">Previous</button>
            <button type="button" data-viewer-zoom-out aria-label="Zoom out">−</button>
            <button type="button" data-viewer-reset aria-label="Reset zoom">100%</button>
            <button type="button" data-viewer-zoom-in aria-label="Zoom in">+</button>
            <button type="button" data-viewer-next aria-label="Next artwork">Next</button>
        </div>
        <p id="artwork-viewer-title" class="artwork-viewer__title" data-viewer-title aria-live="polite"></p>
    </div>
</dialog>
