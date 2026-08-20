const SUBMENU_TRANSITION_MS = 180;

function submenuControls(item) {
    const parentLink = item.querySelector('[data-navigation-parent-link]');
    const submenu = item.querySelector('[data-navigation-submenu]');

    if (!(parentLink instanceof HTMLAnchorElement)
        || !(submenu instanceof HTMLElement)) {
        return null;
    }

    return { parentLink, submenu };
}

function setExpanded(controls, expanded) {
    controls.parentLink.setAttribute('aria-expanded', expanded ? 'true' : 'false');
}

function initializeSubmenus(container) {
    const region = container.querySelector('[data-navigation-submenu-region]');
    const regionInner = container.querySelector('[data-navigation-submenu-region-inner]');
    if (!(region instanceof HTMLElement) || !(regionInner instanceof HTMLElement)) {
        return () => {};
    }

    const entries = Array.from(container.querySelectorAll('[data-navigation-item]'))
        .filter((item) => item instanceof HTMLElement)
        .map((item) => ({ item, controls: submenuControls(item) }))
        .filter((entry) => entry.controls !== null);

    entries.forEach(({ controls }) => regionInner.append(controls.submenu));

    let activeEntry = null;
    let closeTimer = null;
    let hideTimer = null;

    const clearCloseTimer = () => {
        if (closeTimer !== null) {
            window.clearTimeout(closeTimer);
            closeTimer = null;
        }
    };

    const clearHideTimer = () => {
        if (hideTimer !== null) {
            window.clearTimeout(hideTimer);
            hideTimer = null;
        }
    };

    const hideEntryImmediately = (entry) => {
        setExpanded(entry.controls, false);
        entry.item.dataset.submenuOpen = 'false';
        entry.controls.submenu.hidden = true;
    };

    const closeActive = (restoreFocus = false) => {
        clearCloseTimer();
        clearHideTimer();
        if (activeEntry === null) return;

        const closingEntry = activeEntry;
        activeEntry = null;
        setExpanded(closingEntry.controls, false);
        closingEntry.item.dataset.submenuOpen = 'false';
        region.dataset.open = 'false';

        hideTimer = window.setTimeout(() => {
            closingEntry.controls.submenu.hidden = true;
            hideTimer = null;
        }, SUBMENU_TRANSITION_MS);

        if (restoreFocus) {
            closingEntry.controls.parentLink.focus();
        }
    };

    const openEntry = (entry) => {
        clearCloseTimer();
        clearHideTimer();

        if (activeEntry !== null && activeEntry !== entry) {
            hideEntryImmediately(activeEntry);
        }

        activeEntry = entry;
        entry.controls.submenu.hidden = false;
        setExpanded(entry.controls, true);
        entry.item.dataset.submenuOpen = 'true';
        region.dataset.open = 'true';
    };

    const scheduleClose = (entry) => {
        clearCloseTimer();
        closeTimer = window.setTimeout(() => {
            const focusInside = entry.item.contains(document.activeElement)
                || entry.controls.submenu.contains(document.activeElement);
            const hoverInside = entry.item.matches(':hover')
                || entry.controls.submenu.matches(':hover');
            if (!focusInside && !hoverInside && activeEntry === entry) {
                closeActive();
            }
            closeTimer = null;
        }, 150);
    };

    entries.forEach((entry) => {
        const { item, controls } = entry;
        const { parentLink, submenu } = controls;

        item.addEventListener('pointerenter', (event) => {
            if (event.pointerType === 'mouse') openEntry(entry);
        });
        item.addEventListener('pointerleave', (event) => {
            if (event.pointerType === 'mouse') scheduleClose(entry);
        });
        submenu.addEventListener('pointerenter', clearCloseTimer);
        submenu.addEventListener('pointerleave', (event) => {
            if (event.pointerType === 'mouse') scheduleClose(entry);
        });

        item.addEventListener('focusin', () => openEntry(entry));
        item.addEventListener('focusout', () => {
            window.setTimeout(() => {
                if (!item.contains(document.activeElement)
                    && !submenu.contains(document.activeElement)
                    && activeEntry === entry) {
                    closeActive();
                }
            }, 0);
        });
        submenu.addEventListener('focusout', () => {
            window.setTimeout(() => {
                if (!item.contains(document.activeElement)
                    && !submenu.contains(document.activeElement)
                    && activeEntry === entry) {
                    closeActive();
                }
            }, 0);
        });

        parentLink.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                openEntry(entry);
                const firstLink = submenu.querySelector('a');
                if (firstLink instanceof HTMLAnchorElement) firstLink.focus();
            } else if (event.key === 'Escape') {
                event.preventDefault();
                closeActive(true);
            }
        });

        submenu.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                closeActive(true);
                return;
            }
            if (!['ArrowDown', 'ArrowUp'].includes(event.key)) return;

            const links = Array.from(submenu.querySelectorAll('a'))
                .filter((link) => link instanceof HTMLAnchorElement);
            const current = links.indexOf(document.activeElement);
            if (current === -1 || links.length === 0) return;

            event.preventDefault();
            const offset = event.key === 'ArrowDown' ? 1 : -1;
            links[(current + offset + links.length) % links.length].focus();
        });
    });

    document.addEventListener('pointerdown', (event) => {
        if (event.target instanceof Node && !container.contains(event.target)) closeActive();
    });

    return closeActive;
}

