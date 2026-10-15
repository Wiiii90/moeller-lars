import { expect, test } from '@playwright/test';
import { signIn } from '../../support/admin.mjs';

test('Header dismisses one current Notification at a time and advances its queue', async ({ page }) => {
    await signIn(page);

    await page.evaluate(() => {
        for (const index of [1, 2, 3]) {
            window.dispatchEvent(new CustomEvent('admin-header-notification', {
                detail: { id: 'dismiss-regression-' + index, title: 'Message ' + index, status: 'info' },
            }));
        }
    });

    const header = page.locator('[data-admin-header-notification]');
    const title = header.locator('[data-admin-header-notification-title]');
    const count = header.locator('[data-admin-header-notification-counter]');
    const dismiss = header.getByRole('button', { name: 'Dismiss notification' });

    await expect(title).toHaveText('Message 1');
    await expect(count).toHaveText('+2');
    await dismiss.click();
    await expect(title).toHaveText('Message 2');
    await expect(count).toHaveText('+1');
    await dismiss.click();
    await expect(title).toHaveText('Message 3');
    await expect(count).toBeHidden();
    await dismiss.click();
    await expect(header).toBeHidden();
});


test('Header dismiss remains clickable while the native Edit modal is open', async ({ page }) => {
    await signIn(page);
    await page.getByRole('button', { name: 'Dashboard settings', exact: true }).click();

    const modal = page.locator('.fi-modal.fi-modal-open').last();
    await expect(modal.getByRole('heading', { name: 'Dashboard settings', exact: true })).toBeVisible();

    await page.evaluate(() => {
        window.dispatchEvent(new CustomEvent('admin-header-notification', {
            detail: { id: 'modal-dismiss-regression', title: 'Dismiss through dialog', status: 'success' },
        }));
    });

    const header = page.locator('[data-admin-header-notification]');
    await expect(header.locator('[data-admin-header-notification-title]')).toHaveText('Dismiss through dialog');
    await header.getByRole('button', { name: 'Dismiss notification' }).click();
    await expect(header).toBeHidden();
    await expect(modal.getByRole('heading', { name: 'Dashboard settings', exact: true })).toBeVisible();
});
