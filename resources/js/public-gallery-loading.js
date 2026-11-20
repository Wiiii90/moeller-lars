// Gallery Artwork cards carry one native lazy-loaded src. Server batches
// limit initial markup; no second image-fetch queue or <noscript> duplicate.
/* The shared fitted-media owner. Both a Gallery's individual Artwork and
   Home's currently visible Hero slide use the same measured width/height
   budget, so captions and bottom padding cannot diverge between templates. */
function fittedMediaWidth(page, owner, availableWidth, sourceWidth, sourceHeight, footer, controls = null) {
    const viewport = window.visualViewport;
    const viewportHeight = viewport?.height ?? window.innerHeight;
    const pageStyle = getComputedStyle(page);
    const outerHeight = (element) => {
        if (!element) return 0;
        const style = getComputedStyle(element);
        if (style.display === 'none') return 0;
        return element.getBoundingClientRect().height
            + (Number.parseFloat(style.marginTop) || 0)
            + (Number.parseFloat(style.marginBottom) || 0);
    };
    // The media group may overflow during its initial fit and move its own
    // rectangle. The Page top plus visible heading/folio heights is stable.
    const heading = page.querySelector(':scope > .public-content-reveal > .public-page-heading');
    const folio = owner.querySelector('.artwork-folio');
    const stageStart = page.getBoundingClientRect().top
        + (Number.parseFloat(pageStyle.paddingTop) || 0)
        + outerHeight(heading) + outerHeight(folio);
    const imageTop = Math.max(0, stageStart - (viewport?.offsetTop ?? 0));
    /* The Content's real bottom margin supplies the ONE external shell
       gap. Do not subtract that gap again at the frame level. */
    const reserveBelow = outerHeight(footer) + outerHeight(controls)
        + (Number.parseFloat(pageStyle.paddingBottom) || 0)
        + (Number.parseFloat(pageStyle.marginBottom) || 0);
    const remaining = viewportHeight - imageTop - reserveBelow;
    // Exceptionally long Artwork information may scroll below the Page.
    // Never shrink the image toward zero through a feedback loop where its
    // narrower caption track creates still more wrapped metadata lines.
    const minimumMediaHeight = Math.min(240, viewportHeight * .3);
    const heightForImage = Math.max(
        minimumMediaHeight, remaining > 0 ? remaining : viewportHeight - reserveBelow,
    );
    return Math.max(1, Math.floor(Math.min(availableWidth, heightForImage * sourceWidth / sourceHeight)));
}

function fittedStyleRule() {
    const stylesheet = [...document.querySelectorAll('style[data-public-layout-style]')].at(-1)?.sheet;
    return [...(stylesheet?.cssRules ?? [])].find((rule) => rule.selectorText === ':root')?.style ?? null;
}

// The same Artwork image lifecycle also resolves the short-lived native ALT
// placeholder on detail routes. The server owns the pending state so the
// placeholder is hidden before JavaScript starts, including during SPA swaps.
function revealDecodedImage(image, key, signal) {
    const source = image.currentSrc || image.src;
    const done = () => {
        if (signal.aborted || (image.currentSrc || image.src) !== source) return;
        image.dataset[key] = image.naturalWidth > 0 ? 'ready' : 'error';
    };
    if (image.naturalWidth <= 0 || typeof image.decode !== 'function') {
        done();
        return null;
    }
    try {
        // Callers can keep a speculative request's budget occupied until
        // the actual image is ready to paint, not merely until bytes arrive.
        return image.decode().then(done, done);
    } catch {
        done();
        return null;
    }
}

function initializeArtworkDetailImage(root) {
    const image = root.querySelector?.('.artwork-detail__image[data-artwork-image-state="pending"]');
    if (!image) return () => {};

    const lifetime = new AbortController();
    const page = image.closest('#content');
    const detail = image.closest('.artwork-detail');
    const stage = detail?.querySelector('.artwork-detail__image-stage');
    const sourceWidth = Number(image.getAttribute('width'));
    const sourceHeight = Number(image.getAttribute('height'));
    const fitStyle = fittedStyleRule();
    let frameRequest = 0;

    const clearFit = () => {
        fitStyle?.removeProperty('--public-fitted-image-width');
        // Retire the previous fitted-paper override on SPA transitions.
        fitStyle?.removeProperty('--public-fitted-paper-width');
    };

    const fit = () => {
        if (lifetime.signal.aborted || !fitStyle || !page || !detail || !stage
            || sourceWidth <= 0 || sourceHeight <= 0) return;

        const fittedWidth = fittedMediaWidth(
            page, detail, Math.max(1, stage.getBoundingClientRect().width),
            sourceWidth, sourceHeight, detail.querySelector('.artwork-detail__footer'),
        );
        fitStyle.setProperty('--public-fitted-image-width', Math.max(1, Math.floor(fittedWidth)) + 'px');
        fitStyle.removeProperty('--public-fitted-paper-width');
    };

    const scheduleFit = () => {
        if (frameRequest) cancelAnimationFrame(frameRequest);
        frameRequest = requestAnimationFrame(() => {
            frameRequest = 0;
            fit();
        });
    };

    if (page?.classList.contains('site-content--fitted-artwork') && fitStyle) {
        fit();
        window.addEventListener('resize', scheduleFit, { passive: true, signal: lifetime.signal });
        // The navigation controller may settle its height after page setup.
        scheduleFit();
        document.fonts?.ready?.then(() => {
            if (!lifetime.signal.aborted) scheduleFit();
        });
    }

    const ready = () => revealDecodedImage(image, 'artworkImageState', lifetime.signal);
    const failed = () => {
        image.dataset.artworkImageState = 'error';
    };

    image.addEventListener('load', ready, { signal: lifetime.signal });
    image.addEventListener('error', failed, { signal: lifetime.signal });
    if (image.complete) ready();

    return () => {
        lifetime.abort();
        if (frameRequest) cancelAnimationFrame(frameRequest);
        clearFit();
    };
}

