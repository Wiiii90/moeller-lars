import assert from 'node:assert/strict';
import test from 'node:test';

import {
    appendPendingNotification,
    canReleaseFollowingNotification,
    MAX_PENDING_NOTIFICATIONS,
} from '../../resources/js/admin-notification-ticker.js';

test('keeps the notification ticker pending queue bounded and FIFO', () => {
    const queue = [];

    for (let id = 1; id <= MAX_PENDING_NOTIFICATIONS; id += 1) {
        assert.equal(appendPendingNotification(queue, { id: String(id) }), true);
    }

    assert.equal(appendPendingNotification(queue, { id: 'overflow' }), false);
    assert.equal(queue.length, MAX_PENDING_NOTIFICATIONS);
    assert.deepEqual(
        queue.map((notification) => notification.id),
        Array.from({ length: MAX_PENDING_NOTIFICATIONS }, (_, index) => String(index + 1)),
    );
});


test('releases the following ticker message once the previous one is fully inside the runway', () => {
    assert.equal(canReleaseFollowingNotification(801, 800), false);
    assert.equal(canReleaseFollowingNotification(800, 800), true);
    assert.equal(canReleaseFollowingNotification(640, 800), true);
});
