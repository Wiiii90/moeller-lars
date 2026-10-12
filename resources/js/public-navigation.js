const SUBMENU_TRANSITION_MS = 180;

// Resolve only immediate children: otherwise the main navigation could bind
// its right arrow to a descendant submenu's right arrow.
export function navigationWindowParts(surface) {
    const direct = Array.from(surface.children);
    return {
        rail: direct.find((element) => element.matches('nav[data-navigation-window-scroll]')),
        previous: direct.find((element) => element.matches('button[data-direction="previous"]')),
        next: direct.find((element) => element.matches('button[data-direction="next"]')),
    };
}

// Arrows are only necessary when the complete set exceeds the rail.
// A single overwide label wraps in place rather than creating useless arrows.
export function needsNavigationOverflow(widths, availableWidth) {
    return widths.length > 1
        && widths.reduce((total, width) => total + width, 0) > availableWidth;
}

// Navigation is server-rendered. A single-use destination-scoped handoff
// preserves the exact visible window when an internal navigation link reloads
// the page. No URL parameters, duplicate renderer or background polling.
const NAVIGATION_HANDOFF_KEY = 'moeller:public-navigation-handoff';

function navigationDestination(url) {
    return url.pathname + url.search;
}

function readNavigationHandoff() {
    try {
        const handoff = JSON.parse(window.sessionStorage.getItem(NAVIGATION_HANDOFF_KEY) ?? 'null');
        return handoff?.destination === navigationDestination(window.location)
            && handoff.windows && typeof handoff.windows === 'object'
            ? handoff.windows
            : null;
    } catch {
        // Storage can be disabled; direct-entry navigation still works.
        return null;
    }
}

export function restoreNavigationWindow(widths, availableWidth, activeIndex, saved, keys) {
    if (!needsNavigationOverflow(widths, availableWidth)) {
        return widths.map((_, index) => index);
    }

    // A handoff is usable only for the identical ordered Site Node tree and
    // matching rail width. Reordering/editing nodes or resizing invalidates it.
    if (saved && Array.isArray(saved.keys) && Array.isArray(saved.visible)
        && saved.keys.length === keys.length
        && keys.every((key, index) => key === saved.keys[index])
        && Math.abs(saved.width - availableWidth) < 1
        && saved.visible.length > 0
        && new Set(saved.visible).size === saved.visible.length
        && saved.visible.every((index, offset) => Number.isInteger(index)
            && index >= 0 && index < widths.length
            && (offset === 0 || index === (saved.visible[offset - 1] + 1) % widths.length))
        && (saved.visible.length === 1
            || saved.visible.reduce((sum, index) => sum + widths[index], 0) <= availableWidth)
        && (activeIndex === -1 || saved.visible.includes(activeIndex))) {
        return [...saved.visible];
    }

    // Direct links have no previous window. Display the active parent/child
    // without cycling forward until it reaches the right-hand edge.
    const initial = fitNavigationWindow(widths, availableWidth);
    return activeIndex === -1 || initial.includes(activeIndex)
        ? initial
        : fitNavigationWindow(widths, availableWidth, activeIndex);
}

