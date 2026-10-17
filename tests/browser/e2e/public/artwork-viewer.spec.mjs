import { createHash } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';

function galleryFixture() {
    expect(process.env.PLAYWRIGHT_PUBLIC_MEDIA_FIXTURE_FILE).toBeTruthy();
    const { gallery } = JSON.parse(readFileSync(process.env.PLAYWRIGHT_PUBLIC_MEDIA_FIXTURE_FILE, 'utf8'));
    expect(gallery.artworks).toHaveLength(2);
    return gallery;
}

async function observePublicErrors(page) {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.addInitScript(() => {
        window.__viewerCspViolations = [];
        document.addEventListener('securitypolicyviolation', event => {
            window.__viewerCspViolations.push({ directive: event.effectiveDirective, blocked: event.blockedURI });
        });
    });
    return async () => {
        expect(errors).toEqual([]);
        expect(await page.evaluate(() => window.__viewerCspViolations)).toEqual([]);
    };
}

async function decodedArtwork(dialog, artwork) {
    await expect(dialog).toBeVisible();
    await expect(dialog).toHaveAccessibleName(artwork.title);
    const image = dialog.locator('img');
    await expect(image).toBeVisible();
    await expect(image).toHaveAttribute('src', new RegExp(`${artwork.original.url}$`));
    expect(await image.evaluate(async element => {
        await element.decode();
        return { width: element.naturalWidth, height: element.naturalHeight };
    })).toEqual({ width: artwork.original.width, height: artwork.original.height });
    return image;
}

