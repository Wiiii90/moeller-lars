import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const css = readFileSync(new URL('../../resources/css/admin/general.css', import.meta.url), 'utf8');
const responsive = readFileSync(new URL('../../resources/css/admin/responsive.css', import.meta.url), 'utf8');
const stage = readFileSync(new URL('../../app/Filament/Pages/General.php', import.meta.url), 'utf8');
const toggle = readFileSync(new URL('../../resources/views/filament/schemas/components/general-appearance-switcher.blade.php', import.meta.url), 'utf8');
const preview = readFileSync(new URL('../../resources/views/filament/schemas/components/general-live-preview.blade.php', import.meta.url), 'utf8');

test('Appearance has one overlapping panel track through Filament wrappers', () => {
    assert.match(css, /\.general-appearance-stage__scroll\s*\{[^}]*display: grid;/);
    assert.match(css, /\.general-appearance-stage__scroll :not\(\.general-appearance-stage__panel\):has\(\.general-appearance-stage__panel\)\s*\{[^}]*display: contents !important;/);
    assert.match(css, /\.general-appearance-stage__panel\s*\{[^}]*grid-area: 1 \/ 1;/);
    assert.match(css, /\.general-appearance-stage__panel\.is-active\s*\{[^}]*position: relative;/);
    assert.doesNotMatch(css, /\.admin-visual-stage--expanded \.general-appearance-stage__panel\s*\{[^}]*position: relative;/);
    assert.match(css, /\.general-appearance-stage\.admin-visual-stage--expanded\s*\{[^}]*height: max-content;/);
    assert.doesNotMatch(css, /\.general-appearance-stage__scroll > \.fi-sc/);
    for (const name of ['global', 'title', 'menu', 'page']) {
        assert.ok(stage.includes("'data-appearance-panel' => '" + name + "'"));
    }
    assert.match(responsive, /\.admin-visual-stage--expanded\s*\{\s*grid-template-rows: max-content !important;/);
});

test('More space is presentation-only; its preview stays mounted while its viewport may grow', () => {
    assert.match(toggle, /x-on:click="moreSpace = ! moreSpace"/);
    assert.match(stage, /'x-data' => "\{ appearanceTab: 'global', moreSpace: false \}"/);
    assert.match(css, /\.admin-visual-stage--expanded \.general-appearance-stage__preview\s*\{[^}]*height: 100%;[^}]*align-self: stretch;/);
    assert.doesNotMatch(css, /max-height: max\(var\(--admin-visual-stage-height\), 75vh\)/);
    assert.match(preview, /new ResizeObserver\(\(\) => this\.fitPreview\(\)\)/);
    assert.doesNotMatch(toggle, /wire:click|general-appearance-updated|refreshPreview/);
    assert.match(preview, /x-on:general-appearance-updated\.window="refreshPreview\(\)"/);
    assert.match(preview, /<iframe\s+[^>]*wire:ignore/s);
});

test('the preview stays top-aligned and shares Appearance toolbar spacing', () => {
    assert.match(css, /\.general-live-preview__viewport\s*\{[^}]*padding: calc\(var\(--admin-section-gap\) \+ \.5rem\) 0 \.35rem;[^}]*place-items: start center;/);
    assert.match(css, /\.general-appearance-stage__scroll\s*\{[^}]*padding-block: var\(--admin-section-gap\) \.2rem;/);
    assert.match(readFileSync(new URL('../../resources/css/admin/stage.css', import.meta.url), 'utf8'), /\.admin-stage-panel-content\s*\{[^}]*padding-top: \.5rem;/);
});
