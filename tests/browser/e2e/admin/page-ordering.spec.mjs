import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';
import { signIn } from '../../support/admin.mjs';

test.setTimeout(60_000);

// Read-only evidence from the independently verified disposable browser DB.
function snapshot() {
    const container = process.env.PLAYWRIGHT_DATABASE_CONTAINER;
    const database = process.env.PLAYWRIGHT_DATABASE_NAME;
    const user = process.env.PLAYWRIGHT_DATABASE_USER;
    expect(container, 'Supply the verified disposable PostgreSQL container').toBeTruthy();
    expect(database).toBeTruthy();
    expect(user).toBeTruthy();
    const query = `select json_build_object(
        'pages', (select json_agg(p order by p.parent_id nulls first, p.position, p.id)
            from (select id, parent_id, position, type, title from site_sections) p),
        'audits', (select count(*) from audit_events),
        'lastAudit', (select max(id) from audit_events),
        'notifications', coalesce((select json_agg(n order by n.id) from
            (select id, source_id, status from admin_notifications) n), '[]'::json),
        'reorders', (select count(*) from audit_events where action = 'site_section.reordered')
    )`;
    return JSON.parse(execFileSync('docker', ['exec', container, 'psql', '-XAt', '-U', user,
        '-d', database, '-c', query], { encoding: 'utf8' }).trim());
}

const row = (page, id) => page.locator(`[data-section-id="${id}"]`);
const roots = state => state.pages.filter(p => p.parent_id === null).sort((a, b) => a.position - b.position);

async function expectProjection(page, state) {
    const ordered = roots(state);
    expect(ordered[0].type).toBe('home');
    await expect.poll(() => page.locator('[data-depth="0"]').evaluateAll(nodes => nodes.map(n => Number(n.dataset.sectionId))))
        .toEqual(ordered.map(p => p.id));
    for (const [index, item] of ordered.entries()) {
        await expect(row(page, item.id).locator('.admin-position')).toHaveText(String(index + 1));
    }
    await expect(row(page, ordered[0].id).locator('[data-hierarchy-drag-handle]')).toHaveCount(0);
}

async function drag(page, handle, target, fraction = 0.35) {
    await handle.scrollIntoViewIfNeeded();
    await target.scrollIntoViewIfNeeded();
    const from = await handle.boundingBox();
    const to = await target.boundingBox();
    expect(from).not.toBeNull();
    expect(to).not.toBeNull();
    const position = await target.getAttribute('data-section-id')
        ? await target.locator('.admin-position').boundingBox() : null;
    // Root ordering uses the position rail, outside nested child drop zones.
    const x = position ? position.x + position.width / 2 : to.x + to.width / 2;
    if (to.height === 0) {
        // An empty native Sortable group accepts a drop at its boundary. It has
        // no box for dragTo's target visibility check, so use the real pointer.
        await page.mouse.move(from.x + from.width / 2, from.y + from.height / 2);
        await page.mouse.down();
        await page.mouse.move(from.x + from.width / 2 + 12, from.y + from.height / 2, { steps: 5 });
        await page.mouse.move(x, to.y, { steps: 20 });
        await page.mouse.move(x, to.y);
        await page.mouse.up();
    } else {
        await handle.dragTo(target, { targetPosition: { x: x - to.x, y: to.height * fraction } });
    }
}

async function expectChange(page, before, eventCount) {
    await expect.poll(() => snapshot().reorders).toBe(before.reorders + 1);
    const added = state => state.notifications.filter(n => !before.notifications.some(old => old.id === n.id));
    await expect.poll(() => added(snapshot()).length).toBe(1);
    const after = snapshot();
    expect(after.audits).toBe(before.audits + 1);
    expect(added(after)).toEqual([{ id: added(after)[0].id, source_id: `activity:${after.lastAudit}`, status: 'success' }]);
    await expect.poll(() => page.evaluate(() => window.pageOrderFeedback.length)).toBe(eventCount + 1);
    expect(await page.evaluate(() => window.pageOrderFeedback.at(-1).notification))
        .toMatchObject({ notification_id: added(after)[0].id, status: 'success' });
    await expectProjection(page, after);
    return after;
}

