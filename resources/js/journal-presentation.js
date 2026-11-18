// The two authored moving-gallery presentations share one page-local
// navigation owner. The fixed CSS stage never derives height from the active
// image, so portrait/landscape changes cannot move controls or following text.
function initializeSteppedGallery(root) {
    const slides = Array.from(root.querySelectorAll('[data-journal-slide]'));
    const isCarousel = root.hasAttribute('data-journal-carousel');
    const previous = root.querySelector('[data-journal-slide-prev]');
    const next = root.querySelector('[data-journal-slide-next]');
    const status = root.querySelector('[data-journal-slide-status]');
    let index = 0;
    let lastDirection = 'next';

    // Prepare only adjacent image assets. Hidden lazy images otherwise wait
    // until navigation, briefly presenting an empty frame on fast switches.
    const prepareNeighbors = () => {
        if (slides.length === 0) return;
        const nearby = new Set([
            index,
            (index + 1) % slides.length,
            (index - 1 + slides.length) % slides.length,
        ]);
        nearby.forEach((position) => {
            slides[position].querySelectorAll('img[loading="lazy"]').forEach((image) => {
                image.loading = 'eager';
            });
        });
    };

    const render = () => {
        prepareNeighbors();
        slides.forEach((slide, position) => {
            const active = position === index;
            const forward = (position - index + slides.length) % slides.length;
            const backward = (index - position + slides.length) % slides.length;
            let slot = active ? 'active' : null;
            if (isCarousel && slides.length > 1 && !active) {
                // With only two real slides the same item is both adjacent
                // neighbors. Show it once, on the last navigated side.
                if (slides.length === 2) {
                    slot = lastDirection;
                } else if (forward === 1) {
                    slot = 'next';
                } else if (backward === 1) {
                    slot = 'previous';
                }
            }

            slide.hidden = slot === null;
            slide.setAttribute('aria-hidden', active ? 'false' : 'true');
            slide.inert = !active;
            if (slot !== null) slide.dataset.journalSlidePosition = slot;
            else delete slide.dataset.journalSlidePosition;
            if (!active) slide.querySelectorAll('video').forEach((video) => video.pause());
        });
        if (status) status.textContent = `${index + 1} / ${slides.length}`;
        // Reconcile the existing Public playback owner immediately when a
        // previously peeked/hidden video becomes the active frame.
        root.dispatchEvent(new Event('journal:gallery-change', { bubbles: true }));
    };

    const goPrevious = () => {
        lastDirection = 'previous';
        index = (index - 1 + slides.length) % slides.length;
        render();
    };
    const goNext = () => {
        lastDirection = 'next';
        index = (index + 1) % slides.length;
        render();
    };

    previous?.addEventListener('click', goPrevious);
    next?.addEventListener('click', goNext);
    render();

    return () => {
        previous?.removeEventListener('click', goPrevious);
        next?.removeEventListener('click', goNext);
        slides.forEach((slide) => slide.querySelectorAll('video').forEach((video) => video.pause()));
    };
}

// Leaflet is a local, version-pinned asset. Only visitors explicitly opening
// an Exhibition map load the library, its stylesheet, or third-party map tiles.
let leafletAssetsPromise;

function loadLeaflet(scriptUrl, stylesheetUrl) {
    if (window.L?.map) return Promise.resolve(window.L);

    leafletAssetsPromise ??= new Promise((resolve, reject) => {
        const style = document.createElement('link');
        style.rel = 'stylesheet';
        style.href = stylesheetUrl;
        const script = document.createElement('script');
        script.src = scriptUrl;
        script.async = true;

        let loaded = 0;
        const ready = () => {
            loaded++;
            if (loaded !== 2) return;
            if (window.L?.map) resolve(window.L);
            else reject(new Error('Leaflet did not initialize'));
        };
        style.addEventListener('load', ready, { once: true });
        script.addEventListener('load', ready, { once: true });
        style.addEventListener('error', () => reject(new Error('Map styles unavailable')), { once: true });
        script.addEventListener('error', () => reject(new Error('Map library unavailable')), { once: true });
        document.head.append(style, script);
    }).catch((error) => {
        leafletAssetsPromise = undefined;
        throw error;
    });

    return leafletAssetsPromise;
}

