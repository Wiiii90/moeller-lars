// Hero navigation and the authenticated editor's simulation select the same
// already-resolved slides. Eligibility and rendering stay with their server owners.
export function adjacentHeroIndex(index, direction, length) {
    if (!Number.isInteger(index) || !Number.isInteger(length) || length < 1
        || index < 0 || index >= length || ![-1, 1].includes(direction)) {
        throw new RangeError('Invalid Hero navigation position.');
    }
    return (index + direction + length) % length;
}

export function initializeGalleryHero(root = document, transition = (change) => change()) {
    const disposers = [];
    root.querySelectorAll('[data-gallery-hero]').forEach((group) => {
        const slides = Array.from(group.querySelectorAll(':scope > [data-gallery-hero-slide]'));
        if (slides.length < 2) return;

        const showSlide = (nextIndex) => {
            const index = slides.findIndex((slide) => !slide.hidden);
            if (index < 0 || nextIndex < 0 || nextIndex === index) return;
            slides[index].querySelectorAll('video').forEach((video) => video.pause());
            transition(() => {
                slides[index].hidden = true;
                slides[nextIndex].hidden = false;
                // The shared media fitter measures the newly active slide.
                group.dispatchEvent(new Event('gallery-hero-change'));
            });
        };

        const onNavigation = (event) => {
            const button = event.target.closest?.('[data-gallery-hero-direction]');
            if (!button || !group.contains(button)) return;
            const direction = button.dataset.homeHeroDirection === 'previous' ? -1
                : button.dataset.homeHeroDirection === 'next' ? 1 : 0;
            if (!direction) return;
            const index = slides.findIndex((slide) => !slide.hidden);
            if (index < 0) return;
            const nextIndex = adjacentHeroIndex(index, direction, slides.length);
            showSlide(nextIndex);
            slides[nextIndex].querySelector('[data-gallery-hero-direction="' +
                button.dataset.homeHeroDirection + '"]')?.focus({ preventScroll: true });
        };
        group.addEventListener('click', onNavigation);
        disposers.push(() => group.removeEventListener('click', onNavigation));

        // Only authenticated preview pages accept same-origin editor selections.
        if (group.hasAttribute('data-gallery-hero-preview')) {
            const onSelection = (event) => {
                if (window.parent === window || event.source !== window.parent
                    || event.origin !== window.location.origin
                    || event.data?.type !== 'gallery-hero-preview-select') return;
                const nextIndex = slides.findIndex(
                    (slide) => slide.dataset.galleryHeroCandidate === event.data.candidateKey,
                );
                showSlide(nextIndex);
            };
            window.addEventListener('message', onSelection);
            disposers.push(() => window.removeEventListener('message', onSelection));
        }
    });
    return () => disposers.forEach((dispose) => dispose());
}
