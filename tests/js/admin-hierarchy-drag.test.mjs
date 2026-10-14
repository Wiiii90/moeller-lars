import test from 'node:test';
import assert from 'node:assert/strict';
import { hierarchyMove, installAdminHierarchyDrag } from '../../resources/js/admin-hierarchy-drag.js';

test('Pages sibling sort is not a parent change', () => {
    const source = { kind: 'pages', id: 13, parent: 8, hasChildren: false };
    assert.equal(hierarchyMove(source, { kind: 'pages', action: 'nest', parent: 8 }), null);
    assert.deepEqual(hierarchyMove(source, { kind: 'pages', action: 'root', parent: null }),
        { method: 'sortSection', args: [13, 999999, 'root'] });
    assert.deepEqual(hierarchyMove({ ...source, parent: null },
        { kind: 'pages', action: 'nest', parent: 8 }),
        { method: 'sortSection', args: [13, 999999, 8] });
    assert.equal(hierarchyMove({ ...source, hasChildren: true },
        { kind: 'pages', action: 'nest', parent: 11 }), null);
});
test('Custom Image nesting and unnesting stay explicit', () => {
    const source = { kind: 'custom', index: 2, type: 'list', canNest: true, child: null };
    assert.deepEqual(hierarchyMove(source, { kind: 'custom', action: 'nest', parent: 0 }),
        { method: 'nestComponent', args: [2, 'list', 0] });
    assert.equal(hierarchyMove(source, { kind: 'custom', action: 'root', parent: null }), null);
    assert.equal(hierarchyMove({ ...source, canNest: false },
        { kind: 'custom', action: 'nest', parent: 0 }), null);
    assert.deepEqual(hierarchyMove({ ...source, image: 0, child: 1 },
        { kind: 'custom', action: 'root', parent: null }),
        { method: 'unnestImageChild', args: [0, 1] });
    assert.deepEqual(hierarchyMove({ ...source, image: 0, child: 1 },
        { kind: 'custom', action: 'nest', parent: 3 }),
        { method: 'moveImageChild', args: [0, 1, 3] });
    assert.equal(hierarchyMove({ ...source, image: 0, child: 1 },
        { kind: 'custom', action: 'nest', parent: 0 }), null);
});


test('the shared pointer controller reaches the Livewire parent action exactly once', () => {
    const listeners = new Map();
    const classes = () => {
        const set = new Set();
        return {
            add(name) { set.add(name); },
            remove(...names) { for (const name of names) set.delete(name); },
            toggle(name, enabled) { if (enabled) set.add(name); else set.delete(name); },
            contains(name) { return set.has(name); },
        };
    };
    const target = {
        dataset: { hierarchyDrop: 'nest', hierarchyParent: '0' },
        classList: classes(),
        getBoundingClientRect: () => ({ left: 200, right: 320, top: 20, bottom: 60 }),
    };
    const calls = [];
    const root = {
        dataset: { adminHierarchy: 'custom' },
        classList: classes(),
        querySelectorAll(query) {
            if (query.includes('.is-eligible')) return target.classList.contains('is-eligible') ? [target] : [];
            if (query.includes('.is-hovered')) return target.classList.contains('is-hovered') ? [target] : [];
            return [target];
        },
        closest() { return { getAttribute: () => 'component-id' }; },
    };
    const row = { dataset: { componentIndex: '1', componentType: 'list', canNest: 'true' } };
    const handle = {
        disabled: false,
        closest(selector) {
            if (selector === '[data-admin-hierarchy]') return root;
            if (selector === '[data-component-index]') return row;
            return null;
        },
        hasAttribute: () => false,
    };
    const doc = {
        addEventListener(type, callback) {
            if (!listeners.has(type)) listeners.set(type, []);
            listeners.get(type).push(callback);
        },
    };
    const priorWindow = globalThis.window;
    globalThis.window = { Livewire: { find: () => ({ $wire: { $call: (...args) => calls.push(args) } }) } };
    const fire = (type, event) => {
        for (const callback of listeners.get(type) ?? []) callback(event);
    };
    try {
        installAdminHierarchyDrag(doc);
        fire('pointerdown', {
            button: 0, target: { closest: () => handle }, clientX: 8, clientY: 8,
        });
        fire('pointermove', { clientX: 220, clientY: 40 });
        assert.equal(target.classList.contains('is-eligible'), true);
        let prevented = false;
        fire('pointerup', {
            clientX: 220, clientY: 40,
            preventDefault() { prevented = true; },
            stopImmediatePropagation() {},
        });
        assert.equal(prevented, true);
        assert.deepEqual(calls, [['nestComponent', 1, 'list', 0]]);
        assert.equal(target.classList.contains('is-eligible'), false);
        fire('pointerup', { clientX: 220, clientY: 40 });
        assert.equal(calls.length, 1);
    } finally {
        globalThis.window = priorWindow;
    }
});


