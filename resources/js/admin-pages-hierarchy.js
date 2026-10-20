/* Native Livewire sorting owns sibling order. Explicit targets alone
 * change the parent via the existing sortSection action. The sort groups are
 * disjoint, so neither owner receives the other's gesture. */
export function pagesDropIntent(source, target) {
    if (!source || !target) return null;
    if (target.kind === 'root') return source.parent === null ? null : [source.id, 999999, 'root'];
    if (target.kind !== 'child' || source.hasChildren || source.id === target.parent || source.parent === target.parent) return null;
    return [source.id, 999999, target.parent];
}

export function installPagesHierarchyDrag(doc = document) {
    if (doc.__pagesHierarchyDragInstalled) return;
    doc.__pagesHierarchyDragInstalled = true;
    let active = null;
    const all = selector => active?.root.querySelectorAll(selector) ?? [];
    const target = element => element.dataset.pagesDrop === 'root'
        ? { kind: 'root' } : { kind: 'child', parent: Number(element.dataset.pagesParent) };

    function reset() {
        for (const element of all('[data-pages-drop]')) {
            element.classList.remove('is-page-drop-eligible', 'is-page-drop-hovered');
        }
        active = null;
    }
    function beginDrag() {
        if (!active || active.dragging) return;
        active.dragging = true;
        for (const element of all('[data-pages-drop]')) {
            element.classList.toggle('is-page-drop-eligible', pagesDropIntent(active.source, target(element)) !== null);
        }
    }
    function hit(x, y) {
        if (!active?.dragging) return null;
        for (const element of all('[data-pages-drop].is-page-drop-eligible')) {
            const r = element.getBoundingClientRect();
            if (r.left <= x && x <= r.right && r.top <= y && y <= r.bottom) return element;
        }
        return null;
    }
    function highlight(x, y) {
        const found = hit(x, y);
        for (const element of all('.is-page-drop-hovered')) element.classList.remove('is-page-drop-hovered');
        found?.classList.add('is-page-drop-hovered');
        return found;
    }
    function apply(event) {
        if (!active?.dragging) return false;
        const found = highlight(event.clientX, event.clientY);
        const args = found && pagesDropIntent(active.source, target(found));
        if (!args) return false;
        const owner = active.root.closest('[wire\\:id]');
        const component = owner && window.Livewire?.find(owner.getAttribute('wire:id'));
        const wire = component?.$wire ?? component;
        if (!wire || (typeof wire.$call !== 'function' && typeof wire.sortSection !== 'function')) return false;
        event.preventDefault?.();
        event.stopImmediatePropagation?.();
        if (typeof wire.$call === 'function') wire.$call('sortSection', ...args);
        else wire.sortSection(...args);
        reset();
        return true;
    }
    doc.addEventListener('pointerdown', event => {
        if (event.button !== 0) return;
        const handle = event.target.closest?.('[data-hierarchy-drag-handle]:not(:disabled)');
        const root = handle?.closest('[data-admin-hierarchy="pages"]');
        const row = handle?.closest('[data-section-id]');
        reset();
        if (!root || !row) return;
        active = { root, source: {
            id: Number(row.dataset.sectionId),
            parent: row.dataset.parentId === '' ? null : Number(row.dataset.parentId),
            hasChildren: row.dataset.hasChildren === 'true',
        }, x: event.clientX, y: event.clientY,
        lastX: event.clientX, lastY: event.clientY, dragging: false };
    }, true);
    doc.addEventListener('pointermove', event => {
        if (!active) return;
        if (!active.dragging && (event.buttons & 1) === 1 &&
            Math.hypot(event.clientX - active.x, event.clientY - active.y) >= 4) beginDrag();
        if (active.dragging) {
            active.lastX = event.clientX;
            active.lastY = event.clientY;
            highlight(event.clientX, event.clientY);
        }
    }, true);
    doc.addEventListener('dragstart', () => { if (active) beginDrag(); }, true);
    doc.addEventListener('dragover', event => {
        if (!active?.dragging) return;
        active.lastX = event.clientX;
        active.lastY = event.clientY;
        if (highlight(event.clientX, event.clientY)) event.preventDefault();
    }, true);
    doc.addEventListener('pointerup', event => { apply(event); reset(); }, true);
    doc.addEventListener('drop', event => { apply(event); reset(); }, true);
    doc.addEventListener('dragend', event => {
        if (active?.dragging) apply({
            clientX: active.lastX, clientY: active.lastY,
            preventDefault: () => event.preventDefault?.(),
            stopImmediatePropagation: () => event.stopImmediatePropagation?.(),
        });
        reset();
    }, true);
    doc.addEventListener('pointercancel', reset, true);
    doc.addEventListener('livewire:navigated', reset);
}
if (typeof document !== 'undefined') installPagesHierarchyDrag(document);
