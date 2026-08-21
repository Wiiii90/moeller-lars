<?php

use App\Domain\Media\MediaTypePolicy;

it('uses operator configured type-specific media upload limits', function () {
    config()->set('media.upload.image_max_bytes', 12345);
    config()->set('media.upload.video_max_bytes', 67890);

    expect(MediaTypePolicy::maxBytesFor('image/jpeg'))->toBe(12345)
        ->and(MediaTypePolicy::maxBytesFor('video/mp4'))->toBe(67890)
        ->and(MediaTypePolicy::maxUploadBytes())->toBe(67890);
});