test('Gallery viewer decodes public originals and supports bounded zoom, pan, navigation and focus return', async ({ page }) => {
    const gallery = galleryFixture();
    const [first, second] = gallery.artworks;
    const verifyErrors = await observePublicErrors(page);
    await page.goto(gallery.url);
    const trigger = page.locator('#content a').filter({ has: page.getByRole('img', { name: 'Browser viewer-1 media', exact: true }) });
    await expect(trigger).toBeVisible();
    const originalResponse = page.waitForResponse(response => new URL(response.url()).pathname === first.original.url);
    await trigger.click();
    const dialog = page.getByRole('dialog', { includeHidden: true });
    const image = await decodedArtwork(dialog, first);
    // Use the shared SVG right arrow, never a platform-dependent glyph
    // or the separate external-destination icon.
    const artworkPageLink = dialog.getByRole('link', { name: 'Open artwork page' });
    await expect(artworkPageLink).toHaveAttribute('href', new RegExp(`${first.url}$`));
    await expect(artworkPageLink.locator('svg.public-icon path'))
        .toHaveAttribute('d', 'M4 12h16m-7-7 7 7-7 7');
    await page.screenshot({ path: test.info().outputPath('artwork-viewer.png') });
    const response = await originalResponse;
    expect(response.status()).toBe(200);
    const headers = await response.allHeaders();
    expect(headers['content-type']).toBe('image/jpeg');
    expect(headers['x-sendfile']).toBeUndefined();
    expect(headers.etag).toBe(`"${first.original.sha256}"`);
    expect(createHash('sha256').update(await response.body()).digest('hex')).toBe(first.original.sha256);
    const zoomIn = dialog.getByRole('button', { name: 'Zoom in', exact: true });
    const zoomOut = dialog.getByRole('button', { name: 'Zoom out', exact: true });
    const reset = dialog.getByRole('button', { name: 'Reset zoom', exact: true });
    const previous = dialog.getByRole('button', { name: 'Previous artwork', exact: true });
    const next = dialog.getByRole('button', { name: 'Next artwork', exact: true });
    await expect(previous).toBeDisabled();
    await expect(next).toBeEnabled();
    await expect(zoomOut).toBeDisabled();
    const fitted = await image.boundingBox();
    await zoomIn.click();
    await expect(reset).toHaveText('125%');
    expect((await image.boundingBox()).width).toBeCloseTo(fitted.width * 1.25, 0);
    await zoomOut.click();
    await expect(reset).toHaveText('100%');
    await expect(dialog.locator(':focus')).toBeEnabled();
    await page.keyboard.press('+');
    await expect(reset).toHaveText('125%');
    await page.keyboard.press('-');
    await expect(reset).toHaveText('100%');
    for (let index = 0; index < 4; index++) await zoomIn.click();
    const enlarged = await image.boundingBox();
    const stage = image.locator('..');
    const bounds = await stage.boundingBox();
    const center = { x: bounds.x + bounds.width / 2, y: bounds.y + bounds.height / 2 };
    await page.mouse.move(center.x, center.y);
    await page.mouse.down();
    await page.mouse.move(center.x + bounds.width, center.y + bounds.height, { steps: 8 });
    await page.mouse.up();
    const panned = await image.boundingBox();
    expect(Math.abs(panned.x - enlarged.x) + Math.abs(panned.y - enlarged.y)).toBeGreaterThan(20);
    // At a clamped edge the image must continue to cover the entire stage.
    expect(panned.x).toBeLessThanOrEqual(bounds.x + 1);
    expect(panned.y).toBeLessThanOrEqual(bounds.y + 1);
    expect(panned.x + panned.width).toBeGreaterThanOrEqual(bounds.x + bounds.width - 1);
    expect(panned.y + panned.height).toBeGreaterThanOrEqual(bounds.y + bounds.height - 1);
    await reset.click();
    await expect(reset).toHaveText('100%');
    expect((await image.boundingBox()).width).toBeCloseTo(fitted.width, 0);
    await page.keyboard.press('ArrowRight');
    await decodedArtwork(dialog, second);
    await expect(next).toBeDisabled();
    await expect(previous).toBeEnabled();
    await page.keyboard.press('ArrowRight');
    await expect(dialog).toHaveAccessibleName(second.title);
    await previous.click();
    await decodedArtwork(dialog, first);
    await expect(previous).toBeDisabled();
    await next.click();
    await decodedArtwork(dialog, second);
    await expect(dialog.locator(':focus')).toBeEnabled();
    await page.keyboard.press('+');
    await expect(reset).toHaveText('125%');
    await page.keyboard.press('0');
    await expect(reset).toHaveText('100%');
    await page.keyboard.press('Escape');
    await expect(dialog).not.toBeVisible();
    await expect(image).not.toHaveAttribute('src');
    await expect(image).not.toHaveAttribute('style');
    await expect(trigger).toBeFocused();
    await trigger.click();
    await decodedArtwork(dialog, first);
    await dialog.getByRole('button', { name: 'Close artwork viewer' }).click();
    await expect(dialog).not.toBeVisible();
    await expect(trigger).toBeFocused();
    await verifyErrors();
});