function initializeFullCellTargets(scroller) {
    Array.from(scroller.querySelectorAll('[data-navigation-item]'))
        .filter((item) => item instanceof HTMLElement)
        .forEach((item) => {
            const primary = item.querySelector('.site-navigation__primary');
            const link = primary?.querySelector('a');
            if (!(primary instanceof HTMLElement) || !(link instanceof HTMLAnchorElement)) return;

            primary.style.cursor = 'pointer';
            primary.addEventListener('click', (event) => {
                if (event.defaultPrevented
                    || event.button !== 0
                    || event.ctrlKey
                    || event.metaKey
                    || event.shiftKey
                    || event.altKey) {
                    return;
                }

                if (event.target instanceof Element && event.target.closest('a')) return;
                link.click();
            });
        });
}

function shiftByOneItem(scroller, direction) {
    const items = Array.from(scroller.querySelectorAll('[data-navigation-item]'))
        .filter((item) => item instanceof HTMLElement);
    if (items.length === 0) return;

    const currentLeft = scroller.scrollLeft;
    let index = items.findIndex((item) => item.offsetLeft + item.offsetWidth > currentLeft + 2);
    if (index < 0) index = items.length - 1;

    if (direction > 0 && items[index].offsetLeft <= currentLeft + 2) index += 1;
    if (direction < 0 && items[index].offsetLeft >= currentLeft - 2) index -= 1;

    index = Math.max(0, Math.min(items.length - 1, index));
    scroller.scrollTo({ left: items[index].offsetLeft, behavior: 'smooth' });
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

        const closeSubmenus = initializeSubmenus(container);
        initializeFullCellTargets(scroller);
        let dragging = false;
        let dragged = false;
        let dragStartX = 0;
        let dragStartScroll = 0;

        const update = () => {
            const overflow = scroller.scrollWidth > scroller.clientWidth + 2;
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

        previous.addEventListener('click', () => {
            closeSubmenus();
            shiftByOneItem(scroller, -1);
        });
        next.addEventListener('click', () => {
            closeSubmenus();
            shiftByOneItem(scroller, 1);
        });

        scroller.addEventListener('scroll', update, { passive: true });
        scroller.addEventListener('pointerdown', (event) => {
            if (event.pointerType !== 'mouse'
                || !container.classList.contains('is-overflowing')) {
                return;
            }

            dragging = true;
            dragged = false;
            dragStartX = event.clientX;
            dragStartScroll = scroller.scrollLeft;
            container.classList.add('is-dragging');
            closeSubmenus();
            scroller.setPointerCapture(event.pointerId);
        });

        scroller.addEventListener('pointermove', (event) => {
            if (!dragging) return;
            const delta = event.clientX - dragStartX;
            if (Math.abs(delta) > 4) dragged = true;
            scroller.scrollLeft = dragStartScroll - delta;
        });

        const finishDrag = (event) => {
            if (!dragging) return;
            dragging = false;
            container.classList.remove('is-dragging');
            if (scroller.hasPointerCapture(event.pointerId)) scroller.releasePointerCapture(event.pointerId);
            update();
        };

        scroller.addEventListener('pointerup', finishDrag);
        scroller.addEventListener('pointercancel', finishDrag);
        scroller.addEventListener('click', (event) => {
            if (!dragged) return;
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
