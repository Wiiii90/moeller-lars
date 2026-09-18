let activeController = null;
let enhanceFrame = null;
let livewireHookRegistered = false;
let selectId = 0;

const controllers = new Map();
const workspaceSelectSelector = [
    '.admin-workspace select:not([multiple]):not([size])',
    '.admin-inline-select:not([multiple]):not([size])',
].join(', ');

function directLabel(select) {
    const parent = select.parentElement;

    if (! parent?.matches('label')) return null;

    return parent.querySelector(':scope > span')?.textContent?.trim() || null;
}

function accessibleLabel(select) {
    return select.getAttribute('aria-label')
        || directLabel(select)
        || select.name
        || 'Select option';
}

function optionSignature(select) {
    return Array.from(select.children).map((child) => {
        if (child instanceof HTMLOptGroupElement) {
            return `group:${child.label}:${child.disabled}:${Array.from(child.children).map((option) => `${option.value}:${option.textContent}:${option.disabled}`).join('|')}`;
        }

        return `option:${child.value}:${child.textContent}:${child.disabled}`;
    }).join('||');
}

function closeActive(except = null) {
    if (activeController && activeController !== except) {
        activeController.close();
    }
}

function createController(select) {
    const id = ++selectId;
    const root = document.createElement('span');
    const trigger = document.createElement('span');
    const value = document.createElement('span');
    const menu = document.createElement('span');
    const originalTabIndex = select.getAttribute('tabindex');
    const originalAriaHidden = select.getAttribute('aria-hidden');
    let signature = '';
    let open = false;

    root.className = 'admin-select';
    root.dataset.adminSelect = '';

    if (select.classList.contains('admin-inline-select')) {
        root.classList.add('admin-select--inline');
    }

    if (select.closest('.admin-pager__size, .media-workspace__pager-size')) {
        root.classList.add('admin-select--compact');
    }

    trigger.className = 'admin-select__trigger';
    trigger.setAttribute('role', 'combobox');
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-controls', `admin-select-menu-${id}`);
    trigger.setAttribute('aria-label', accessibleLabel(select));
    trigger.tabIndex = select.disabled ? -1 : 0;

    value.className = 'admin-select__value';
    value.setAttribute('aria-hidden', 'true');
    trigger.append(value);

    menu.className = 'admin-select__menu';
    menu.id = `admin-select-menu-${id}`;
    menu.setAttribute('role', 'listbox');
    menu.setAttribute('aria-label', accessibleLabel(select));
    menu.hidden = true;

    root.append(trigger);
    select.insertAdjacentElement('afterend', root);
    document.body.append(menu);

    select.classList.add('admin-controlled-select__native');
    select.setAttribute('tabindex', '-1');
    select.setAttribute('aria-hidden', 'true');

    function selectedOption() {
        return select.selectedOptions[0] || select.options[select.selectedIndex] || null;
    }

    function positionMenu() {
        if (! open) return;

        const rect = trigger.getBoundingClientRect();
        const viewportPadding = 12;
        const gap = 5;
        const availableHeight = Math.max(96, window.innerHeight - rect.bottom - gap - viewportPadding);

        menu.style.top = `${Math.round(rect.bottom + gap)}px`;
        menu.style.left = `${Math.round(Math.max(viewportPadding, Math.min(rect.left, window.innerWidth - rect.width - viewportPadding)))}px`;
        menu.style.width = `${Math.round(Math.min(rect.width, window.innerWidth - (viewportPadding * 2)))}px`;
        menu.style.maxHeight = `${Math.floor(Math.min(288, availableHeight))}px`;
    }

    function focusRelativeOption(direction) {
        const options = Array.from(menu.querySelectorAll('.admin-select__option:not(:disabled)'));
        if (options.length === 0) return;

        const currentIndex = options.indexOf(document.activeElement);
        const selectedIndex = options.findIndex((option) => option.getAttribute('aria-selected') === 'true');
        const baseIndex = currentIndex >= 0 ? currentIndex : selectedIndex;
        const nextIndex = baseIndex < 0
            ? (direction > 0 ? 0 : options.length - 1)
            : (baseIndex + direction + options.length) % options.length;

        options[nextIndex]?.focus();
    }

    function selectValue(nextValue) {
        const option = Array.from(select.options).find((candidate) => candidate.value === nextValue);
        if (! option || option.disabled || select.disabled) return;

        const changed = select.value !== nextValue;
        select.value = nextValue;
        sync();
        close();
        trigger.focus({ preventScroll: true });

        if (changed) {
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function appendOption(option, container) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'admin-select__option';
        button.setAttribute('role', 'option');
        button.dataset.value = option.value;
        button.disabled = option.disabled;
        button.textContent = option.textContent?.trim() || option.value;
        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            selectValue(option.value);
        });
        container.append(button);
    }

    function rebuildOptions() {
        signature = optionSignature(select);
        menu.replaceChildren();

        for (const child of select.children) {
            if (child instanceof HTMLOptGroupElement) {
                const group = document.createElement('span');
                const heading = document.createElement('span');

                group.className = 'admin-select__group';
                heading.className = 'admin-select__group-label';
                heading.textContent = child.label;
                group.append(heading);

                for (const option of child.children) {
                    appendOption(option, group);
                }

                menu.append(group);
                continue;
            }

            if (child instanceof HTMLOptionElement) {
                appendOption(child, menu);
            }
        }
    }

    function sync() {
        select.classList.add('admin-controlled-select__native');
        select.setAttribute('tabindex', '-1');
        select.setAttribute('aria-hidden', 'true');

        const nextSignature = optionSignature(select);
        if (signature !== nextSignature) rebuildOptions();

        const selected = selectedOption();
        value.textContent = selected?.textContent?.trim() || '';
        trigger.setAttribute('aria-label', accessibleLabel(select));
        trigger.setAttribute('aria-disabled', select.disabled ? 'true' : 'false');
        trigger.tabIndex = select.disabled ? -1 : 0;
        root.classList.toggle('is-disabled', select.disabled);

        for (const option of menu.querySelectorAll('.admin-select__option')) {
            const isSelected = option.dataset.value === select.value;
            option.classList.toggle('is-selected', isSelected);
            option.setAttribute('aria-selected', isSelected ? 'true' : 'false');
        }

        if (open) positionMenu();
    }

    function openMenu(focusOption = false) {
        if (open || select.disabled) return;

        closeActive(controller);
        sync();
        open = true;
        activeController = controller;
        menu.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        document.body.classList.add('admin-dropdown-open');
        window.addEventListener('resize', positionMenu);
        window.addEventListener('scroll', positionMenu, true);

        requestAnimationFrame(() => {
            const rect = trigger.getBoundingClientRect();
            const gap = 5;
            const viewportPadding = 12;
            const menuHeight = Math.min(menu.scrollHeight, 288);
            const overflow = rect.bottom + gap + menuHeight + viewportPadding - window.innerHeight;

            if (overflow > 0) {
                window.scrollBy({ top: overflow, left: 0, behavior: 'auto' });
            }

            requestAnimationFrame(() => {
                positionMenu();
                if (focusOption) focusRelativeOption(1);
            });
        });
    }

    function close() {
        if (! open) return;

        open = false;
        menu.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
        window.removeEventListener('resize', positionMenu);
        window.removeEventListener('scroll', positionMenu, true);

        if (activeController === controller) {
            activeController = null;
            document.body.classList.remove('admin-dropdown-open');
        }
    }

    function destroy() {
        close();
        select.removeEventListener('change', sync);
        select.removeEventListener('input', sync);
        root.remove();
        menu.remove();
        select.classList.remove('admin-controlled-select__native');

        if (originalTabIndex === null) select.removeAttribute('tabindex');
        else select.setAttribute('tabindex', originalTabIndex);

        if (originalAriaHidden === null) select.removeAttribute('aria-hidden');
        else select.setAttribute('aria-hidden', originalAriaHidden);
    }

    trigger.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        open ? close() : openMenu();
    });

    trigger.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            open ? close() : openMenu(true);
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            if (! open) openMenu(true);
            else focusRelativeOption(1);
            return;
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            if (! open) openMenu(true);
            else focusRelativeOption(-1);
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            close();
        }
    });

    menu.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            focusRelativeOption(event.key === 'ArrowDown' ? 1 : -1);
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            close();
            trigger.focus({ preventScroll: true });
        }
    });

    select.addEventListener('change', sync);
    select.addEventListener('input', sync);

    const controller = { close, destroy, root, select, sync };
    rebuildOptions();
    sync();

    return controller;
}