function initializeExhibitionMap(button) {
    const dialogId = button.getAttribute('aria-controls');
    const dialog = dialogId ? document.getElementById(dialogId) : null;
    if (!(dialog instanceof HTMLDialogElement)) return;

    const canvas = dialog.querySelector('[data-exhibition-map-canvas]');
    const status = dialog.querySelector('[data-exhibition-map-status]');
    const closeButton = dialog.querySelector('[data-exhibition-map-close]');
    if (!canvas || !status || !closeButton) return;

    let map = null;
    let opening = false;
    let disposed = false;
    let unavailable = false;
    // Observe the map viewport rather than guessing device breakpoints or
    // polling after a device rotation / dialog layout change.
    const resizeObserver = typeof ResizeObserver !== 'undefined'
        ? new ResizeObserver(() => {
            if (dialog.open && map) map.invalidateSize({ pan: false });
        })
        : null;

    // A failed optional map must never change Exhibition data or leave a
    // broken map in the public layout. Hide only this map's UI for this visit.
    const retireUnavailableMap = () => {
        if (unavailable || disposed) return;
        unavailable = true;
        resizeObserver?.disconnect();
        if (map) {
            map.remove();
            map = null;
        }
        if (dialog.open) dialog.close();
        button.remove();
        dialog.remove();
    };

    const openMap = async () => {
        if (unavailable || disposed) return;
        try {
            if (!dialog.open) dialog.showModal();
            closeButton.focus({ preventScroll: true });

            if (map) {
                map.invalidateSize({ pan: false });
                return;
            }
            if (opening) return;
            opening = true;
            status.hidden = false;
            status.textContent = 'Loading map…';

            const L = await loadLeaflet(canvas.dataset.leafletScript, canvas.dataset.leafletStyle);
            if (disposed || unavailable || !dialog.isConnected || !dialog.open) return;

            const latitude = Number(canvas.dataset.latitude);
            const longitude = Number(canvas.dataset.longitude);
            if (!Number.isFinite(latitude) || Math.abs(latitude) > 90
                || !Number.isFinite(longitude) || Math.abs(longitude) > 180) {
                throw new Error('Invalid exhibition map coordinates');
            }

            map = L.map(canvas, {
                zoomControl: false,
                scrollWheelZoom: false,
            }).setView([latitude, longitude], 15);

            let loadedTiles = 0;
            let failedTiles = 0;
            let hasDisplayedTiles = false;
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a>',
            }).on({
                loading: () => {
                    loadedTiles = 0;
                    failedTiles = 0;
                    // Keep the neutral loading surface until a real tile arrived;
                    // initializing Leaflet alone is not a successful map load.
                    if (!hasDisplayedTiles) status.hidden = false;
                },
                tileload: () => {
                    loadedTiles++;
                    hasDisplayedTiles = true;
                    status.hidden = true;
                },
                tileerror: () => { failedTiles++; },
                load: () => {
                    if (failedTiles > 0 && loadedTiles === 0) retireUnavailableMap();
                },
            }).addTo(map);

            L.control.zoom({ position: 'topright', zoomInTitle: 'Zoom in', zoomOutTitle: 'Zoom out' }).addTo(map);
            L.circleMarker([latitude, longitude], {
                radius: 7, weight: 2, color: '#fff', fillColor: '#4b443d', fillOpacity: 1,
            }).addTo(map);
            map.attributionControl.setPrefix(false);
            resizeObserver?.observe(canvas);
            map.invalidateSize({ pan: false });
        } catch {
            // Browser network diagnostics are not suppressible, but a failed
            // optional map must not produce an unhandled application error.
            retireUnavailableMap();
        } finally {
            opening = false;
        }
    };

    const closeMap = () => dialog.close();
    const closeOnBackdrop = (event) => {
        if (event.target === dialog) dialog.close();
    };
    button.addEventListener('click', openMap);
    closeButton.addEventListener('click', closeMap);
    dialog.addEventListener('click', closeOnBackdrop);

    return () => {
        disposed = true;
        button.removeEventListener('click', openMap);
        closeButton.removeEventListener('click', closeMap);
        dialog.removeEventListener('click', closeOnBackdrop);
        resizeObserver?.disconnect();
        if (map) map.remove();
        if (dialog.open) dialog.close();
    };
}

function initializeExhibitionVideoPreview(button) {
    const details = button.closest('details.exhibition-entry__disclosure');
    const video = details?.querySelector('.exhibition-entry__cover video');
    if (!details || !(video instanceof HTMLVideoElement)) return null;

    // The catalogue preview is a poster, not a miniature player. Clicking
    // its play symbol opens the original Folio and plays the existing video.
    const openAndPlay = (event) => {
        event.preventDefault();
        event.stopPropagation();
        details.open = true;
        const result = video.play();
        result?.catch(() => {
            // Browser media policies can reject play(). The expanded Exhibition
            // cover always has native controls for a manual start.
        });
    };
    const pauseWhenClosed = () => {
        if (!details.open) video.pause();
    };
    button.addEventListener('click', openAndPlay);
    details.addEventListener('toggle', pauseWhenClosed);

    return () => {
        button.removeEventListener('click', openAndPlay);
        details.removeEventListener('toggle', pauseWhenClosed);
        video.pause();
    };
}

export function initializeJournalPresentation() {
    const disposers = [
        ...Array.from(document.querySelectorAll('[data-journal-slideshow], [data-journal-carousel]'))
            .map(initializeSteppedGallery),
        ...Array.from(document.querySelectorAll('[data-exhibition-map-open]'))
            .map(initializeExhibitionMap),
        ...Array.from(document.querySelectorAll('[data-exhibition-video-play]'))
            .map(initializeExhibitionVideoPreview),
    ].filter(Boolean);
    return () => disposers.forEach((dispose) => dispose());
}
