import test from 'node:test';
import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';

const source = (file) => readFileSync(new URL('../../' + file, import.meta.url), 'utf8');

test('public paper cannot reintroduce DOM measurement feedback', () => {
    const app = source('resources/js/app.js');
    const header = source('resources/js/public-header.js');
    const presentation = source('resources/css/public-presentation.css');

    // This document's contour is presentation-only. Its former observers
    // measured content and then changed its ancestor's geometry.
    assert.equal(existsSync(new URL('../../resources/js/public-paper-frame.js', import.meta.url)), false);
    assert.doesNotMatch(app, /public-paper-frame\.js|initializePublicPaperFrame/);
    assert.doesNotMatch(presentation, /--public-paper-content-end/);

    // A header that changes its own stacked class must never observe its
    // own dimensions or an element whose width that class mutates.
    assert.doesNotMatch(header, /\bnew\s+(?:ResizeObserver|MutationObserver)\b|\b(?:requestAnimationFrame|setInterval)\s*\(/);
    assert.match(header, /window\.addEventListener\('resize', update/);
    assert.match(header, /window\.removeEventListener\('resize', update/);
});

test('authored public CSS has no transition or animation properties', () => {
    for (const file of [
        'resources/css/app.css',
        'resources/css/public-content.css',
        'resources/css/public-presentation.css',
        'resources/css/custom-pages.css',
        'resources/css/journal-presentation.css',
    ]) {
        assert.doesNotMatch(
            source(file),
            /^\s*(?:-webkit-)?(?:animation|transition)(?:-[\w-]+)?\s*:/gm,
            file + ' unexpectedly introduced public CSS motion',
        );
    }
});

test('public paper end remains static and independent from an observer', () => {
    const presentation = source('resources/css/public-presentation.css');
    assert.match(presentation, /\.public-paper-decoration::before,\s*\.public-paper-decoration::after\s*\{/);
    assert.match(presentation, /100% - var\(--public-content-block-inset\) - var\(--public-paper-return-inset\)/);
    assert.doesNotMatch(presentation, /--public-paper-content-end/);
});
