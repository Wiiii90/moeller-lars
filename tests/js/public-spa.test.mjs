import test from 'node:test';
import assert from 'node:assert/strict';
import { isPublicSpaDestination } from '../../resources/js/public-spa.js';

const current = 'https://artist.test/painting';

test('public content navigates with the existing Livewire transport', () => {
    assert.equal(isPublicSpaDestination('/prints', current), true);
    assert.equal(isPublicSpaDestination('/artworks/sample', current), true);
    assert.equal(isPublicSpaDestination('/preview/exhibitions', current), true);
    assert.equal(isPublicSpaDestination('/painting#details', current), false);
});

test('system destinations and browser-native links never enter SPA transport', () => {
    assert.equal(isPublicSpaDestination('https://external.test/', current), false);
    assert.equal(isPublicSpaDestination('/admin', current), false);
    assert.equal(isPublicSpaDestination('/media/variant/12', current), false);
    assert.equal(isPublicSpaDestination('/preview/media/variant/12', current), false);
    assert.equal(isPublicSpaDestination('/site-assets/livewire-csp.js', current), false);
    assert.equal(isPublicSpaDestination('/file.pdf', current), false);
    assert.equal(isPublicSpaDestination('/prints', current, '_blank'), false);
    assert.equal(isPublicSpaDestination('/prints', current, '', true), false);
});
