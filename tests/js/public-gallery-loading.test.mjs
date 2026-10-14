import test from 'node:test';
import assert from 'node:assert/strict';
import {
    GALLERY_PREFETCH_LIMIT, GALLERY_PREFETCH_MARGIN,
    galleryImageDistance, initializeGalleryImageLoading,
} from '../../resources/js/public-gallery-loading.js';

function fixture() {
    const originalObserver = globalThis.IntersectionObserver;
    const originalWindow = globalThis.window;
    const observers = [];
    globalThis.window = { innerHeight: 800 };
    globalThis.IntersectionObserver = class {
        constructor(callback, options) {
            this.callback = callback;
            this.options = options;
            this.observed = new Set();
            observers.push(this);
        }
        observe(image) { this.observed.add(image); }
        unobserve(image) { this.observed.delete(image); }
        disconnect() { this.disconnected = true; this.observed.clear(); }
        enter(...images) { this.callback(images.map(target => ({ target, isIntersecting: true }))); }
        exit(...images) { this.callback(images.map(target => ({ target, isIntersecting: false }))); }
    };
    const images = Array.from({ length: 5 }, (_, index) => ({
        loading: index < 2 ? 'eager' : 'lazy',
        complete: false,
        naturalWidth: 0,
        dataset: {},
        getBoundingClientRect: () => ({ top: 700 + index * 600, bottom: 1200 + index * 600 }),
    }));
    const listeners = new Map();
    const root = {
        querySelectorAll: () => images,
        addEventListener: (type, callback) => { listeners.set(type, callback); },
        dispatch: (type, image) => listeners.get(type)?.({ type, target: image }),
    };
    return {
        root, images, observers,
        restore() { globalThis.IntersectionObserver = originalObserver; globalThis.window = originalWindow; },
    };
}

test('viewport distance prioritizes in-view and nearer gallery items', () => {
    const image = (top, bottom) => ({ getBoundingClientRect: () => ({ top, bottom }) });
    assert.equal(galleryImageDistance(image(50, 650), 800), 0);
    assert.equal(galleryImageDistance(image(1000, 1400), 800), 200);
    assert.equal(galleryImageDistance(image(-500, -50), 800), 50);
});

test('Gallery promotes at most two nearby images and advances after completion', () => {
    const scene = fixture();
    try {
        const dispose = initializeGalleryImageLoading(scene.root);
        const observer = scene.observers[0];
        assert.equal(observer.options.rootMargin, GALLERY_PREFETCH_MARGIN);
        assert.equal(observer.observed.size, 3);
        assert.equal(scene.images[0].loading, 'eager');
        assert.equal(scene.images[1].loading, 'eager');
        observer.enter(scene.images[4], scene.images[3], scene.images[2]);
        assert.equal(scene.images.slice(2).filter(image => image.loading === 'eager').length, GALLERY_PREFETCH_LIMIT);
        assert.equal(scene.images[2].loading, 'eager');
        assert.equal(scene.images[3].loading, 'eager');
        assert.equal(scene.images[4].loading, 'lazy');
        scene.images[2].complete = true;
        scene.images[2].naturalWidth = 1200;
        scene.root.dispatch('load', scene.images[2]);
        assert.equal(scene.images[2].dataset.galleryImageState, 'ready');
        assert.equal(scene.images[4].loading, 'eager');
        scene.root.dispatch('error', scene.images[4]);
        assert.equal(scene.images[4].dataset.galleryImageState, 'error');
        dispose();
        assert.equal(observer.disconnected, true);
    } finally {
        scene.restore();
    }
});

test('out-of-buffer candidates and abandoned SPA pages are not promoted', () => {
    const scene = fixture();
    try {
        const dispose = initializeGalleryImageLoading(scene.root);
        const observer = scene.observers[0];
        observer.enter(scene.images[2], scene.images[3], scene.images[4]);
        observer.exit(scene.images[4]);
        scene.root.dispatch('load', scene.images[2]);
        assert.equal(scene.images[4].loading, 'lazy');
        dispose();
        observer.enter(scene.images[4]);
        scene.root.dispatch('load', scene.images[4]);
        assert.equal(scene.images[4].loading, 'lazy');
    } finally {
        scene.restore();
    }
});
