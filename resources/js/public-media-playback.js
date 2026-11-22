// One page-scoped owner for inline Public videos; the Artwork Viewer owns
// its separate full-size video player. No timers, polling or duplicate players.
export function shouldPlayPublicVideo(intersecting, pageVisible, slideVisible, reducedMotion = false) {
    return intersecting && pageVisible && slideVisible && !reducedMotion;
}

// Native controls are rendered by several canonical Public media owners.
// Apply their shared presentation policy here, including the fullscreen
// Viewer and Journal/Custom Page players, without replacing browser controls.
// This hides the Download action where supported; it does not restrict access
// to publicly delivered media.
function configureNativeVideoControls(container) {
    for (const video of container.querySelectorAll('video[controls]')) {
        video.controlsList?.add('nodownload');
    }
}

export function initializePublicMediaPlayback(root = document) {
    configureNativeVideoControls(root);
    const videos = Array.from(root.querySelectorAll('video[data-public-loop-video]'));
    if (videos.length === 0 && !root.querySelector('[data-gallery-infinite]')) return () => {};

    const intersections = new WeakMap();
    const listeners = [];
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    const synchronize = (video) => {
        const active = shouldPlayPublicVideo(
            intersections.get(video) === true,
            document.visibilityState === 'visible',
            !video.closest('[hidden], [data-journal-slide][aria-hidden="true"]') && video.isConnected,
            reducedMotion.matches,
        );
        // Permit native muted autoplay only for visible, motion-allowed media.
        // Browser/device autoplay restrictions still take precedence.
        video.autoplay = active;
        if (!active) {
            video.pause();
            return;
        }

        if (!video.paused) return;
        const result = video.play();
        if (result && typeof result.catch === 'function') result.catch(() => {
            // Browsers may reject autoplay. Opening the accessible Viewer
            // still provides native manual playback.
        });
    };

    const observer = typeof IntersectionObserver === 'function'
        ? new IntersectionObserver((entries) => {
            for (const entry of entries) {
                intersections.set(entry.target, entry.intersectionRatio >= .15);
                synchronize(entry.target);
            }
        }, { threshold: [0, .15] })
        : null;

    const registered = new Set();
    const attachVideo = (video) => {
        if (registered.has(video)) return;
        registered.add(video);
        videos.push(video);
        video.muted = true;
        video.loop = true;
        video.playsInline = true;
        observer?.observe(video);

        const button = video.closest('.artwork-card, .artwork-detail')?.querySelector('[data-public-video-sound-toggle]');
        if (!button) return;
        const toggle = () => {
            video.muted = !video.muted;
            button.setAttribute('aria-pressed', String(!video.muted));
            button.setAttribute('aria-label', video.muted ? 'Enable video sound' : 'Mute video');
            button.title = video.muted ? 'Enable video sound' : 'Mute video';
            button.querySelector('[data-public-video-icon-muted]').hidden = !video.muted;
            button.querySelector('[data-public-video-icon-playing]').hidden = video.muted;
        };
        button.addEventListener('click', toggle);
        listeners.push([button, toggle]);
    };

    // The existing Gallery and subsequent batches use the same observer
    // and playback/sound lifecycle.
    const initiallyRendered = [...videos];
    videos.length = 0;
    initiallyRendered.forEach(attachVideo);
    const onGalleryBatch = (event) => {
        event.detail.elements.forEach((element) => {
            configureNativeVideoControls(element);
            element.querySelectorAll('video[data-public-loop-video]').forEach(attachVideo);
        });
    };
    root.addEventListener('gallery:items-appended', onGalleryBatch);

    const syncAll = () => videos.forEach(synchronize);
    document.addEventListener('visibilitychange', syncAll);
    document.addEventListener('journal:gallery-change', syncAll);
    reducedMotion.addEventListener?.('change', syncAll);

    return () => {
        document.removeEventListener('visibilitychange', syncAll);
        document.removeEventListener('journal:gallery-change', syncAll);
        reducedMotion.removeEventListener?.('change', syncAll);
        observer?.disconnect();
        root.removeEventListener('gallery:items-appended', onGalleryBatch);
        listeners.forEach(([button, toggle]) => button.removeEventListener('click', toggle));
        videos.forEach((video) => video.pause());
    };
}
