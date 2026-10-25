import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';
import { signIn } from '../../support/admin.mjs';

test.setTimeout(120_000);
test.use({ viewport: { width: 1800, height: 1200 } });

// Read-only evidence from the independently verified disposable database.
function snapshot(template) {
    const container = process.env.PLAYWRIGHT_DATABASE_CONTAINER;
    const database = process.env.PLAYWRIGHT_DATABASE_NAME;
    const user = process.env.PLAYWRIGHT_DATABASE_USER;
    expect(container, 'Supply the verified disposable PostgreSQL container').toBeTruthy();
    expect(database).toBeTruthy();
    expect(user).toBeTruthy();
    const table = { blog: 'blog_posts', exhibitions: 'exhibitions' }[template];
    expect(table).toBeTruthy();
    const section = `(select id from public.site_sections where slug='browser-sort-${template}')`;
    const entries = schema => `(select json_agg(e order by e.position,e.id) from
        (select id,title,position from ${schema}.${table} where site_section_id=${section}) e)`;
    const query = `select json_build_object('section',${section},'working',${entries('public')},
        'committed',${entries('committed')},'audits',(select count(*) from audit_events),
        'lastAudit',(select json_build_object('id',id,'action',action) from audit_events order by id desc limit 1),
        'notifications',coalesce((select json_agg(n order by n.id) from
            (select id,source_id,status from admin_notifications) n),'[]'::json))`;
    return JSON.parse(execFileSync('docker', ['exec', container, 'psql', '-XAt', '-U', user,
        '-d', database, '-c', query], { encoding: 'utf8' }).trim());
}

const rows = page => page.locator('.journal-table tbody > tr');
const row = (page, entry) => rows(page).filter({ has: page.getByRole('button', { name: `Drag ${entry.title} to reorder`, exact: true }) });
const handle = (page, entry) => row(page, entry).getByRole('button', { name: `Drag ${entry.title} to reorder`, exact: true });
const events = page => page.evaluate(() => window.journalOrderFeedback.length);

async function projection(page, entries) {
    await expect.poll(() => rows(page).evaluateAll(nodes => nodes.map(node => ({
        title: node.querySelector('.admin-drag-handle').getAttribute('aria-label').slice(5, -11),
        position: Number(node.querySelector('.admin-position').textContent.trim()),
    })))).toEqual(entries.map(entry => ({ title: entry.title, position: entry.position })));
}

async function changed(page, template, before, eventCount, expectedIds) {
    await expect.poll(() => snapshot(template).working.map(e => e.id)).toEqual(expectedIds);
    await expect.poll(() => events(page)).toBe(eventCount + 1);
    const after = snapshot(template);
    expect(after.audits).toBe(before.audits + 1);
    expect(after.lastAudit.action).toBe(template === 'blog' ? 'blog_post.reordered' : 'exhibition.reordered');
    const added = after.notifications.filter(n => !before.notifications.some(old => old.id === n.id));
    expect(added).toHaveLength(1);
    expect(added[0]).toMatchObject({ status: 'success', source_id: `activity:${after.lastAudit.id}` });
    expect(await page.evaluate(() => window.journalOrderFeedback.at(-1).notification))
        .toMatchObject({ status: 'success', notification_id: added[0].id });
    expect(after.working.map(e => e.position)).toEqual(Array.from({ length: 26 }, (_, i) => i + 1));
    expect(after.committed).toEqual(before.committed);
    await projection(page, after.working);
    return after;
}

async function dragBefore(page, source, target) {
    await handle(page, source).dragTo(row(page, target), { targetPosition: { x: 15, y: 12 } });
}

for (const template of ['blog', 'exhibitions']) {
    test(`Journal ${template}: native drag, filter and pagination guards preserve canonical Working order`, async ({ page }) => {
        await page.addInitScript(() => {
            window.journalOrderFeedback = [];
            window.addEventListener('admin-header-notification', event => window.journalOrderFeedback.push(event.detail));
        });
        await signIn(page);
        let state = snapshot(template);
        expect(state.working).toHaveLength(26);
        await page.goto(`/admin/pages/journal/${state.section}`);
        await projection(page, state.working);
        const [first, second, third] = state.working;
        await dragBefore(page, third, first);
        state = await changed(page, template, state, 0, [third.id, first.id, second.id, ...state.working.slice(3).map(e => e.id)]);
        expect(state.working).not.toEqual(state.committed);
        await page.reload();
        await projection(page, state.working);
        expect(snapshot(template)).toEqual(state);

        await expect(row(page, state.working[0]).getByRole('button', { name: `Move ${state.working[0].title} up`, exact: true })).toBeDisabled();
        const last = state.working.at(-1);
        await expect(row(page, last).getByRole('button', { name: `Move ${last.title} down`, exact: true })).toBeDisabled();
        await handle(page, third).dragTo(row(page, third), { targetPosition: { x: 15, y: 20 } });
        expect(snapshot(template)).toEqual(state);
        expect(await events(page)).toBe(0);

        const search = page.getByPlaceholder(template === 'blog' ? 'Title or excerpt' : 'Title, venue, place or date');
        const clear = page.getByRole('button', { name: 'Clear', exact: true });
        await search.fill(`Browser ${template} entry`);
        for (const control of await page.getByRole('button', { name: /^Drag Browser/ }).all()) await expect(control).toBeDisabled();
        // Explicit moves remain canonical and usable while Drag is unavailable.
        await row(page, third).getByRole('button', { name: `Move ${third.title} down`, exact: true }).click();
        const expected = state.working.map(e => e.id);
        [expected[0], expected[1]] = [expected[1], expected[0]];
        state = await changed(page, template, state, 0, expected);
        await clear.click();
        await expect(handle(page, third)).toBeEnabled();
        const feedbackCount = await events(page);

        const filters = [() => page.getByRole('combobox', { name: 'Status', exact: true }).selectOption('published')];
        if (template === 'exhibitions') filters.push(() => page.getByRole('combobox', { name: 'Timing', exact: true }).selectOption('past'));
        for (const apply of filters) {
            await apply();
            await projection(page, state.working);
            const handles = page.getByRole('button', { name: /^Drag Browser/ });
            await expect(handles).toHaveCount(26);
            for (const control of await handles.all()) await expect(control).toBeDisabled();
            expect(snapshot(template)).toEqual(state);
            expect(await events(page)).toBe(feedbackCount);
            await clear.click();
            await expect(handle(page, third)).toBeEnabled();
        }

        const size = page.getByRole('button', { name: template === 'blog' ? 'Blog posts per page' : 'Exhibitions per page', exact: true });
        await size.click();
        await page.getByRole('option', { name: '25', exact: true }).click();
        await projection(page, state.working.slice(0, 25));
        for (const control of await page.getByRole('button', { name: /^Drag Browser/ }).all()) await expect(control).toBeDisabled();
        await page.getByRole('button', { name: 'Next', exact: true }).click();
        await projection(page, state.working.slice(25));
        await expect(handle(page, last)).toBeDisabled();
        await size.click();
        await page.getByRole('option', { name: '50', exact: true }).click();
        await projection(page, state.working);
        await expect(handle(page, third)).toBeEnabled();
        expect(snapshot(template)).toEqual(state);
        expect(await events(page)).toBe(feedbackCount);
        const [a, b, c] = state.working;
        await dragBefore(page, c, a);
        state = await changed(page, template, state, feedbackCount, [c.id, a.id, b.id, ...state.working.slice(3).map(e => e.id)]);
        await page.reload();
        await projection(page, state.working);
        expect(snapshot(template)).toEqual(state);
    });
}
