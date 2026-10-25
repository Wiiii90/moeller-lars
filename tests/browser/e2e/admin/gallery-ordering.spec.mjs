import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';
import { signIn } from '../../support/admin.mjs';

test.setTimeout(90_000);
test.use({ viewport: { width: 1800, height: 1200 } });

// Read-only assertions against the independently verified disposable DB.
function snapshot() {
    const container = process.env.PLAYWRIGHT_DATABASE_CONTAINER;
    const database = process.env.PLAYWRIGHT_DATABASE_NAME;
    const user = process.env.PLAYWRIGHT_DATABASE_USER;
    expect(container, 'Supply the verified disposable PostgreSQL container').toBeTruthy();
    expect(database).toBeTruthy();
    expect(user).toBeTruthy();
    const query = `select json_build_object(
        'gallery', (select id from artwork_categories where slug='browser-sort-gallery'),
        'working', (select json_agg(a order by a.position, a.id) from
            (select id,title,position from public.artworks where artwork_category_id=
                (select id from artwork_categories where slug='browser-sort-gallery')) a),
        'committed', (select json_agg(a order by a.position, a.id) from
            (select id,title,position from committed.artworks where artwork_category_id=
                (select id from artwork_categories where slug='browser-sort-gallery')) a),
        'viewer', (select json_agg(a order by a.id) from
            (select id,position from public.artworks where artwork_category_id=
                (select id from artwork_categories where slug='browser-gallery')) a),
        'audits', (select count(*) from audit_events),
        'lastAudit', (select max(id) from audit_events),
        'notifications', coalesce((select json_agg(n order by n.id) from
            (select id,source_id,status from admin_notifications) n), '[]'::json)
    )`;
    return JSON.parse(execFileSync('docker', ['exec', container, 'psql', '-XAt', '-U', user,
        '-d', database, '-c', query], { encoding: 'utf8' }).trim());
}

const grid = page => page.getByRole('region', { name: 'Artwork sequence for Browser Sort Gallery' });
const card = (page, artwork) => grid(page).getByRole('article').filter({
    has: page.getByRole('button', { name: `Preview ${artwork.title}`, exact: true }),
});
const handle = (page, artwork) => card(page, artwork).getByRole('button', { name: `Drag ${artwork.title} to reorder`, exact: true });
const events = page => page.evaluate(() => window.galleryOrderFeedback.length);

async function projection(page, state) {
    await expect(grid(page).getByRole('article')).toHaveCount(3);
    await expect.poll(() => grid(page).getByRole('button', { name: /^Preview Browser Sort Artwork/ })
        .evaluateAll(nodes => nodes.map(node => node.getAttribute('aria-label').replace(/^Preview /, ''))))
        .toEqual(state.working.map(a => a.title));
    for (const [index, artwork] of state.working.entries()) {
        await expect(card(page, artwork).locator('.admin-gallery-grid__sequence')).toHaveText(String(index + 1).padStart(2, '0'));
    }
}

async function changed(page, before, eventCount, expectedIds) {
    await expect.poll(() => snapshot().working.map(a => a.id)).toEqual(expectedIds);
    await expect.poll(() => events(page)).toBe(eventCount + 1);
    const after = snapshot();
    expect(after.audits).toBe(before.audits + 1);
    const added = after.notifications.filter(n => !before.notifications.some(old => old.id === n.id));
    expect(added).toHaveLength(1);
    expect(added[0]).toMatchObject({ source_id: `activity:${after.lastAudit}`, status: 'success' });
    expect(await page.evaluate(() => window.galleryOrderFeedback.at(-1).notification))
        .toMatchObject({ notification_id: added[0].id, status: 'success' });
    expect(after.working.map(a => a.position)).toEqual([0, 1, 2]);
    expect(after.committed).toEqual(before.committed);
    expect(after.viewer).toEqual(before.viewer);
    await projection(page, after);
    return after;
}

async function dragBefore(page, source, destination) {
    const target = card(page, destination);
    const box = await target.boundingBox();
    expect(box).not.toBeNull();
    await handle(page, source).dragTo(target, { targetPosition: { x: box.width * 0.15, y: box.height * 0.25 } });
}

test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
        window.galleryOrderFeedback = [];
        window.addEventListener('admin-header-notification', event => window.galleryOrderFeedback.push(event.detail));
    });
    await signIn(page);
    const state = snapshot();
    expect(state.working).toHaveLength(3);
    await page.goto(`/admin/pages/gallery/${state.gallery}`);
    await projection(page, state);
    await expect(grid(page).locator('img')).toHaveCount(3);
    await expect.poll(() => grid(page).locator('img').evaluateAll(images =>
        images.every(image => image.complete && image.naturalWidth > 0))).toBe(true);
});

test('Gallery native handle drag persists Working order with one feedback and silent boundaries', async ({ page }) => {
    const before = snapshot();
    const [first, middle, last] = before.working;
    await dragBefore(page, last, first);
    const reordered = await changed(page, before, 0, [last.id, first.id, middle.id]);
    expect(reordered.working).not.toEqual(reordered.committed);
    await page.reload();
    await projection(page, reordered);
    expect(snapshot()).toEqual(reordered);

    await expect(card(page, last).getByRole('button', { name: `Move ${last.title} earlier`, exact: true })).toBeDisabled();
    await expect(card(page, middle).getByRole('button', { name: `Move ${middle.title} later`, exact: true })).toBeDisabled();
    const ownBox = await card(page, last).boundingBox();
    await handle(page, last).dragTo(card(page, last), { targetPosition: { x: ownBox.width / 2, y: ownBox.height / 2 } });
    await projection(page, reordered);
    expect(snapshot()).toEqual(reordered);
    expect(await events(page)).toBe(0);

    await card(page, last).getByRole('button', { name: `Move ${last.title} later`, exact: true }).click();
    const moved = await changed(page, reordered, 0, [first.id, last.id, middle.id]);
    await page.reload();
    await projection(page, moved);
    expect(snapshot()).toEqual(moved);
});

test('Gallery search, status and readiness block handles until Clear restores native sorting', async ({ page }) => {
    const before = snapshot();
    const controls = page.getByLabel('Gallery controls');
    const clear = controls.getByRole('button', { name: 'Clear', exact: true });
    for (const apply of [
        () => page.getByPlaceholder('Title, material, dimensions').fill('Browser Sort Artwork'),
        () => controls.getByRole('combobox', { name: 'Status', exact: true }).selectOption('published'),
        () => controls.getByRole('combobox', { name: 'Readiness', exact: true }).selectOption('ready'),
    ]) {
        await apply();
        await projection(page, before);
        const handles = grid(page).getByRole('button', { name: /^Clear filters to reorder Browser Sort Artwork/ });
        await expect(handles).toHaveCount(9);
        // Each artwork has a handle and two fallback order actions; all are disabled.
        for (const control of await handles.all()) await expect(control).toBeDisabled();
        expect(snapshot()).toEqual(before);
        expect(await events(page)).toBe(0);
        await clear.click();
        for (const artwork of before.working) await expect(handle(page, artwork)).toBeEnabled();
    }
    const [first, middle, last] = before.working;
    await dragBefore(page, last, first);
    const after = await changed(page, before, 0, [last.id, first.id, middle.id]);
    await page.reload();
    await projection(page, after);
    expect(snapshot()).toEqual(after);
});