test('parent content is a drop surface, while sorting cells remain outside it', () => {
    const listeners = new Map();
    const makeClassList = () => {
        const items = new Set();
        return {
            add(name) { items.add(name); },
            remove(...names) { for (const name of names) items.delete(name); },
            toggle(name, on) { if (on) items.add(name); else items.delete(name); },
            contains(name) { return items.has(name); },
        };
    };
    const content = {
        classList: makeClassList(),
        getBoundingClientRect: () => ({ left: 100, right: 280, top: 30, bottom: 65 }),
    };
    const row = { dataset: { componentIndex: '0' }, querySelector: () => content };
    const target = {
        dataset: { hierarchyDrop: 'nest', hierarchyParent: '0' },
        classList: makeClassList(),
        getBoundingClientRect: () => ({ left: 250, right: 280, top: 10, bottom: 25 }),
        closest: () => row,
    };
    const root = {
        dataset: { adminHierarchy: 'custom' }, classList: makeClassList(),
        querySelectorAll(selector) {
            if (selector.includes('.is-eligible')) return target.classList.contains('is-eligible') ? [target] : [];
            if (selector.includes('.is-hovered')) return target.classList.contains('is-hovered') ? [target] : [];
            if (selector.includes('is-hierarchy-parent-')) return [content];
            return [target];
        },
        closest: () => ({ getAttribute: () => 'the-editor' }),
    };
    const gripRow = { dataset: { componentIndex: '1', componentType: 'list', canNest: 'true' } };
    const grip = {
        disabled: false, closest(selector) {
            return selector === '[data-admin-hierarchy]' ? root
                : selector === '[data-component-index]' ? gripRow : null;
        },
        hasAttribute: () => false,
    };
    const doc = { addEventListener(type, fn) {
        listeners.set(type, [...(listeners.get(type) ?? []), fn]);
    } };
    const previous = globalThis.window;
    const calls = [];
    globalThis.window = { Livewire: { find: () => ({ $wire: { $call: (...args) => calls.push(args) } }) } };
    const fire = (type, values) => {
        for (const fn of listeners.get(type) ?? []) fn(values);
    };
    try {
        installAdminHierarchyDrag(doc);
        fire('pointerdown', { button: 0, target: { closest: () => grip }, clientX: 20, clientY: 40 });
        fire('pointermove', { clientX: 150, clientY: 46 });
        assert.equal(content.classList.contains('is-hierarchy-parent-hovered'), true);
        fire('dragend', { preventDefault() {}, stopImmediatePropagation() {} });
        assert.deepEqual(calls, [['nestComponent', 1, 'list', 0]]);
        // Outside the content surface, the same drag must stay a normal sort.
        fire('pointerdown', { button: 0, target: { closest: () => grip }, clientX: 20, clientY: 40 });
        fire('pointermove', { clientX: 88, clientY: 46 });
        fire('dragend', { preventDefault() {}, stopImmediatePropagation() {} });
        assert.equal(calls.length, 1);
    } finally {
        globalThis.window = previous;
    }
});
