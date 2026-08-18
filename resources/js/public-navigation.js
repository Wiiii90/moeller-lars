function navigationCanOverflow() {
    return !window.matchMedia('(max-width: 550px)').matches;
}

export function initializePublicNavigation() {
    document.querySelectorAll('[data-site-navigation]').forEach((container) => {
        const scroller = container.querySelector('[data-site-navigation-scroll]');
        const previous = container.querySelector('[data-direction="previous"]');
        const next = container.querySelector('[data-direction="next"]');

        if (!(scroller instanceof HTMLElement)
            || !(previous instanceof HTMLButtonElement)
            || !(next instanceof HTMLButtonElement)) {
            return;
        }

        let dragging = false;
        let dragged = false;
        let dragStartX = 0;
        let dragStartScroll = 0;

        const update = () => {
            const overflow = navigationCanOverflow() && scroller.scrollWidth > scroller.clientWidth + 2;
            container.classList.toggle('is-overflowing', overflow);
            previous.hidden = !overflow;
            next.hidden = !overflow;

            if (!overflow) {
                scroller.scrollLeft = 0;
                previous.disabled = true;
                next.disabled = true;
                return;
            }

            const maxScroll = Math.max(0, scroller.scrollWidth - scroller.clientWidth);
            previous.disabled = scroller.scrollLeft <= 1;
            next.disabled = scroller.scrollLeft >= maxScroll - 1;
        };

        const scrollByPage = (direction) => {
            scroller.scrollBy({
                left: direction * Math.max(180, scroller.clientWidth * .55),
                behavior: 'smooth',
            });
        };

        previous.addEventListener('click', () => scrollByPage(-1));
        next.addEventListener('click', () => scrollByPage(1));
        scroller.addEventListener('scroll', update, { passive: true });

        scroller.addEventListener('pointerdown', (event) => {
            if (event.pointerType !== 'mouse' || !container.classList.contains('is-overflowing')) {
                return;
            }

            dragging = true;
            dragged = false;
            dragStartX = event.clientX;
            dragStartScroll = scroller.scrollLeft;
            container.classList.add('is-dragging');
            scroller.setPointerCapture(event.pointerId);
        });

        scroller.addEventListener('pointermove', (event) => {
            if (!dragging) {
                return;
            }

            const delta = event.clientX - dragStartX;
            if (Math.abs(delta) > 4) {
                dragged = true;
            }
            scroller.scrollLeft = dragStartScroll - delta;
        });

        const finishDrag = (event) => {
            if (!dragging) {
                return;
            }

            dragging = false;
            container.classList.remove('is-dragging');
            if (scroller.hasPointerCapture(event.pointerId)) {
                scroller.releasePointerCapture(event.pointerId);
            }
            update();
        };

        scroller.addEventListener('pointerup', finishDrag);
        scroller.addEventListener('pointercancel', finishDrag);
        scroller.addEventListener('click', (event) => {
            if (!dragged) {
                return;
            }

            event.preventDefault();
            dragged = false;
        }, true);

        if ('ResizeObserver' in window) {
            new ResizeObserver(update).observe(scroller);
        } else {
            window.addEventListener('resize', update, { passive: true });
        }

        update();
    });
}