function enhanceWorkspaceSelects() {
    enhanceFrame = null;

    for (const [select, controller] of controllers) {
        if (! select.isConnected || ! controller.root.isConnected) {
            controller.destroy();
            controllers.delete(select);
        }
    }

    for (const select of document.querySelectorAll(workspaceSelectSelector)) {
        const existing = controllers.get(select);

        if (existing) {
            existing.sync();
            continue;
        }

        controllers.set(select, createController(select));
    }
}

function scheduleEnhancement() {
    if (enhanceFrame !== null) return;
    enhanceFrame = window.requestAnimationFrame(enhanceWorkspaceSelects);
}

function registerLivewireHook() {
    if (livewireHookRegistered || ! window.Livewire?.hook) return;

    livewireHookRegistered = true;
    window.Livewire.hook('morph.updated', scheduleEnhancement);
}

document.addEventListener('pointerdown', (event) => {
    if (! activeController) return;

    const target = event.target;
    if (activeController.root.contains(target)) return;

    const menu = document.getElementById(activeController.root.querySelector('[aria-controls]')?.getAttribute('aria-controls'));
    if (menu?.contains(target)) return;

    activeController.close();
}, true);

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', scheduleEnhancement, { once: true });
} else {
    scheduleEnhancement();
}

registerLivewireHook();
document.addEventListener('livewire:init', registerLivewireHook, { once: true });
document.addEventListener('livewire:navigated', () => {
    activeController?.close();
    scheduleEnhancement();
});
