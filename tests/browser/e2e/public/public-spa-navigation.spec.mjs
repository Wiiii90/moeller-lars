import { expect, test } from '@playwright/test';

// Requires BrowserProfilingSeeder on the disposable database. The pair of
// published public Custom Pages is committed by that seeder, never by this test.
test('public CSP authorizes real styles and scripts across SPA navigation and back/forward', async ({ page }) => {
    await page.addInitScript(() => {
        window.__publicCspViolations = [];
        document.addEventListener('securitypolicyviolation', (event) => {
            window.__publicCspViolations.push({
                directive: event.effectiveDirective, blocked: event.blockedURI,
                source: event.sourceFile, line: event.lineNumber, sample: event.sample,
                stage: window.__publicNavigationStage ?? 'initial',
            });
        });
    });
    const errors = [];
    const failedAssets = [];
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('requestfailed', (request) => {
        if (['script', 'stylesheet'].includes(request.resourceType())) failedAssets.push(request.url());
    });
    page.on('response', (response) => {
        if (['script', 'stylesheet'].includes(response.request().resourceType()) && response.status() >= 400) {
            failedAssets.push(`${response.status()} ${response.url()}`);
        }
    });
    const firstResponse = await page.goto('/browser-public-spa-1');
    expect(firstResponse.status()).toBe(200);
    const initialNonce = await publicCspNonce(firstResponse);
    await expect(page.locator('#content')).toContainText('Public SPA content 1');
    const firstWidth = await authorizedPublicStyles(page, initialNonce);
    await expect.poll(() => page.evaluate(() => typeof window.Livewire?.navigate)).toBe('function');
    const states = [await publicStyleState(page, 'initial', initialNonce)];

    const secondLink = page.locator('[data-site-navigation] a[href$="/browser-public-spa-2"]').first();
    await expect(secondLink).toBeVisible();
    await expect(secondLink).toHaveAttribute('wire:navigate', '');

    // A full HTTP navigation destroys window properties; a Livewire navigate
    // keeps this document and swaps its page content in place.
    await page.evaluate(() => { window.__spaDocumentIdentity = 'retained'; });

    const nextResponse = page.waitForResponse((response) => new URL(response.url()).pathname === '/browser-public-spa-2');
    await page.evaluate(() => { window.__publicNavigationStage = 'navigate'; });
    await secondLink.click();
    const nextNonce = await publicCspNonce(await nextResponse);
    expect(nextNonce).not.toBe(initialNonce);
    await expect(page).toHaveURL(/\/browser-public-spa-2(?:[?#]|$)/);
    await expect(page.locator('#content')).toContainText('Public SPA content 2');
    expect(await page.evaluate(() => window.__spaDocumentIdentity)).toBe('retained');
    const secondWidth = await authorizedPublicStyles(page, initialNonce);
    states.push(await publicStyleState(page, 'navigate', initialNonce));
    // The fixture changes editorial image scale from 50% to 75%, proving
    // that the destination's nonce-authorized rule really takes effect.
    expect(secondWidth / firstWidth).toBeCloseTo(75 / 50, 2);

    await page.evaluate(() => { window.__publicNavigationStage = 'back'; });
    await page.goBack();
    await expect(page).toHaveURL(/\/browser-public-spa-1(?:[?#]|$)/);
    await expect(page.locator('#content')).toContainText('Public SPA content 1');
    expect(await page.evaluate(() => window.__spaDocumentIdentity)).toBe('retained');
    expect(await authorizedPublicStyles(page, initialNonce)).toBeCloseTo(firstWidth, 0);
    states.push(await publicStyleState(page, 'back', initialNonce));

    await page.evaluate(() => { window.__publicNavigationStage = 'forward'; });
    await page.goForward();
    await expect(page).toHaveURL(/\/browser-public-spa-2(?:[?#]|$)/);
    await expect(page.locator('#content')).toContainText('Public SPA content 2');
    expect(await page.evaluate(() => window.__spaDocumentIdentity)).toBe('retained');
    expect(await authorizedPublicStyles(page, initialNonce)).toBeCloseTo(secondWidth, 0);
    states.push(await publicStyleState(page, 'forward', initialNonce));
    expect(states[1].layout).not.toEqual(states[0].layout);
    expect(states[2].layout).toEqual(states[0].layout);
    expect(states[3].layout).toEqual(states[1].layout);
    await test.info().attach('public-style-states', {
        body: JSON.stringify({ states, violations: await page.evaluate(() => window.__publicCspViolations) }, null, 2),
        contentType: 'application/json',
    });
    expect(await page.evaluate(() => window.__publicCspViolations)).toEqual([]);
    expect(errors).toEqual([]);
    expect(failedAssets).toEqual([]);
});

async function publicStyleState(page, stage, activeNonce) {
    const state = await page.evaluate(({ stage, activeNonce }) => ({
        stage, activeNonce,
        styles: [...document.querySelectorAll('style')].map((element) => ({
            property: element.nonce, attribute: element.getAttribute('nonce'),
            serialized: element.outerHTML.match(/nonce="([^"]*)"/)?.[1],
            applied: element.sheet !== null,
        })),
        inlineStyles: [...document.querySelectorAll('[style]')].map((element) => ({
            tag: element.tagName, id: element.id, value: element.getAttribute('style'),
        })),
        layout: ['--public-shell-max', '--public-art-max', '--public-content-padding', '--public-folio-left-top']
            .map((name) => [name, getComputedStyle(document.documentElement).getPropertyValue(name).trim()]),
        expectedLayout: ['publicLayoutShell', 'publicLayoutArt', 'publicLayoutPadding', 'folioLeftTop']
            .map((name) => document.querySelector('meta[data-public-layout]').dataset[name]),
        rootStyle: document.documentElement.getAttribute('style'),
        revealStyle: document.querySelector('.public-content-reveal').getAttribute('style'),
    }), { stage, activeNonce });
    expect(state.layout.map(([, value]) => value)).toEqual(state.expectedLayout);
    expect(state.rootStyle).toBeNull();
    expect(state.revealStyle).toBeNull();
    for (const style of state.styles) {
        expect(style.property).toBe(activeNonce);
        expect([null, '']).toContain(style.attribute);
        expect([null, undefined, '']).toContain(style.serialized);
        expect(style.applied).toBe(true);
    }
    const screenshot = test.info().outputPath(`public-${stage}.png`);
    await page.screenshot({ path: screenshot });
    await test.info().attach(`public-${stage}`, { path: screenshot, contentType: 'image/png' });
    return state;
}

async function publicCspNonce(response) {
    const csp = (await response.allHeaders())['content-security-policy'];
    expect(csp).toBeTruthy();
    expect(csp).not.toMatch(/unsafe-inline|unsafe-eval/);
    expect(csp).toContain("frame-ancestors 'none'");
    const nonce = /(?:^|;)\s*style-src[^;]*'nonce-([^']+)'/.exec(csp)?.[1];
    expect(nonce).toBeTruthy();
    expect(csp).toMatch(new RegExp(`script-src[^;]*'nonce-${nonce.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}'`));
    return nonce;
}

async function authorizedPublicStyles(page, nonce) {
    const image = page.getByRole('img', { name: 'Browser committed media' });
    await image.scrollIntoViewIfNeeded();
    await expect(image).toBeVisible();
    await image.evaluate((element) => element.decode());
    const styles = await page.locator('style').evaluateAll((elements) => elements.map((element) => ({
        nonce: element.nonce, applied: element.sheet !== null, rules: element.sheet?.cssRules.length ?? 0,
    })));
    expect(styles.length).toBeGreaterThan(0);
    for (const style of styles) {
        expect(style.nonce).toBe(nonce);
        expect(style.applied).toBe(true);
        expect(style.rules).toBeGreaterThan(0);
    }
    expect(await page.locator('link[rel="stylesheet"][href*="/build/"]').evaluateAll((elements) =>
        elements.some((element) => element.sheet?.cssRules.length > 0))).toBe(true);
    return image.evaluate((element) => {
        if (getComputedStyle(element).display !== 'block') throw new Error('Public image stylesheet did not apply');
        return element.closest('figure').getBoundingClientRect().width;
    });
}
