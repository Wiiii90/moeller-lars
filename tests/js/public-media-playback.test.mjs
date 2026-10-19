import test from 'node:test';
import assert from 'node:assert/strict';
import { shouldPlayPublicVideo } from '../../resources/js/public-media-playback.js';

test('inline video plays only when intersecting, visible, in its active slide and movement is allowed', () => {
    assert.equal(shouldPlayPublicVideo(true, true, true), true);
    assert.equal(shouldPlayPublicVideo(false, true, true), false);
    assert.equal(shouldPlayPublicVideo(true, false, true), false);
    assert.equal(shouldPlayPublicVideo(true, true, false), false);
    assert.equal(shouldPlayPublicVideo(true, true, true, true), false);
});
