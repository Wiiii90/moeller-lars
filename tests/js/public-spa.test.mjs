import test from 'node:test';
import assert from 'node:assert/strict';
import {
    applyPublicLayoutFromMeta,
    isPublicSpaDestination,
    markPublicSpaLinks,
} from '../../resources/js/public-spa.js';

const currentPage = 'https://artist.example/about?language=en';

test('SPA navigation includes internal pages and changed queries but preserves same-document anchors', () => {
    for (const href of ['/', '/gallery', 'biography', '/about?language=de#details', '/preview/about', '/administration']) {
        assert.equal(isPublicSpaDestination(href, currentPage), true, href);
    }

    for (const href of ['#details', '/about?language=en#details']) {
        assert.equal(isPublicSpaDestination(href, currentPage), false, href);
    }
});

test('SPA navigation does not intercept external, private, download or new-window destinations', () => {
    for (const href of [
        'https://external.example/page',
        'mailto:studio@example.org',
        'javascript:alert(1)',
        '//external.example/page',
        '/admin', '/admin/pages', '/api', '/api/status', '/pulse',
        '/livewire/update', '/logout', '/media/file.jpg', '/storage/file.jpg', '/vendor/file.js',
    ]) {
        assert.equal(isPublicSpaDestination(href, currentPage), false, href);
    }

    assert.equal(isPublicSpaDestination('/gallery', currentPage, '_blank'), false);
    assert.equal(isPublicSpaDestination('/gallery', currentPage, 'viewer'), false);
    assert.equal(isPublicSpaDestination('/gallery', currentPage, '_self'), true);
    assert.equal(isPublicSpaDestination('/gallery', currentPage, '', true), false);
    assert.equal(isPublicSpaDestination('/gallery', 'not a URL'), false);
});

function link(href, { target = '', download = false, fullLoad = false, external = false, parent = false } = {}) {
    const attributes = new Map([['href', href]]);
    if (download) attributes.set('download', '');
    if (fullLoad) attributes.set('data-public-full-load', '');
    if (parent) attributes.set('data-navigation-parent-link', '');

    return {
        target,
        hasAttribute: (name) => attributes.has(name),
        getAttribute: (name) => attributes.get(name) ?? null,
        setAttribute: (name, value) => attributes.set(name, value),
        relList: { contains: (value) => external && value === 'external' },
        navigates: () => attributes.has('wire:navigate'),
    };
}

test('only eligible real anchors opt into the existing Livewire SPA router', () => {
    const links = [
        link('/gallery'),
        link('https://artist.example/contact'),
        link('#contact'),
        link('/admin/pages'),
        link('/gallery', { target: '_blank' }),
        link('/gallery', { download: true }),
        link('/gallery', { fullLoad: true }),
        link('/gallery', { external: true }),
        link('/gallery', { parent: true }),
    ];
    const previousWindow = globalThis.window;
    globalThis.window = { location: { href: currentPage } };
    try {
        markPublicSpaLinks({ querySelectorAll: (selector) => {
            assert.equal(selector, 'a[href]');
            return links;
        } });
        assert.deepEqual(links.map((entry) => entry.navigates()), [
            true, true, false, false, false, false, false, false, false,
        ]);
    } finally {
        if (previousWindow === undefined) delete globalThis.window;
        else globalThis.window = previousWindow;
    }
});

test('public layout follows the destination metadata through CSSOM without inserting styles', () => {
    const values = new Map();
    const previousDocument = globalThis.document;
    const meta = { dataset: {
        publicLayoutBackground: 'linear-gradient(45deg, #112233, #445566)',
        publicLayoutShell: '1120px',
        publicLayoutArt: '960px',
        publicLayoutPadding: '48px',
    } };
    globalThis.document = {
        querySelector: (selector) => {
            assert.equal(selector, 'meta[data-public-layout]');
            return meta;
        },
        documentElement: { style: { setProperty: (name, value) => values.set(name, value) } },
    };
    try {
        applyPublicLayoutFromMeta();
        assert.deepEqual(Object.fromEntries(values), {
            '--public-page': 'linear-gradient(45deg, #112233, #445566)',
            '--public-shell-max': '1120px',
            '--public-art-max': '960px',
            '--public-content-padding': '48px',
        });

        values.clear();
        meta.dataset.publicLayoutBackground = '';
        meta.dataset.publicLayoutArt = '';
        applyPublicLayoutFromMeta();
        assert.equal(values.get('--public-page'), '#777');
        assert.equal(values.has('--public-art-max'), false);

        values.clear();
        globalThis.document.querySelector = () => null;
        applyPublicLayoutFromMeta();
        assert.equal(values.size, 0);
    } finally {
        if (previousDocument === undefined) delete globalThis.document;
        else globalThis.document = previousDocument;
    }
});
