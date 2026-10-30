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
    const images = Array.from({ length: 6 }, (_, index) => ({
        loading: index < 2 ? 'eager' : 'lazy',
        src: index < 2 ? '/media/' + index : '',
        complete: false,
        naturalWidth: 0,
        fetchPriority: index === 0 ? 'high' : 'low',
        hidden: index >= 2,
        dataset: index >= 2
            ? { gallerySrc: '/media/' + index, galleryImageState: 'deferred' }
            : { galleryImageState: 'pending' },
        getBoundingClientRect: () => ({ top: 600 + index * 600, bottom: 1100 + index * 600 }),
    }));
    const listeners = new Map();
    const root = {
        querySelectorAll: (selector) => {
            assert.equal(selector, '.artwork-card__image');
            return images;
        },
        addEventListener: (type, callback) => { listeners.set(type, callback); },
        dispatch: (type, image) => listeners.get(type)?.({ type, target: image }),
    };
    return {
        root, images, observers,
        restore() { globalThis.IntersectionObserver = originalObserver; globalThis.window = originalWindow; },
    };
}

test('viewport distance prioritizes visible and near items', () => {
    const image = (top, bottom) => ({ getBoundingClientRect: () => ({ top, bottom }) });
    assert.equal(galleryImageDistance(image(50, 650), 800), 0);
    assert.equal(galleryImageDistance(image(1000, 1400), 800), 200);
    assert.equal(galleryImageDistance(image(-500, -50), 800), 50);
});

test('Gallery requests at most two speculative images, nearest first, then replenishes', () => {
    const scene = fixture();
    try {
        const dispose = initializeGalleryImageLoading(scene.root);
        const [buffer, visible] = scene.observers;
        assert.equal(buffer.options.rootMargin, GALLERY_PREFETCH_MARGIN);
        assert.equal(visible.options.rootMargin, '0px');
        assert.equal(buffer.observed.size, 4);
        assert.equal(scene.images.slice(2).every(image => !image.hidden), true);
        assert.equal(scene.images.slice(2).every(image => image.src === ''), true);

        buffer.enter(scene.images[5], scene.images[4], scene.images[3], scene.images[2]);
        assert.equal(scene.images.slice(2).filter(image => image.src !== '').length, GALLERY_PREFETCH_LIMIT);
        assert.equal(scene.images[2].src, '/media/2');
        assert.equal(scene.images[3].src, '/media/3');
        assert.equal(scene.images[4].src, '');
        assert.equal(scene.images[2].fetchPriority, 'low');

        scene.images[2].complete = true;
        scene.images[2].naturalWidth = 960;
        scene.root.dispatch('load', scene.images[2]);
        assert.equal(scene.images[2].dataset.galleryImageState, 'ready');
        assert.equal(scene.images[4].src, '/media/4');
        scene.root.dispatch('error', scene.images[4]);
        assert.equal(scene.images[4].dataset.galleryImageState, 'error');
        dispose();
        assert.equal(buffer.disconnected, true);
        assert.equal(visible.disconnected, true);
    } finally {
        scene.restore();
    }
});

test('a newly visible image bypasses the prefetch budget without starting distant images', () => {
    const scene = fixture();
    try {
        const dispose = initializeGalleryImageLoading(scene.root);
        const [buffer, visible] = scene.observers;
        buffer.enter(scene.images[2], scene.images[3], scene.images[4], scene.images[5]);
        assert.equal(scene.images[5].src, '');
        visible.enter(scene.images[5]);
        assert.equal(scene.images[5].src, '/media/5');
        assert.equal(scene.images[5].fetchPriority, 'high');
        assert.equal(scene.images[4].src, '');
        dispose();
    } finally {
        scene.restore();
    }
});

