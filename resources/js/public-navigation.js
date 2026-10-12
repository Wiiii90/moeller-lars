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
        const scroller = container.querySelector('[data-site-navigation-scroll]');
        const previous = container.querySelector('[data-direction="previous"]');
        const next = container.querySelector('[data-direction="next"]');

        if (!(scroller instanceof HTMLElement)
            || !(previous instanceof HTMLButtonElement)
            || !(next instanceof HTMLButtonElement)) {
            return;
        }

        const items = Array.from(scroller.querySelectorAll('[data-navigation-item]'))
            .filter((item) => item instanceof HTMLElement);
        const activeIndex = items.findIndex((item) => item.classList.contains('is-active'));
        const closeSubmenus = initializeSubmenus(container);
        let widths = [];
        let availableWidth = 0;
        let visible = [];
        let cycling = false;

        const render = (indices) => {
            const sameWindow = visible.length === indices.length
                && visible.every((index, position) => index === indices[position]);
            if (!sameWindow) closeSubmenus();

            const shown = new Set(indices);
            const focused = document.activeElement;
            const focusedItem = focused instanceof Element
                ? focused.closest('[data-navigation-item]')
                : null;

            items.forEach((item, index) => {
                item.hidden = !shown.has(index);
                item.classList.toggle('is-overwide', shown.has(index) && widths[index] > availableWidth);
            });

            // Reorder the original DOM nodes, not copies, so keyboard and
            // screen-reader order agrees with the visual cyclic order.
            const ordered = [
                ...indices.map((index) => items[index]),
                ...items.filter((_, index) => !shown.has(index)),
            ];
            if (ordered.some((item, position) => scroller.children[position] !== item)) {
                scroller.append(...ordered);
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

        const update = () => {
            // Measure intrinsic label widths, including currently hidden items.
            // The synchronous read/write pass completes before the next paint.
            container.classList.add('is-measuring');
            items.forEach((item) => {
                item.hidden = false;
                item.classList.remove('is-overwide');
            });
            widths = items.map((item) => item.getBoundingClientRect().width);
            container.classList.remove('is-measuring');

            const wasCycling = cycling;
            cycling = widths.length > 1
                && widths.reduce((sum, width) => sum + width, 0) > scroller.clientWidth;
            container.classList.toggle('is-overflowing', cycling);
            previous.hidden = !cycling;
            next.hidden = !cycling;

            const style = getComputedStyle(scroller);
            availableWidth = Math.max(
                1,
                scroller.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight),
            );

            if (!cycling) {
                render(items.map((_, index) => index));
                return;
            }

            let fitted = fitNavigationWindow(widths, availableWidth, wasCycling && visible.length ? visible[0] : 0);
            const keepActive = activeIndex >= 0 && (!wasCycling || visible.includes(activeIndex));
            // Reveal the currently active page on first load and retain it
            // when a smaller viewport forces the window to contract.
            for (let step = 0; keepActive && !fitted.includes(activeIndex) && step < items.length; step += 1) {
                fitted = advanceNavigationWindow(widths, fitted, availableWidth, 1);
            }
            render(fitted);
        };

        previous.addEventListener('click', () => {
            if (cycling) render(advanceNavigationWindow(widths, visible, availableWidth, -1));
        });
        next.addEventListener('click', () => {
            if (cycling) render(advanceNavigationWindow(widths, visible, availableWidth, 1));
        });

        if ('ResizeObserver' in window) {
            new ResizeObserver(update).observe(scroller);
        } else {
            window.addEventListener('resize', update, { passive: true });
        }

        update();
    });
}
