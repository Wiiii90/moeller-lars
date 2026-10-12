const SUBMENU_TRANSITION_MS = 180;

// Both navigation levels use the same whole-item cyclic window and never scroll pixels.
// The surface is a three-column grid: previous control, canonical content width, next control.
function initializeNavigationWindow(surface, onWindowChange = () => {}) {
    const rail = surface.querySelector('[data-navigation-window-scroll]');
    const previous = surface.querySelector('[data-direction="previous"]');
    const next = surface.querySelector('[data-direction="next"]');
    if (!(rail instanceof HTMLElement)
        || !(previous instanceof HTMLButtonElement)
        || !(next instanceof HTMLButtonElement)) {
        throw new Error('Public navigation window is missing required controls.');
    }

    const items = Array.from(rail.children).filter((item) => item.matches('[data-navigation-item]'));
    const activeIndex = items.findIndex((item) => item.classList.contains('is-active'));
    let widths = [];
    let visible = [];
    let availableWidth = 0;
    let cycling = false;
    let observedWidth = -1;

    const render = (indices) => {
        const changed = visible.length !== indices.length
            || visible.some((index, position) => indices[position] !== index);
        if (changed) onWindowChange();

        const shown = new Set(indices);
        const focused = document.activeElement;
        const focusedItem = focused instanceof Element
            ? focused.closest('[data-navigation-item]')
            : null;

        items.forEach((item, index) => {
            item.hidden = !shown.has(index);
            item.classList.toggle('is-overwide', shown.has(index) && widths[index] > availableWidth);
            item.classList.toggle('is-last-visible', index === indices[indices.length - 1]);
        });

        // Reorder existing DOM nodes, not copies, to match the cyclic visual,
        // focus and screen-reader order when the sequence wraps around.
        const ordered = [
            ...indices.map((index) => items[index]),
            ...items.filter((_, index) => !shown.has(index)),
        ];
        if (ordered.some((item, position) => rail.children[position] !== item)) {
            rail.append(...ordered);
        }

        visible = indices;
        if (focusedItem && focused instanceof HTMLElement) {
            if (shown.has(items.indexOf(focusedItem))) {
                focused.focus({ preventScroll: true });
            } else if (cycling) {
                previous.focus({ preventScroll: true });
            }
        }
    };

    const refresh = () => {
        if (surface.hidden) return;

        surface.classList.add('is-measuring');
        items.forEach((item) => {
            item.hidden = false;
            item.classList.remove('is-overwide', 'is-last-visible');
        });
        widths = items.map((item) => item.getBoundingClientRect().width);
        surface.classList.remove('is-measuring');

        // Reserve the final visible item's 1px right border when fitting.
        availableWidth = Math.max(1, rail.clientWidth - 1);
        const wasCycling = cycling;
        cycling = widths.length > 1
            && widths.reduce((sum, width) => sum + width, 0) > availableWidth;

        surface.classList.toggle('is-overflowing', cycling);
        previous.hidden = !cycling;
        next.hidden = !cycling;

        if (!cycling) {
            render(items.map((_, index) => index));
            return;
        }

        let fitted = fitNavigationWindow(widths, availableWidth, wasCycling && visible.length ? visible[0] : 0);
        const keepActive = activeIndex >= 0 && (!wasCycling || visible.includes(activeIndex));
        for (let step = 0; keepActive && !fitted.includes(activeIndex) && step < items.length; step += 1) {
            fitted = advanceNavigationWindow(widths, fitted, availableWidth, 1);
        }
        render(fitted);
    };

    const shift = (direction) => {
        if (!cycling) return;
        render(advanceNavigationWindow(widths, visible, availableWidth, direction));
    };
    previous.addEventListener('click', () => shift(-1));
    next.addEventListener('click', () => shift(1));

    if ('ResizeObserver' in window) {
        new ResizeObserver(() => {
            const width = rail.getBoundingClientRect().width;
            if (Math.abs(observedWidth - width) > 0.5) {
                observedWidth = width;
                refresh();
            }
        }).observe(rail);
    } else {
        window.addEventListener('resize', refresh, { passive: true });
    }

    return { items, refresh, previous, next };
}