test('out-of-buffer candidates and SPA-disposed pages remain unrequested', () => {
    const scene = fixture();
    try {
        const dispose = initializeGalleryImageLoading(scene.root);
        const [buffer, visible] = scene.observers;
        buffer.enter(scene.images[2], scene.images[3], scene.images[4]);
        buffer.exit(scene.images[4]);
        scene.root.dispatch('load', scene.images[2]);
        assert.equal(scene.images[4].src, '');
        dispose();
        visible.enter(scene.images[4]);
        buffer.enter(scene.images[4]);
        scene.root.dispatch('load', scene.images[3]);
        assert.equal(scene.images[4].src, '');
    } finally {
        scene.restore();
    }
});

test('without IntersectionObserver every image falls back to native lazy loading', () => {
    const scene = fixture();
    try {
        globalThis.IntersectionObserver = undefined;
        initializeGalleryImageLoading(scene.root);
        assert.equal(scene.images.slice(2).every(image => !image.hidden), true);
        assert.equal(scene.images.slice(2).every(image => image.src.startsWith('/media/')), true);
        assert.equal(scene.images.slice(2).every(image => image.loading === 'lazy'), true);
    } finally {
        scene.restore();
    }
});

test('Artwork detail image displays only when loaded, and exposes a real media failure', () => {
    const makeImage = (complete = false, naturalWidth = 0) => {
        const image = new EventTarget();
        image.complete = complete;
        image.naturalWidth = naturalWidth;
        image.dataset = { artworkImageState: 'pending' };
        image.closest = () => null;
        image.getAttribute = () => null;
        return image;
    };
    const setup = (image) => ({
        querySelector: () => image,
        querySelectorAll: () => [],
    });

    const previousDocument = globalThis.document;
    globalThis.document = { querySelectorAll: () => [] };
    try {
        const loading = makeImage();
        const stop = initializeGalleryImageLoading(setup(loading));
        assert.equal(loading.dataset.artworkImageState, 'pending');
        loading.naturalWidth = 960;
        loading.dispatchEvent(new Event('load'));
        assert.equal(loading.dataset.artworkImageState, 'ready');
        stop();

        const broken = makeImage();
        initializeGalleryImageLoading(setup(broken));
        broken.dispatchEvent(new Event('error'));
        assert.equal(broken.dataset.artworkImageState, 'error');

        const cached = makeImage(true, 960);
        initializeGalleryImageLoading(setup(cached));
        assert.equal(cached.dataset.artworkImageState, 'ready');

        const disposed = makeImage();
        initializeGalleryImageLoading(setup(disposed))();
        disposed.naturalWidth = 960;
        disposed.dispatchEvent(new Event('load'));
        assert.equal(disposed.dataset.artworkImageState, 'pending');
    } finally {
        if (previousDocument === undefined) delete globalThis.document;
        else globalThis.document = previousDocument;
    }
});

test('initial Gallery images report decode, cached readiness and failure', async () => {
    const scene = fixture();
    try {
        const original = scene.root.querySelectorAll;
        scene.root.querySelectorAll = () => original().slice(0, 2);
        let finish;
        const image = scene.images[0];
        image.decode = () => new Promise(resolve => { finish = resolve; });
        const dispose = initializeGalleryImageLoading(scene.root);
        assert.equal(scene.observers.length, 0);
        image.naturalWidth = 850;
        image.complete = true;
        scene.root.dispatch('load', image);
        assert.equal(image.dataset.galleryImageState, 'pending');
        finish();
        await Promise.resolve();
        assert.equal(image.dataset.galleryImageState, 'ready');
        scene.root.dispatch('error', scene.images[1]);
        assert.equal(scene.images[1].dataset.galleryImageState, 'error');
        dispose();
        image.dataset.galleryImageState = 'pending';
        scene.root.dispatch('load', image);
        assert.equal(image.dataset.galleryImageState, 'pending');

        scene.images[1].complete = true;
        scene.images[1].naturalWidth = 1200;
        const cachedDispose = initializeGalleryImageLoading(scene.root);
        assert.equal(scene.images[1].dataset.galleryImageState, 'ready');
        cachedDispose();
    } finally {
        scene.restore();
    }
});