test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
        window.pageOrderFeedback = [];
        window.addEventListener('admin-header-notification', event => window.pageOrderFeedback.push(event.detail));
    });
    await signIn(page);
    await page.goto('/admin/pages');
});

test('Pages native root drag persists order, keeps Home first and leaves no-ops silent', async ({ page }) => {
    const before = snapshot();
    const [first, second] = roots(before).filter(p => /^Browser fixture page [12]$/.test(p.title));
    expect(first.parent_id).toBeNull();
    expect(second.parent_id).toBeNull();
    await drag(page, row(page, second.id).getByRole('button', { name: /^Drag / }), row(page, first.id));
    const after = await expectChange(page, before, 0);
    expect(after.pages.find(p => p.id === second.id).parent_id).toBeNull();
    const expectedOrder = roots(before).map(p => p.id);
    const firstIndex = expectedOrder.indexOf(first.id);
    const secondIndex = expectedOrder.indexOf(second.id);
    [expectedOrder[firstIndex], expectedOrder[secondIndex]] = [second.id, first.id];
    expect(roots(after).map(p => p.id)).toEqual(expectedOrder);
    expect(roots(after).map(p => p.position)).toEqual(roots(after).map((_, index) => (index + 1) * 10));
    await page.reload();
    await expectProjection(page, after);

    // A real gesture released within its own row must not report a mutation.
    const feedback = await page.evaluate(() => window.pageOrderFeedback.length);
    await drag(page, row(page, second.id).getByRole('button', { name: /^Drag / }), row(page, second.id), 0.5);
    await expectProjection(page, after);
    expect(snapshot()).toEqual(after);
    expect(await page.evaluate(() => window.pageOrderFeedback.length)).toBe(feedback);

    const move = row(page, second.id).getByRole('button', { name: /^Move .* down$/ });
    await expect(move).toBeEnabled();
    await move.click();
    await expectChange(page, after, feedback);
});

test('Pages native cross-group drag persists hierarchy and filtered views disable ordering', async ({ page }) => {
    const before = snapshot();
    const parent = before.pages.find(p => p.title === 'Browser fixture page 1');
    const child = before.pages.find(p => p.title === 'Browser fixture page 2');
    const children = page.getByRole('rowgroup', { name: `Child pages under ${parent.title}`, exact: true });
    await expect(children).toHaveAttribute('data-drop-target', 'true');
    await drag(page, row(page, child.id).getByRole('button', { name: /^Drag / }), children, 0.5);
    const nested = await expectChange(page, before, 0);
    expect(nested.pages.find(p => p.id === child.id)).toMatchObject({ parent_id: parent.id, position: 10 });
    await expect(children.locator(`[data-section-id="${child.id}"]`)).toHaveAttribute('data-depth', '1');
    await page.reload();
    await expect(row(page, child.id)).toHaveAttribute('data-parent-id', String(parent.id));
    await expectProjection(page, nested);

    await drag(page, row(page, child.id).getByRole('button', { name: /^Drag / }), row(page, parent.id));
    const restored = await expectChange(page, nested, 0);
    expect(restored.pages.find(p => p.id === child.id).parent_id).toBeNull();
    await page.reload();
    await expectProjection(page, restored);
    await expect(row(page, child.id)).toHaveAttribute('data-depth', '0');

    await page.getByPlaceholder('Search pages').fill('Browser fixture page');
    const handles = page.locator('[data-hierarchy-drag-handle]');
    await expect(handles).toHaveCount(2);
    for (const handle of await handles.all()) await expect(handle).toBeDisabled();
    await expect(row(page, child.id).getByRole('button', { name: /^Move .* down$/ })).toBeDisabled();
    const filtered = snapshot();
    await drag(page, handles.last(), row(page, parent.id));
    expect(snapshot()).toEqual(filtered);
    expect(await page.evaluate(() => window.pageOrderFeedback.length)).toBe(0);
    await page.getByPlaceholder('Search pages').fill('');
    await expect(row(page, child.id).getByRole('button', { name: /^Drag / })).toBeEnabled();
    await expectProjection(page, restored);
});
