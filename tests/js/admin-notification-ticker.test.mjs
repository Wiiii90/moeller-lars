import assert from 'node:assert/strict';
import test from 'node:test';

import {
    nextStaticFeedbackState,
} from '../../resources/js/admin-notification-ticker.js';

test('keeps the newest feedback visible and counts additional notifications', () => {
    const first = { id: '1', title: 'Gallery saved', body: '', status: 'success' };
    const second = { id: '2', title: 'Gallery saved', body: '', status: 'success' };
    const third = { id: '3', title: 'General settings saved', body: '', status: 'success' };

    const afterFirst = nextStaticFeedbackState(null, 0, first);
    assert.equal(afterFirst.current, first);
    assert.equal(afterFirst.additionalCount, 0);

    const afterSecond = nextStaticFeedbackState(
        afterFirst.current,
        afterFirst.additionalCount,
        second,
    );
    assert.equal(afterSecond.current, second);
    assert.equal(afterSecond.additionalCount, 1);

    const afterThird = nextStaticFeedbackState(
        afterSecond.current,
        afterSecond.additionalCount,
        third,
    );
    assert.equal(afterThird.current, third);
    assert.equal(afterThird.additionalCount, 2);
});
