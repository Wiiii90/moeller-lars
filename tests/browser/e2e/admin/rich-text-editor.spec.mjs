import { randomUUID } from 'node:crypto';
import { expect, test } from '@playwright/test';
import { signIn } from '../../support/admin.mjs';

test.setTimeout(90_000);

/**
 * These tests use the same disposable BrowserProfilingSeeder as the other
 * Admin end-to-end tests. Create unique, unpublished pages through the
 * real UI; never alter a seeded reference page or an artist's content.
 */
async function openFreshEditor(page) {
    await page.setViewportSize({ width: 1500, height: 820 });
    await signIn(page);
    await page.goto('/admin/pages');

    const token = randomUUID();
    const title = 'Browser rich text ' + token;
    const slug = 'browser-rich-text-' + token;

    await page.getByLabel('Page controls').getByRole('button', { name: 'Add page', exact: true }).click();
    await page.locator('[data-admin-control="name"] input').fill(title);
    await page.locator('[data-admin-control="slug"] input').fill(slug);
    await page.getByRole('button', { name: 'Create page', exact: true }).click();

    const destination = page.getByRole('table', { name: 'Pages' })
        .getByRole('link', { name: title, exact: true });
    await expect(destination).toBeVisible();
    await destination.click();
    await expect(page).toHaveURL(/\/admin\/pages\/custom\/\d+(?:[/?#]|$)/);

    await page.getByRole('button', { name: 'Add component', exact: true }).click();
    const creation = page.locator('.fi-modal.fi-modal-open').last();
    await expect(creation.getByRole('heading', { name: 'Add component' })).toBeVisible();
    await creation.getByRole('combobox', { name: 'Component', exact: true }).selectOption('text');
    const editable = creation.locator('.admin-rich-text-editor-shell [contenteditable="true"]');
    await expect(editable).toBeVisible();
    await editable.fill('Studio rich text');
    await creation.getByRole('button', { name: 'Add component', exact: true }).click();
    await expect(creation).toBeHidden();

    return openExistingEditor(page);
}

async function openExistingEditor(page) {
    const row = page.locator('.custom-page-component__header[data-component-type="text"]').first();
    await expect(row).toBeVisible();
    await row.getByRole('button', { name: 'Edit', exact: true }).click();
    const modal = page.locator('.fi-modal.fi-modal-open').last();
    await expect(modal.getByRole('heading', { name: 'Edit component' })).toBeVisible();
    const shell = modal.locator('.admin-rich-text-editor-shell').first();
    const editable = shell.locator('[contenteditable="true"]');
    await expect(editable).toBeVisible();
    return { modal, shell, editable };
}

const panel = (modal, label) => modal.getByRole('group', { name: label, exact: true });
const visiblePanels = modal => modal.locator('.admin-rich-text-popover:visible');

test('one Rich Text popover remains active; Escape, outside click and focus preserve the owning edit dialog', async ({ page }) => {
    const { modal, shell, editable } = await openFreshEditor(page);
    const scrollport = modal.locator('.fi-modal-content').first();

    await shell.getByRole('button', { name: 'Link', exact: true }).click();
    await expect(panel(modal, 'Link')).toBeVisible();
    await expect(visiblePanels(modal)).toHaveCount(1);
    await expect(panel(modal, 'Link').getByRole('textbox', { name: 'Link URL' })).toBeFocused();

    const before = await scrollport.evaluate(element => element.scrollTop);
    await shell.getByRole('button', { name: 'Text color', exact: true }).click();
    await expect(panel(modal, 'Text color')).toBeVisible();
    await expect(visiblePanels(modal)).toHaveCount(1);

    await shell.getByRole('button', { name: 'Line spacing', exact: true }).click();
    await expect(panel(modal, 'Line spacing')).toBeVisible();
    await expect(visiblePanels(modal)).toHaveCount(1);
    await page.keyboard.press('Escape');
    await expect(visiblePanels(modal)).toHaveCount(0);
    await expect(modal).toBeVisible();

    await shell.getByRole('button', { name: 'Insert image from Storage', exact: true }).click();
    await expect(panel(modal, 'Image from Storage')).toBeVisible();
    await expect(visiblePanels(modal)).toHaveCount(1);
    await modal.getByRole('heading', { name: 'Edit component' }).click();
    await expect(visiblePanels(modal)).toHaveCount(0);
    await expect(modal).toBeVisible();
    expect(await scrollport.evaluate(element => element.scrollTop)).toBe(before);

    await editable.click();
    await shell.getByRole('button', { name: 'Link', exact: true }).click();
    const url = panel(modal, 'Link').getByRole('textbox', { name: 'Link URL' });
    await url.fill('javascript:alert(1)');
    await url.press('Enter');
    await expect(url).toHaveAttribute('aria-invalid', 'true');
    await expect(editable.locator('a')).toHaveCount(0);

    await url.fill('https://example.org/');
    await url.press('Enter');
    await expect(editable.locator('a[href="https://example.org/"]')).toHaveCount(1);
    await editable.locator('a[href="https://example.org/"]').click();
    await shell.getByRole('button', { name: 'Link', exact: true }).click();
    const editedUrl = panel(modal, 'Link').getByRole('textbox', { name: 'Link URL' });
    await expect(editedUrl).toHaveValue('https://example.org/');
    await editedUrl.fill('https://example.net/studio');
    await editedUrl.press('Enter');
    await expect(editable.locator('a[href="https://example.net/studio"]')).toHaveCount(1);
    await panel(modal, 'Link').getByRole('button', { name: 'Remove link' }).click();
    await expect(editable.locator('a')).toHaveCount(0);
});

test('each native Storage selection inserts exactly one image and the saved editor retains the caption', async ({ page }) => {
    let { modal, shell, editable } = await openFreshEditor(page);

    for (let count = 1; count <= 2; count++) {
        await shell.getByRole('button', { name: 'Insert image from Storage', exact: true }).click();
        await expect(panel(modal, 'Image from Storage')).toBeVisible();
        const item = page.getByRole('option', { name: /browser-viewer-1\.jpg/ }).first();
        await expect(item).toBeVisible();
        await item.click();
        await expect(editable.locator('img[data-id]')).toHaveCount(count);
        await expect(panel(modal, 'Image from Storage')).toBeHidden();
    }

    const first = editable.locator('img[data-id]').first();
    await first.click();
    await modal.getByRole('button', { name: 'Image caption', exact: true }).click();
    const caption = panel(modal, 'Image caption').getByRole('textbox', { name: 'Image caption' });
    await expect(caption).toBeFocused();
    await caption.fill('Studio image and detail');
    await caption.press('Enter');
    await expect(visiblePanels(modal)).toHaveCount(0);
    await expect(editable.locator('[data-artist-caption="Studio image and detail"]')).toHaveCount(1);

    await editable.locator('img[data-id]').first().click();
    await modal.getByRole('button', { name: 'Image caption', exact: true }).click();
    const editedCaption = panel(modal, 'Image caption').getByRole('textbox', { name: 'Image caption' });
    await expect(editedCaption).toHaveValue('Studio image and detail');
    await editedCaption.fill('Revised caption');
    await editedCaption.press('Enter');
    await expect(editable.locator('[data-artist-caption="Revised caption"]')).toHaveCount(1);

    // Continue switching within the same owning editor. Caption must never
    // remain visible behind Text Color or the native Storage selector.
    await editable.locator('img[data-id]').first().click();
    await modal.getByRole('button', { name: 'Image caption', exact: true }).click();
    await expect(panel(modal, 'Image caption')).toBeVisible();
    await expect(visiblePanels(modal)).toHaveCount(1);
    await shell.getByRole('button', { name: 'Text color', exact: true }).click();
    await expect(panel(modal, 'Image caption')).toBeHidden();
    await expect(panel(modal, 'Text color')).toBeVisible();
    await expect(visiblePanels(modal)).toHaveCount(1);
    await shell.getByRole('button', { name: 'Insert image from Storage', exact: true }).click();
    await expect(panel(modal, 'Text color')).toBeHidden();
    await expect(panel(modal, 'Image from Storage')).toBeVisible();
    await expect(visiblePanels(modal)).toHaveCount(1);
    await page.keyboard.press('Escape');
    await expect(visiblePanels(modal)).toHaveCount(0);
    await expect(modal).toBeVisible();

    // Edit dialogs persist on native change/focusout; browser navigation must
    // discard any editor-only state and reconstruct the two saved image nodes.
    await modal.getByRole('heading', { name: 'Edit component' }).click();
    await page.keyboard.press('Escape');
    await expect(modal).toBeHidden();
    await page.reload();

    ({ modal, shell, editable } = await openExistingEditor(page));
    await expect(editable.locator('img[data-id]')).toHaveCount(2);
    await expect(editable.locator('[data-artist-caption="Revised caption"]')).toHaveCount(1);

    // Removing the caption is an ordinary editor mutation, not an image deletion.
    await editable.locator('img[data-id]').first().click();
    await modal.getByRole('button', { name: 'Image caption', exact: true }).click();
    await panel(modal, 'Image caption').getByRole('textbox', { name: 'Image caption' }).fill('');
    await panel(modal, 'Image caption').getByRole('textbox', { name: 'Image caption' }).press('Enter');
    await expect(editable.locator('img[data-id]')).toHaveCount(2);
    await expect(editable.locator('[data-artist-caption]')).toHaveCount(0);
});

test('Rich Text toolbar edits a real table and persists colors, spacing and undo/redo after reopening', async ({ page }) => {
    let { modal, shell, editable } = await openFreshEditor(page);

    await editable.click();
    await page.keyboard.press('ControlOrMeta+A');
    await shell.getByRole('button', { name: 'Text color', exact: true }).click();
    const hex = panel(modal, 'Text color').getByRole('textbox', { name: 'Hex color' });
    await hex.fill('#3265ac');
    await hex.press('Enter');
    await expect(editable.locator('[data-color="#3265ac"]')).toHaveCount(1);

    await shell.getByRole('button', { name: 'Line spacing', exact: true }).click();
    await panel(modal, 'Line spacing').getByRole('combobox', { name: 'Line spacing' })
        .selectOption('2.0');
    await expect(editable.locator('[data-artist-line-spacing="2.0"]')).toHaveCount(1);

    await shell.getByRole('button', { name: /^Table$|^Insert table$/i }).click();
    const rows = editable.locator('table tr');
    await expect(rows).toHaveCount(2);
    const cell = rows.nth(1).locator('td').first();
    await cell.click();
    await page.keyboard.type('2026');
    await expect(cell).toContainText('2026');

    await shell.getByRole('button', { name: 'Undo', exact: true }).click();
    await expect(cell).not.toContainText('2026');
    await shell.getByRole('button', { name: 'Redo', exact: true }).click();
    await expect(cell).toContainText('2026');

    await modal.getByRole('heading', { name: 'Edit component' }).click();
    await page.keyboard.press('Escape');
    await expect(modal).toBeHidden();
    await page.reload();

    ({ modal, shell, editable } = await openExistingEditor(page));
    await expect(editable.locator('table')).toHaveCount(1);
    await expect(editable.locator('table')).toContainText('2026');
    await expect(editable.locator('[data-color="#3265ac"]')).toHaveCount(1);
    await expect(editable.locator('[data-artist-line-spacing="2.0"]')).toHaveCount(1);
});

test('two Rich Text component editors keep independent Storage image selections across reopening', async ({ page }) => {
    let { modal, shell, editable } = await openFreshEditor(page);
    await expect(editable).toContainText('Studio rich text');
    await page.keyboard.press('Escape');
    await expect(modal).toBeHidden();

    await page.getByRole('button', { name: 'Add component', exact: true }).click();
    const creation = page.locator('.fi-modal.fi-modal-open').last();
    await creation.getByRole('combobox', { name: 'Component', exact: true }).selectOption('text');
    await creation.locator('.admin-rich-text-editor-shell [contenteditable="true"]').fill('Second editor');
    await creation.getByRole('button', { name: 'Add component', exact: true }).click();
    await expect(creation).toBeHidden();

    const rows = page.locator('.custom-page-component__header[data-component-type="text"]');
    await expect(rows).toHaveCount(2);
    await rows.nth(1).getByRole('button', { name: 'Edit', exact: true }).click();
    modal = page.locator('.fi-modal.fi-modal-open').last();
    shell = modal.locator('.admin-rich-text-editor-shell').first();
    editable = shell.locator('[contenteditable="true"]');
    await expect(editable).toContainText('Second editor');
    await expect(editable.locator('img[data-id]')).toHaveCount(0);
    await shell.getByRole('button', { name: 'Insert image from Storage', exact: true }).click();
    await page.getByRole('option', { name: /browser-viewer-1\.jpg/ }).first().click();
    await expect(editable.locator('img[data-id]')).toHaveCount(1);
    await expect(modal).toBeVisible();

    await modal.getByRole('heading', { name: 'Edit component' }).click();
    await page.keyboard.press('Escape');
    await expect(modal).toBeHidden();

    await rows.first().getByRole('button', { name: 'Edit', exact: true }).click();
    modal = page.locator('.fi-modal.fi-modal-open').last();
    shell = modal.locator('.admin-rich-text-editor-shell').first();
    editable = shell.locator('[contenteditable="true"]');
    await expect(editable).toContainText('Studio rich text');
    await expect(editable.locator('img[data-id]')).toHaveCount(0);
    await shell.getByRole('button', { name: 'Insert image from Storage', exact: true }).click();
    await page.getByRole('option', { name: /browser-viewer-1\.jpg/ }).first().click();
    await expect(editable.locator('img[data-id]')).toHaveCount(1);
    await modal.getByRole('heading', { name: 'Edit component' }).click();
    await page.keyboard.press('Escape');
    await expect(modal).toBeHidden();
    await page.reload();

    await expect(rows).toHaveCount(2);
    await rows.nth(1).getByRole('button', { name: 'Edit', exact: true }).click();
    modal = page.locator('.fi-modal.fi-modal-open').last();
    editable = modal.locator('.admin-rich-text-editor-shell [contenteditable="true"]');
    await expect(editable).toContainText('Second editor');
    await expect(editable.locator('img[data-id]')).toHaveCount(1);
    await page.keyboard.press('Escape');
    await expect(modal).toBeHidden();

    await rows.first().getByRole('button', { name: 'Edit', exact: true }).click();
    modal = page.locator('.fi-modal.fi-modal-open').last();
    editable = modal.locator('.admin-rich-text-editor-shell [contenteditable="true"]');
    await expect(editable).toContainText('Studio rich text');
    await expect(editable.locator('img[data-id]')).toHaveCount(1);
});
