// One page-scoped owner for inline Public videos; the Artwork Viewer owns
// its separate full-size video player. No timers, polling or duplicate players.
export function shouldPlayPublicVideo(intersecting, pageVisible, slideVisible, reducedMotion = false) {
    return intersecting && pageVisible && slideVisible && !reducedMotion;
}

export function initializePublicMediaPlayback(root = document) {
    const videos = Array.from(root.querySelectorAll('video[data-public-loop-video]'));
    if (videos.length === 0) return () => {};

    const intersections = new WeakMap();
    const listeners = [];
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    const synchronize = (video) => {
        const active = shouldPlayPublicVideo(
            intersections.get(video) === true,
            document.visibilityState === 'visible',
            !video.closest('[hidden]') && video.isConnected,
            reducedMotion.matches,
        );
        if (!active) {
            video.pause();
            return;
        }

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

    for (const video of videos) {
        video.muted = true;
        video.loop = true;
        video.playsInline = true;
        observer?.observe(video);

        const button = video.closest('.artwork-card, .artwork-detail')?.querySelector('[data-public-video-sound-toggle]');
        if (!button) continue;
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
    }

    const syncAll = () => videos.forEach(synchronize);
    document.addEventListener('visibilitychange', syncAll);
    reducedMotion.addEventListener?.('change', syncAll);

    return () => {
        document.removeEventListener('visibilitychange', syncAll);
        reducedMotion.removeEventListener?.('change', syncAll);
        observer?.disconnect();
        listeners.forEach(([button, toggle]) => button.removeEventListener('click', toggle));
        videos.forEach((video) => video.pause());
    };
}
