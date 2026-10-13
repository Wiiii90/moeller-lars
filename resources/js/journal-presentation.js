function initializeSlideshow(root) {
    const slides = Array.from(root.querySelectorAll('[data-journal-slide]'));
    if (slides.length <= 1) return;

    const previous = root.querySelector('[data-journal-slide-prev]');
    const next = root.querySelector('[data-journal-slide-next]');
    const status = root.querySelector('[data-journal-slide-status]');
    let index = 0;

    const render = () => {
        slides.forEach((slide, position) => {
            const active = position === index;
            slide.hidden = !active;
            slide.setAttribute('aria-hidden', active ? 'false' : 'true');
        });
        if (status) status.textContent = `${index + 1} / ${slides.length}`;
    };

    previous?.addEventListener('click', () => {
        index = (index - 1 + slides.length) % slides.length;
        render();
    });
    next?.addEventListener('click', () => {
        index = (index + 1) % slides.length;
        render();
    });

    render();
}

function initializeMapDialog(button) {
    const dialogId = button.getAttribute('aria-controls');
    if (!dialogId) return;

    const dialog = document.getElementById(dialogId);
    if (!(dialog instanceof HTMLDialogElement)) return;

    button.addEventListener('click', () => {
        const frame = dialog.querySelector('iframe[data-exhibition-dialog-src]');
        if (frame) {
            frame.src = frame.dataset.exhibitionDialogSrc;
            frame.removeAttribute('data-exhibition-dialog-src');
        }
        if (!dialog.open) dialog.showModal();
    });
    dialog.querySelector('[data-exhibition-map-close]')?.addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
    });
}

function initializeExhibitionMapDisclosure(disclosure) {
    disclosure.addEventListener('toggle', () => {
        if (!disclosure.open) return;

        // Do not request historical map embeds until a visitor asks for one.
        const frame = disclosure.querySelector('iframe[data-exhibition-map-src]');
        if (frame) {
            frame.src = frame.dataset.exhibitionMapSrc;
            frame.removeAttribute('data-exhibition-map-src');
        }
    });
}

export function initializeJournalPresentation() {
    document.querySelectorAll('[data-exhibition-map-disclosure]').forEach(initializeExhibitionMapDisclosure);
    document.querySelectorAll('[data-journal-slideshow]').forEach(initializeSlideshow);
    document.querySelectorAll('[data-exhibition-map-expand]').forEach(initializeMapDialog);
}
