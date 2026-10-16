import { createHash } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';

function fixtures() {
    const path = process.env.PLAYWRIGHT_PUBLIC_MEDIA_FIXTURE_FILE;
    expect(path, 'Isolated BrowserProfilingSeeder media manifest is required').toBeTruthy();
    return JSON.parse(readFileSync(path, 'utf8'));
}

test('Apache sends committed original and thumbnail bytes that the browser can decode', async ({ page }) => {
    const { committed } = fixtures();
    await page.goto('/browser-public-spa-1');
    const publishedImage = page.getByRole('img', { name: 'Browser committed media' });
    await expect(publishedImage).toHaveAttribute('src', new RegExp(`${committed.thumbnail.url}$`));
    for (const image of [committed.original, committed.thumbnail]) {
        const response = await page.request.get(image.url);
        expect(response.status()).toBe(200);
        const headers = response.headers();
        expect(headers['content-type']).toBe(image.mime);
        expect(Number(headers['content-length'])).toBe(image.byteSize);
        expect(headers.etag).toBe(`"${image.sha256}"`);
        expect(headers['cache-control']).toContain('public');
        expect(headers['cache-control']).toContain('max-age=31536000');
        expect(headers['cache-control']).toContain('immutable');
        expect(headers['accept-ranges']).toBe('bytes');
        expect(headers['content-disposition']).toBe('inline');
        expect(headers['x-content-type-options']).toBe('nosniff');
        expect(headers['tdm-reservation']).toBe('1');
        expect(headers['x-sendfile']).toBeUndefined();
        const bytes = await response.body();
        expect(bytes.length).toBe(image.byteSize);
        expect(createHash('sha256').update(bytes).digest('hex')).toBe(image.sha256);
        const decoded = await page.evaluate(async (url) => {
            const element = new Image();
            element.src = url;
            document.querySelector('#content').append(element);
            try { await element.decode(); return { width: element.naturalWidth, height: element.naturalHeight }; }
            finally { element.remove(); }
        }, image.url);
        expect(decoded).toEqual({ width: image.width, height: image.height });
        const range = await page.request.get(image.url, { headers: { Range: 'bytes=0-15' } });
        expect(range.status()).toBe(206);
        expect(range.headers()['content-range']).toBe(`bytes 0-15/${image.byteSize}`);
        expect(await range.body()).toEqual(bytes.subarray(0, 16));
    }
});

test('stored but unreferenced and Working-only media stay private for both public routes', async ({ request }) => {
    const { unreferenced, workingOnly } = fixtures();
    for (const fixture of [unreferenced, workingOnly]) {
        for (const image of [fixture.original, fixture.thumbnail]) {
            const response = await request.get(image.url);
            expect(response.status()).toBe(404);
            expect(response.headers()['x-sendfile']).toBeUndefined();
        }
    }
});