/* Home switches among already-present slides. Fit only the active member
   using exactly the same General content/viewport/metadata calculation as
   an individual Artwork. No inline style attributes or extra style owners. */
function initializeGalleryHeroFitting(root) {
    const page = root.querySelector?.('#content.site-content--gallery-hero-fitted');
    const group = page?.querySelector('.gallery-hero-presentation');
    const fitStyle = fittedStyleRule();
    if (!page || !group || !fitStyle) return () => {};

    const lifetime = new AbortController();
    let frameRequest = 0;
    const fit = () => {
        if (lifetime.signal.aborted) return;
        const active = [...group.querySelectorAll(':scope > [data-gallery-hero-slide]')]
            .find((slide) => !slide.hidden);
        const owner = active?.querySelector('.artwork-card');
        const media = owner?.querySelector('.artwork-card__composition--gallery-hero :is(.artwork-card__image, .artwork-card__video)');
        if (!owner || !media) {
            fitStyle.removeProperty('--public-fitted-image-width');
            return;
        }

        const width = Number(media.getAttribute('width')) || Number(media.naturalWidth)
            || Number(media.videoWidth) || 0;
        const height = Number(media.getAttribute('height')) || Number(media.naturalHeight)
            || Number(media.videoHeight) || 0;
        if (width <= 0 || height <= 0) {
            fitStyle.removeProperty('--public-fitted-image-width');
            return;
        }
        const fittedWidth = fittedMediaWidth(
            page, owner, Math.max(1, owner.getBoundingClientRect().width),
            width, height, owner.querySelector('.artwork-card__footer'),
            owner.querySelector('.public-video__controls'),
        );
        fitStyle.setProperty('--public-fitted-image-width', fittedWidth + 'px');
    };
    const schedule = () => {
        if (frameRequest) cancelAnimationFrame(frameRequest);
        frameRequest = requestAnimationFrame(() => {
            frameRequest = 0;
            fit();
        });
    };

    fit();
    window.addEventListener('resize', schedule, { passive: true, signal: lifetime.signal });
    group.addEventListener('gallery-hero-change', schedule, { signal: lifetime.signal });
    group.addEventListener('load', schedule, { capture: true, signal: lifetime.signal });
    group.addEventListener('loadedmetadata', schedule, { capture: true, signal: lifetime.signal });
    // A media-width change can wrap long captions into additional lines.
    // Re-measure their real height without adding a separate fitting owner.
    const footerObserver = typeof ResizeObserver === 'function'
        ? new ResizeObserver(schedule) : null;
    group.querySelectorAll('.artwork-card__footer, .public-video__controls').forEach((element) => {
        footerObserver?.observe(element);
    });
    document.fonts?.ready?.then(() => {
        if (!lifetime.signal.aborted) schedule();
    });
    return () => {
        lifetime.abort();
        footerObserver?.disconnect();
        if (frameRequest) cancelAnimationFrame(frameRequest);
        fitStyle.removeProperty('--public-fitted-image-width');
    };
}

export function initializeGalleryImageLoading(root = document) {
    const disposeDetail = initializeArtworkDetailImage(root);
    const disposeGalleryHero = initializeGalleryHeroFitting(root);
    const disposeFitted = () => {
        disposeDetail();
        disposeGalleryHero();
    };
    const images = new Set(root.querySelectorAll('.artwork-card__image'));
    if (images.size === 0 && !root.querySelector?.('[data-gallery-infinite]')) return disposeFitted;

    const lifetime = new AbortController();
    const settled = (event) => {
        const image = event.target;
        if (lifetime.signal.aborted || !images.has(image)) return;
        if (event.type === 'error') {
            // Preserve the canonical ALT text on a genuine media failure.
            image.dataset.galleryImageState = 'error';
        } else {
            revealDecodedImage(image, 'galleryImageState', lifetime.signal);
        }
    };

    // Every image already has its canonical src. Hide native ALT rendering
    // until decoding finishes while intrinsic dimensions reserve its space.
    root.addEventListener('load', settled, { capture: true, signal: lifetime.signal });
    root.addEventListener('error', settled, { capture: true, signal: lifetime.signal });

    const track = (image) => {
        if (images.has(image) && image.dataset.galleryImageState === 'ready') return;
        images.add(image);
        if (image.src && image.complete) {
            revealDecodedImage(image, 'galleryImageState', lifetime.signal);
        }
    };
    images.forEach(track);

    // Server-rendered batches enter the same decode/error lifecycle.
    // Browser-native loading="lazy" also works when JavaScript is disabled.
    root.addEventListener('gallery:items-appended', (event) => {
        for (const element of event.detail.elements) {
            element.querySelectorAll('.artwork-card__image').forEach(track);
        }
    }, { signal: lifetime.signal });

    return () => {
        lifetime.abort();
        disposeFitted();
    };
}