// Both navigation levels use the same whole-item cyclic window and never scroll pixels.
// The surface is a three-column grid: previous control, canonical content width, next control.
function initializeNavigationWindow(surface, restoredWindows, onWindowChange = () => {}) {
    const { rail, previous, next } = navigationWindowParts(surface);
    if (!(rail instanceof HTMLElement)
        || !(previous instanceof HTMLButtonElement)
        || !(next instanceof HTMLButtonElement)) {
        throw new Error('Public navigation window is missing required controls.');
    }

    const items = Array.from(rail.children).filter((item) => item.matches('[data-navigation-item]'));
    const activeIndex = items.findIndex((item) => item.classList.contains('is-active'));
    const keys = items.map((item) => item.dataset.navigationKey);
    const surfaceKey = surface.hasAttribute('data-navigation-main') ? 'main' : surface.id;
    let initialized = false;
    let widths = [];
    let visible = [];
    let availableWidth = 0;
    let cycling = false;
    let observedWidth = -1;

    const render = (indices) => {
        const changed = visible.length !== indices.length
            || visible.some((index, position) => indices[position] !== index);
        if (changed) onWindowChange(indices);

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
        cycling = needsNavigationOverflow(widths, availableWidth);

        surface.classList.toggle('is-overflowing', cycling);
        previous.hidden = !cycling;
        next.hidden = !cycling;

        if (!cycling) {
            initialized = true;
            render(items.map((_, index) => index));
            return;
        }

        let fitted;
        if (!initialized) {
            fitted = restoreNavigationWindow(
                widths, availableWidth, activeIndex, restoredWindows?.[surfaceKey], keys,
            );
        } else {
            fitted = fitNavigationWindow(widths, availableWidth, visible[0] ?? 0);
            if (activeIndex !== -1 && visible.includes(activeIndex) && !fitted.includes(activeIndex)) {
                fitted = fitNavigationWindow(widths, availableWidth, activeIndex);
            }
        }
        initialized = true;
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

    return {
        items, refresh, previous, next,
        snapshot: () => initialized && visible.length
            ? { keys, visible: [...visible], width: availableWidth }
            : null,
        key: surfaceKey,
    };
}

function submenuControls(item, region) {
    const parentLink = item.querySelector('[data-navigation-parent-link]');
    const submenuId = parentLink?.getAttribute('aria-controls');
    const submenu = submenuId ? document.getElementById(submenuId) : null;
    if (!(parentLink instanceof HTMLElement)
        || !(submenu instanceof HTMLElement)
        || !region.contains(submenu)) return null;
    return { parentLink, submenu };
}

function initializeSubmenus(container, topLevelItems, restoredWindows, windows) {
    const region = container.querySelector('[data-navigation-submenu-region]');
    const regionInner = container.querySelector('[data-navigation-submenu-region-inner]');
    if (!(region instanceof HTMLElement) || !(regionInner instanceof HTMLElement)) return () => {};

    const entries = topLevelItems
        .map((item) => ({ item, controls: submenuControls(item, region) }))
        .filter((entry) => entry.controls !== null)
        .map((entry) => ({
            ...entry,
            window: initializeNavigationWindow(entry.controls.submenu, restoredWindows),
        }));
    windows.push(...entries.map((entry) => entry.window));

    let activeEntry = null;
    let hideTimer = null;

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
        if (restoreFocus) closing.controls.parentLink.focus({ preventScroll: true });
    };
    const openEntry = (entry) => {
        clearHideTimer();
        if (activeEntry === entry) return;
        if (activeEntry !== null) hideEntryImmediately(activeEntry);
        activeEntry = entry;
        entry.controls.submenu.hidden = false;
        entry.controls.parentLink.setAttribute('aria-expanded', 'true');
        entry.item.dataset.submenuOpen = 'true';
        region.dataset.open = 'true';
        entry.window.refresh();
    };

    entries.forEach((entry) => {
        const { item, controls } = entry;
        const { parentLink, submenu } = controls;

        // No hover/focus-open side channel: parent activation owns the submenu.
        // If a parent has its own URL, first activation opens its children,
        // subsequent activation follows the URL.
        parentLink.addEventListener('click', (event) => {
            if (activeEntry !== entry) {
                event.preventDefault();
                openEntry(entry);
            } else if (parentLink instanceof HTMLButtonElement) {
                event.preventDefault();
                closeActive(true);
            }
        });

        const closeOnFocusLeave = () => window.setTimeout(() => {
            if (!item.contains(document.activeElement)
                && !submenu.contains(document.activeElement)
                && activeEntry === entry) closeActive();
        }, 0);
        item.addEventListener('focusout', closeOnFocusLeave);
        submenu.addEventListener('focusout', closeOnFocusLeave);

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
    // The selected child is part of the current route, not a transient hover
    // menu. Reopen its parent on direct URLs and after navigation handoff.
    const selected = entries.find(({ controls }) =>
        controls.submenu.querySelector('[data-navigation-item].is-active'));
    if (selected) openEntry(selected);

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

        const restoredWindows = readNavigationHandoff();
        const windows = [];
        let closeSubmenus = () => {};
        // Keep a selected child's parent expanded while its window is visible.
        const selectedParentIndex = Array.from(
            mainSurface.querySelectorAll(':scope > nav > [data-navigation-item]'),
        ).findIndex((item) => item.classList.contains('is-active')
            && !item.querySelector(':scope > .site-navigation__primary [aria-current="page"]'));

        const main = initializeNavigationWindow(mainSurface, restoredWindows, (indices) => {
            if (selectedParentIndex === -1 || !indices.includes(selectedParentIndex)) {
                closeSubmenus();
            }
        });
        windows.push(main);
        main.refresh();
        closeSubmenus = initializeSubmenus(container, main.items, restoredWindows, windows);

        container.addEventListener('click', (event) => {
            if (event.defaultPrevented || event.button !== 0
                || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey
                || !(event.target instanceof Element)) return;

            const link = event.target.closest('a[href]');
            if (!(link instanceof HTMLAnchorElement) || !container.contains(link)
                || (link.target && link.target !== '_self') || link.hasAttribute('download')) return;

            const target = new URL(link.href);
            if (target.origin !== window.location.origin) return;

            const snapshots = Object.fromEntries(
                windows.map((entry) => [entry.key, entry.snapshot()]).filter(([, value]) => value),
            );
            try {
                window.sessionStorage.setItem(NAVIGATION_HANDOFF_KEY, JSON.stringify({
                    destination: navigationDestination(target),
                    windows: snapshots,
                }));
            } catch {
                // Continue normal navigation when session storage is unavailable.
            }
        });
    });
}
