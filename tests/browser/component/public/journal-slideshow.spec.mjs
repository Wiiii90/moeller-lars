import { expect, test } from '@playwright/test';
import { readFileSync } from 'node:fs';

// Run the real journal presentation script in an isolated browser DOM.
// CSS layout and image geometry are intentionally outside this contract.
const source = readFileSync(new URL('../../../../resources/js/journal-presentation.js', import.meta.url), 'utf8');

test('Blog slideshow cycles the real slides and maintains accessible state', async ({ page }) => {
  await page.setContent(`<!doctype html><html><body>
    <section data-journal-slideshow aria-label="Post images">
      <button type="button" data-journal-slide-prev>Previous image</button>
      <span data-journal-slide-status aria-live="polite"></span>
      <button type="button" data-journal-slide-next>Next image</button>
      <div data-journal-slide>First image</div>
      <div data-journal-slide hidden>Second image</div>
      <div data-journal-slide hidden>Third image</div>
    </section>
  </body></html>`);

  await page.addScriptTag({
    content: source.replace(/^export /gm, '') + '\nwindow.initializeJournalPresentation = initializeJournalPresentation;',
  });
  await page.evaluate(() => window.initializeJournalPresentation());

  const slides = page.locator('[data-journal-slide]');
  const status = page.locator('[data-journal-slide-status]');
  const previous = page.getByRole('button', { name: 'Previous image' });
  const next = page.getByRole('button', { name: 'Next image' });

  await expect(status).toHaveText('1 / 3');
  await expect(slides.nth(0)).toBeVisible();
  await expect(slides.nth(1)).toBeHidden();
  await expect(slides.nth(2)).toBeHidden();
  await expect(slides.nth(0)).toHaveAttribute('aria-hidden', 'false');

  await previous.click();
  await expect(status).toHaveText('3 / 3');
  await expect(slides.nth(2)).toBeVisible();
  await expect(slides.nth(0)).toHaveAttribute('aria-hidden', 'true');
  await expect(slides.nth(2)).toHaveAttribute('aria-hidden', 'false');

  await next.click();
  await expect(status).toHaveText('1 / 3');
  await expect(slides.nth(0)).toBeVisible();

  await next.focus();
  await page.keyboard.press('Enter');
  await expect(status).toHaveText('2 / 3');
  await expect(slides.nth(1)).toBeVisible();
  await expect(next).toBeFocused();
});

test('a one-image Blog slideshow remains visible without navigation controls', async ({ page }) => {
  await page.setContent(`<!doctype html><html><body>
    <section data-journal-slideshow aria-label="Single image">
      <div data-journal-slide>Only image</div>
    </section>
  </body></html>`);

  await page.addScriptTag({
    content: source.replace(/^export /gm, '') + '\nwindow.initializeJournalPresentation = initializeJournalPresentation;',
  });
  await page.evaluate(() => window.initializeJournalPresentation());

  await expect(page.locator('[data-journal-slide]')).toBeVisible();
  await expect(page.getByRole('button')).toHaveCount(0);
});
