import test from 'node:test';
import assert from 'node:assert/strict';
import { hierarchyMove } from '../../resources/js/admin-hierarchy-drag.js';

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
});
