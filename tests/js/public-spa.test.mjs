import test from 'node:test';
import assert from 'node:assert/strict';
import {
    applyPublicLayoutFromMeta,
    isPublicSpaDestination,
    initializePublicSpaLinks,
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
        '/preview/media/variant/1', '/site-assets/livewire-csp.js', '/file.pdf',
    ]) {
        assert.equal(isPublicSpaDestination(href, currentPage), false, href);
    }

    assert.equal(isPublicSpaDestination('/gallery', currentPage, '_blank'), false);
    assert.equal(isPublicSpaDestination('/gallery', currentPage, 'viewer'), false);
    assert.equal(isPublicSpaDestination('/gallery', currentPage, '_self'), true);
    assert.equal(isPublicSpaDestination('/gallery', currentPage, '', true), false);
    assert.equal(isPublicSpaDestination('/gallery', 'not a URL'), false);
});

test('delegated Livewire navigation preserves browser-native link behavior', () => {
    const oldWindow = globalThis.window;
    const oldAnchor = globalThis.HTMLAnchorElement;
    const visited = [];
    let onClick;
    class Anchor {
        constructor(href, opts = {}) {
            this.href = new URL(href, currentPage).href;
            this.target = opts.target || '';
            this.attributes = new Map([['href', href]]);
            for (const [name, flag] of [
                ['download', opts.download], ['data-public-full-load', opts.fullLoad],
                ['data-navigation-parent-link', opts.parent], ['wire:navigate', opts.navigate],
            ]) if (flag) this.attributes.set(name, '');
            this.relList = { contains: (name) => name === 'external' && opts.external === true };
        }
        hasAttribute(name) { return this.attributes.has(name); }
        getAttribute(name) { return this.attributes.get(name) ?? null; }
    }
    try {
        globalThis.HTMLAnchorElement = Anchor;
        globalThis.window = {
            location: { href: currentPage },
            Livewire: { navigate: (url) => visited.push(url) },
        };
        initializePublicSpaLinks({ addEventListener: (type, listener) => {
            assert.equal(type, 'click');
            onClick = listener;
        } });
        assert.equal(typeof onClick, 'function');
        const click = (anchor, overrides = {}) => {
            let prevented = false;
            onClick({
                defaultPrevented: false, button: 0,
                ctrlKey: false, metaKey: false, altKey: false, shiftKey: false,
                target: { closest: (selector) => {
                    assert.equal(selector, 'a[href]');
                    return anchor;
                } },
                preventDefault: () => { prevented = true; },
                ...overrides,
            });
            return prevented;
        };
        assert.equal(click(new Anchor('/gallery')), true);
        assert.equal(click(new Anchor('/contact')), true);
        assert.deepEqual(visited, [
            'https://artist.example/gallery',
            'https://artist.example/contact',
        ]);
        for (const anchor of [
            new Anchor('#details'), new Anchor('/admin/pages'),
            new Anchor('/preview/media/variant/1'),
            new Anchor('/gallery', { target: '_blank' }),
            new Anchor('/gallery', { download: true }),
            new Anchor('/gallery', { fullLoad: true }),
            new Anchor('/gallery', { parent: true }),
            new Anchor('/gallery', { navigate: true }),
            new Anchor('/gallery', { external: true }),
        ]) assert.equal(click(anchor), false);
        assert.equal(click(new Anchor('/gallery'), { ctrlKey: true }), false);
        assert.equal(click(new Anchor('/gallery'), { defaultPrevented: true }), false);
        assert.equal(click(new Anchor('/gallery'), { button: 1 }), false);
        globalThis.window.Livewire = null;
        assert.equal(click(new Anchor('/gallery')), false);
        assert.equal(visited.length, 2);
    } finally {
        if (oldWindow === undefined) delete globalThis.window;
        else globalThis.window = oldWindow;
        if (oldAnchor === undefined) delete globalThis.HTMLAnchorElement;
        else globalThis.HTMLAnchorElement = oldAnchor;
    }
});

test('public layout follows the destination metadata through CSSOM without inserting styles', () => {
    const values = new Map();
    const previousDocument = globalThis.document;
    const frameClasses = new Set();
    const meta = { dataset: {
        publicLayoutBackground: 'linear-gradient(45deg, #112233, #445566)',
        publicLayoutDividerMode: 'menu',
        publicLayoutShell: '1120px',
        publicLayoutArt: '960px',
        publicLayoutPadding: '48px',
    } };
    const headerClasses = new Set(['site-header--divider-full', 'site-header--name-above']);
    const header = { classList: { toggle: (name, enabled) => {
        if (enabled) headerClasses.add(name);
        else headerClasses.delete(name);
    } } };
    globalThis.document = {
        body: { classList: { toggle: (name, enabled) => {
            if (enabled) frameClasses.add(name);
            else frameClasses.delete(name);
        } } },
        querySelector: (selector) => {
            if (selector === 'meta[data-public-layout]') return meta;
            if (selector === '.site-header') return header;
            assert.fail('Unexpected selector ' + selector);
        },
        querySelectorAll: (selector) => {
            assert.equal(selector, 'style[data-public-layout-style]');
            return [{ sheet: { cssRules: [{ selectorText: ':root', style: {
                setProperty: (name, value) => values.set(name, value),
                removeProperty: (name) => values.delete(name),
            } }] } }];
        },
        documentElement: { style: { setProperty: () => assert.fail('Layout must not create snapshot style attributes') } },
    };
    try {
        applyPublicLayoutFromMeta();
        assert.equal(headerClasses.has('site-header--divider-full'), false);
        assert.equal(headerClasses.has('site-header--divider-menu'), true);
        assert.equal(headerClasses.has('site-header--name-above'), true);
        assert.equal(frameClasses.has('public-site--frame-layered'), true);
        assert.deepEqual(Object.fromEntries(values), {
            '--public-page': 'linear-gradient(45deg, #112233, #445566)',
            '--public-shell-max': '1120px',
            '--public-art-max': '960px',
            '--public-content-padding': '48px',
        });

        values.clear();
        meta.dataset.publicLayoutBackground = '';
        meta.dataset.publicLayoutArt = '';
        meta.dataset.publicLayoutDividerMode = 'fine';
        applyPublicLayoutFromMeta();
        assert.equal(headerClasses.has('site-header--divider-menu'), false);
        assert.equal(headerClasses.has('site-header--divider-fine'), true);
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
