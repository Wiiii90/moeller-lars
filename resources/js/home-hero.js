// Hero navigation changes only the visible member of the already-resolved
// Home group. Artwork rendering, media eligibility, and the viewer remain
// owned by their existing server-side services/components.
export function adjacentHeroIndex(index, direction, length) {
    if (!Number.isInteger(index) || !Number.isInteger(length) || length < 1
        || index < 0 || index >= length || ![-1, 1].includes(direction)) {
        throw new RangeError('Invalid Hero navigation position.');
    }
    return (index + direction + length) % length;
}

export function initializeHomeHero(root = document) {
    root.querySelectorAll('[data-home-hero]').forEach((group) => {
        const slides = Array.from(group.querySelectorAll(':scope > [data-home-hero-slide]'));
        if (slides.length < 2) return;

        group.addEventListener('click', (event) => {
            const button = event.target.closest?.('[data-home-hero-direction]');
            if (!button || !group.contains(button)) return;

            const direction = button.dataset.homeHeroDirection === 'previous' ? -1
                : button.dataset.homeHeroDirection === 'next' ? 1 : 0;
            if (!direction) return;

            const index = slides.findIndex((slide) => !slide.hidden);
            if (index < 0) return;
            const nextIndex = adjacentHeroIndex(index, direction, slides.length);
            slides[index].querySelectorAll('video').forEach((video) => video.pause());
            slides[index].hidden = true;
            slides[nextIndex].hidden = false;
            slides[nextIndex].querySelector('[data-home-hero-direction="' +
                button.dataset.homeHeroDirection + '"]')?.focus({ preventScroll: true });
        });
    });
}
