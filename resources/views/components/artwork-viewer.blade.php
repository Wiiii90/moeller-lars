<dialog
    id="artwork-viewer"
    class="artwork-viewer"
    data-artwork-viewer
    aria-labelledby="artwork-viewer-title"
>
    <div class="artwork-viewer__surface">
        <div class="artwork-viewer__topbar" aria-label="Artwork viewer actions">
            <a
                class="artwork-viewer__icon-button artwork-viewer__page-link"
                data-viewer-page-link
                aria-label="Open artwork page"
                title="Open artwork page"
            >↗</a>
            <button
                type="button"
                class="artwork-viewer__icon-button artwork-viewer__close"
                data-viewer-close
                aria-label="Close artwork viewer"
                title="Close"
            >×</button>
        </div>

        <div class="artwork-viewer__stage" data-viewer-stage>
            <div class="artwork-viewer__loading" data-viewer-loading role="status" aria-live="polite">Loading media…</div>
            <img class="artwork-viewer__image" data-viewer-image alt="" draggable="false" hidden>
            <video class="artwork-viewer__video" data-viewer-video controls playsinline preload="metadata" hidden></video>
            <div class="artwork-viewer__missing" data-viewer-missing role="img" aria-label="Media unavailable" hidden>Media unavailable</div>
        </div>

        <div class="artwork-viewer__controls" aria-label="Artwork viewer controls">
            <button type="button" data-viewer-previous aria-label="Previous artwork" title="Previous artwork">‹</button>
            <button type="button" data-viewer-zoom-out aria-label="Zoom out" title="Zoom out">−</button>
            <button type="button" class="artwork-viewer__reset" data-viewer-reset aria-label="Reset zoom" title="Reset zoom">100%</button>
            <button type="button" data-viewer-zoom-in aria-label="Zoom in" title="Zoom in">+</button>
            <button type="button" data-viewer-next aria-label="Next artwork" title="Next artwork">›</button>
        </div>

        <p id="artwork-viewer-title" class="artwork-viewer__title" data-viewer-title aria-live="polite"></p>
    </div>
</dialog>