test('landscape Artwork detail widens the canvas without enlarging Gallery or overflowing phones', async ({ page }) => {
    const gallery = galleryFixture();
    const first = gallery.artworks[0]; // 1600 × 1200 fixture
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto(gallery.url);
    await expect(page.locator('#content')).not.toHaveClass(/site-content--landscape-artwork/);
    const galleryWidth = await page.locator('.artwork-list .artwork-card__image').first().evaluate(image =>
        image.getBoundingClientRect().width);
    expect(galleryWidth).toBeLessThanOrEqual(650);

    await page.locator('.artwork-caption-link').first().click();
    await expect(page).toHaveURL(new RegExp(first.url + '$'));
    await expect(page.locator('#content')).toHaveClass(/site-content--landscape-artwork/);
    const detailWidth = await page.locator('.artwork-detail__image').evaluate(image =>
        image.getBoundingClientRect().width);
    expect(detailWidth).toBeGreaterThan(galleryWidth);
    expect(detailWidth).toBeLessThanOrEqual(960);

    const alignment = await page.locator('.artwork-detail').evaluate(element => {
        const image = element.querySelector('.artwork-detail__image').getBoundingClientRect();
        const nav = element.querySelector('.artwork-folio').getBoundingClientRect();
        const caption = element.querySelector('.artwork-detail__footer').getBoundingClientRect();
        return { imageRight: image.right, navRight: nav.right, captionRight: caption.right };
    });
    expect(alignment.navRight).toBeCloseTo(alignment.imageRight, 0);
    expect(alignment.captionRight).toBeCloseTo(alignment.imageRight, 0);

    await page.setViewportSize({ width: 844, height: 390 });
    const landscapePhoneWidth = await page.locator('.artwork-detail__image').evaluate(image =>
        image.getBoundingClientRect().width);
    expect(landscapePhoneWidth).toBeGreaterThan(650);

    await page.setViewportSize({ width: 390, height: 844 });
    const narrow = await page.evaluate(() => ({
        image: document.querySelector('.artwork-detail__image').getBoundingClientRect().width,
        scroll: document.documentElement.scrollWidth,
        viewport: window.innerWidth,
    }));
    expect(narrow.image).toBeLessThan(390);
    expect(narrow.scroll).toBeLessThanOrEqual(narrow.viewport + 1);

    await page.goBack();
    await expect(page).toHaveURL(new RegExp(gallery.url + '$'));
    await expect(page.locator('#content')).not.toHaveClass(/site-content--landscape-artwork/);
});

test('Viewer survives real SPA navigation and history without stale dialogs or duplicate interactions', async ({ page }) => {
    const gallery = galleryFixture();
    const first = gallery.artworks[0];
    const verifyErrors = await observePublicErrors(page);
    await page.goto(gallery.url);
    await page.evaluate(() => { window.__viewerDocumentIdentity = 'retained'; });
    const trigger = page.locator('#content a').filter({ has: page.getByRole('img', { name: 'Browser viewer-1 media', exact: true }) });
    await trigger.click();
    let dialog = page.getByRole('dialog');
    await decodedArtwork(dialog, first);
    await dialog.getByRole('button', { name: 'Zoom in', exact: true }).click();
    await expect(dialog.getByRole('button', { name: 'Reset zoom' })).toHaveText('125%');
    await dialog.getByRole('link', { name: 'Open artwork page' }).click();
    await expect(page).toHaveURL(new RegExp(`${first.url}$`));
    await expect(page.locator('#content')).toContainText(first.title);
    await expect(page.locator('dialog[open]')).toHaveCount(0);
    expect(await page.evaluate(() => window.__viewerDocumentIdentity)).toBe('retained');
    await page.goBack();
    await expect(page).toHaveURL(new RegExp(`${gallery.url}$`));
    await expect(page.locator('dialog[open]')).toHaveCount(0);
    await trigger.click();
    dialog = page.getByRole('dialog');
    await decodedArtwork(dialog, first);
    await dialog.getByRole('button', { name: 'Zoom in', exact: true }).click();
    await expect(dialog.getByRole('button', { name: 'Reset zoom' })).toHaveText('125%');
    await page.keyboard.press('Escape');
    await expect(trigger).toBeFocused();
    await page.goForward();
    await expect(page).toHaveURL(new RegExp(`${first.url}$`));
    await expect(page.locator('dialog[open]')).toHaveCount(0);
    await page.locator('#content [data-artwork-viewer-trigger]').click();
    await decodedArtwork(page.getByRole('dialog'), first);
    await page.keyboard.press('Escape');
    await page.goBack();
    await expect(page).toHaveURL(new RegExp(`${gallery.url}$`));
    await trigger.click();
    await decodedArtwork(page.getByRole('dialog'), first);
    await page.keyboard.press('Escape');
    expect(await page.evaluate(() => window.__viewerDocumentIdentity)).toBe('retained');
    await verifyErrors();
});
