/**
 * Shared hierarchy drag intent: native Livewire Sortable handles only sibling
 * reordering. Nesting and returning to the top level are explicit drop targets.
 * No Alpine shadow state, timers, DOM copies or synthetic sort events.
 */
export function hierarchyMove(source, target) {
    if (!source || !target || source.kind !== target.kind) return null;
    if (source.kind === 'pages') {
        if (target.action === 'root') {
            return source.parent === null ? null
                : { method: 'sortSection', args: [source.id, 999999, 'root'] };
        }
        if (target.action !== 'nest' || source.hasChildren ||
            source.id === target.parent || source.parent === target.parent) return null;
        return { method: 'sortSection', args: [source.id, 999999, target.parent] };
    }
    if (source.kind === 'custom') {
        if (target.action === 'root') {
            return source.child === null ? null
                : { method: 'unnestImageChild', args: [source.image, source.child] };
        }
        if (target.action !== 'nest' || !source.canNest) return null;
        if (source.child !== null) {
            return source.image === target.parent ? null
                : { method: 'moveImageChild', args: [source.image, source.child, target.parent] };
        }
        if (source.index === target.parent) return null;
        return { method: 'nestComponent', args: [source.index, source.type, target.parent] };
    }
    return null;
}

let active = null;

function sourceFromHandle(handle) {
    const root = handle.closest('[data-admin-hierarchy]');
    if (!root || handle.disabled) return null;
    const kind = root.dataset.adminHierarchy;
    if (kind === 'pages') {
        const row = handle.closest('[data-section-id]');
        if (!row) return null;
        return { root, kind, id: Number(row.dataset.sectionId),
            parent: row.dataset.parentId === '' ? null : Number(row.dataset.parentId),
            hasChildren: row.dataset.hasChildren === 'true' };
    }
    if (kind === 'custom') {
        const row = handle.closest('[data-component-index]');
        if (!row) return null;
        const child = handle.hasAttribute('data-child-index') ? Number(handle.dataset.childIndex) : null;
        return { root, kind, index: Number(row.dataset.componentIndex),
            type: child === null ? row.dataset.componentType : handle.dataset.childType,
            canNest: child === null ? row.dataset.canNest === 'true' : true,
            image: handle.hasAttribute('data-image-index') ? Number(handle.dataset.imageIndex) : null,
            child };
    }
    return null;
}

function targetFromElement(el, kind) {
    return { kind, action: el.dataset.hierarchyDrop,
        parent: el.dataset.hierarchyParent === 'root' ? null : Number(el.dataset.hierarchyParent) };
}

function setDragging() {
    if (!active || active.dragging) return;
    active.dragging = true;
    active.root.classList.add('is-hierarchy-dragging');
    for (const el of active.root.querySelectorAll('[data-hierarchy-drop]')) {
        const intent = hierarchyMove(active.source, targetFromElement(el, active.source.kind));
        el.classList.toggle('is-eligible', intent !== null);
    }
}

function hitTarget(x, y) {
    if (!active?.dragging) return null;
    // ElementFromPoint may return a Sortable ghost; test the real target rects.
    const eligible = active.root.querySelectorAll('[data-hierarchy-drop].is-eligible');
    for (const el of eligible) {
        const rect = el.getBoundingClientRect();
        if (x >= rect.left && x <= rect.right && y >= rect.top && y <= rect.bottom) return el;
    }
    return null;
}

function highlight(x, y) {
    const hovered = hitTarget(x, y);
    active?.root.querySelectorAll('[data-hierarchy-drop].is-hovered')
        .forEach(el => el.classList.remove('is-hovered'));
    hovered?.classList.add('is-hovered');
    return hovered;
}

function reset() {
    if (!active) return;
    active.root.classList.remove('is-hierarchy-dragging');
    active.root.querySelectorAll('[data-hierarchy-drop]')
        .forEach(el => el.classList.remove('is-eligible', 'is-hovered'));
    active = null;
}

function applyDrop(event) {
    if (!active?.dragging) return false;
    const el = highlight(event.clientX, event.clientY);
    if (!el) return false;
    const intent = hierarchyMove(active.source, targetFromElement(el, active.source.kind));
    if (!intent) return false;
    const wireRoot = active.root.closest('[wire\\:id]');
    const component = wireRoot && window.Livewire?.find(wireRoot.getAttribute('wire:id'));
    const wire = component?.$wire ?? component;
    if (!wire || (typeof wire.$call !== 'function' && typeof wire[intent.method] !== 'function')) return false;
    event.preventDefault();
    event.stopImmediatePropagation();
    if (typeof wire.$call === 'function') {
        wire.$call(intent.method, ...intent.args);
    } else {
        wire[intent.method](...intent.args);
    }
    reset();
    return true;
}

export function installAdminHierarchyDrag(doc = document) {
    if (doc.__adminHierarchyInstalled) return;
    doc.__adminHierarchyInstalled = true;
    doc.addEventListener('pointerdown', e => {
        if (e.button !== 0) return;
        const handle = e.target.closest?.('[data-hierarchy-drag-handle]:not(:disabled)');
        const source = handle && sourceFromHandle(handle);
        reset();
        if (source) active = { source, root: source.root, x: e.clientX, y: e.clientY, dragging: false };
    }, true);
    doc.addEventListener('pointermove', e => {
        if (!active) return;
        if (!active.dragging && Math.hypot(e.clientX - active.x, e.clientY - active.y) >= 4) setDragging();
        if (active.dragging) highlight(e.clientX, e.clientY);
    }, true);
    doc.addEventListener('dragstart', () => { if (active) setDragging(); }, true);
    doc.addEventListener('dragover', e => {
        if (active?.dragging && highlight(e.clientX, e.clientY)) e.preventDefault();
    }, true);
    doc.addEventListener('pointerup', e => { applyDrop(e); reset(); }, true);
    doc.addEventListener('drop', e => { applyDrop(e); reset(); }, true);
    doc.addEventListener('pointercancel', reset, true);
    doc.addEventListener('dragend', reset, true);
    doc.addEventListener('livewire:navigated', reset);
}
if (typeof document !== 'undefined') installAdminHierarchyDrag(document);