function submenuControls(item) {
    const parentLink = item.querySelector('[data-navigation-parent-link]');
    const submenu = item.querySelector('[data-navigation-submenu]');
    if (!(parentLink instanceof HTMLElement)
        || !(submenu instanceof HTMLElement)) return null;
    return { parentLink, submenu };
}

function initializeSubmenus(container, topLevelItems) {
    const region = container.querySelector('[data-navigation-submenu-region]');
    const regionInner = container.querySelector('[data-navigation-submenu-region-inner]');
    if (!(region instanceof HTMLElement) || !(regionInner instanceof HTMLElement)) return () => {};

    const entries = topLevelItems
        .map((item) => ({ item, controls: submenuControls(item) }))
        .filter((entry) => entry.controls !== null)
        .map((entry) => {
            regionInner.append(entry.controls.submenu);
            return {
                ...entry,
                window: initializeNavigationWindow(entry.controls.submenu),
            };
        });

    let activeEntry = null;
    let suppressFocusOpen = false;
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
        entry.controls.parentLink.setAttribute('aria-expanded', 'false');
        entry.item.dataset.submenuOpen = 'false';
        entry.controls.submenu.hidden = true;
    };
    const closeActive = (restoreFocus = false) => {
        clearCloseTimer();
        clearHideTimer();
        if (activeEntry === null) return;

        const closing = activeEntry;
        activeEntry = null;
        closing.controls.parentLink.setAttribute('aria-expanded', 'false');
        closing.item.dataset.submenuOpen = 'false';
        region.dataset.open = 'false';
        hideTimer = window.setTimeout(() => {
            closing.controls.submenu.hidden = true;
            hideTimer = null;
        }, SUBMENU_TRANSITION_MS);
        if (restoreFocus) {
            suppressFocusOpen = true;
            closing.controls.parentLink.focus({ preventScroll: true });
            suppressFocusOpen = false;
        }
    };
    const openEntry = (entry) => {
        clearCloseTimer();
        clearHideTimer();
        if (activeEntry !== null && activeEntry !== entry) hideEntryImmediately(activeEntry);
        activeEntry = entry;
        entry.controls.submenu.hidden = false;
        entry.controls.parentLink.setAttribute('aria-expanded', 'true');
        entry.item.dataset.submenuOpen = 'true';
        region.dataset.open = 'true';
        entry.window.refresh();
    };
    const scheduleClose = (entry) => {
        clearCloseTimer();
        closeTimer = window.setTimeout(() => {
            const focusInside = entry.item.contains(document.activeElement)
                || entry.controls.submenu.contains(document.activeElement);
            const hoverInside = entry.item.matches(':hover')
                || entry.controls.submenu.matches(':hover');
            if (!focusInside && !hoverInside && activeEntry === entry) closeActive();
            closeTimer = null;
        }, 150);
    };

    entries.forEach((entry) => {
        const { item, controls } = entry;
        const { parentLink, submenu } = controls;
        let openAtPointerDown = false;
        let lastPointerType = null;

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
        item.addEventListener('focusin', () => {
            if (!suppressFocusOpen) openEntry(entry);
        });
        const scheduleFocusClose = () => window.setTimeout(() => {
            if (!item.contains(document.activeElement)
                && !submenu.contains(document.activeElement)
                && activeEntry === entry) closeActive();
        }, 0);
        item.addEventListener('focusout', scheduleFocusClose);
        submenu.addEventListener('focusout', scheduleFocusClose);

        parentLink.addEventListener('pointerdown', (event) => {
            openAtPointerDown = activeEntry === entry;
            lastPointerType = event.pointerType;
        });
        parentLink.addEventListener('click', (event) => {
            const coarsePointer = lastPointerType === 'touch' || lastPointerType === 'pen';
            lastPointerType = null;
            if (parentLink instanceof HTMLButtonElement) {
                event.preventDefault();
                if (openAtPointerDown) closeActive(true);
                else openEntry(entry);
                return;
            }
            // A coarse-pointer user can reveal child links on the first tap
            // and follow the parent URL on the second. Desktop links still work.
            if (coarsePointer && !openAtPointerDown) {
                event.preventDefault();
                openEntry(entry);
            }
        });

        parentLink.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                openEntry(entry);
                const firstLink = submenu.querySelector('.site-navigation__item:not([hidden]) a');
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
            if (!['ArrowLeft', 'ArrowRight', 'ArrowDown', 'ArrowUp'].includes(event.key)) return;
            const links = Array.from(submenu.querySelectorAll('.site-navigation__item:not([hidden]) a'));
            const current = links.indexOf(document.activeElement);
            if (current === -1 || links.length === 0) return;
            event.preventDefault();
            const offset = event.key === 'ArrowRight' || event.key === 'ArrowDown' ? 1 : -1;
            const destination = current + offset;
            if (destination >= 0 && destination < links.length) {
                links[destination].focus();
            } else {
                const control = offset > 0 ? entry.window.next : entry.window.previous;
                if (!control.hidden) control.click();
                const updated = Array.from(submenu.querySelectorAll('.site-navigation__item:not([hidden]) a'));
                (offset > 0 ? updated[updated.length - 1] : updated[0])?.focus();
            }
        });
    });

    document.addEventListener('pointerdown', (event) => {
        if (event.target instanceof Node && !container.contains(event.target)) closeActive();
    });
    return closeActive;
}

