function navigationCanOverflow() {
    return !window.matchMedia('(max-width: 550px)').matches;
}

function submenuFor(control) {
    const id = control.getAttribute('aria-controls');
    if (!id) {
        return null;
    }

    const submenu = document.getElementById(id);
    return submenu instanceof HTMLElement ? submenu : null;
}

function submenuControls(item) {
    const parentLink = item.querySelector('[data-navigation-parent-link]');
    const toggle = item.querySelector('[data-navigation-submenu-toggle]');
    const submenu = item.querySelector('[data-navigation-submenu]');

    if (!(parentLink instanceof HTMLAnchorElement)
        || !(toggle instanceof HTMLButtonElement)
        || !(submenu instanceof HTMLElement)) {
        return null;
    }

    return { parentLink, toggle, submenu };
}

function positionSubmenu(item, submenu) {
    if (!navigationCanOverflow()) {
        submenu.style.removeProperty('--navigation-submenu-top');
        submenu.style.removeProperty('--navigation-submenu-left');
        submenu.style.removeProperty('--navigation-submenu-width');
        return;
    }

    const rect = item.getBoundingClientRect();
    const viewportPadding = 8;
    const naturalWidth = Math.max(rect.width, submenu.scrollWidth);
    const width = Math.min(naturalWidth, window.innerWidth - viewportPadding * 2);
    const left = Math.min(
        Math.max(viewportPadding, rect.left),
        Math.max(viewportPadding, window.innerWidth - width - viewportPadding),
    );

    submenu.style.setProperty('--navigation-submenu-top', `${Math.round(rect.bottom)}px`);
    submenu.style.setProperty('--navigation-submenu-left', `${Math.round(left)}px`);
    submenu.style.setProperty('--navigation-submenu-width', `${Math.round(width)}px`);
}

function setExpanded(controls, expanded) {
    controls.parentLink.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    controls.toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
}

function closeSubmenu(item, controls, restoreFocus = false) {
    setExpanded(controls, false);
    item.dataset.submenuOpen = 'false';
    controls.submenu.hidden = true;
    controls.submenu.style.removeProperty('--navigation-submenu-top');
    controls.submenu.style.removeProperty('--navigation-submenu-left');
    controls.submenu.style.removeProperty('--navigation-submenu-width');

    if (restoreFocus) {
        controls.parentLink.focus();
    }
}

function openSubmenu(item, controls) {
    setExpanded(controls, true);
    item.dataset.submenuOpen = 'true';
    controls.submenu.hidden = false;
    positionSubmenu(item, controls.submenu);
}

function initializeSubmenus(container) {
    const entries = Array.from(container.querySelectorAll('[data-navigation-item]'))
        .filter((item) => item instanceof HTMLElement)
        .map((item) => ({ item, controls: submenuControls(item) }))
        .filter((entry) => entry.controls !== null);
    let closeTimer = null;

    const clearCloseTimer = () => {
        if (closeTimer !== null) {
            window.clearTimeout(closeTimer);
            closeTimer = null;
        }
    };

    const closeOthers = (current = null) => {
        clearCloseTimer();
        entries.forEach(({ item, controls }) => {
            if (item !== current) {
                closeSubmenu(item, controls);
            }
        });
    };

    const openEntry = (entry) => {
        clearCloseTimer();
        closeOthers(entry.item);
        openSubmenu(entry.item, entry.controls);
    };

    const scheduleDesktopClose = (entry) => {
        clearCloseTimer();
        closeTimer = window.setTimeout(() => {
            if (!entry.item.matches(':hover') && !entry.item.contains(document.activeElement)) {
                closeSubmenu(entry.item, entry.controls);
            }
            closeTimer = null;
        }, 140);
    };

    entries.forEach((entry) => {
        const { item, controls } = entry;
        const { parentLink, toggle, submenu } = controls;

        item.addEventListener('pointerenter', (event) => {
            if (event.pointerType === 'mouse' && navigationCanOverflow()) {
                openEntry(entry);
            }
        });

        item.addEventListener('pointerleave', (event) => {
            if (event.pointerType === 'mouse' && navigationCanOverflow()) {
                scheduleDesktopClose(entry);
            }
        });

        item.addEventListener('focusin', () => {
            if (navigationCanOverflow()) {
                openEntry(entry);
            }
        });

        item.addEventListener('focusout', () => {
            window.setTimeout(() => {
                if (!item.contains(document.activeElement)) {
                    closeSubmenu(item, controls);
                }
            }, 0);
        });

        parentLink.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                openEntry(entry);
                const firstLink = submenu.querySelector('a');
                if (firstLink instanceof HTMLAnchorElement) {
                    firstLink.focus();
                }
            } else if (event.key === 'Escape') {
                event.preventDefault();
                closeSubmenu(item, controls, true);
            }
        });

        toggle.addEventListener('click', () => {
            const expanded = toggle.getAttribute('aria-expanded') === 'true';
            closeOthers(item);
            expanded ? closeSubmenu(item, controls) : openSubmenu(item, controls);
        });

        toggle.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                openEntry(entry);
                const firstLink = submenu.querySelector('a');
                if (firstLink instanceof HTMLAnchorElement) {
                    firstLink.focus();
                }
            } else if (event.key === 'Escape') {
                event.preventDefault();
                closeSubmenu(item, controls, true);
            }
        });

        submenu.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                closeSubmenu(item, controls, true);
                return;
            }

            if (!['ArrowDown', 'ArrowUp'].includes(event.key)) {
                return;
            }

            const links = Array.from(submenu.querySelectorAll('a'))
                .filter((link) => link instanceof HTMLAnchorElement);
            const current = links.indexOf(document.activeElement);
            if (current === -1 || links.length === 0) {
                return;
            }

            event.preventDefault();
            const offset = event.key === 'ArrowDown' ? 1 : -1;
            links[(current + offset + links.length) % links.length].focus();
        });
    });

    document.addEventListener('pointerdown', (event) => {
        if (!(event.target instanceof Node) || container.contains(event.target)) {
            return;
        }
        closeOthers();
    });

    window.addEventListener('resize', () => {
        entries.forEach(({ item, controls }) => {
            if (controls.parentLink.getAttribute('aria-expanded') === 'true') {
                positionSubmenu(item, controls.submenu);
            }
        });
    }, { passive: true });

    return closeOthers;
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
            closeSubmenus();
            scroller.scrollBy({
                left: direction * Math.max(180, scroller.clientWidth * .55),
                behavior: 'smooth',
            });
        };

        previous.addEventListener('click', () => scrollByPage(-1));
        next.addEventListener('click', () => scrollByPage(1));
        scroller.addEventListener('scroll', () => {
            closeSubmenus();
            update();
        }, { passive: true });

        scroller.addEventListener('pointerdown', (event) => {
            if (event.pointerType !== 'mouse'
                || !container.classList.contains('is-overflowing')
                || (event.target instanceof Element && event.target.closest('[data-navigation-submenu-toggle]'))) {
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