// The visible menu is a cyclic window of complete Site Node items.
export function fitNavigationWindow(widths, availableWidth, startIndex = 0) {
    if (widths.length === 0) return [];

    const start = ((startIndex % widths.length) + widths.length) % widths.length;
    const visible = [start];
    let occupied = widths[start];

    for (let step = 1; step < widths.length; step += 1) {
        const next = (start + step) % widths.length;
        if (occupied + widths[next] > availableWidth) break;
        visible.push(next);
        occupied += widths[next];
    }

    // Even an overwide label stays whole as one item; CSS wraps its text.
    return visible;
}

export function advanceNavigationWindow(widths, visible, availableWidth, direction) {
    if (widths.length === 0) return [];
    if (direction !== -1 && direction !== 1) {
        throw new RangeError('Navigation direction must be -1 or 1.');
    }
    if (visible.length === 0) return fitNavigationWindow(widths, availableWidth);
    if (visible.length === widths.length) return [...visible];

    const adjacent = direction === 1
        ? (visible[visible.length - 1] + 1) % widths.length
        : (visible[0] - 1 + widths.length) % widths.length;
    const candidate = direction === 1 ? [...visible, adjacent] : [adjacent, ...visible];
    let occupied = candidate.reduce((sum, index) => sum + widths[index], 0);

    while (candidate.length > 1 && occupied > availableWidth) {
        const removed = direction === 1 ? candidate.shift() : candidate.pop();
        occupied -= widths[removed];
    }

    // A former long label may have occupied the whole rail. Refill unused
    // space with following/preceding whole items in the chosen direction.
    while (candidate.length < widths.length) {
        const edge = direction === 1 ? candidate[candidate.length - 1] : candidate[0];
        const adjacentIndex = (edge + direction + widths.length) % widths.length;
        if (occupied + widths[adjacentIndex] > availableWidth) break;
        if (direction === 1) candidate.push(adjacentIndex);
        else candidate.unshift(adjacentIndex);
        occupied += widths[adjacentIndex];
    }

    return candidate;
}

export function initializePublicNavigation() {
    document.querySelectorAll('[data-site-navigation]').forEach((container) => {
        const mainSurface = container.querySelector('[data-navigation-main]');
        if (!(mainSurface instanceof HTMLElement)) return;

        let closeSubmenus = () => {};
        const main = initializeNavigationWindow(mainSurface, () => closeSubmenus());
        closeSubmenus = initializeSubmenus(container, main.items);
        main.refresh();
    });
}
